<?php

namespace App\Console;

use Flipbox\LumenGenerator\GeneratorsServiceProvider;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [];

    public function bootstrap()
    {
        parent::bootstrap();
        $this->app->register(GeneratorsServiceProvider::class);
    }
}
