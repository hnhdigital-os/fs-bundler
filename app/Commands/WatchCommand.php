<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class WatchCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'watch';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Watch a folder for changes and run the bundler';

    /**
     * Default config file path.
     *
     * @var string
     */
    protected $config_yaml_path = '.bundler.yml';

    /**
     * Config.
     *
     * @var array
     */
    protected $config = [];

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        
    }
}
