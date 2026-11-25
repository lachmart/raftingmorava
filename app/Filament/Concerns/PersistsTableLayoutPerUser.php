<?php

namespace App\Filament\Concerns;

use App\Models\TablePreference;

/**
 * Remembers a table's layout per user across logins: column order and visibility (column manager)
 * and rows per page. Filament keeps these only in the session, which is thrown away on logout.
 *
 * Works around Filament instead of overriding its methods: before the table boots, the user's saved
 * values are put into the session under Filament's own keys; at the end of each request, whatever
 * Filament left in the session is saved back when it changed. Sort, filters and search stay
 * session-only on purpose (the date filters have "from today" defaults).
 */
trait PersistsTableLayoutPerUser
{
    /** Runs before InteractsWithTable's booted hook, which reads the session. */
    public function bootPersistsTableLayoutPerUser(): void
    {
        $userId = auth()->id();
        $loadedFlag = $this->tableLayoutSessionKeyPrefix().'loaded';

        if (! $userId || session()->get($loadedFlag)) {
            return;
        }

        TablePreference::query()
            ->where('user_id', $userId)
            ->whereIn('key', $this->tableLayoutKeys())
            ->get()
            ->each(function (TablePreference $preference) {
                session()->put($preference->key, $preference->value);
                session()->put($this->tableLayoutSavedKey($preference->key), $this->tableLayoutHash($preference->value));
            });

        session()->put($loadedFlag, true);
    }

    public function dehydratePersistsTableLayoutPerUser(): void
    {
        $userId = auth()->id();

        if (! $userId) {
            return;
        }

        foreach ($this->tableLayoutKeys() as $key) {
            if (! session()->has($key)) {
                continue;
            }

            $value = session()->get($key);
            $hash = $this->tableLayoutHash($value);

            if (session()->get($this->tableLayoutSavedKey($key)) === $hash) {
                continue;
            }

            TablePreference::updateOrCreate(['user_id' => $userId, 'key' => $key], ['value' => $value]);
            session()->put($this->tableLayoutSavedKey($key), $hash);
        }
    }

    /** @return array<int, string> Filament's own session keys for this table. */
    protected function tableLayoutKeys(): array
    {
        return [
            $this->getTableColumnsSessionKey(),
            $this->getHasReorderedTableColumnsSessionKey(),
            $this->getTablePerPageSessionKey(),
        ];
    }

    protected function tableLayoutSessionKeyPrefix(): string
    {
        return 'table_layout.'.md5(static::class).'.';
    }

    protected function tableLayoutSavedKey(string $key): string
    {
        return $this->tableLayoutSessionKeyPrefix().'saved.'.md5($key);
    }

    protected function tableLayoutHash(mixed $value): string
    {
        return md5(json_encode($value));
    }
}
