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
    protected $description = 'Watch a folder for changes and run the tasker';

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
