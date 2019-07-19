<?php

namespace App\Plugins;

use Illuminate\Support\Arr;

class CreatePlugin extends BasePlugin
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

            $this->storePath($path);
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
        if ($this->process->isDry()) {
            return;
        }

        if (file_exists($path)) {
            return;
        }

        $this->process->line(sprintf('  %s created', $path));

        mkdir($path);
    }
}