<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;


class EditProject extends EditRecord
{

    protected static string $resource = ProjectResource::class;
    
    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
