<?php

namespace App\Plugins;

use Illuminate\Support\Arr;

class EmptyPlugin extends BasePlugin
{
    /**
     * Paths.
     *
     * @var array
     */
    private $paths = [];

    /**
     * Verify the configuration.
     *
     * @return bool
     */
    public function verify()
    {
        $this->paths = Arr::get($this->config, 'paths', []);

        foreach ($this->paths as &$path) {
            $path = $this->parseOptions($this->process->getCwd($path));

            if (!$this->verifyPath(...$path)) {
                return;
            }
        }

        return true;
    }

    /**
     * Verify the path.
     *
     * @param string $path
     * @param array  $options
     *
     * @return bool
     */
    private function verifyPath($path, $options)
    {
        if (!Arr::has($options, 'ignore') && $this->checkPath($path) === false) {
            $this->process->error(sprintf('Path \'%s\' does not exist.', $path));

            return;
        }

        if ($path === $this->process->getCwd()) {
            $this->process->error(sprintf('Path matches current working directory.', $path));

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
        foreach ($this->paths as $path) {
            $this->handlePath(...$path);
        }

        return true;
    }

    /**
     * Handle the path.
     *
     * @param string $path
     * @param array  $options
     *
     * @return void
     */
    private function handlePath($path, $options)
    {
        (substr($path, -1) !== '/') ? $path .= '/' : false;

        if (!file_exists($path)) {
            return;
        }

        $paths = $this->scan($path);

        if ($this->isVerbose() && count($paths) > 0) {
            $this->process->info(sprintf('  Deleting %s files...', count($paths)));
        }

        foreach ($paths as $path) {
            if ($this->process->isDry()) {
                continue;
            }

            if (!file_exists($path)) {
                continue;
            }

            if ($this->isVeryVerbose()) {
                $this->process->line(sprintf('  <fg=red>Deleted</> %s', str_replace($this->process->getCwd(), '', $path)));
            }
            
            is_dir($path) ? rmdir($path) : unlink($path);
        }
    }
}
