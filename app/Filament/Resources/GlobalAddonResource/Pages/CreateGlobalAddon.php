<?php

namespace App\Filament\Resources\GlobalAddonResource\Pages;

use App\Filament\Resources\GlobalAddonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGlobalAddon extends CreateRecord
{
    protected static string $resource = GlobalAddonResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['product_id'] = null;
        return $data;
    }
}
