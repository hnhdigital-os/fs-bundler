<?php

namespace App\Plugins;

use File;
use Illuminate\Support\Arr;

class CombinePlugin extends BasePlugin
{
    /**
     * Output path.
     *
     * @var string
     */
    private $output_path;

    /**
     * Paths.
     *
     * @var array
     */
    private $paths = [];

    /**
     * Remove paths after combining.
     *
     * @var array
     */
    private $remove_paths = false;

    /**
     * Verify the configuration.
     *
     * @return bool
     */
    public function verify()
    {
        if (!$this->verifyRequiredConfig(['output'])) {
            return;
        }

        $this->output_path = Arr::get($this->config, 'output');
        $this->remove_paths = $this->parseBooleanValue(Arr::get($this->config, 'remove', false));

        $paths = $this->parseStringArrayValue(Arr::get($this->config, 'paths', []));

        foreach ($paths as $path) {
            list($path, $options) = $this->parseOptions($path);

            $this->paths[] = [$path, $options];

            // Check that this path exists.
            if ($this->checkPath($path) === false) {
                $this->process->line(sprintf('   <error>ERROR</error> Path does not exist: %s', $path));

                return false;
            }
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
        $contents = '';

        // Load the contents of each file.
        foreach ($this->paths as $path_info) {
            $contents .= $this->handlePath(...$path_info);
        }

        // Put the contents into the new file.
        $this->createDirectory($this->output_path);

        if ($this->isVeryVerbose()) {
            $this->process->line(sprintf('   <fg=yellow>Created</> %s', $this->output_path));
        }

        if ($this->process->isDry()) {
            return;
        }

        File::put($this->output_path, $contents);
    }

    /**
     * Handle the given path, getting the content from files.
     *
     * @param string $path
     * @param array  $options
     *
     * @return string
     */
    private function handlePath($path, $options)
    {
        $content = '';

        // Remove search depth if appended.
        if (substr($path, -2) === '**') {
            $path = substr($path, 0, -2);
            $method = 'all';
        } elseif (substr($path, -1) === '*' || substr($path, -1) === '/') {
            $path = substr($path, 0, -1);
            $method = 'base';
        } else {
            $file_paths = [$path];
        }

        // Lookup file paths.
        if (!isset($file_paths)) {
            $method_arguments = ($method == 'base') ? [true, 1] : [];
            $file_paths = $this->scan($path, false, ...$method_arguments);
            $file_paths = $this->filterPathExtensions($file_paths, array_get($options, 'filter', ''));
        }

        foreach ($file_paths as $file_path) {
            $content .= $this->handlePathContent($file_path);
        }

        return $content;
    }

    /**
     * Handle the content from the source file.
     *
     * @param string $file_path
     *
     * @return string
     */
    private function handlePathContent($file_path)
    {
        if (!file_exists($file_path)) {
            return '';
        }

        if ($this->isVeryVerbose()) {
            $this->process->line(sprintf('   <fg=cyan>Adding</>  %s', $file_path));
        }

        $relative_path = str_replace($this->process->getCwd(), '', $file_path);

        $content = sprintf("\n/* %s */\n\n", $relative_path);
        $content .= File::get($file_path);

        if (!$this->process->isDry() && $this->remove_paths) {
            File::delete($file_path);

            if ($this->isVeryVerbose()) {
                $this->process->line(sprintf('   <fg=red>Deleted</>  %s', $file_path));
            }
        }

        return $content;
    }
}
