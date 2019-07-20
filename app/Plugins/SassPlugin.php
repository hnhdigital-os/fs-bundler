<?php

namespace App\Plugins;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ScssPhp\ScssPhp\Compiler;

class SassPlugin extends BasePlugin
{
    /**
     * Format options.
     *
     * @var array
     */
    private $format_options = ['expanded', 'nested', 'compressed', 'compact', 'crunched'];

    /**
     * Source path.
     *
     * @var string
     */
    private $src_path;

    /**
     * Destination path.
     *
     * @var string
     */
    private $dest_path;

    /**
     * Destination path.
     *
     * @var array
     */
    private $import_paths = [];

    /**
     * Options.
     *
     * @var array
     */
    private $options = [];

    /**
     * Source Map.
     *
     * @var string
     */
    private $source_map;

    /**
     * Source Map Keys.
     *
     * @var array
     */
    private $source_map_keys = [
        'url'      => 'sourceMapURL',
        'filename' => 'sourceMapFilename',
        'basepath' => 'sourceMapBasepath',
        'root'     => 'sourceRoot',
    ];

    /**
     * Verify the configuration.
     *
     * @return bool
     */
    public function verify()
    {
        if (!$this->verifyRequiredConfig(['src', 'dest'])) {
            return;
        }

        $this->src_path = $this->process->getCwd(Arr::get($this->config, 'src'));
        $this->dest_path = $this->process->getCwd(Arr::get($this->config, 'dest'));
        $this->import_paths = Arr::get($this->config, 'import-paths', []);
        $this->source_map = Arr::get($this->config, 'source-map', false);
        $this->options = Arr::get($this->config, 'options', []);

        // Force array.
        if (is_string($this->options)) {
            $this->options = [];
        }

        // Check source path.
        if (!$this->verifyPaths(['src'])) {
            return;
        }

        // Formatter option.
        if (Arr::has($this->options, 'format')) {
            if (!in_array(Arr::get($this->options, 'format'), $this->format_options)) {
                $this->process->error(sprintf('Invalid SASS format option provided: %s', Arr::get($this->options, 'format')));
                $this->process->line(sprintf('Format options available: %s', implode(' ', $this->format_options)));

                return;
            }
        } else {
            Arr::set($this->options, 'format', 'nested');
        }

        // Number precision.
        if (Arr::has($this->options, 'precision')) {
            $precision = Arr::get($this->options, 'precision');

            if ($precision < 1 || $precision > 10) {
                $this->process->error(sprintf('Invalid SASS number precision provided: %s', Arr::get($this->options, 'precision')));
                $this->process->line('Precision should be between 1 and 10. Default is 5.');

                return;
            }
        } else {
            Arr::set($this->options, 'precision', 5);
        }

        $this->storePath($this->dest_path);

        if ($this->source_map !== false) {
            if (!$this->verifyRequiredConfig(['source-map.path'])) {
                return;
            }

            Arr::set($this->source_map, 'path', Arr::get($this->source_map, 'path'));
            $this->storePath(Arr::get($this->source_map, 'path'));
        }

        foreach ($this->import_paths as &$path) {
            $path = $this->process->getCwd($path);
            unset($path);
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
        if ($this->process->isDry()) {
            return;
        }

        $import_path = dirname($this->src_path);

        $scss = new Compiler();

        $scss->addImportPath($import_path);
        $scss->addImportPath($this->process->getCwd());

        foreach ($this->import_paths as $path) {
            $scss->addImportPath($path);
        }

        $sass_contents = file_get_contents($this->src_path);

        // Make ~ relative paths point to node_modules.
        if (Arr::get($this->options, 'disable-node-modules-tilde') !== '1') {
            $sass_contents = preg_replace("/@import(?:\s)'~(.*?)'/", "@import 'node_modules/$1'", $sass_contents);
        }

        // Set formatter.
        $scss->setFormatter('ScssPhp\\ScssPhp\\Formatter\\'.Str::studly(Arr::get($this->options, 'format')));

        // Set number precision
        $scss->setNumberPrecision(Arr::get($this->options, 'precision'));

        if ($this->source_map !== false) {
            $scss->setSourceMap(Compiler::SOURCE_MAP_FILE);

            $source_map_options = [
                'sourceMapWriteTo' => Arr::get($this->source_map, 'path'),
            ];

            foreach ($this->source_map_keys as $config_key => $compiler_key) {
                if (Arr::has($this->source_map, $config_key)) {
                    Arr::set($source_map_options, $compiler_key, Arr::get($this->source_map, $config_key));
                }
            }

            $scss->setSourceMapOptions($source_map_options);
        }

        try {
            $compiled_scss = $scss->compile($sass_contents);
        } catch (\Exception $e) {
            $this->process->error($e->getMessage());

            return;
        }

        if ($this->checkPath(dirname($this->dest_path)) === false) {
            $this->createDirectory($dest_path_dir);
        }

        file_put_contents($this->dest_path, $compiled_scss);

        if ($this->isVeryVerbose()) {
            $this->process->line(sprintf('   <fg=yellow>Created</> %s', str_replace($this->process->getCwd(), '', $this->dest_path)));
        }

        if ($this->source_map !== false && $this->isVeryVerbose()) {
            $this->process->line(sprintf('   <fg=yellow>Created</> %s', str_replace($this->process->getCwd(), '', Arr::get($this->source_map, 'path'))));
        }

        return true;
    }
}
