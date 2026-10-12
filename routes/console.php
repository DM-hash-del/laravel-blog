<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| The only task that moves a post from scheduled to published. Run locally
| with `php artisan schedule:work` (part of `composer run dev`) and on the
| host with one cron entry every minute. The overlap lock expires after 5
| minutes, so a run killed partway can block publishing for minutes, not a day.
|
*/

Schedule::command('posts:publish-scheduled')->everyMinute()->withoutOverlapping(5);
