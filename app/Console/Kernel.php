<?php

namespace App\Console;

use Flipbox\LumenGenerator\GeneratorsServiceProvider;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\GenerateInspectionReports::class,
    ];

    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule)
    {
        // Check for delivered shipments and generate BAs every hour or minute for testing
        $schedule->command('inspection:generate')->everyFiveMinutes();
    }

    public function bootstrap()
    {
        parent::bootstrap();
        $this->app->register(GeneratorsServiceProvider::class);
    }
}
