<?php

namespace App\Console;

use App\Console\Commands\InstallApplication;
use App\Jobs\RotateStalePassTokensJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        InstallApplication::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new RotateStalePassTokensJob())
            ->everyFiveMinutes()
            ->name('rotate-pass-tokens')
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}