<?php

namespace App\Filament\Resources\TripPrograms\Pages;

use App\Filament\Concerns\PersistsTableLayoutPerUser;
use App\Filament\Resources\TripPrograms\TripProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTripPrograms extends ListRecords
{
    use PersistsTableLayoutPerUser;

    protected static string $resource = TripProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
