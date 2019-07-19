<?php

namespace App\Plugins;

use Illuminate\Support\Arr;

abstract class BasePlugin
{
    protected $process;

    protected $config;

    /**
     * Create task.
     *
     * @param array $config
     */
    public function __construct(&$process, $config)
    {
        $this->process = $process;
        $this->config = $config;
    }

    /**
     * Verify the configuration.
     *
     * @return bool
     */
    abstract public function verify();

    /**
     * Handle the task.
     *
     * @return bool
     */
    abstract public function handle();

    /**
     * Verify required config.
     *
     * @return bool
     */
    public function verifyRequiredConfig($keys)
    {
        foreach ($keys as $key) {
            if (empty(Arr::get($this->config, $key))) {
                $this->process->line(sprintf('<error>ERROR</error> Missing `%s` from configuration', $key));

                return false;
            }
        }

        return true;
    }

    /**
     * Verify paths.
     *
     * @return bool
     */
    public function verifyPaths($keys)
    {
        foreach ($keys as $key) {
            $path = Arr::get($this->config, $key);
            list($path, $options) = $this->parseOptions($path);
            $path = $this->process->stripSearchDepth($path);

            if ($this->checkPath($path) === false) {
                $this->process->line(sprintf('<error>ERROR</error> Path does not exist: `%s`', $path));

                return false;
            }
        }

        return true;
    }

    /**
     * Get task name.
     *
     * @return string
     */
    public function getId()
    {
        return Arr::get($this->config, 'task_id');
    }

    /**
     * Get task name.
     *
     * @return string
     */
    public function getName()
    {
        return Arr::get($this->config, 'name', Arr::get($this->config, 'task_id'));
    }

    /**
     * Check path.
     *
     * @param string $path
     *
     * @return void
     */
    protected function checkPath($path)
    {
        return $this->process->checkPath($path);
    }

    /**
     * Store path.
     *
     * @return void
     */
    protected function storePath(...$paths)
    {
        return $this->process->storePath($paths);
    }

    /**
     * Parse options off a string.
     *
     * @return array
     */
    protected function parseOptions($input)
    {
        return $this->process->parseOptions($input);
    }

    /**
     * Scan recursively through each folder for all files and folders.
     *
     * @param string $scan_path
     * @param bool   $include_folders
     * @param bool   $include_files
     * @param int    $depth
     *
     * @return void
     */
    protected function scan($scan_path, $include_folders = true, $include_files = true, $depth = -1)
    {
        $paths = [];

        if (substr($scan_path, -1) != '/') {
            $scan_path .= '/';
        }

        try {
            $contents = scandir($scan_path);

            foreach ($contents as $key => $value) {
                if ($value === '.' || $value === '..') {
                    continue;
                }

                $absolute_path = $scan_path.$value;

                if (is_dir($absolute_path) && $depth != 0) {
                    $new_paths = self::scan($absolute_path.'/', $include_folders, $include_files, $depth - 1);
                    $paths = array_merge($paths, $new_paths);
                }

                if ((is_file($absolute_path) && $include_files) || (is_dir($absolute_path) && $include_folders)) {
                    $paths[] = $absolute_path;
                }
            }

        } catch (\Exception $exception) {
            $this->process->error(sprintf('Path %s %s.', $scan_path, $exception->getMessage()));

            exit(1);
        }

        return $paths;
    }

    /**
     * Filter array of paths.
     *
     * @return array
     */
    public function filterPaths($paths, $filter)
    {
        if (!empty($filter)) {
            $filter = explode(',', $filter);
        }

        if (is_array($filter) && count($filter)) {
            $paths = array_filter($paths, function ($path) use ($filter) {
                $module = pathinfo($path, PATHINFO_EXTENSION);
                return in_array($module, $filter);
            });
        }

        return $paths;
    }

    /**
     * Create directory.
     * 
     * @param string $source_path
     * @param string $destination_path
     *
     * @return void
     */
    public function createDirectory($path, $is_directory = false)
    {
        if ($this->process->isDry()) {
            return;
        }

        $path = $is_directory ? $path : dirname($path);

        if (file_exists($path)) {
            return;
        }

        $parent_path = $path;

        while (!file_exists($parent_path)) {
            $parent_path = dirname($parent_path);
        }

        mkdir($path, fileperms($parent_path), true);
    }

    /**
     * Copy file.
     * 
     * @param string $source_path
     * @param string $destination_path
     *
     * @return void
     */
    public function copyFile($source_path, $destination_path)
    {
        if ($this->isVeryVerbose()) {
            $this->process->line(sprintf('  <fg=cyan>From</> %s', str_replace($this->process->getCwd(), '', $source_path)));
            $this->process->line(sprintf('  <fg=yellow>To</>   %s', str_replace($this->process->getCwd(), '', $destination_path)));
        }

        if ($this->process->isDry()) {
            return;
        }

        $this->createDirectory($destination_path);

        copy($source_path, $destination_path);
    }

    /**
     * Is quiet?
     *
     * @return boolean
     */
    public function isQuiet()
    {
        return $this->process->getOutput()->isQuiet();
    }

    /**
     * Is verbose?
     *
     * @return boolean
     */
    public function isVerbose()
    {
        return $this->process->getOutput()->isVerbose();
    }

    /**
     * Is very verbose?
     *
     * @return boolean
     */
    public function isVeryVerbose()
    {
        return $this->process->getOutput()->isVeryVerbose();
    }

    /**
     * Is debug?
     *
     * @return boolean
     */
    public function isDebug()
    {
        return $this->process->getOutput()->isDebug();
    }
}
