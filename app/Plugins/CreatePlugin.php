<?php

namespace App\Plugins;

use File;
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
        $this->paths = $this->parseStringArrayValue(Arr::get($this->config, 'paths', []));

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

        return 0;
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
        if (File::exists($path)) {
            return;
        }

        if ($this->isVerbose()) {
            $this->process->line(sprintf('   Created <fg=cyan>%s</>', $path));
        }

        if ($this->process->isDry()) {
            return;
        }

        File::makeDirectory($path, 0777, true);
    }
}