<?php

namespace App\Filament\Resources\ProjectResource\Pages\TranslationKeys;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use App\Models\TranslationKey;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListTranslationKeys extends ListRecords
{
    protected static string $resource = ProjectResource::class;
    
    public function getTitle(): string
    {
        return __('Translation Keys');
    }
    
    public function getBreadcrumb(): string
    {
        return __('Translation Keys');
    }
    
    public static function getNavigationLabel(): string
    {
        return __('Translation Keys');
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Translation Key')
                ->url(static::getResource()::getUrl('translation-keys.create', ['record' => $this->getParentId()]))
                ->icon('heroicon-o-plus'),
        ];
    }
    
    protected function authorizeAccess(): void
    {
        static::authorizeResourceAccess();
    }
    
    protected function getParentId(): int|string
    {
        $record = request()->route('record');
        
        if ($record === null) {
            // Fallback to a default value instead of 404
            // Using '0' as a fallback ID that won't match any real projects
            return '0';
        }
        
        return $record;
    }
    
    protected function getProject(): ?Project
    {
        try {
            return Project::find($this->getParentId());
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }
    
    protected function getTableQuery(): Builder
    {
        $project = $this->getProject();
        $query = TranslationKey::query();
        
        if ($project) {
            // Add the group_name alias for the group column to fix sorting issues
            return $query->where('project_id', $project->id)
                ->select('translation_keys.*')
                ->selectRaw('"group" as group_name');
        }
        
        return $query->whereRaw('1=0'); // Return empty result set if no project
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->defaultGroup('group') // Use the actual column name, not the alias
            ->defaultSort('key', 'asc')
            ->contentGrid([
                'md' => 2,
                'lg' => 3,
            ])
            ->paginated([10, 25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50)
                    ->tooltip(function (TranslationKey $record): string {
                        return $record->key;
                    }),
                Tables\Columns\TextColumn::make('group')
                    ->searchable()
                    ->sortable()
                    ->badge(),
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
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->options(function () {
                        $project = $this->getProject();
                        if (!$project) {
                            return [];
                        }
                        
                        return TranslationKey::where('project_id', $project->id)
                            ->whereNotNull('group')
                            ->selectRaw('"group" as group_name') // Use proper quoting for reserved keyword
                            ->pluck('group_name', 'group_name')
                            ->unique()
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('missing_translations')
                    ->label('Missing Translations')
                    ->form([
                        Forms\Components\Select::make('language_id')
                            ->label('Language')
                            ->options(function () {
                                $project = $this->getProject();
                                if (!$project || !isset($project->languages)) {
                                    return [];
                                }
                                return $project->languages->pluck('name', 'id');
                            })
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
                Tables\Actions\Action::make('edit')
                    ->url(fn (TranslationKey $record): string => 
                        ProjectResource::getUrl('translation-keys.edit', ['record' => $this->getParentId(), 'translationKey' => $record]))
                    ->icon('heroicon-o-pencil'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
} 