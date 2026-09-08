<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Servisni endpointy pres URL – hosting neumi spustit `php artisan` z prikazove radky.
 *
 * run():        zastupuje `schedule:run` (naplanovane ulohy z routes/console.php)
 *               a dobere zbytky z databazove fronty.
 *               Volat periodicky z cronu hostingu: /cron/<CRON_TOKEN>
 *
 * clearCache(): zahodi zkompilovane sablony a cache konfigurace/rout po kazdem nasazeni.
 *               Volat rucne v prohlizeci:          /cron/<CRON_TOKEN>/clear-cache
 */
class CronController extends Controller
{
    public function run(Request $request, string $token): Response
    {
        $this->authorizeToken($token);

        // Cron hostingu muze zavolat znovu driv, nez predchozi beh dojede.
        $lock = Cache::lock('cron:http', 600);

        if (! $lock->get()) {
            return $this->text("Predchozi beh cronu jeste probiha, preskoceno.\n");
        }

        @set_time_limit(300);
        ignore_user_abort(true);

        try {
            Artisan::call('schedule:run');
            $schedule = trim(Artisan::output());

            // Fronta je na produkci prepnuta na `sync` (e-maily odchazi hned), tohle je uklid
            // pripadnych zbytku v tabulce jobs – proto natvrdo connection `database`.
            // --stop-when-empty: worker skonci, jakmile je fronta prazdna (nebezi donekonecna).
            // --max-time: tvrdy strop, aby se request vesel do limitu hostingu.
            Artisan::call('queue:work', [
                'connection' => 'database',
                '--stop-when-empty' => true,
                '--max-time' => 240,
                '--tries' => 3,
            ]);
            $queue = trim(Artisan::output());
        } finally {
            $lock->release();
        }

        return $this->text("== schedule:run ==\n{$schedule}\n\n== queue:work ==\n{$queue}\n");
    }

    public function clearCache(Request $request, string $token): Response
    {
        $this->authorizeToken($token);

        @set_time_limit(120);

        // Zamerne bez `optimize:clear` – ten volá i `cache:clear`, coz by smazalo denni
        // priznak odeslanych pripominek a mohly by tak odejit dvakrat.
        $commands = [
            'view:clear',
            'config:clear',
            'route:clear',
            'event:clear',
            'clear-compiled',
            'filament:clear-cached-components',
        ];

        $output = [];

        foreach ($commands as $command) {
            try {
                Artisan::call($command);
                $output[] = "[ok]   {$command}: ".(trim(Artisan::output()) ?: 'hotovo');
            } catch (\Throwable $e) {
                $output[] = "[chyba] {$command}: ".$e->getMessage();
            }
        }

        return $this->text(implode("\n", $output)."\n");
    }

    /** Nespravny token se tvari jako neexistujici stranka – endpoint se nema cim prozradit. */
    private function authorizeToken(string $token): void
    {
        $expected = (string) config('company.cron_token');

        abort_if($expected === '' || ! hash_equals($expected, $token), 404);
    }

    private function text(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
