<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Мақалалар';

    protected static ?string $modelLabel = 'мақала';

    protected static ?string $pluralModelLabel = 'Мақалалар';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Қазақша')->schema([
                    Forms\Components\TextInput::make('title_kk')->label('Тақырып')->required()->maxLength(255),
                    Forms\Components\Textarea::make('summary_kk')->label('Қысқаша')->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('content_kk')->label('Мәтін')->rows(12)->columnSpanFull(),
                ]),
                Forms\Components\Section::make('Ағылшынша / түпнұсқа')->schema([
                    Forms\Components\TextInput::make('title_en')->label('Title')->required()->maxLength(255),
                    Forms\Components\Textarea::make('summary_en')->label('Summary')->rows(3)->columnSpanFull(),
                    Forms\Components\Textarea::make('content_en')->label('Content')->rows(8)->columnSpanFull(),
                ])->collapsed(),
                Forms\Components\Section::make('Мета')->schema([
                    Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
                    Forms\Components\Select::make('status')->options([
                        'published' => 'Жарияланған',
                        'draft' => 'Жоба',
                    ])->required()->default('published'),
                    Forms\Components\TextInput::make('source_name')->label('Дереккөз')->required(),
                    Forms\Components\TextInput::make('source_url')->label('URL')->url()->required(),
                    Forms\Components\TextInput::make('image_url')->label('Сурет URL / жол'),
                    Forms\Components\DateTimePicker::make('published_at')->label('Жариялану уақыты'),
                    Forms\Components\DateTimePicker::make('fetched_at')->label('Жүктелген уақыты'),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_kk')->label('Тақырып')->searchable()->limit(50)->sortable(),
                Tables\Columns\TextColumn::make('source_name')->label('Дереккөз')->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'published' => 'success',
                    default => 'gray',
                }),
                Tables\Columns\TextColumn::make('published_at')->label('Күні')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'published' => 'Жарияланған',
                    'draft' => 'Жоба',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
