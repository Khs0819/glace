<?php

namespace App\Filament\Resources\HomeAboutResource\Pages;

use App\Filament\Resources\HomeAboutResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHomeAbout extends EditRecord
{
    protected static string $resource = HomeAboutResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
