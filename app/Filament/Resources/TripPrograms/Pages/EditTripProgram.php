<?php

namespace App\Filament\Resources\TripPrograms\Pages;

use App\Filament\Resources\TripPrograms\TripProgramResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTripProgram extends EditRecord
{
    protected static string $resource = TripProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
