{{--
    The accountant's view.

    Ordered by what an audit asks first: what came in, does the cash match,
    what was taken by which method, what is owed, and what was given away.
--}}
<x-filament-panels::page>

    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @php
        $r     = $this->report;
        $money = fn ($v) => number_format((float) $v, 2) . ' ₪';
    @endphp

    {{-- ─── headline ────────────────────────────────────────────────────── --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mt-4">
        <x-filament::section>
            <div class="text-xs text-gray-500">صافي المبيعات</div>
            <div class="text-2xl font-black">{{ $money($r['sales']['net']) }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $r['sales']['orders'] }} طلب مدفوع</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs text-gray-500">إجمالي المبيعات</div>
            <div class="text-2xl font-black">{{ $money($r['sales']['gross']) }}</div>
            <div class="text-xs text-gray-500 mt-1">قبل خصم الاستردادات</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs text-gray-500">الإيداعات المعتمدة</div>
            <div class="text-2xl font-black">{{ $money($r['deposits']['approved']) }}</div>
            {{-- Said explicitly: booking this as revenue counts the same shekel
                 twice, once on deposit and again when it pays for an order. --}}
            <div class="text-xs text-gray-500 mt-1">شحن محافظ — ليست مبيعات</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-xs text-gray-500">فرق الدرج</div>
            @php $diff = (float) $r['reconciliation']['difference']; @endphp
            <div @class([
                'text-2xl font-black',
                'text-emerald-600' => abs($diff) < 0.01,
                'text-rose-600'    => $diff < -0.01,
                'text-amber-600'   => $diff > 0.01,
            ])>{{ $money($diff) }}</div>
            <div class="text-xs text-gray-500 mt-1">
                {{ $r['reconciliation']['shiftsClosed'] }} وردية مغلقة
                @if ($r['reconciliation']['shiftsOpen'] > 0)
                    · <span class="text-amber-600">{{ $r['reconciliation']['shiftsOpen'] }} ما زالت مفتوحة</span>
                @endif
            </div>
        </x-filament::section>
    </div>

    {{-- ─── cash reconciliation ─────────────────────────────────────────── --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">مطابقة النقد</x-slot>
        <x-slot name="description">النقد الذي يقول النظام إنه استُلم، مقابل ما عُدّ فعلياً في الدرج</x-slot>

        <div class="grid gap-3 sm:grid-cols-4 text-center">
            <div>
                <div class="text-xs text-gray-500">المتوقع</div>
                <div class="text-xl font-bold">{{ $money($r['reconciliation']['expectedCash']) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">المعدود</div>
                <div class="text-xl font-bold">{{ $money($r['reconciliation']['countedCash']) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">ورديات بعجز</div>
                <div class="text-xl font-bold text-rose-600">{{ $r['reconciliation']['shortShifts'] }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">ورديات بزيادة</div>
                <div class="text-xl font-bold text-amber-600">{{ $r['reconciliation']['overShifts'] }}</div>
            </div>
        </div>
    </x-filament::section>

    {{-- ─── by method ───────────────────────────────────────────────────── --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">المبيعات حسب طريقة الدفع</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-gray-500 border-b">
                    <tr>
                        <th class="text-start py-2">الطريقة</th>
                        <th class="text-center">الطلبات</th>
                        <th class="text-end">الإجمالي</th>
                        <th class="text-end">مسترد</th>
                        <th class="text-end">الصافي</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['salesByMethod'] as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-2">
                                {{ $row['label'] }}
                                @unless ($row['isNewMoney'])
                                    {{-- Wallet spend was already banked at deposit time. --}}
                                    <span class="text-xs text-gray-500">(رصيد مُودع سابقاً)</span>
                                @endunless
                            </td>
                            <td class="text-center">{{ $row['orders'] }}</td>
                            <td class="text-end">{{ $money($row['total']) }}</td>
                            <td class="text-end text-rose-600">{{ $row['refunded'] > 0 ? '−' . $money($row['refunded']) : '—' }}</td>
                            <td class="text-end font-bold">{{ $money($row['net']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500">لا توجد مبيعات في هذه الفترة</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- ─── by channel ──────────────────────────────────────────────────── --}}
    <div class="grid gap-4 lg:grid-cols-2 mt-4">
        <x-filament::section>
            <x-slot name="heading">المبيعات حسب القناة</x-slot>

            <table class="w-full text-sm">
                <tbody>
                    @forelse ($r['salesByChannel'] as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-2">{{ $row['label'] }}</td>
                            <td class="text-center text-gray-500">{{ $row['orders'] }} طلب</td>
                            <td class="text-end font-bold">{{ $money($row['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-gray-500">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">المحفظة</x-slot>

            <table class="w-full text-sm">
                <tbody>
                    <tr class="border-b">
                        <td class="py-2">إيداعات معتمدة</td>
                        <td class="text-end font-bold">{{ $money($r['deposits']['approved']) }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2">قيد المراجعة</td>
                        <td class="text-end">{{ $money($r['deposits']['pending']) }}</td>
                    </tr>
                    <tr class="border-b">
                        <td class="py-2">أُنفق من الرصيد</td>
                        <td class="text-end">{{ $money($r['deposits']['spent']) }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 font-bold">
                            رصيد قائم على المحل
                            {{-- A balance, not a flow: it is read as it stands now. --}}
                            <div class="text-xs font-normal text-gray-500">التزام حالي تجاه الزبائن</div>
                        </td>
                        <td class="text-end font-black">{{ $money($r['deposits']['outstanding']) }}</td>
                    </tr>
                </tbody>
            </table>
        </x-filament::section>
    </div>

    {{-- ─── adjustments ─────────────────────────────────────────────────── --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">الخصومات والاستردادات والإلغاءات</x-slot>
        <x-slot name="description">المبالغ التي خرجت دون بيع — وهي البنود التي تستحق مراجعة</x-slot>

        <div class="grid gap-3 sm:grid-cols-3 text-center mb-4">
            <div>
                <div class="text-xs text-gray-500">خصومات</div>
                <div class="text-xl font-bold">{{ $money($r['adjustments']['discountTotal']) }}</div>
                <div class="text-xs text-gray-500">{{ $r['adjustments']['discountOrders'] }} طلب</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">استردادات</div>
                <div class="text-xl font-bold text-rose-600">{{ $money($r['adjustments']['refundedTotal']) }}</div>
                <div class="text-xs text-gray-500">{{ $r['adjustments']['refundedOrders'] }} طلب</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">طلبات ملغاة</div>
                <div class="text-xl font-bold text-amber-600">{{ $r['adjustments']['cancelledOrders'] }}</div>
                <div class="text-xs text-gray-500">بقيمة {{ $money($r['adjustments']['cancelledValue']) }}</div>
            </div>
        </div>

        @if ($r['adjustments']['byCoupon'])
            <div class="text-sm font-bold mb-2">حسب الكوبون</div>
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($r['adjustments']['byCoupon'] as $row)
                        <tr class="border-b last:border-0">
                            <td class="py-2 font-mono">{{ $row['code'] }}</td>
                            <td class="text-center text-gray-500">{{ $row['uses'] }} مرة</td>
                            <td class="text-end font-bold">{{ $money($row['total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    {{-- ─── shifts ──────────────────────────────────────────────────────── --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">ورديات الكاشير</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-gray-500 border-b">
                    <tr>
                        <th class="text-start py-2">الكاشير</th>
                        <th class="text-start">من</th>
                        <th class="text-start">إلى</th>
                        <th class="text-center">طلبات</th>
                        <th class="text-end">متوقع</th>
                        <th class="text-end">معدود</th>
                        <th class="text-end">الفرق</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['shifts'] as $shift)
                        <tr class="border-b last:border-0">
                            <td class="py-2 font-semibold">{{ $shift['cashier'] ?? '—' }}</td>
                            <td>{{ $shift['openedAt'] }}</td>
                            <td>
                                @if ($shift['open'])
                                    <span class="rounded bg-amber-100 text-amber-700 px-1.5 py-0.5 text-xs font-bold">مفتوحة</span>
                                @else
                                    {{ $shift['closedAt'] }}
                                @endif
                            </td>
                            <td class="text-center">{{ $shift['orders'] }}</td>
                            <td class="text-end">{{ $shift['expected'] === null ? '—' : $money($shift['expected']) }}</td>
                            <td class="text-end">{{ $shift['counted'] === null ? '—' : $money($shift['counted']) }}</td>
                            <td @class([
                                'text-end font-bold',
                                'text-emerald-600' => $shift['difference'] !== null && abs((float) $shift['difference']) < 0.01,
                                'text-rose-600'    => $shift['difference'] !== null && (float) $shift['difference'] < -0.01,
                                'text-amber-600'   => $shift['difference'] !== null && (float) $shift['difference'] > 0.01,
                            ])>{{ $shift['difference'] === null ? '—' : $money($shift['difference']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-gray-500">لا توجد ورديات في هذه الفترة</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

</x-filament-panels::page>
