<?php

namespace App\Filament\Resources\SiteContentResource\Pages;

use App\Filament\Resources\SiteContentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSiteContent extends CreateRecord
{
    protected static string $resource = SiteContentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
