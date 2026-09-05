<?php

namespace App\Filament\Resources\DeliveryZoneResource\Pages;

use App\Filament\Resources\DeliveryZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryZone extends CreateRecord
{
    protected static string $resource = DeliveryZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
