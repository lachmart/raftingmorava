<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListInquiries extends ListRecords
{
    protected static string $resource = InquiryResource::class;

    // The table has enough columns to need horizontal scrolling at the default width – give it
    // the full page width instead.
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
