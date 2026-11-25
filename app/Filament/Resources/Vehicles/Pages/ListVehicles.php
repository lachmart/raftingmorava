<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Concerns\PersistsTableLayoutPerUser;
use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    use PersistsTableLayoutPerUser;

    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
