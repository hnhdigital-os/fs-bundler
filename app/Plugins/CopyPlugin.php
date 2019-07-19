<?php

namespace App\Plugins;

use Illuminate\Support\Arr;

class CopyPlugin extends BasePlugin
{
    /**
     * Paths.
     *
     * @var array
     */
    private $paths = [];

    /**
     * Error understanding path.
     */
    const COPY_ERROR = -1;

    /**
     * Copy all files and folders.
     */
    const COPY_ALL = 1;

    /**
     * Copy only files in the base directory.
     */
    const COPY_BASE = 2;

    /**
     * Copy file.
     */
    const COPY_FILE = 3;

    /**
     * Verify the configuration.
     *
     * @return bool
     */
    public function verify()
    {
        $paths = Arr::wrap(Arr::get($this->config, 'paths', []));

        foreach ($paths as $source_path => $destination_path) {
            $this->verifyPath($source_path, $destination_path);
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
    private function verifyPath($source_path, $destination_path)
    {
        $this->paths[$source_path] = $this->parsePaths($source_path, $destination_path);

        list($method, $source_path, $destination_path, $options) = $this->paths[$source_path];

        $this->storePath($source_path);

        // Generate the new file paths so we can validate other tasks in the future.
        switch ($method) {
            /*
             * Copying all files.
             * or Copying files from the base of the provided directory.
             */
            case self::COPY_ALL:
            case self::COPY_BASE:
                $method_arguments = ($method == self::COPY_BASE) ? [true, 1] : [];
                $paths = $this->scan($source_path, false, ...$method_arguments);
                $paths = $this->filterPaths($paths, array_get($options, 'source.filter', ''));

                if (substr($destination_path, -1) != '/') {
                    $destination_path .= '/';
                }

                foreach ($paths as $path) {
                    $destination_file = $destination_path.$path;
                    $this->storePath($destination_file);
                }

                break;
            /*
             * Copying a single file.
             */
            case self::COPY_FILE:
                if (substr($destination_path, -1) == '/') {
                    $destination_path = $this->checkPath($destination_path, !Elixir::dryRun());
                    $source_basename = basename($source_path);
                    $destination_path .= $source_basename;
                }

                $this->storePath($destination_path);
                break;
            /*
             * Copying error. File may not exist.
             */
            case self::COPY_ERROR:
                $this->process->error(sprintf('%s not found.', $original_source_path));

                return false;
        }

        return true;
    }

    /**
     * Check the path, set the options and clean the path.
     *
     * @param string $path
     *
     * @return array
     */
    private function parsePaths($source_path, $destination_path)
    {
        $options = [
            'source'      => [],
            'destination' => [],
        ];

        $source_options = &$options['source'];
        $destination_options = &$options['destination'];

        list($source_path, $source_options) = $this->parseOptions($source_path);
        list($destination_path, $destination_options) = $this->parseOptions($destination_path);

        if (($index = stripos($source_path, '*.')) !== false) {
            array_set($source_options, 'filter', substr($source_path, $index + 2));
            $source_path = substr($source_path, 0, $index + 1);
        }

        if (substr($source_path, -2) == '**') {
            return [self::COPY_ALL, substr($source_path, 0, -2),  $destination_path, $options];
        }

        if (substr($source_path, -1) == '*' || substr($source_path, -1) == '/') {
            return [self::COPY_BASE, substr($source_path, 0, -1),  $destination_path, $options];
        }

        if (is_file($source_path)) {
            return [self::COPY_FILE, $source_path, $destination_path, $options];
        }

        return [self::COPY_ERROR, '', '', $options];
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
    }

    /**
     * Handle the task.
     *
     * @return bool
     */
    private function handlePath($method, $source_path, $destination_path, $options)
    {
        switch ($method) {
            /*
             * Copying all files.
             */
            case self::COPY_ALL:
                $paths = $this->scan($source_path, false);
                $paths = $this->filterPaths($paths, array_get($options, 'source.filter', ''));

                if ($this->isVerbose() && count($paths) > 0) {
                    $this->process->info(sprintf('  Copying %s files...', count($paths)));
                }

                if (substr($destination_path, -1) != '/') {
                    $destination_path .= '/';
                }

                foreach ($paths as $path) {
                    $new_path = str_replace($source_path, $destination_path, $path);

                    if (array_has($options, 'destination.remove_extension_folder')) {
                        $pathinfo = pathinfo($new_path);
                        $new_path = preg_replace('/\b'.$pathinfo['extension'].'$/', '', $pathinfo['dirname']).$pathinfo['basename'];
                    }

                    $this->copyFile($path, $new_path);
                }

                break;
            /*
             * Copying files from the base of the provided directory.
             */
            case self::COPY_BASE:
                $paths = array_filter(scandir($source_path), function ($path) use ($source_path) {
                    return is_file($source_path.$path);
                });

                $paths = $this->filterPaths($paths, array_get($options, 'source.filter', ''));

                if ($this->isVerbose() && count($paths) > 0) {
                    $this->process->info(sprintf('  Copying %s files...', count($paths)));
                }

                if (substr($destination_path, -1) != '/') {
                    $destination_path .= '/';
                }

                foreach ($paths as $path) {
                    if ($path === '.' || $path === '..') {
                        continue;
                    }

                    $source_file = $source_path.$path;
                    $destination_file = $destination_path.$path;

                    $this->copyFile($source_file, $destination_file);
                }

                break;
            /*
             * Copying a single file.
             */
            case self::COPY_FILE:
                if (substr($destination_path, -1) == '/') {
                    $destination_path = $this->checkPath($destination_path);
                    $source_basename = basename($source_path);
                    $destination_path .= $source_basename;
                }

                $this->copyFile($source_path, $destination_path);

                break;
            /*
             * Error understanding path.
             */
            case self::COPY_ERROR:
                $this->process->error($source_path);

                return false;
        }


        return true;
    }
}
