<?php

namespace App\Filament\Resources\HomeAboutResource\Pages;

use App\Filament\Resources\HomeAboutResource;
use Filament\Resources\Pages\EditRecord;

class EditHomeAbout extends EditRecord
{
    protected static string $resource = HomeAboutResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['paragraphs']) && is_array($data['paragraphs'])) {
            $data['paragraphs'] = array_map(
                fn ($p) => is_string($p) ? ['text' => $p] : $p,
                $data['paragraphs'],
            );
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
