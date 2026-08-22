<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('pos:expire-pending')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('analytics:recalculate')
    ->dailyAt('00:15')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(180)
    ->onOneServer();

Schedule::command('billing:sweep-subscriptions')
    ->dailyAt('00:05')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(180)
    ->onOneServer();

Schedule::command('impersonation:expire')
    ->everyFiveMinutes()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('tenant-deletion:queue-due')
    ->dailyAt('00:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(180)
    ->onOneServer();

Schedule::command('tenant-deletion:purge')
    ->dailyAt('01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(180)
    ->onOneServer();
