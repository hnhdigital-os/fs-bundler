<?php

namespace App\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

trait TasksTrait
{
    /**
     * Default config file path.
     *
     * @var string
     */
    protected $config_yaml_path = '.tasker.config.yml';

    /**
     * Current Working Directory.
     *
     * @var array
     */
    protected $cwd;

    /**
     * Current environment.
     *
     * @var string
     */
    protected $env;

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
    protected $environments = [];

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
     * Paths.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * Tasks.
     *
     * @var array
     */
    protected $tasks = [];

    /**
     * Parse config.
     *
     * @return bool
     */
    private function parseConfig()
    {
        // Config path provided.
        if (!empty($this->option('config'))) {
            $this->config_yaml_path = $this->option('config');
        }

        // Check config path exists.
        if (!file_exists($this->config_yaml_path)) {
            $this->error(sprintf('Unable to find config file: %s', $this->config_yaml_path));

            return false;
        }

        // Parse the YAML config file.
        try {
            $this->config = Yaml::parse(file_get_contents($this->config_yaml_path));
        } catch (ParseException $e) {
            $this->error(sprintf('Unable to parse .elixir.yml: %s', $e->getMessage()));

            return false;
        }

        // Config path provided.
        if (!Arr::has($this->config, 'environments')) {
            $this->error('No environments specified!');

            return false;
        }

        $this->environments = Arr::get($this->config, 'environments', []);

        // Check if the env specified is in the list of environments.
        if ($this->option('env') && !in_array($this->option('env'), $this->environments)) {
            $this->error(sprintf('--env is one of %s', implode(', ', $this->environments)));

            return false;
        }

        if ($this->option('env')) {
            $this->env = $this->option('env');
        } else {
            $this->env = Arr::get($this->environments, '0');
        }

        $this->line(sprintf('Bundler is running in <info>%s</info>', $this->env));
        $this->line('');

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

        return true;
    }

    /**
     * Get environments.
     *
     * @return array
     */
    public function getEnvironments()
    {
        return $this->environments;
    }

    /**
     * Get current environment.
     *
     * @return array
     */
    public function getCurrentEnvironment()
    {
        return $this->env;
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
     * Verify tasks.
     *
     * @return void
     */
    private function verifyTasks()
    {
        if ($this->getOutput()->isVerbose()) {
            $this->line('');
        }

        foreach ($this->tasks as $task) {

            // Skip task if current environment not applicable for this environment.
            if (!$task->checkEnvironment()) {
                if ($this->getOutput()->isVerbose()) {
                    $this->line(sprintf('#%s <info>%s</info> <error>skipped</error>', $task->getId(), $task->getName()));
                }

                continue;
            }

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
    }

    /**
     * Process tasks.
     *
     * @return void
     */
    private function processTasks()
    {
        if ($this->getOutput()->isVerbose()) {
            $this->line('');
        }

        if (!is_null($this->option('tasks'))) {
            $tasks = explode(',', $this->option('tasks'));

            foreach ($tasks as $task_id) {
                $this->handleTask($this->tasks[(int) $task_id]);
            }
            

            $this->info('Completed.');
            return;
        }

        foreach ($this->tasks as $task) {
            $this->handleTask($task);
        }

        if ($this->getOutput()->isVerbose()) {
            $this->line('');
        }

        return true;
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
        // Skip task if current environment not applicable for this environment.
        if (!$task->checkEnvironment()) {
            if ($this->getOutput()->isVerbose()) {
                $this->line(sprintf('#%s <info>%s</info> <error>skipped</error>', $task->getId(), $task->getName()));
            }

            return;
        }

        if ($this->getOutput()->isVerbose()) {
            $this->line(sprintf('#%s <info>%s</info>', $task->getId(), $task->getName()));
        }

        $task->handle();
    }
}
