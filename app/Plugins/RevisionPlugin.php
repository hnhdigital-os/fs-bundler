<?php

namespace App\Plugins;

use File;
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
        if (!$this->verifyRequiredConfig(['src', 'dest', 'manifest', 'manifest.formats'])) {
            return;
        }

        $this->src_path = Arr::get($this->config, 'src');
        $this->dest_path = Arr::get($this->config, 'dest');

        $this->options = [
            'cache'       => $this->parseBooleanValue(Arr::get($this->config, 'cache', true)),
            'minify'      => $this->parseBooleanValue(Arr::get($this->config, 'minify', true)),
            'hash'        => Arr::get($this->config, 'hash', 'sha256'),
            'hash_length' => Arr::get($this->config, 'hash_length', 0),
        ];

        $this->manifest = Arr::get($this->config, 'manifest');

        if (!$this->verifyPaths(['src', 'dest'])) {
            return;
        }

        // Create the cache.
        if (Arr::get($this->options, 'cache') === true || Arr::get($this->options, 'cache') === '1') {
            Arr::set($this->options, 'cache-path', '.tasker.cache');
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
        $paths = File::allFiles($this->src_path);

        $manifest = [];

        foreach ($paths as $path) {
            $this->handleFile($path);
        }

        if (Arr::has($this->manifest, 'formats.json')) {
            $this->generateJsonManifest();
        }

        if (Arr::has($this->manifest, 'formats.php')) {
            $this->generatePhpManifest();
        }
    }

    /**
     * Handle each of the files.
     *
     * @param SplFileInfo $file
     *
     * @return void
     */
    private function handleFile($file)
    {
        $file_hash = $this->hashFile($file->getPathName());

        // Source file details
        $relative_path = str_replace($this->src_path.'/', '', $file->getRelativePath());

        // Minify file if enabled and not already minified (best guess by .min in filename)
        $minify = false;
        $minify_ext = '';

        if (Arr::get($this->options, 'minify', false)
            && in_array($file->getExtension(), ['css', 'js'])
            && stripos($file->getPathName(), '.min') === false) {
            $minify = true;
            $minify_ext = 'min.';
        }

        // Hashed and minified new file name.
        $destination_path = sprintf('%s/%s/', $this->dest_path, $relative_path);
        $destination_path .= sprintf('%s.%s.%s%s', $file->getBaseName(), $file_hash, $minify_ext, $file->getExtension());

        // Process minification to destination.
        if ($minify) {
            $this->minifyFile($file->getPathName(), $destination_path, $relative_path);
        } else if (!$minify) {
            $this->copyFile($file->getPathName(), $destination_path);
        }

        // Make file paths relative.
        $manifest_source = str_replace($this->src_path.'/', '', $file->getPathName());
        $manifest_rev = str_replace($this->dest_path.'/', '', $destination_path);

        $this->manifest_paths[$manifest_source] = $manifest_rev;
    }

    /**
     * Generate JSON manifest.
     *
     * @return void
     */
    private function generateJsonManifest()
    {

        if ($this->isVerbose()) {
            $this->process->line(sprintf(
                '   Generated <fg=cyan>%s</>',
                Arr::get($this->manifest, 'formats.json')
            ));
        }

        if ($this->process->isDry()) {
            return;
        }

        $json_manifest = json_encode($this->manifest_paths, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        File::put(Arr::get($this->manifest, 'formats.json'), $json_manifest);
    }

    /**
     * Generate PHP manifest.
     *
     * @return void
     */
    private function generatePhpManifest()
    {
        if ($this->isVerbose()) {
            $this->process->line(sprintf(
                '   Generated <fg=cyan>%s</>',
                Arr::get($this->manifest, 'formats.php')
            ));
        }

        if ($this->process->isDry()) {
            return;
        }

        $contents = "<?php\n\n";
        $contents .= "return [\n";

        foreach ($this->manifest_paths as $asset_path => $build_path) {
            $contents .= sprintf("    \"%s\" => \"%s\",\n", $asset_path, $build_path);
        }

        $contents .= "];\n";

        if ($this->process->isDry()) {
            return;
        }

        File::put(Arr::get($this->manifest, 'formats.php'), $contents);
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
        $minify_contents_hash = hash('sha384', File::get($source_path));
        $minify_previous_path = Arr::get($this->options, 'cache-path').'/'.$minify_file_hash.'.'.$minify_contents_hash;

        if (!file_exists($minify_previous_path)) {
            $this->runFileMinification($source_path, $minify_previous_path);
        }

        $this->copyFile($minify_previous_path, $destination_path);

        // Remove previous copies.
        foreach (File::glob(Arr::get($this->options, 'cache-path').'/'.$minify_file_hash.'.*') as $file_path) {
            if ($minify_previous_path === $file_path) {
                continue;
            }

            File::delete($file_path);
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
            $file_hash = File::lastModified($path);
        } else {
            $file_hash = hash(Arr::get($this->options, 'hash'), File::get($path));
        }

        if (Arr::get($this->options, 'hash_length', 0) > 0) {
            $file_hash = substr($file_hash, 0, Arr::get($this->options, 'hash_length'));
        }

        return $file_hash;
    }
}
