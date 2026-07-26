<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use App\Models\EmailTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        // The key is a system identifier – not editable.
                        TextInput::make('key')->label('Klíč (systémový)')->disabled()->dehydrated(false),
                        TextInput::make('name')->label('Název šablony')->required(),
                        TextInput::make('subject')->label('Předmet e-mailu')->required()->columnSpanFull(),
                        Textarea::make('body')->label('Tělo e-mailu')->required()->rows(12)->columnSpanFull()
                            ->helperText('Používejte makra ve tvaru {{makro}} – před odesláním se nahradí skutečnými hodnotami.'),
                        Textarea::make('description')->label('Interní popis (kdy se odesílá)')->rows(2)->columnSpanFull(),
                    ]),

                // Help: readonly list of macros available for the given template type.
                Section::make('Dostupná makra')
                    ->description('Vložte do předmětu nebo těla. Neznámé makro se nahradí prázdným řetězcem.')
                    ->schema([
                        Placeholder::make('macros_help')
                            ->hiddenLabel()
                            ->content(function (Get $get): HtmlString {
                                $macros = EmailTemplate::availableMacros()[$get('key')] ?? [];
                                if ($macros === []) {
                                    return new HtmlString('<span class="text-gray-500">Pro tuto šablonu nejsou definována žádná makra.</span>');
                                }

                                $rows = collect($macros)->map(
                                    fn ($description, $key) => '<li><code class="px-1 rounded bg-gray-100 dark:bg-gray-700">{{' . e($key) . '}}</code> — ' . e($description) . '</li>'
                                )->implode('');

                                return new HtmlString('<ul class="list-disc ps-5 space-y-1 text-sm">' . $rows . '</ul>');
                            }),
                    ]),
            ]);
    }
}
