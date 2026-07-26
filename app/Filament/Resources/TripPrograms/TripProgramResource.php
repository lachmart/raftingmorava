<?php

namespace App\Filament\Resources\TripPrograms;

use App\Filament\Resources\TripPrograms\Pages\CreateTripProgram;
use App\Filament\Resources\TripPrograms\Pages\EditTripProgram;
use App\Filament\Resources\TripPrograms\Pages\ListTripPrograms;
use App\Filament\Resources\TripPrograms\Schemas\TripProgramForm;
use App\Filament\Resources\TripPrograms\Tables\TripProgramsTable;
use App\Models\TripProgram;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TripProgramResource extends Resource
{
    protected static ?string $model = TripProgram::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'program (ceník)';

    protected static ?string $pluralModelLabel = 'programy (ceník)';

    protected static string|UnitEnum|null $navigationGroup = 'Číselníky';

    public static function form(Schema $schema): Schema
    {
        return TripProgramForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TripProgramsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTripPrograms::route('/'),
            'create' => CreateTripProgram::route('/create'),
            'edit' => EditTripProgram::route('/{record}/edit'),
        ];
    }
}
