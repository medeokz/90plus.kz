<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleCommentResource\Pages;
use App\Models\ArticleComment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ArticleCommentResource extends Resource
{
    protected static ?string $model = ArticleComment::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Пікірлер';

    protected static ?string $modelLabel = 'пікір';

    protected static ?string $pluralModelLabel = 'Пікірлер';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('article_id')
                    ->label('Мақала')
                    ->relationship('article', 'title_kk')
                    ->searchable()
                    ->required()
                    ->disabled(),
                Forms\Components\TextInput::make('author_name')
                    ->label('Аты')
                    ->required()
                    ->maxLength(80),
                Forms\Components\Textarea::make('body')
                    ->label('Мәтін')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label('Күйі')
                    ->options([
                        'approved' => 'Жарияланған',
                        'rejected' => 'Жасырылған',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('article.title_kk')
                    ->label('Мақала')
                    ->limit(40)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('author_name')
                    ->label('Аты')
                    ->searchable(),
                Tables\Columns\TextColumn::make('body')
                    ->label('Пікір')
                    ->limit(60)
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Күйі')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Күні')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'approved' => 'Жарияланған',
                    'rejected' => 'Жасырылған',
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
            'index' => Pages\ListArticleComments::route('/'),
            'edit' => Pages\EditArticleComment::route('/{record}/edit'),
        ];
    }
}
