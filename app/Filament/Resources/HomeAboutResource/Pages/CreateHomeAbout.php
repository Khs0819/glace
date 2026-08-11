<?php

namespace App\Filament\Resources\HomeAboutResource\Pages;

use App\Filament\Resources\HomeAboutResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeAbout extends CreateRecord
{
    protected static string $resource = HomeAboutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['paragraphs']) && is_array($data['paragraphs'])) {
            $data['paragraphs'] = array_values(array_map(
                fn ($p) => is_array($p) ? (string) ($p['text'] ?? '') : (string) $p,
                $data['paragraphs'],
            ));
        }
        return $data;
    }
}
