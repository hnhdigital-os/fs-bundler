<?php

namespace App\Commands;

use App\Traits\TasksTrait;
use LaravelZero\Framework\Commands\Command;

class TasksCommand extends Command
{
    use TasksTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'tasks
                            {--config= : Config file path}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'List the tasks';

    /**
     * Tasks.
     *
     * @var array
     */
    protected $tasks = [];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->cwd = getcwd();

        $this->parseConfig();

        $this->line('');
        $this->line('Tasks:');

        foreach ($this->tasks as $task) {
            $this->line(sprintf('#%s <info>%s</info>', $task->getId(), $task->getName()));
        }
    }
}
