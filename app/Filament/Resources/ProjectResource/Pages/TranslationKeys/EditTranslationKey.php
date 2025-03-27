<?php

namespace App\Filament\Resources\ProjectResource\Pages\TranslationKeys;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use App\Models\TranslationKey;
use App\Models\Translation;
use App\Services\InterpolationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EditTranslationKey extends EditRecord
{
    protected function authorizeAccess(): void
    {
        static::authorizeResourceAccess();
    }
    
    protected function getParentId(): int|string
    {
        $record = request()->route('record');
        
        if ($record === null) {
            // Get the project ID from the translation key if the route parameter is missing
            $translationKeyId = request()->route('translationKey');
            if ($translationKeyId) {
                try {
                    $translationKey = TranslationKey::find($translationKeyId);
                    if ($translationKey) {
                        return $translationKey->project_id;
                    }
                } catch (\Exception $e) {
                    // Continue to fallback
                }
            }
            
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
    
    protected function getTranslationKeyId(): int|string
    {
        $translationKeyId = request()->route('translationKey');
        
        if ($translationKeyId === null) {
            // Return a fallback ID that won't match any real translation keys
            return '0';
        }
        
        return $translationKeyId;
    }
    
    protected function getTranslationKey(): ?TranslationKey
    {
        try {
            $translationKey = TranslationKey::find($this->getTranslationKeyId());
            
            if (!$translationKey) {
                return null;
            }
            
        
        // Verify the translation key belongs to this project
        $project = $this->getProject();
        if ($project && $translationKey->project_id !== $project->id) {
            abort(404);
        }
        
            return $translationKey;
        } catch (\Exception $e) {
            return null;
        }
    }
    protected static string $resource = ProjectResource::class;
    
    protected function getRedirectUrl(): string
    {
        return ProjectResource::getUrl('translation-keys', ['record' => $this->getParentId()]);
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        try {
            // Prepare translations data to be loaded into the repeater
            $translationKey = TranslationKey::with('translations.language')->find($this->getTranslationKeyId());
            $project = $this->getProject();
            
            // If either project or translationKey is null, return data as is
            if (!$project || !$translationKey) {
                return $data;
            }
            
            // Format translations for all project languages
            $formattedTranslations = [];
            if (isset($project->languages) && $project->languages->count() > 0) {
                foreach ($project->languages as $language) {
                    $translation = $translationKey->translations->firstWhere('language_id', $language->id);
                    
                    $formattedTranslations[$language->id] = [
                        'language_id' => $language->id,
                        'language_name' => $language->name . ' (' . $language->code . ')',
                        'text' => $translation->text ?? '',
                        'status' => $translation->status ?? 'draft',
                        'is_machine_translated' => $translation->is_machine_translated ?? false,
                        'notes' => $translation->notes ?? '',
                    ];
                }
            }
            $data['translations'] = $formattedTranslations;
            return $data;
        } catch (\Exception $e) {
            // If there's an error, return the original data without modifications
            return $data;
        }
        // This code will not be reached as we're already returning $data in the try block
        return $data;
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }
        

    
    public function getTitle(): string
    {
        $translationKey = $this->getTranslationKey();
        $key = $translationKey ? $translationKey->key ?? '' : '';
        return __('Edit Translation Key') . ($key ? ': ' . $key : '');
    }
    
    public function getBreadcrumb(): string
    {
        return __('Edit');
    }
    
    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label(__('Save'))
                ->submit('save_form')
                ->keyBindings(['mod+s']),
            
            Actions\Action::make('cancel')
                ->label(__('Cancel'))
                ->url(ProjectResource::getUrl('translation-keys', ['record' => $this->getParentId()]))
                ->color('gray'),
        ];
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Translation Key Details')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'translation_keys', column: 'key', ignorable: fn () => $this->getTranslationKey())
                            ->helperText('The identifier for this translation (e.g., "homepage.welcome")')
                            ->columnSpan(1),
                        Forms\Components\Select::make('group')
                            ->options(function () {
                                // Combine existing project groups and predefined groups
                                $existingGroups = [];
                                $project = $this->getProject();
                                
                                if ($project) {
                                    $existingGroups = TranslationKey::where('project_id', $project->id)
                                        ->whereNotNull('group')
                                        ->pluck('group', 'group')
                                        ->unique()
                                        ->toArray();
                                }
                                
                                // Check if Groups model exists and the table exists
                                try {
                                    if (class_exists('\App\Models\Group') && \Illuminate\Support\Facades\Schema::hasTable('groups')) {
                                        $predefinedGroups = \App\Models\Group::pluck('name', 'name')->toArray();
                                        $existingGroups = array_merge($existingGroups, $predefinedGroups);
                                    }
                                } catch (\Exception $e) {
                                    // Silently handle any database errors
                                }
                                
                                return collect($existingGroups)->unique()->toArray();
                            })
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Group Name'),
                                Forms\Components\Textarea::make('description')
                                    ->rows(2)
                                    ->maxLength(255),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Create New Group')
                                    ->modalDescription('Define a new group for organizing translation keys')
                                    ->modalIcon('heroicon-o-folder-plus')
                                    ->closeModalByClickingAway(false);
                            })
                            ->createOptionUsing(function (array $data) {
                                // Create the group if we have the Group model
                                if (class_exists('\App\Models\Group')) {
                                    \App\Models\Group::create([
                                        'name' => $data['name'],
                                        'description' => $data['description'] ?? null,
                                    ]);
                                }
                                
                                return $data['name'];
                            })
                            ->placeholder('None')
                            ->helperText('Group for organizing related translations')
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpan(2),
                    ])->columns(2)->compact(),
                
                Forms\Components\Section::make('Translations')
                    ->schema([
                        Forms\Components\Repeater::make('translations')
                            ->schema([
                                Forms\Components\Grid::make()
                                    ->schema([
                                        Forms\Components\Hidden::make('language_id'),
                                        Forms\Components\TextInput::make('language_name')
                                            ->disabled()
                                            ->columnSpan(1),
                                        Forms\Components\Grid::make()
                                            ->schema([
                                                Forms\Components\Toggle::make('is_machine_translated')
                                                    ->label('MT')
                                                    ->helperText('Machine translated')
                                                    ->columnSpan(1),
                                                Forms\Components\Select::make('status')
                                                    ->options([
                                                        'draft' => 'Draft',
                                                        'review' => 'Review',
                                                        'final' => 'Final',
                                                    ])
                                                    ->default('draft')
                                                    ->required()
                                                    ->columnSpan(1),
                                            ])
                                            ->columnSpan(1)
                                            ->columns(2),
                                    ])
                                    ->columns(2)
                                    ->columnSpan(2),
                                Forms\Components\Textarea::make('text')
                                    ->label('Translation')
                                    ->required()
                                    ->rows(3)
                                    ->hintIcon('heroicon-o-code-bracket', tooltip: 'Supports interpolation with {variable_name} syntax')
                                    ->afterStateUpdated(function (Forms\Components\Textarea $component, $state, $record) {
                                        if (empty($state)) return;
                                        
                                        // Get the primary translation to compare variables
                                        $project = $this->getProject();
                                        $primaryLanguageId = $project ? $project->primary_language_id : null;
                                        
                                        if (!$primaryLanguageId) return; // Exit if no primary language is set
                                        
                                        $primaryTranslation = $this->data['translations'][$primaryLanguageId]['text'] ?? null;
                                        
                                        if (!$primaryTranslation) return;
                                        
                                        // Check for variable mismatches
                                        $interpolationService = app(InterpolationService::class);
                                        $hasMismatch = !$interpolationService->validateVariables(
                                            $primaryTranslation, 
                                            $state
                                        );
                                        
                                        if ($hasMismatch) {
                                            $sourceVars = $interpolationService->extractVariables($primaryTranslation);
                                            $targetVars = $interpolationService->extractVariables($state);
                                            $missingVars = array_diff($sourceVars, $targetVars);
                                            
                                            if (!empty($missingVars)) {
                                                $component
                                                    ->helperText('⚠️ Missing variables: ' . implode(', ', $missingVars))
                                                    ->hintColor('danger');
                                            }
                                        } else {
                                            $component->helperText(null);
                                        }
                                    })
                                    ->lazy() // This makes it check after user finishes typing
                                    ->columnSpan(2)
                                    ->placeholder(function ($get) {
                                        $languageName = $get('language_name');
                                        $project = $this->getProject();
                                        $primaryLanguageId = $project ? $project->primary_language_id : null;
                                        
                                        if (!$primaryLanguageId) {
                                            return 'Enter text for ' . $languageName;
                                        }
                                        
                                        $primaryText = isset($this->data['translations'][$primaryLanguageId]['text']) 
                                            ? $this->data['translations'][$primaryLanguageId]['text'] 
                                            : '';
                                        
                                        if ($get('language_id') == $primaryLanguageId) {
                                            return 'Enter the source text here...';
                                        }
                                        
                                        return "Translate: \"" . Str::limit($primaryText, 50) . "\"";
                                    }),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['language_name'] ?? null)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->collapsible()
                            ->collapsed(function ($get) {
                                // Keep primary language expanded by default
                                $project = $this->getProject();
                                $primaryLanguageId = $project ? $project->primary_language_id : null;
                                
                                if (!$primaryLanguageId) {
                                    return false; // If no primary language, don't collapse
                                }
                                
                                return $get('language_id') != $primaryLanguageId;
                            })
                            ->grid(1)
                            ->columns(2)
                    ])->collapsible()->compact(),
            ])
            ->statePath('data');
    }
    
    protected function afterSave(): void
    {
        // Add any custom logic after saving the record
    }
} 