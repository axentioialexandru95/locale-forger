<?php

namespace App\Filament\Resources\TranslationKeyResource\RelationManagers;

use App\Models\Language;
use App\Models\Translation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TranslationsRelationManager extends RelationManager
{
    protected static string $relationship = 'translations';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('language_id')
                    ->label('Language')
                    ->options(Language::pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->unique(fn ($record) => $record, modifyRuleUsing: fn (Builder $query, $record) => $query->where('translation_key_id', $this->getOwnerRecord()->id))
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('text')
                    ->label('Translation Text')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_machine_translated')
                            ->label('Machine Translated')
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
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('text')
            ->columns([
                Tables\Columns\TextColumn::make('language.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('language.code')
                    ->label('Code')
                    ->sortable(),
                Tables\Columns\TextColumn::make('text')
                    ->limit(50)
                    ->searchable()
                    ->tooltip(fn (Translation $record): string => $record->text)
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_machine_translated')
                    ->label('Machine')
                    ->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'review' => 'warning',
                        'final' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'review' => 'Needs Review',
                        'final' => 'Final',
                    ]),
                Tables\Filters\Filter::make('is_machine_translated')
                    ->label('Machine Translated')
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['translation_key_id'] = $this->getOwnerRecord()->id;
                        // Set current user as the one who updated it
                        $data['updated_by'] = Auth::id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Update the user who last edited this translation
                        $data['updated_by'] = Auth::id();
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('markAsFinal')
                        ->label('Mark as Final')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(function (array $records): void {
                            foreach ($records as $record) {
                                $record->status = 'final';
                                $record->updated_by = Auth::id();
                                $record->save();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('markAsNeedsReview')
                        ->label('Mark for Review')
                        ->icon('heroicon-o-exclamation-circle')
                        ->color('warning')
                        ->action(function (array $records): void {
                            foreach ($records as $record) {
                                $record->status = 'review';
                                $record->updated_by = Auth::id();
                                $record->save();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
