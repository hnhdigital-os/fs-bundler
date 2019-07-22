<?php

namespace App\Plugins;

use File;
use Illuminate\Support\Arr;

class ReplaceTextPlugin extends BasePlugin
{
    /**
     * Verify the configuration.
     *
     * @return bool
     */
    public function verify()
    {
        // Required config.
        if (!$this->verifyRequiredConfig(['src', 'find'])) {
            return;
        }

        $this->src_path = Arr::get($this->config, 'src');
        $this->find_text = Arr::get($this->config, 'find');
        $this->replace_text = Arr::get($this->config, 'replace', '');
        $this->enable_preg = Arr::get($this->config, 'preg', false);
        $this->extensions = Arr::get($this->config, 'extensions', false);

        // Check source path.
        if (!$this->verifyPaths(['src'])) {
            return;
        }

        return true;
    }

    /**
     * Handle the task.
     *
     * @return bool
     */
    public function handle()
    {
        list($method, $source_path, $destination_path, $options) = $this->parsePaths($this->src_path);

        if ($this->isVerbose()) {
            $this->process->line(sprintf('   Finding <fg=yellow>%s</> in <fg=cyan>%s</>', $this->find_text, $this->src_path));
        }

        // Generate the new file paths so we can validate other tasks in the future.
        switch ($method) {
            /*
             * Copying all files.
             * or Copying files from the base of the provided directory.
             */
            case self::COPY_ALL:
            case self::COPY_BASE:
                $method_arguments = ($method === self::COPY_BASE) ? [true, 0] : [];
                $paths = $this->scan($source_path, false, ...$method_arguments);
                $paths = $this->filterPathExtensions($paths, $this->extensions);

                foreach ($paths as $path) {
                    $this->handleFile($path);
                }

                return true;
            /*
             * Copying a single file.
             */
            case self::COPY_FILE:

                return $this->handleFile($source_path);
            /*
             * Copying error. File may not exist.
             */
            case self::COPY_ERROR:
                $this->process->error(sprintf('%s not found.', $this->src_path));

                return false;
        }
    }

    /**
     * Handle the file to have find/replace.
     *
     * @param string $path
     *
     * @return bool
     */
    private function handleFile($path)
    {
        if (!file_exists($path)) {
            return false;
        }

        $method = 'str_replace';

        $find_text = $this->find_text;

        if ($this->enable_preg) {
            $find_text = '~'.$find_text.'~';
            $method = 'preg_replace';
        }

        $content = File::get($path);

        preg_match_all('~'.preg_quote($this->find_text).'~', $content, $matches);

        $match_count = isset($matches[0]) ? count($matches[0]) : 'no';

        if ($this->isVerbose()) {
            $this->process->line(sprintf('   Found %s matches in <fg=cyan>%s</>', $match_count, $path));
        }

        // When doing a dry run, we just count matches and return.
        if ($this->process->isDry()) {
            return true;
        }

        // Find and replace.
        $content = $method($find_text, $this->replace_text, $content);

        // Update file.
        File::put($path, $content);

        return true;
    }
}
