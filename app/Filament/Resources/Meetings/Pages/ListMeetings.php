<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Concerns\PersistsTableLayoutPerUser;
use App\Filament\Resources\Meetings\MeetingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMeetings extends ListRecords
{
    use PersistsTableLayoutPerUser;

    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
