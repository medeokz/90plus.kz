<?php

namespace App\Filament\Resources\FixtureResource\Pages;

use App\Filament\Resources\FixtureResource;
use App\Services\FixtureStatsService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFixture extends EditRecord
{
    protected static string $resource = FixtureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('syncApi')
                ->label('Синхронизировать с API')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn () => filled($this->record->api_fixture_id))
                ->action(function () {
                    $updated = app(FixtureStatsService::class)->syncFromApi(
                        $this->record->api_fixture_id,
                        $this->record->external_id,
                    );

                    if ($updated) {
                        $this->refreshFormData(['home_score', 'away_score', 'status', 'minute', 'competition']);
                    }

                    Notification::make()
                        ->title($updated ? 'Данные обновлены' : 'Не удалось получить данные API')
                        ->success($updated !== null)
                        ->danger($updated === null)
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
