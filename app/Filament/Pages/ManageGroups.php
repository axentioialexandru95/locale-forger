<?php

namespace App\Filament\Pages;

use App\Models\Project;
use App\Models\TranslationKey;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ManageGroups extends Page implements HasTable
{
    use InteractsWithTable;
    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationLabel = 'Manage Groups';
    protected static ?string $navigationGroup = 'Translation Management';
    protected static ?int $navigationSort = 5;
    
    protected static string $view = 'filament.pages.manage-groups';
    
    public ?array $data = [];
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('project_id')
                ->label('Project')
                ->options(Project::pluck('name', 'id'))
                ->required()
                ->live(),
            Forms\Components\TextInput::make('name')
                ->label('Group Name')
                ->required()
                ->maxLength(100),
            Forms\Components\Textarea::make('description')
                ->label('Description')
                ->rows(2)
                ->maxLength(255),
        ];
    }
    

    
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('group')
                    ->label('Group Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50),
                Tables\Columns\TextColumn::make('key_count')
                    ->label('Key Count')
                    ->state(function (TranslationKey $record): int {
                        return TranslationKey::where('project_id', $record->project_id)
                            ->where('group', $record->group)
                            ->count();
                    })
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project')
                    ->label('Project')
                    ->options(Project::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }
                        
                        return $query->where('project_id', $data['value']);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->form([
                        Forms\Components\TextInput::make('new_group')
                            ->label('Group Name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, TranslationKey $record): void {
                        // Update all translation keys with this group
                        TranslationKey::where('project_id', $record->project_id)
                            ->where('group', $record->group)
                            ->update([
                                'group' => $data['new_group'],
                                'description' => $data['description'],
                            ]);
                        
                        Notification::make()
                            ->title('Group updated')
                            ->success()
                            ->send();
                    }),
                    
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (TranslationKey $record): void {
                        // Remove the group from all translation keys (set to null)
                        TranslationKey::where('project_id', $record->project_id)
                            ->where('group', $record->group)
                            ->update(['group' => null]);
                        
                        Notification::make()
                            ->title('Group deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->emptyStateActions([]);
    }
    
    protected function getTableQuery(): Builder
    {
        $query = TranslationKey::query()
            ->whereNotNull('group')
            ->select('project_id', 'group', \Illuminate\Support\Facades\DB::raw('MIN(description) as description'), \Illuminate\Support\Facades\DB::raw('MIN(id) as id'))
            ->groupBy('project_id', 'group')
            ->orderBy('project_id')
            ->orderBy('group');
            
        if (isset($this->data['project_id'])) {
            $query->where('project_id', $this->data['project_id']);
        }
        
        return $query;
    }
    
    protected function getFormStatePath(): string
    {
        return 'data';
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Group')
                ->form($this->getFormSchema())
                ->action(function (array $data): void {
                    // Create a placeholder translation key with this group
                    TranslationKey::create([
                        'project_id' => $data['project_id'],
                        'key' => '_group_' . Str::slug($data['name']),
                        'group' => $data['name'],
                        'description' => $data['description'] ?? 'Group placeholder',
                    ]);
                    
                    Notification::make()
                        ->title('Group created')
                        ->success()
                        ->send();
                    
                    $this->form->fill();
                }),
        ];
    }
} 