<?php

namespace App\Filament\Resources\TopUpRequestResource\Pages;

use App\Filament\Resources\TopUpRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTopUpRequest extends ViewRecord
{
    protected static string $resource = TopUpRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
