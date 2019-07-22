<?php

namespace App\Plugins;

use Illuminate\Support\Arr;

class ExecPlugin extends BasePlugin
{
    /**
     * Exxecutable path.
     *
     * @var string
     */
    private $executable;

    /**
     * Arguments.
     *
     * @var array
     */
    private $src_path;

    /**
     * Paths to store.
     *
     * @var array
     */
    private $store_paths;

    /**
     * Verify the configuration.
     *
     * @return bool
     */
    public function verify()
    {
        if (!$this->verifyRequiredConfig(['executable'])) {
            return;
        }

        $executable = Arr::get($this->config, 'executable');
        $this->arguments = Arr::get($this->config, 'arguments');
        $this->store_paths = $this->parseStringArrayValue(Arr::get($this->config, 'store-paths', []));

        if (is_dir($this->executable)) {
            $this->process->error(sprintf('Invalid executable: %s', $this->executable));

            return false;
        }

        if (($this->executable = $this->getExecutablePath($executable)) === false) {
            $this->process->error(sprintf('Can not find executable %s.', $executable));

            return false;
        }

        foreach ($this->store_paths as $path) {
            $this->storePath($path);
        }

        // Check permissions to execute this file
        $permissions = fileperms($this->executable);
        $file_stat = stat($this->executable);
        $file_uid = posix_getuid();
        $file_gid = posix_getgid();
        $is_executable = false;

        if ($file_stat['uid'] == $file_uid) {
            $is_executable = (($permissions & 0x0040) ?
                (($permissions & 0x0800) ? true : true) :
                (($permissions & 0x0800) ? true : $is_executable));
        }

        if ($file_stat['gid'] == $file_gid) {
            $is_executable = (($permissions & 0x0008) ?
                (($permissions & 0x0400) ? true : true) :
                (($permissions & 0x0400) ? true : $is_executable));
        }

        $is_executable = (($permissions & 0x0001) ?
            (($permissions & 0x0200) ? true : true) :
            (($permissions & 0x0200) ? true : $is_executable));

        if (!$is_executable) {
            $this->process->error(sprintf('Can not run %s %s', $executable, $this->arguments));
        }

        return $is_executable;
    }

    /**
     * Get excutable path.
     *
     * @param string $path
     *
     * @return string|bool
     */
    private function getExecutablePath($path)
    {
        if (file_exists($path)) {
            return $path;
        }

        $path = trim(shell_exec(sprintf('which %s 2>&1', $path)));

        if (stripos($path, 'which: no') === false) {
            $path = trim(shell_exec(sprintf('readlink -f %s', $path)));
            return file_exists($path) ? $path : false;
        }

        return false;
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

        $run_function = $this->isVerbose() ? 'passthru' : 'exec';
        $arguments = $this->arguments;
        $arguments .= !$this->isVerbose() ? ' > /dev/null 2> /dev/null' : '';

        if ($this->isVerbose()) {
            $this->process->line(sprintf('Excuting %s %s', $this->executable, $arguments));
        }

        $run_function(sprintf('%s %s', $this->executable, $arguments));
    }
}
