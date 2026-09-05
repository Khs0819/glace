<?php

namespace App\Filament\Pages;

use App\Services\Reporting\FinancialReport;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;

/**
 * The accountant's screen.
 *
 * Built around the one question an audit actually asks: does the money the
 * system says came in match the money that is there? Everything else on the
 * page exists to explain a discrepancy in that figure — which method, which
 * channel, which shift, and what was given away.
 */
class FinancialReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'التقارير المالية';
    protected static ?string $navigationGroup = 'التقارير';
    protected static ?string $title = 'التقارير المالية';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.financial-reports';
    protected static ?string $slug = 'financial-reports';

    public ?string $preset = 'today';
    public ?string $from = null;
    public ?string $to = null;

    public function mount(): void
    {
        $this->applyPreset('today');
        $this->form->fill(['preset' => $this->preset, 'from' => $this->from, 'to' => $this->to]);
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('preset')
                        ->label('الفترة')
                        ->options([
                            'today'     => 'اليوم',
                            'yesterday' => 'أمس',
                            'week'      => 'آخر 7 أيام',
                            'month'     => 'هذا الشهر',
                            'last_month' => 'الشهر الماضي',
                            'custom'    => 'فترة مخصصة',
                        ])
                        ->live()
                        ->afterStateUpdated(fn (?string $state) => $this->applyPreset($state ?? 'today'))
                        ->native(false),

                    Forms\Components\DatePicker::make('from')
                        ->label('من')
                        ->live()
                        // Only meaningful once the preset stops driving them.
                        ->disabled(fn (Forms\Get $get) => $get('preset') !== 'custom')
                        ->native(false),

                    Forms\Components\DatePicker::make('to')
                        ->label('إلى')
                        ->live()
                        ->disabled(fn (Forms\Get $get) => $get('preset') !== 'custom')
                        ->native(false),
                ]),
            ])
            ->statePath('');
    }

    protected function applyPreset(string $preset): void
    {
        $this->preset = $preset;

        [$from, $to] = match ($preset) {
            'yesterday'  => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'week'       => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'month'      => [now()->startOfMonth(), now()->endOfDay()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'custom'     => [
                $this->from ? Carbon::parse($this->from)->startOfDay() : now()->startOfDay(),
                $this->to ? Carbon::parse($this->to)->endOfDay() : now()->endOfDay(),
            ],
            default      => [now()->startOfDay(), now()->endOfDay()],
        };

        $this->from = $from->toDateString();
        $this->to   = $to->toDateString();
    }

    /** @return array<string, mixed> */
    public function getReportProperty(): array
    {
        $from = $this->from ? Carbon::parse($this->from)->startOfDay() : now()->startOfDay();
        $to   = $this->to ? Carbon::parse($this->to)->endOfDay() : now()->endOfDay();

        // A reversed range would silently report zero of everything, which
        // reads as "a quiet week" rather than as the mistake it is.
        if ($to->lessThan($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return (new FinancialReport($from, $to))->toArray();
    }
}
