<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FixtureResource\Pages;
use App\Models\Fixture;
use App\Services\FixtureStatsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FixtureResource extends Resource
{
    protected static ?string $model = Fixture::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Матчтар';

    protected static ?string $modelLabel = 'матч';

    protected static ?string $pluralModelLabel = 'Матчтар';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('API-Football')->schema([
                    Forms\Components\TextInput::make('api_fixture_id')
                        ->label('API Fixture ID')
                        ->numeric()
                        ->helperText('ID с api-football.com — при открытии страницы матча данные обновляются автоматически'),
                    Forms\Components\TextInput::make('external_id')
                        ->label('URL ID (/games/{id})')
                        ->required()
                        ->numeric(),
                ])->columns(2),
                Forms\Components\Section::make('Матч')->schema([
                    Forms\Components\TextInput::make('competition')->label('Турнир'),
                    Forms\Components\TextInput::make('home_team')->label('Хозяева')->required(),
                    Forms\Components\TextInput::make('away_team')->label('Гости')->required(),
                    Forms\Components\TextInput::make('home_score')->label('Счёт х')->numeric()->default(0),
                    Forms\Components\TextInput::make('away_score')->label('Счёт г')->numeric()->default(0),
                    Forms\Components\Select::make('status')->options([
                        'NS' => 'Не начался',
                        'LIVE' => 'Live',
                        '1H' => '1-й тайм',
                        'HT' => 'Перерыв',
                        '2H' => '2-й тайм',
                        'FT' => 'Завершён',
                    ])->default('NS'),
                    Forms\Components\TextInput::make('minute')->label('Минута')->numeric(),
                    Forms\Components\DateTimePicker::make('kickoff_at')->label('Начало'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('external_id')->label('URL ID')->sortable(),
                Tables\Columns\TextColumn::make('api_fixture_id')->label('API ID')->sortable(),
                Tables\Columns\TextColumn::make('competition')->label('Турнир')->limit(30),
                Tables\Columns\TextColumn::make('home_team')->label('Хозяева'),
                Tables\Columns\TextColumn::make('away_team')->label('Гости'),
                Tables\Columns\TextColumn::make('home_score')->label('С'),
                Tables\Columns\TextColumn::make('away_score')->label('С'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('kickoff_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('kickoff_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label('Sync API')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (Fixture $record) => filled($record->api_fixture_id))
                    ->action(function (Fixture $record) {
                        $updated = app(FixtureStatsService::class)->syncFromApi(
                            $record->api_fixture_id,
                            $record->external_id,
                        );

                        Notification::make()
                            ->title($updated ? 'Синхронизировано' : 'Ошибка API')
                            ->success($updated !== null)
                            ->danger($updated === null)
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFixtures::route('/'),
            'create' => Pages\CreateFixture::route('/create'),
            'edit' => Pages\EditFixture::route('/{record}/edit'),
        ];
    }
}
