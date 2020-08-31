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
     * Verify the configuration.
     *
     * @return bool
     */
    public function verify()
    {
        $paths = $this->parseStringArrayValue(Arr::get($this->config, 'paths', []));

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
                $paths = $this->filterPathExtensions($paths, Arr::get($options, 'source.extensions', ''));

                if (substr($destination_path, -1) != '/') {
                    $destination_path .= '/';
                }

                foreach ($paths as $path) {
                    $destination_file = $destination_path.basename($path);
                    $this->storePath($destination_file, $path);
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

                $this->storePath($destination_path, $source_path);
                break;
            /*
             * Copying error. File may not exist.
             */
            case self::COPY_ERROR:
                $this->process->error(sprintf('%s not found.', $source_path));

                return false;
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
                $paths = $this->filterPathExtensions($paths, Arr::get($options, 'source.extensions', ''));

                if ($this->isVerbose() && !$this->isVeryVerbose() && count($paths) > 0) {
                    $this->process->line(sprintf(
                        '   Copying %s files from <fg=cyan>%s</> to <fg=cyan>%s</>',
                        count($paths),
                        $source_path,
                        $destination_path
                    ));
                }

                if (substr($destination_path, -1) != '/') {
                    $destination_path .= '/';
                }

                foreach ($paths as $path) {
                    $new_path = str_replace($source_path, $destination_path, $path);

                    if (Arr::has($options, 'destination.remove_extension_folder')) {
                        $pathinfo = pathinfo($new_path);

                        if (Arr::has($pathinfo, 'extension')) {
                            $new_path = preg_replace('/\b'.$pathinfo['extension'].'$/', '', $pathinfo['dirname']).$pathinfo['basename'];
                        }
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

                $paths = $this->filterPathExtensions($paths, Arr::get($options, 'source.extensions', ''));

                if ($this->isVerbose() && !$this->isVeryVerbose() && count($paths) > 0) {
                    $this->process->line(sprintf(
                        '   Copying %s files <fg=cyan>%s</> to <fg=cyan>%s</>',
                        count($paths),
                        $source_path,
                        $destination_path
                    ));
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

                if ($this->isVerbose() && !$this->isVeryVerbose()) {
                    $this->process->line(sprintf(
                        '   Copying <fg=cyan>%s</> to <fg=cyan>%s</>',
                        $source_path,
                        $destination_path
                    ));
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
