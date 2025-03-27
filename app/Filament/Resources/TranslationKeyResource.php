<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TranslationKeyResource\Pages;
use App\Filament\Resources\TranslationKeyResource\RelationManagers;
use App\Models\TranslationKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TranslationKeyResource extends Resource
{
    protected static ?string $model = TranslationKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'Translation Keys';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Key Details')
                    ->schema([
                        Forms\Components\Select::make('project_id')
                            ->relationship('project', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(fn ($record) => $record, modifyRuleUsing: fn (Builder $query, $record) => $query->where('project_id', $record?->project_id))
                            ->helperText('The identifier for this translation (e.g., "homepage.welcome")'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Optional context for translators'),
                    ])->columns(2),
                Forms\Components\Section::make('Translations')
                    ->schema([
                        Forms\Components\Repeater::make('translations')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('language_id')
                                    ->relationship('language', 'name')
                                    ->required()
                                    ->searchable(),
                                Forms\Components\Textarea::make('text')
                                    ->required()
                                    ->rows(2),
                                Forms\Components\Toggle::make('is_machine_translated')
                                    ->default(false)
                                    ->helperText('Mark if this translation was generated automatically'),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'draft' => 'Draft',
                                        'review' => 'Needs Review',
                                        'final' => 'Final',
                                    ])
                                    ->default('draft')
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(function (TranslationKey $record): string {
                        return $record->key;
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->toggleable()
                    ->limit(50)
                    ->tooltip(function (TranslationKey $record): ?string {
                        return $record->description;
                    }),
                Tables\Columns\TextColumn::make('translations_count')
                    ->counts('translations')
                    ->label('Translations')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('missing_translations')
                    ->label('Missing Translations')
                    ->form([
                        Forms\Components\Select::make('language_id')
                            ->label('Language')
                            ->relationship('project.languages', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['language_id']) {
                            return $query;
                        }
                        
                        return $query->whereDoesntHave('translations', function ($query) use ($data) {
                            $query->where('language_id', $data['language_id']);
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // We'll add machine translation bulk action later
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TranslationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTranslationKeys::route('/'),
            'create' => Pages\CreateTranslationKey::route('/create'),
            'edit' => Pages\EditTranslationKey::route('/{record}/edit'),
        ];
    }
}
