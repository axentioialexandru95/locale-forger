<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TranslationKeyResource\Pages;
use App\Filament\Resources\TranslationKeyResource\RelationManagers;
use App\Models\TranslationKey;
use App\Services\InterpolationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

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
                Forms\Components\Section::make('Translation Key Details')
                    ->schema([
                        Forms\Components\Select::make('project_id')
                            ->relationship('project', 'name')
                            ->required()
                            ->searchable()
                            ->disabled(fn ($livewire) => $livewire instanceof Pages\EditTranslationKey),
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(fn ($record) => $record, modifyRuleUsing: fn (Builder $query, $record) => $query->where('project_id', $record?->project_id))
                            ->helperText('The identifier for this translation (e.g., "homepage.welcome")'),
                        Forms\Components\Select::make('group')
                            ->options(function ($get) {
                                if (!$get('project_id')) return [];
                                return TranslationKey::where('project_id', $get('project_id'))
                                    ->whereNotNull('group')
                                    ->pluck('group', 'group')
                                    ->unique()
                                    ->toArray();
                            })
                            ->searchable()
                            ->placeholder('None')
                            ->helperText('Group for organizing related translations')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('createGroup')
                                    ->icon('heroicon-m-plus-circle')
                                    ->form([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Group Name')
                                            ->required(),
                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')
                                            ->rows(2),
                                    ])
                                    ->action(function (array $data, Forms\Set $set, Forms\Get $get): void {
                                        $project_id = $get('project_id');
                                        if (!$project_id) {
                                            Notification::make()
                                                ->title('Project required')
                                                ->body('Please select a project first')
                                                ->danger()
                                                ->send();
                                            return;
                                        }
                                        
                                        // Set the group in the form
                                        $set('group', $data['name']);
                                        
                                        // Optional: Create a dummy translation key with this group
                                        // to ensure it shows up in the dropdown for future selections
                                        $existingGroup = TranslationKey::where('project_id', $project_id)
                                            ->where('group', $data['name'])
                                            ->first();
                                            
                                        if (!$existingGroup) {
                                            TranslationKey::create([
                                                'project_id' => $project_id,
                                                'key' => '_group_' . \Illuminate\Support\Str::slug($data['name']),
                                                'group' => $data['name'],
                                                'description' => $data['description'] ?? 'Group placeholder',
                                            ]);
                                        }
                                        
                                        Notification::make()
                                            ->title('Group created')
                                            ->body('Group "' . $data['name'] . '" has been created')
                                            ->success()
                                            ->send();
                                    })
                            ),
                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])->columns(3),
                
                Forms\Components\Section::make('Translations')
                    ->schema([
                        Forms\Components\Repeater::make('translations')
                            ->relationship()
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('language_id')
                                            ->relationship('language', 'name')
                                            ->required()
                                            ->searchable()
                                            ->columnSpan(1),
                                            
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Toggle::make('is_machine_translated')
                                                    ->default(false)
                                                    ->helperText('Machine translated'),
                                                Forms\Components\Select::make('status')
                                                    ->options([
                                                        'draft' => 'Draft',
                                                        'review' => 'Needs Review',
                                                        'final' => 'Final',
                                                    ])
                                                    ->default('draft')
                                                    ->required(),
                                            ])
                                            ->columnSpan(1),
                                    ]),
                                
                                Forms\Components\Textarea::make('text')
                                    ->label('Translation')
                                    ->required()
                                    ->rows(3)
                                    ->hintIcon('heroicon-o-code-bracket', 
                                        tooltip: 'Supports interpolation with {variable_name} syntax')
                                    ->afterStateUpdated(function (Forms\Components\TextArea $component, $state, $record) {
                                        if (!$record || !$state) return;
                                        
                                        // Get the primary translation to compare variables
                                        $primaryTranslation = null;
                                        if ($record->project && $record->project->primaryLanguage) {
                                            foreach ($record->translations as $translation) {
                                                if ($translation->language_id === $record->project->primary_language_id) {
                                                    $primaryTranslation = $translation;
                                                    break;
                                                }
                                            }
                                        }
                                        
                                        if (!$primaryTranslation) return;
                                        
                                        // Check for variable mismatches
                                        $interpolationService = app(InterpolationService::class);
                                        $hasMismatch = !$interpolationService->validateVariables(
                                            $primaryTranslation->text, 
                                            $state
                                        );
                                        
                                        if ($hasMismatch) {
                                            $sourceVars = $interpolationService->extractVariables($primaryTranslation->text);
                                            $component->helperText('Warning: Missing variables: ' . implode(', ', $sourceVars));
                                        }
                                    })
                                    ->columnSpanFull()
                                    ->live(),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                $data['updated_by'] = Auth::id();
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                                $data['updated_by'] = Auth::id();
                                return $data;
                            })
                            ->columns(1)
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
                Tables\Columns\TextColumn::make('group')
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
                Tables\Filters\SelectFilter::make('group')
                    ->options(function () {
                        return TranslationKey::whereNotNull('group')
                            ->pluck('group', 'group')
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
            // We're now handling translations in the form directly
            // RelationManagers\TranslationsRelationManager::class,
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
