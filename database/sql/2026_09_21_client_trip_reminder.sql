-- Produkční SQL pro funkci "připomínkový e-mail klientovi 14 dní před výletem".
-- Hosting nemá CLI (žádné `php artisan migrate`/`db:seed`), proto se schéma a šablony
-- nahrávají ručně přes Adminer/phpMyAdmin. Odpovídá migracím:
--   2026_09_15_090000_add_customer_category_to_trips_table
--   2026_09_15_100000_add_email_fields_to_locations_table
--   2026_09_15_100001_add_reminder_sent_at_to_trips_table
-- a řádkům v database/seeders/EmailTemplateSeeder.php (client_reminder_guided / client_reminder_rental).
--
-- Spustit jako jeden skript v jedné transakci. Po importu na produkci zavolat
-- /cron/{token}/clear-cache (viz app/Http/Controllers/CronController.php).

START TRANSACTION;

-- 1) trips.customer_category (kategorie zákazníka – kdo si výlet objednal)
ALTER TABLE `trips`
    ADD COLUMN `customer_category` ENUM('school_nonprofit', 'company', 'public') NULL DEFAULT NULL AFTER `client_name`;

-- 2) locations.email_note + locations.map_url (text do připomínkového e-mailu a odkaz na mapy.com)
ALTER TABLE `locations`
    ADD COLUMN `email_note` TEXT NULL DEFAULT NULL AFTER `name`,
    ADD COLUMN `map_url` VARCHAR(255) NULL DEFAULT NULL AFTER `email_note`;

-- 3) trips.reminder_sent_at (ochrana proti opakovanému odeslání připomínky)
ALTER TABLE `trips`
    ADD COLUMN `reminder_sent_at` TIMESTAMP NULL DEFAULT NULL AFTER `inquiry_id`;

-- 4) Záznamy do `migrations`, aby si Laravel myslel, že migrace už proběhly
--    (kdyby se v budoucnu spustilo `php artisan migrate` přes nějaký servisní endpoint).
INSERT INTO `migrations` (`migration`, `batch`)
SELECT * FROM (
    SELECT '2026_09_15_090000_add_customer_category_to_trips_table' AS migration, (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`) AS batch
    UNION ALL
    SELECT '2026_09_15_100000_add_email_fields_to_locations_table', (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`)
    UNION ALL
    SELECT '2026_09_15_100001_add_reminder_sent_at_to_trips_table', (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations`)
) AS new_migrations
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations` m WHERE m.migration = new_migrations.migration
);

-- 5) E-mailové šablony (odpovídá EmailTemplateSeeder – lze bezpečně spustit i podruhé,
--    případná ruční úprava textu v adminu se přepíše zpět na výchozí znění).
INSERT INTO `email_templates` (`key`, `name`, `subject`, `body`, `description`, `created_at`, `updated_at`)
VALUES ('client_reminder_guided', 'Připomínka výletu klientovi (akce s instruktorem)', 'Připomínka – za 14 dní Vás čeká výlet', 'Dobrý den {{client_name}},

připomínáme, že {{date_from}} Vás čeká výlet „{{trip_name}}“.

Sraz: {{start_time}} – {{location_name}}
{{location_note}}
Mapa: {{location_map_url}}

V případě dotazů nás kontaktujte na {{company_phone}} nebo {{company_email}}.

S pozdravem
Rafting Morava', 'Odesílá naplánovaný job 14 dní před termínem výletu, s kopií na e-mail firmy.', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `subject` = VALUES(`subject`),
    `body` = VALUES(`body`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

INSERT INTO `email_templates` (`key`, `name`, `subject`, `body`, `description`, `created_at`, `updated_at`)
VALUES ('client_reminder_rental', 'Připomínka výletu klientovi (půjčovna)', 'Připomínka – za 14 dní si vyzvedáváte vybavení', 'Dobrý den {{client_name}},

připomínáme, že {{date_from}} si vyzvedáváte vybavení („{{trip_name}}“).

Čas a místo vyzvednutí: {{start_time}} – {{location_name}}
{{location_note}}
Mapa: {{location_map_url}}

V případě dotazů nás kontaktujte na {{company_phone}} nebo {{company_email}}.

S pozdravem
Rafting Morava', 'Odesílá naplánovaný job 14 dní před termínem výletu, s kopií na e-mail firmy.', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `subject` = VALUES(`subject`),
    `body` = VALUES(`body`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

COMMIT;
