<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TripResource::class;

    // After creating (and syncing equipment): generate the trip days and recalculate the rental price.
    protected function afterCreate(): void
    {
        $this->record->generateDays();
        $this->record->refresh()->recalculateRentalPrice();
    }
}
