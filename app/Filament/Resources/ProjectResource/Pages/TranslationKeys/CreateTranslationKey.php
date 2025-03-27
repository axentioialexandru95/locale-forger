<?php

namespace App\Filament\Resources\ProjectResource\Pages\TranslationKeys;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use App\Models\TranslationKey;
use App\Services\InterpolationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Filament\Pages\Concerns\HasSubNavigation;

class CreateTranslationKey extends CreateRecord
{
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
    
    protected function getProject(): Project
    {
        return Project::findOrFail($this->getParentId());
    }
    
    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }
    protected static string $resource = ProjectResource::class;
    
    
    
    protected function getRedirectUrl(): string
    {
        return ProjectResource::getUrl('translation-keys', ['record' => $this->getParentId()]);
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['project_id'] = $this->getProject()->id;
        return $data;
    }
        

    
    public function getTitle(): string
    {
        return __('Create Translation Key');
    }
    
    public function getBreadcrumb(): string
    {
        return __('Create Translation Key');
    }
    
    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('create')
                ->label(__('Create'))
                ->submit('create_form')
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
                        Forms\Components\Hidden::make('project_id')
                            ->default(fn () => $this->getProject()->id),
                        Forms\Components\TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'translation_keys', column: 'key', modifyRuleUsing: function (Builder $query) {
                                return $query->where('project_id', $this->getProject()->id);
                            })
                            ->helperText('The identifier for this translation (e.g., "homepage.welcome")'),
                        Forms\Components\Select::make('group')
                            ->options(function () {
                                // Combine existing project groups and predefined groups
                                $existingGroups = TranslationKey::where('project_id', $this->getProject()->id)
                                    ->whereNotNull('group')
                                    ->pluck('group', 'group')
                                    ->unique()
                                    ->toArray();
                                
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
                            ->helperText('Group for organizing related translations'),
                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500),
                    ])->columns(2),
                
                Forms\Components\Section::make('Base Translation')
                    ->schema([
                        Forms\Components\Grid::make()
                            ->schema([
                                Forms\Components\TextInput::make('primary_language')
                                    ->default(fn () => $this->getProject()->primaryLanguage->name)
                                    ->disabled()
                                    ->dehydrated(false),
                                Forms\Components\Textarea::make('primary_translation')
                                    ->required()
                                    ->rows(3)
                                    ->hintIcon('heroicon-o-code-bracket', tooltip: 'Supports interpolation with {variable_name} syntax')
                                    ->helperText('The base translation text in the primary language')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }
    
    protected function afterCreate(): void
    {
        $data = $this->data;
        $translationKey = $this->record;
        
        $project = $this->getProject();
        
        // Don't proceed if no project found or no primary language set
        if (!$project || !$project->primary_language_id) {
            return;
        }
        
        // Create the primary translation
        $translationKey->translations()->create([
            'language_id' => $project->primary_language_id,
            'text' => $data['primary_translation'] ?? '',
            'status' => 'final',
            'is_machine_translated' => false,
            'updated_by' => Auth::id(),
        ]);
    }
} 