{{--
    The counter screen.

    Polls for orders, prints the ones the network printer did not get, and puts
    every action the cashier needs on the card itself. Deliberately plain and
    large: it is read at a glance, standing up, with people waiting.
--}}
<x-filament-panels::page>

    @php
        $settings = $this->settings();
        $shift    = $this->shift();
        $summary  = $this->shiftSummary();
    @endphp

    {{-- ─── shift strip ─────────────────────────────────────────────────── --}}
    @if ($shift)
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-filament::section class="text-center">
                <div class="text-xs text-gray-500">الوردية مفتوحة منذ</div>
                <div class="text-xl font-bold">{{ $summary['opened'] ?? '—' }}</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-xs text-gray-500">طلبات الوردية</div>
                <div class="text-xl font-bold">{{ $summary['orders'] ?? 0 }}</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-xs text-gray-500">النقد المتوقع في الدرج</div>
                <div class="text-xl font-bold">{{ number_format($summary['expected'] ?? 0, 2) }} ₪</div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-xs text-gray-500">الطابعة</div>
                <div class="text-xl font-bold">
                    {{ $this->networkPrinter() ? 'شبكية + متصفح' : 'المتصفح فقط' }}
                </div>
            </x-filament::section>
        </div>
    @else
        <x-filament::section>
            <div class="text-center py-4">
                <div class="text-lg font-bold">لا توجد وردية مفتوحة</div>
                {{-- Said plainly, because a cashier who takes cash without a
                     shift open leaves the closing report short. --}}
                <div class="text-sm text-gray-500 mt-1">
                    افتح وردية قبل استلام أي مبلغ نقدي، وإلا لن يظهر في تقرير الإغلاق.
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- ─── live queue ──────────────────────────────────────────────────── --}}
    <div
        x-data="cashierBoard({
            poll:      {{ $settings['poll'] }},
            autoPrint: {{ $settings['autoPrint'] ? 'true' : 'false' }},
            width:     {{ $settings['width'] }},
            queueUrl:  @js(route('receipts.queue')),
            printUrl:  @js(url('admin/receipts')),
        })"
        x-init="start()"
        wire:ignore
        class="mt-4"
    >
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-primary-500"></span>
                </span>
                <span class="text-sm text-gray-500">
                    تحديث تلقائي كل <span x-text="poll"></span> ثانية ·
                    <span x-text="orders.length"></span> طلب
                </span>
            </div>

            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" x-model="autoPrint" class="rounded">
                <span>طباعة تلقائية</span>
            </label>
        </div>

        <template x-if="orders.length === 0">
            <x-filament::section>
                <div class="text-center py-8 text-gray-500">لا توجد طلبات قيد التنفيذ</div>
            </x-filament::section>
        </template>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="order in orders" :key="order.reference">
                <div
                    class="rounded-xl border-2 bg-white dark:bg-gray-900 p-4 shadow-sm"
                    :class="{
                        'border-amber-400': order.deliveryMethod === 'dine-in',
                        'border-sky-400':   order.deliveryMethod === 'delivery',
                        'border-gray-300':  order.deliveryMethod === 'pickup',
                    }"
                >
                    {{-- The three kinds are colour-coded and labelled, because
                         "where does this go" is the first question asked. --}}
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-lg font-black" x-text="order.reference"></div>
                            <div class="text-xs text-gray-500" x-text="timeAgo(order.createdAt)"></div>
                        </div>
                        <div class="text-end">
                            <span
                                class="inline-block rounded-lg px-2 py-1 text-xs font-bold text-white"
                                :class="{
                                    'bg-amber-500': order.deliveryMethod === 'dine-in',
                                    'bg-sky-500':   order.deliveryMethod === 'delivery',
                                    'bg-gray-500':  order.deliveryMethod === 'pickup',
                                }"
                                x-text="kindLabel(order)"
                            ></span>
                        </div>
                    </div>

                    <div class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">الزبون</span>
                            <span class="font-semibold" x-text="order.customerName || '—'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">الهاتف</span>
                            <span x-text="order.customerPhone || '—'"></span>
                        </div>
                        <template x-if="order.deliveryMethod === 'delivery' && order.area">
                            <div class="flex justify-between">
                                <span class="text-gray-500">المنطقة</span>
                                <span class="font-semibold" x-text="order.area"></span>
                            </div>
                        </template>
                        <div class="flex justify-between">
                            <span class="text-gray-500">الأصناف</span>
                            <span x-text="order.itemCount"></span>
                        </div>
                        <div class="flex justify-between text-base">
                            <span class="text-gray-500">الإجمالي</span>
                            <span class="font-black" x-text="Number(order.total).toFixed(2) + ' ₪'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">الدفع</span>
                            <span>
                                <span x-text="paymentLabel(order.paymentMethod)"></span>
                                <span
                                    class="ms-1 rounded px-1.5 py-0.5 text-xs font-bold"
                                    :class="order.paid ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                    x-text="order.paid ? 'مدفوع' : 'غير مدفوع'"
                                ></span>
                            </span>
                        </div>
                    </div>

                    {{-- Table number: shown for dine-in, and settable right here
                         when the order arrived without one. --}}
                    <template x-if="order.deliveryMethod === 'dine-in'">
                        <div class="mt-3 flex items-center gap-2">
                            <span class="text-sm text-gray-500">طاولة</span>
                            <template x-if="order.tableNumber">
                                <span class="text-lg font-black" x-text="order.tableNumber"></span>
                            </template>
                            <template x-if="!order.tableNumber">
                                <input
                                    type="text"
                                    placeholder="رقم الطاولة"
                                    class="w-24 rounded-lg border-gray-300 text-sm"
                                    @keydown.enter="$wire.setTable(order.reference, $event.target.value); refresh()"
                                >
                            </template>
                        </div>
                    </template>

                    <div class="mt-3 flex items-center justify-between">
                        <span class="rounded-lg bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-semibold"
                              x-text="order.status"></span>

                        {{-- A receipt that never made it to paper is called out
                             rather than silently missing. --}}
                        <template x-if="order.printError">
                            <span class="text-xs text-rose-600" title="خطأ الطباعة" x-text="'⚠ ' + order.printError"></span>
                        </template>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-bold text-white hover:bg-primary-500"
                            @click="print(order, false)"
                        >
                            <span x-text="order.printed ? 'إعادة طباعة' : 'طباعة'"></span>
                        </button>

                        <template x-if="!order.paid && ['cash','visa'].includes(order.paymentMethod)">
                            <button
                                class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-500"
                                @click="$wire.markPaid(order.reference).then(() => refresh())"
                            >استلام الدفع</button>
                        </template>

                        <button
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold hover:bg-gray-50 dark:hover:bg-gray-800"
                            @click="advance(order)"
                        >تحديث الحالة</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    @push('scripts')
    <script>
        function cashierBoard(config) {
            return {
                orders: [],
                poll: config.poll,
                autoPrint: config.autoPrint,
                width: config.width,
                // Every reference this tab has already sent to the printer.
                // Without it a re-poll would reprint the same docket forever.
                printed: new Set(),
                timer: null,

                start() {
                    this.refresh();
                    this.timer = setInterval(() => this.refresh(), this.poll * 1000);

                    // A background tab throttles timers, so the queue is
                    // refreshed the moment it comes back to the front.
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) this.refresh();
                    });
                },

                async refresh() {
                    try {
                        const res = await fetch(config.queueUrl, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });

                        if (!res.ok) return;

                        const data = await res.json();
                        this.orders = data.orders;

                        if (this.autoPrint) this.printNew();
                    } catch (e) {
                        // A dropped poll is not worth interrupting the counter
                        // over; the next tick picks it up.
                    }
                },

                printNew() {
                    this.orders
                        // Only what the network printer did not already handle.
                        .filter(o => !o.printed && !this.printed.has(o.reference))
                        .forEach(o => this.print(o, true));
                },

                print(order, auto) {
                    this.printed.add(order.reference);

                    const url = config.printUrl + '/' + encodeURIComponent(order.reference)
                        + '?width=' + this.width + (auto ? '&auto=1' : '');

                    // A named window per order: printing three dockets at once
                    // must not have them overwrite each other.
                    window.open(url, 'receipt-' + order.reference, 'width=420,height=700');
                },

                async advance(order) {
                    const options = await this.$wire.nextStatuses(order.reference);

                    if (!options.length) return;

                    const choice = window.prompt(
                        'الحالة الجديدة:\n' + options.map((s, i) => (i + 1) + ') ' + s).join('\n'),
                        '1',
                    );

                    const index = parseInt(choice, 10) - 1;

                    if (isNaN(index) || !options[index]) return;

                    await this.$wire.advance(order.reference, options[index]);
                    this.refresh();
                },

                kindLabel(order) {
                    return {
                        'dine-in':  'داخل المحل' + (order.tableNumber ? ' · طاولة ' + order.tableNumber : ''),
                        'pickup':   'استلام',
                        'delivery': 'توصيل',
                    }[order.deliveryMethod] || order.deliveryMethod;
                },

                paymentLabel(method) {
                    return {
                        'cash': 'نقداً', 'visa': 'بطاقة', 'wallet': 'محفظة',
                        'jawwal': 'جوال باي', 'jawwal-manual': 'جوال باي (تحويل)',
                        'bop': 'بنك فلسطين', 'paypal': 'PayPal',
                    }[method] || method;
                },

                timeAgo(iso) {
                    if (!iso) return '';

                    const minutes = Math.floor((Date.now() - new Date(iso)) / 60000);

                    if (minutes < 1)  return 'الآن';
                    if (minutes < 60) return 'منذ ' + minutes + ' دقيقة';

                    return 'منذ ' + Math.floor(minutes / 60) + ' ساعة';
                },
            };
        }
    </script>
    @endpush

</x-filament-panels::page>
