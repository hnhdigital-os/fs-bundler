<?php

namespace App\Commands;

use App\Traits\HelperTrait;
use App\Traits\TasksTrait;
use LaravelZero\Framework\Commands\Command;

class DryCommand extends Command
{
    use HelperTrait, TasksTrait;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'dry
                            {--config= : Config file path}
                            {--env= : Set environment}
                            {--tasks= : Run specific tasks}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Dry run the tasks';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->dry = true;
        $this->cwd = getcwd();

        $this->info('Dry run of the bundler...');
        $this->line('');

        if (!$this->parseConfig()) {
            return;
        }

        $this->info('Verifying...');

        $this->verifyTasks();        

        $this->info('Processing...');

        if ($this->processTasks()) {
            $this->info('Completed.');
        }
    }
}
