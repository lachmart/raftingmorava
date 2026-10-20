<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTrip extends EditRecord
{
    protected static string $resource = TripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    // After saving: move the trip days to the new dates (if they changed) and recalculate the rental price.
    protected function afterSave(): void
    {
        if ($this->record->wasChanged(['date_from', 'date_to'])) {
            $removedWithPeople = $this->record->syncDays();

            if ($removedWithPeople > 0) {
                Notification::make()->warning()->persistent()
                    ->title('Dny výletu upraveny podle nového termínu')
                    ->body("Zkrácením termínu se smazaly i dny s přiřazenými lidmi (počet: {$removedWithPeople}). Zkontroluj přiřazení v Plánování.")
                    ->send();
            }
        }

        $this->record->refresh()->recalculateRentalPrice();
    }
}
