<?php

namespace App\Filament\Resources\HomeAboutResource\Pages;

use App\Filament\Resources\HomeAboutResource;
use App\Models\HomeAbout;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHomeAbouts extends ListRecords
{
    protected static string $resource = HomeAboutResource::class;

    public function mount(): void
    {
        // If a record exists, redirect straight to edit it
        $record = HomeAbout::first();
        if ($record) {
            $this->redirect(HomeAboutResource::getUrl('edit', ['record' => $record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('إنشاء القسم')];
    }
}
