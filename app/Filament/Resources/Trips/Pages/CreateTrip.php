<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrip extends CreateRecord
{
    protected static string $resource = TripResource::class;

    // After creating (and syncing equipment) recalculate the rental price.
    protected function afterCreate(): void
    {
        $this->record->refresh()->recalculateRentalPrice();
    }
}
