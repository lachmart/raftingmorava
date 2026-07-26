<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Jméno')->required()->maxLength(255),
                        TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true),
                        TextInput::make('phone')->label('Telefon')->tel(),
                        Toggle::make('active')->label('Aktivní')->default(true)->inline(false),
                        Toggle::make('is_driver')->label('Může řídit (řidič)')->inline(false)
                            ->helperText('Řidič není role, ale příznak – přiřadit na výlet lze i tak kohokoli.'),

                        // Role (Spatie). One role per user.
                        Select::make('roles')
                            ->label('Role')
                            ->relationship('roles', 'name')
                            ->preload()
                            ->required()
                            ->helperText('Superadmin: vše. Admin: provoz bez číselníků/uživatelů/žebříčku. Instruktor: portál.'),

                        // Password: required only on create; empty on edit = unchanged.
                        TextInput::make('password')
                            ->label('Heslo')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText('Při editaci nechte prázdné, pokud heslo neměníte.'),
                    ]),
            ]);
    }
}
