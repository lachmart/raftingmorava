<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Day-before reminder for instructors.
 |
 | Scheduler bezi pres URL cron (App\Http\Controllers\CronController), coz prinasi dve omezeni:
 |
 |  1) Schedule::call() misto Schedule::command() – `command()` spousti novy proces
 |     (PHP_BINARY artisan ...), coz pod PHP-FPM na sdilenem hostingu casto selze
 |     (proc_open byva zakazany). Callback bezi primo v tomto requestu.
 |
 |  2) Cron hostingu se netrefi do konkretni minuty, takze dailyAt('08:00') by se nemusel
 |     nikdy uplatnit. Uloha je proto splatna v celem okne 8:00–11:59 a `skip()` s dennim
 |     priznakem v cache hlida, aby se pripominky odeslaly jen jednou za den.
 |
 | Pozn.: config('app.timezone') je UTC, proto je u ulohy explicitni ->timezone('Europe/Prague'),
 | aby 8:00 znamenalo 8:00 ceskeho casu (bez toho by okno zacinalo v 10:00 letniho casu).
 */
Schedule::call(fn () => Artisan::call('instructors:reminder'))
    ->name('instructors:reminder')
    ->everyMinute()
    ->timezone('Europe/Prague')
    ->between('08:00', '11:59')
    ->skip(fn () => Cache::get('cron:instructors-reminder') === now()->toDateString())
    ->onSuccess(fn () => Cache::put('cron:instructors-reminder', now()->toDateString(), now()->addDay()))
    ->withoutOverlapping();

/*
 | 14-day-before reminder for clients (see app/Console/Commands/SendClientTripReminders.php).
 | Same hourly-URL-cron constraints as above. Unlike the instructor reminder, this one is also
 | guarded by a per-trip `reminder_sent_at` flag, so a missed day still catches up later - the
 | daily cache flag here is only to avoid re-querying every minute of the window.
 */
Schedule::call(fn () => Artisan::call('clients:trip-reminder'))
    ->name('clients:trip-reminder')
    ->everyMinute()
    ->timezone('Europe/Prague')
    ->between('08:00', '11:59')
    ->skip(fn () => Cache::get('cron:clients-trip-reminder') === now()->toDateString())
    ->onSuccess(fn () => Cache::put('cron:clients-trip-reminder', now()->toDateString(), now()->addDay()))
    ->withoutOverlapping();
