<?php

namespace App\Filament\Pages;

use App\Models\Export;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class ListExports extends Page implements HasTable
{
    use InteractsWithTable;
    
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string $view = 'filament.pages.list-exports';
    
    protected static ?string $navigationLabel = 'Downloads';
    
    protected static ?string $title = 'Available Downloads';
    
    protected static ?int $navigationSort = 10;
    
    protected static ?string $slug = 'downloads';

    protected function getHeaderActions(): array
    {
        return [];
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Export::query()->where('user_id', Auth::id())->latest()
            )
            ->columns([
                TextColumn::make('project.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('format')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'json' => 'success',
                        'csv' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Export $record) => route('exports.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (Export $record) => $record->status === 'completed'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
} 