<?php

namespace App\Filament\Widgets;

use App\Models\Addon;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Flavor;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\HeroSlide;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalProducts   = Product::count();
        $offProducts     = Product::where('available', false)->count();
        $totalFlavors    = Flavor::count();
        $offFlavors      = Flavor::where('available', false)->count();
        $totalAddons     = Addon::whereNull('product_id')->count();
        $todayMessages   = Contact::whereDate('created_at', today())->count();
        $totalMessages   = Contact::count();
        $unreadMessages  = Contact::where('is_read', false)->count();

        return [
            Stat::make('المنتجات النشطة', $totalProducts - $offProducts)
                ->description($offProducts > 0 ? "⚠ {$offProducts} منتج مُوقف" : 'جميعها نشطة ✓')
                ->descriptionIcon($offProducts > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($offProducts > 0 ? 'warning' : 'success')
                ->chart([max(0, $totalProducts - $offProducts)]),

            Stat::make('النكهات المتوفرة', $totalFlavors - $offFlavors)
                ->description($offFlavors > 0 ? "⚠ {$offFlavors} نكهة مُوقفة" : 'جميعها متوفرة ✓')
                ->descriptionIcon($offFlavors > 0 ? 'heroicon-m-eye-slash' : 'heroicon-m-eye')
                ->color($offFlavors > 0 ? 'danger' : 'success'),

            Stat::make('فئات القائمة', MenuCategory::count())
                ->description($totalProducts . ' منتج إجمالاً')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('primary'),

            Stat::make('الإضافات المشتركة', $totalAddons)
                ->description('GET /api/menu/addons')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('info'),

            Stat::make('الفعاليات', Event::count())
                ->description('في معرض الأحداث')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),

            Stat::make('رسائل غير مقروءة', $unreadMessages)
                ->description("إجمالي: {$totalMessages} رسالة — اليوم: {$todayMessages}")
                ->descriptionIcon('heroicon-m-envelope')
                ->color($unreadMessages > 0 ? 'danger' : 'gray'),
        ];
    }
}
