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
v termínu {{date_from}} – {{date_to}}.

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
    }
}
