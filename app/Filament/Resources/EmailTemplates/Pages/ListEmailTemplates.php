<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Concerns\PersistsTableLayoutPerUser;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    use PersistsTableLayoutPerUser;

    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
