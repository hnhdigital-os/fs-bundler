<?php

namespace App\Plugins;

use Illuminate\Support\Arr;

class RevisionPlugin extends BasePlugin
{
    /**
     * Source path.
     *
     * @var array
     */
    private $src_path;

    /**
     * Destination path.
     *
     * @var array
     */
    private $dest_path;

    /**
     * Manifest settings.
     *
     * @var array
     */
    private $manifest;

    /**
     * Manifest paths.
     *
     * @var array
     */
    private $manifest_paths;

    /**
     * Manifest settings.
     *
     * @var array
     */
    private $options;

    /**
     * Verify the configuration.
     *
     * @return bool
     */
    public function verify()
    {
        if (!$this->verifyRequiredConfig(['src', 'dest', 'manifest', 'manifest.path'])) {
            return;
        }

        $this->src_path = $this->process->getCwd(Arr::get($this->config, 'src'));
        $this->dest_path = $this->process->getCwd(Arr::get($this->config, 'dest'));

        $this->options = [
            'cache'       => Arr::get($this->config, 'cache', true),
            'minify'      => Arr::get($this->config, 'minify', true),
            'hash'        => Arr::get($this->config, 'hash', 'sha256'),
            'hash_length' => Arr::get($this->config, 'hash_length', 0),
        ];

        $this->manifest = Arr::get($this->config, 'manifest');

        if (!$this->verifyPaths(['src', 'dest'])) {
            return;
        }

        // Create the cache.
        if (Arr::get($this->options, 'cache') === true || Arr::get($this->options, 'cache') === '1') {
            Arr::set($this->options, 'cache-path', $this->process->getCwd('.bundler.cache'));
        // Create custom cache.
        } elseif (is_string(Arr::get($this->options, 'cache', ''))
            && !empty(Arr::get($this->options, 'cache', ''))) {
            Arr::set($this->options, 'cache-path', Arr::get($this->options, 'cache', ''));
            Arr::set($this->options, 'cache', true);
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
        $paths = $this->scan($this->src_path, false);

        $manifest = [];

        foreach ($paths as $source_path) {
            $this->handlePath($source_path);
        }

        if ($this->process->isDry()) {
            return;
        }

        if (in_array('json', Arr::get($this->manifest, 'formats'))) {
            $this->generateJsonManifest();
        }


        if (in_array('php', Arr::get($this->manifest, 'formats'))) {
            $this->generatePhpManifest();
        }
    }

    /**
     * Generate JSON manifest.
     *
     * @return void
     */
    private function generateJsonManifest()
    {
        $json_manifest = json_encode($this->manifest_paths, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        file_put_contents(Arr::get($this->manifest, 'path').'.js', $json_manifest);
    }

    /**
     * Generate PHP manifest.
     *
     * @return void
     */
    private function generatePhpManifest()
    {
        $contents = "<?php\n\n";
        $contents .= "return [\n";

        foreach ($this->manifest_paths as $asset_path => $build_path) {
            $contents .= sprintf("    \"%s\" => \"%s\",\n", $asset_path, $build_path);
        }

        $contents .= "];\n";

        file_put_contents(Arr::get($this->manifest, 'path').'.php', $contents);
    }

    /**
     * Handle each of the files.
     *
     * @param string $source_path
     *
     * @return void
     */
    private function handlePath($source_path)
    {
        $file_hash = $this->hashFile($source_path);

        // Source file details
        $path_info = pathinfo($source_path);
        $relative_path = str_replace($this->src_path.'/', '', $path_info['dirname']);

        // Minify file if enabled and not already minified (best guess by .min in filename)
        $minify = false;
        $minify_ext = '';

        if (Arr::get($this->options, 'minify', false)
            && in_array($path_info['extension'], ['css', 'js'])
            && stripos($source_path, '.min') === false) {
            $minify = true;
            $minify_ext = 'min.';
        }

        // Hashed and minified new file name.
        $destination_path = sprintf('%s/%s/', $this->dest_path, $relative_path);
        $destination_path .= sprintf('%s.%s.%s%s', $path_info['filename'], $file_hash, $minify_ext, $path_info['extension']);

        // Process minification to destination.
        if ($minify) {
            $this->minifyFile($source_path, $destination_path, $relative_path);
        } else if (!$minify) {
            $this->copyFile($source_path, $destination_path);
        }

        // Make file paths relative.
        $manifest_source = str_replace($this->src_path.'/', '', $source_path);
        $manifest_rev = str_replace($this->dest_path.'/', '', $destination_path);

        $this->manifest_paths[$manifest_source] = $manifest_rev;
    }

    /**
     * Minify file.
     *
     * @param string $source_path
     * @param string $destination_path
     *
     * @return string
     */
    private function minifyFile($source_path, $destination_path)
    {
        if ($this->process->isDry()) {
            return;
        }

        if (!Arr::get($this->options, 'cache')) {
            $this->runFileMinification($source_path, $destination_path);

            return;
        }

        $this->createDirectory(Arr::get($this->options, 'cache-path'), true);

        // Minify cache.
        $minify_file_hash = hash('sha384', $source_path);
        $minify_contents_hash = hash_file('sha384', $source_path);
        $minify_previous_path = Arr::get($this->options, 'cache-path').'/'.$minify_file_hash.'.'.$minify_contents_hash;

        if (!file_exists($minify_previous_path)) {
            $this->runFileMinification($source_path, $minify_previous_path);
        }

        $this->copyFile($minify_previous_path, $destination_path);

        // Remove previous copies.
        foreach (glob(Arr::get($this->options, 'cache-path').'/'.$minify_file_hash.'.*') as $file_path) {
            if ($minify_previous_path === $file_path) {
                continue;
            }

            unlink($file_path);
        }
    }

    /**
     * Run the file minificiation.
     *
     * @param string $source_path
     * @param string $destination_path
     *
     * @return void
     */
    private function runFileMinification($source_path, $destination_path)
    {
        $class = '\\MatthiasMullie\\Minify\\'.strtoupper(pathinfo($source_path, PATHINFO_EXTENSION));
        (new $class($source_path))->minify($destination_path);
    }

    /**
     * Hash file.
     *
     * @param string $path
     *
     * @return string
     */
    private function hashFile($path)
    {
        // Generate hash of file.
        if (Arr::get($this->options, 'hash') === 'mtime') {
            $file_hash = filemtime($path);
        } else {
            $file_hash = hash_file(Arr::get($this->options, 'hash'), $path);
        }

        if (Arr::get($this->options, 'hash_length', 0) > 0) {
            $file_hash = substr($file_hash, 0, Arr::get($this->options, 'hash_length'));
        }

        return $file_hash;
    }
}
