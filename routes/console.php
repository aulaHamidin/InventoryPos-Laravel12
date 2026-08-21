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
