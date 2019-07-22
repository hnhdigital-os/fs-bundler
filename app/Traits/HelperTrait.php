<?php

namespace App\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

trait HelperTrait
{
    protected $dry = false;

    /**
     * Get current working directory.
     *
     * @param  string $path
     *
     * @return string
     */
    public function getCwd($path = '')
    {
        if ($path === false) {
            return false;
        }

        return $this->cwd.'/'.$path;
    }

    /**
     * Is dry run.
     *
     * @return boolean
     */
    public function isDry()
    {
        return $this->dry;
    }

    /**
     * Parse options off a string.
     *
     * @return array
     */
    public function parseOptions($input)
    {
        $input_array = explode('?', $input);
        $string = $input_array[0];
        $string_options = !empty($input_array[1]) ? $input_array[1] : '';
        $options = [];
        parse_str($string_options, $options);

        return [$string, $options];
    }

    /**
     * Strip search depth from path.
     *
     * @param string $path
     *
     * @return void
     */
    public function stripSearchDepth($path)
    {
        // Remove search depth if appended.
        if (substr($path, -2) == '**') {
            $path = substr($path, 0, -2);
        } elseif (substr($path, -1) == '*' || substr($path, -1) == '/') {
            $path = substr($path, 0, -1);
        }

        return $path;
    }

    /**
     * Check path.
     *
     * @return bool
     */
    public function checkPath($original_path)
    {
        list($path, $options) = $this->parseOptions(trim($original_path));

        $path = $this->stripSearchDepth($path);

        $cwd_path = str_replace('//', '/', $this->getCwd($path));

        if (Arr::has($this->paths, $path) || file_exists($path)) {
            return $path;
        } elseif (Arr::has($this->paths, $cwd_path) || file_exists($cwd_path)) {
            return $base_path;
        }

        return false;
    }

    /**
     * Store path.
     *
     * @param string|array $path
     * @param bool|string $source_path
     * 
     * @return void
     */
    public function storePath($path, $source_path = true)
    {
        // Paths is an array.
        if (is_array($path)) {
            foreach ($path as $file_path) {
                $this->storePath($file_path);
            }

            return;
        }

        $this->paths[$path] = $source_path;
    }
}
