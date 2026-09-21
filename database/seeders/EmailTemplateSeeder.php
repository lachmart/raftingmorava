<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

// Default (simple, working) texts for all three templates – the system works right after deployment.
// The body text is Czech (user-facing); the {{macro}} tokens are English (code identifiers).
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => EmailTemplate::KEY_CONFIRMATION],
            [
                'name' => 'Potvrzení poptávky zákazníkovi',
                'description' => 'Odešle se automaticky při převedení poptávky na výlet.',
                'subject' => 'Potvrzení termínu – {{program_name}}',
                'body' => <<<'TXT'
Dobrý den {{client_name}},

děkujeme za Váš zájem. Potvrzujeme termín výletu „{{program_name}}“
v termínu {{date_range}}, start v {{start_time}}.

Předběžná cena celkem: {{total_price}}.

V případě dotazů nás kontaktujte na {{company_phone}} nebo {{company_email}}.

S pozdravem
Rafting Morava
TXT,
            ],
        );

        EmailTemplate::updateOrCreate(
            ['key' => EmailTemplate::KEY_SUMMARY],
            [
                'name' => 'Souhrnný e-mail instruktorovi',
                'description' => 'Odešle se ručně akcí „Odeslat souhrnné e-maily“ po přiřazení.',
                'subject' => 'Souhrn Vašich nadcházejících výletů',
                'body' => <<<'TXT'
Ahoj {{instructor_name}},

posíláme přehled Tvých nadcházejících výletů:

{{trip_list}}

Díky a ať to jede!
Rafting Morava
TXT,
            ],
        );

        EmailTemplate::updateOrCreate(
            ['key' => EmailTemplate::KEY_REMINDER],
            [
                'name' => 'Připomínka instruktorovi den předem',
                'description' => 'Odesílá naplánovaný job denně instruktorům, kteří mají výlet zítra.',
                'subject' => 'Připomínka – zítra máš výlet',
                'body' => <<<'TXT'
Ahoj {{instructor_name}},

připomínáme, že zítra Tě čeká:

{{tomorrow_trip_list}}

Měj se a ať to jede!
Rafting Morava
TXT,
            ],
        );

        EmailTemplate::updateOrCreate(
            ['key' => EmailTemplate::KEY_CLIENT_REMINDER_GUIDED],
            [
                'name' => 'Připomínka výletu klientovi (akce s instruktorem)',
                'description' => 'Odesílá naplánovaný job 14 dní před termínem výletu, s kopií na e-mail firmy.',
                'subject' => 'Připomínka – za 14 dní Vás čeká výlet',
                'body' => <<<'TXT'
Dobrý den {{client_name}},

připomínáme, že {{date_from}} Vás čeká výlet „{{trip_name}}“.

Sraz: {{start_time}} – {{location_name}}
{{location_note}}
Mapa: {{location_map_url}}

V případě dotazů nás kontaktujte na {{company_phone}} nebo {{company_email}}.

S pozdravem
Rafting Morava
TXT,
            ],
        );

        EmailTemplate::updateOrCreate(
            ['key' => EmailTemplate::KEY_CLIENT_REMINDER_RENTAL],
            [
                'name' => 'Připomínka výletu klientovi (půjčovna)',
                'description' => 'Odesílá naplánovaný job 14 dní před termínem výletu, s kopií na e-mail firmy.',
                'subject' => 'Připomínka – za 14 dní si vyzvedáváte vybavení',
                'body' => <<<'TXT'
Dobrý den {{client_name}},

připomínáme, že {{date_from}} si vyzvedáváte vybavení („{{trip_name}}“).

Čas a místo vyzvednutí: {{start_time}} – {{location_name}}
{{location_note}}
Mapa: {{location_map_url}}

V případě dotazů nás kontaktujte na {{company_phone}} nebo {{company_email}}.

S pozdravem
Rafting Morava
TXT,
            ],
        );
    }
}
