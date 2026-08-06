<?php

namespace App\Filament\Resources\FlavorResource\Pages;

use App\Filament\Resources\FlavorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFlavor extends EditRecord
{
    protected static string $resource = FlavorResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
