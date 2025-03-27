<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Jobs\ExportTranslationsJob;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Projects';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Project Details')
                    ->schema([
                        Forms\Components\Select::make('organization_id')
                            ->relationship('organization', 'name')
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', \Illuminate\Support\Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ])->columns(2),
                Forms\Components\Section::make('Language Settings')
                    ->schema([
                        Forms\Components\Select::make('primary_language_id')
                            ->relationship('primaryLanguage', 'name')
                            ->required()
                            ->helperText('The primary language of this project (source language)'),
                        Forms\Components\Select::make('languages')
                            ->relationship('languages', 'name')
                            ->multiple()
                            ->preload()
                            ->required()
                            ->helperText('Select the languages available for this project'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('primaryLanguage.name')
                    ->label('Primary Language'),
                Tables\Columns\TextColumn::make('languages_count')
                    ->counts('languages')
                    ->label('Languages'),
                Tables\Columns\TextColumn::make('translationKeys_count')
                    ->counts('translationKeys')
                    ->label('Translation Keys'),
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
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Forms\Components\Select::make('format')
                            ->label('Export Format')
                            ->options([
                                'json' => 'JSON',
                                'csv' => 'CSV',
                            ])
                            ->required(),
                        Forms\Components\Select::make('languages')
                            ->label('Languages to Export')
                            ->options(function (Project $record) {
                                return $record->languages->pluck('name', 'id');
                            })
                            ->multiple()
                            ->helperText('Leave empty to export all languages'),
                    ])
                    ->action(function (Project $record, array $data) {
                        // Dispatch export job
                        ExportTranslationsJob::dispatch(
                            $record->id,
                            $data['format'],
                            $data['languages'] ?? [],
                            Auth::id()
                        );
                        
                        Notification::make()
                            ->title('Export started')
                            ->body('Your export has been queued and will be available shortly in the Downloads section.')
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('view_downloads')
                                    ->label('View Downloads')
                                    ->url(route('filament.admin.pages.downloads'))
                                    ->icon('heroicon-o-arrow-down-tray')
                            ])
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // We now handle translations through sub-navigation
            // RelationManagers\TranslationKeysRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            'translation-keys' => Pages\TranslationKeys\ListTranslationKeys::route('/{record}/translation-keys'),
            'translation-keys.create' => Pages\TranslationKeys\CreateTranslationKey::route('/{record}/translation-keys/create'),
            'translation-keys.edit' => Pages\TranslationKeys\EditTranslationKey::route('/{record}/translation-keys/{translationKey}/edit'),
        ];
    }

    /**
     * Get the resource's record sub-navigation
     */
    public static function getRecordSubNavigation(\Filament\Resources\Pages\Page $page): array
    {
        // Get the record ID from the route parameters
        $recordId = request()->route('record');
        
        if (!$recordId) {
            return [];
        }
        
        // Manually create navigation items
        return [
            'edit' => \Filament\Navigation\NavigationItem::make()
                ->label('Details')
                ->icon('heroicon-o-pencil')
                ->isActiveWhen(fn () => $page instanceof Pages\EditProject)
                ->url(static::getUrl('edit', ['record' => $recordId])),
            'translation-keys' => \Filament\Navigation\NavigationItem::make()
                ->label('Translation Keys')
                ->icon('heroicon-o-language')
                ->isActiveWhen(fn () => $page instanceof Pages\TranslationKeys\ListTranslationKeys || 
                                        $page instanceof Pages\TranslationKeys\CreateTranslationKey || 
                                        $page instanceof Pages\TranslationKeys\EditTranslationKey)
                ->url(static::getUrl('translation-keys', ['record' => $recordId])),
        ];
    }
    
    /**
     * Register the sidebar navigation items for the resource.
     */
    public static function getNavigationItems(): array
    {
        return parent::getNavigationItems();
    }
}
