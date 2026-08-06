<?php

namespace App\Filament\Resources\HomeWhyGlaceResource\Pages;

use App\Filament\Resources\HomeWhyGlaceResource;
use App\Models\HomeWhyGlace;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHomeWhyGlaces extends ListRecords
{
    protected static string $resource = HomeWhyGlaceResource::class;

    public function mount(): void
    {
        $record = HomeWhyGlace::first();
        if ($record) {
            $this->redirect(HomeWhyGlaceResource::getUrl('edit', ['record' => $record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('إنشاء القسم')];
    }
}
