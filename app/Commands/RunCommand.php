<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class RunCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'run
                            {--config= : Config file path}
                            {--task-id= : Run this specific task}
                            {--list-only : List the tasks}
                            {--dry : Run tasks dry}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Run the bundler';

    /**
     * Default config file path.
     *
     * @var string
     */
    protected $config_yaml_path = '.bundler.yml';

    /**
     * Current Working Directory.
     *
     * @var array
     */
    protected $cwd;

    /**
     * Config.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Config.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Path constants.
     *
     * @var array
     */
    protected $path_constants = [];

    /**
     * Tasks config.
     *
     * @var array
     */
    protected $task_config = [];

    /**
     * Tasks.
     *
     * @var array
     */
    protected $tasks = [];

    /**
     * Paths.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->cwd = getcwd();

        $this->info('Running bundler...');
        $this->line('');

        $this->parseConfig();


        $this->info('Verifying...');

        if ($this->getOutput()->isVerbose()) {
            $this->line('');
        }

        foreach ($this->tasks as $task) {
            if ($this->getOutput()->isVerbose()) {
                $this->line(sprintf('#%s <info>%s</info>', $task->getId(), $task->getName()));
            }

            if (!$task->verify()) {
                $this->line('');
                $this->error('Verification failed');

                return;
            }
        }

        if ($this->getOutput()->isVerbose()) {
            $this->line('');
        }

        if ($this->option('list-only')) {
            $this->line('');
            $this->line('Tasks:');

            foreach ($this->tasks as $task) {
                $this->line(sprintf('#%s <info>%s</info>', $task->getId(), $task->getName()));
            }

            return;
        }

        $this->info('Processing...');

        if ($this->getOutput()->isVerbose()) {
            $this->line('');
        }

        if (!is_null($this->option('task-id'))) {
            $this->handleTask($this->tasks[(int) $this->option('task-id')]);

            $this->info('Completed.');
            return;
        }

        foreach ($this->tasks as $task) {
            $this->handleTask($task);
        }

        if ($this->getOutput()->isVerbose()) {
            $this->line('');
        }

        $this->info('Completed.');
    }

    /**
     * Handle a given task.
     *
     * @param Plugin $task
     *
     * @return void
     */
    private function handleTask($task)
    {
        if ($this->getOutput()->isVerbose()) {
            $this->line(sprintf('#%s <info>%s</info>', $task->getId(), $task->getName()));
        }

        $task->handle();
    }

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
        return $this->option('dry');
    }

    /**
     * Parse config.
     *
     * @return boid
     */
    private function parseConfig()
    {
        // Config path provided.
        if (!empty($this->option('config'))) {
            $this->config_yaml_path = $this->option('config');
        }

        // Check config path exists.
        if (!file_exists($this->config_yaml_path)) {
            $this->error(sprintf('   Unable to find config file: %s', $this->config_yaml_path));

            return false;
        }

        // Parse the YAML config file.
        try {
            $this->config = Yaml::parse(file_get_contents($this->config_yaml_path));
        } catch (ParseException $e) {
            $this->error(sprintf('   Unable to parse .elixir.yml: %s', $e->getMessage()));

            return false;
        }

        // Options.
        $this->options = Arr::get($this->config, 'options', []);

        // Path constants.
        $this->path_constants = Arr::get($this->config, 'paths', []);

        // Tasks.
        $this->task_config = $this->parsePathConstants(Arr::get($this->config, 'tasks', []));

        foreach ($this->task_config as $task_id => $config) {
            $plugin_class = $this->getPluginClass($config['plugin']);
            $config['task_id'] = $task_id;

            $this->tasks[] = new $plugin_class($this, $config);
        }
    }

    /**
     * Get plugin class.
     *
     * @param string $plugin
     *
     * @return string
     */
    private function getPluginClass($plugin)
    {
        return 'App\\Plugins\\'.Str::studly($plugin).'Plugin';
    }

    /**
     * Parse constant paths over the task config.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function parsePathConstants($value)
    {
        if (!is_array($value)) {
            $value = str_replace(array_keys($this->path_constants), array_values($this->path_constants), $value);
            $value = str_replace([' + ', '+', '"', "'"], '', $value);

            return $value;
        }

        foreach ($value as $key => &$sub_value) {
            $sub_value = $this->parsePathConstants($sub_value);

            if (!is_string($key)) {
                continue;
            }

            $new_key = $this->parsePathConstants($key);

            if ($new_key !== $key) {
                unset($value[$key]);
                $value[$new_key] = $sub_value;
            }
        }

        return $value;
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
     * @param mixed $path
     * 
     * @return void
     */
    public function storePath($path)
    {
        // Multiple path parameters.
        if (count(func_get_args()) >= 2) {
            $this->storePath(func_get_args());

            return;
        }

        // Paths is an array.
        if (is_array($path)) {
            foreach ($path as $file_path) {
                $this->storePath($file_path);
            }

            return;
        }

        $this->paths[$path] = true;
    }
}
