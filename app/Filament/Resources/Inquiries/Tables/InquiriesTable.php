<?php

namespace App\Filament\Resources\Inquiries\Tables;

use App\Actions\ConvertInquiryToTrip;
use App\Models\Inquiry;
use App\Models\Trip;
use App\Models\TripProgram;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Přijato')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('client_name')->label('Klient')->searchable(),
                TextColumn::make('proposed_program_name')->label('Navrhovaný program')->searchable()->wrap(),
                TextColumn::make('program.name')->label('Spárovaný program')->placeholder('— nespárováno —')->toggleable(),
                TextColumn::make('approximate_dates')->label('Termíny (orient.)')->toggleable(),
                TextColumn::make('people_count')->label('Osob')->numeric()->sortable(),
                TextColumn::make('source')->label('Zdroj')->badge()
                    ->formatStateUsing(fn (string $state) => Inquiry::SOURCES[$state] ?? $state)->toggleable(),
                TextColumn::make('status')->label('Stav')->badge()
                    ->formatStateUsing(fn (string $state) => Inquiry::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'warning',
                        'in_progress' => 'info',
                        'confirmed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('trip.name')->label('Výlet')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Stav')->options(Inquiry::STATUSES),
            ])
            ->recordActions([
                // Convert the inquiry to a trip + automatic confirmation email to the customer.
                Action::make('convert')
                    ->label('Převést na výlet')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn (Inquiry $record) => $record->status !== 'confirmed')
                    ->schema([
                        Select::make('type')->label('Typ výletu')->options(Trip::TYPES)
                            ->default('guided')->required(),
                        Select::make('trip_program_id')->label('Program (potvrdit/upravit)')
                            ->options(fn () => TripProgram::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->default(fn (Inquiry $record) => $record->program_id)
                            ->helperText('Předvyplněno dle shody názvu; admin může upravit.'),
                        DatePicker::make('date_from')->label('Termín od')->required()->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('date_to')->label('Termín do')->required()->native(false)->displayFormat('d.m.Y'),
                        TextInput::make('planned_paying_count')->label('Počet platících')->numeric()->minValue(1)
                            ->default(fn (Inquiry $record) => $record->people_count),
                    ])
                    ->action(function (Inquiry $record, array $data) {
                        $trip = app(ConvertInquiryToTrip::class)->handle($record, $data);

                        Notification::make()
                            ->success()
                            ->title('Poptávka převedena na výlet')
                            ->body('Výlet „' . $trip->name . '“ byl vytvořen a zákazníkovi odeslán potvrzovací e-mail.')
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
