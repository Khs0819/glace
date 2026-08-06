<?php

namespace App\Filament\Resources\HomeWhyGlaceResource\Pages;

use App\Filament\Resources\HomeWhyGlaceResource;
use Filament\Resources\Pages\EditRecord;

class EditHomeWhyGlace extends EditRecord
{
    protected static string $resource = HomeWhyGlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
