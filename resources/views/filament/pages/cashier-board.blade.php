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
                    <span x-text="visible().length"></span> من <span x-text="orders.length"></span> طلب
                </span>
            </div>

            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" x-model="autoPrint" class="rounded">
                <span>طباعة تلقائية</span>
            </label>
        </div>

        {{-- Filters.

             Everything the counter needs is already in memory, so these are
             applied here rather than by asking the server again: the list
             redraws as fast as the chip is clicked, which is the whole point
             of a screen someone uses with a queue in front of them.

             Counts respect the OTHER active filters, so a chip showing 3
             really does yield 3 — a count that ignored them would invite
             clicks that land on an empty list. --}}
        <div class="mb-3 space-y-2">
            <div class="flex items-center gap-2">
                <input
                    type="search"
                    x-model="search"
                    placeholder="ابحث برقم الطلب أو الهاتف أو اسم الزبون…"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm"
                >
                <template x-if="isFiltered()">
                    <button
                        class="shrink-0 rounded-lg border border-rose-300 px-3 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950"
                        @click="clearFilters()"
                    >مسح الفلاتر</button>
                </template>
            </div>

            <template x-for="group in filterGroups" :key="group.key">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs text-gray-500 w-20 shrink-0" x-text="group.label"></span>
                    <template x-for="option in group.options" :key="option.value">
                        <button
                            class="rounded-full border px-3 py-1 text-xs font-bold transition"
                            :class="filters[group.key] === option.value
                                ? 'border-primary-600 bg-primary-600 text-white'
                                : 'border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800'"
                            @click="filters[group.key] = option.value"
                        >
                            <span x-text="option.label"></span>
                            <span
                                class="ms-1 rounded-full px-1.5"
                                :class="filters[group.key] === option.value ? 'bg-white/25' : 'bg-gray-200 dark:bg-gray-700'"
                                x-text="count(group.key, option.value)"
                            ></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        <template x-if="visible().length === 0">
            <x-filament::section>
                <div class="text-center py-8 text-gray-500">
                    <span x-show="orders.length === 0">لا توجد طلبات</span>
                    <span x-show="orders.length > 0">لا يوجد طلب يطابق الفلاتر المختارة</span>
                </div>
            </x-filament::section>
        </template>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="order in visible()" :key="order.reference">
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

                        {{-- Which account the transfer landed in. "Paid by
                             Jawwal Pay" does not say which of the shop's
                             numbers to go and check. --}}
                        <template x-if="order.paidToAccount">
                            <div class="flex justify-between">
                                <span class="text-gray-500">تم عبر حساب</span>
                                <span class="font-semibold" x-text="order.paidToAccount"></span>
                            </div>
                        </template>
                    </div>

                    {{-- The driver, once there is one: the answer to "where is
                         my order", available without leaving this screen. --}}
                    <template x-if="order.driver">
                        <div class="mt-3 rounded-lg bg-sky-50 dark:bg-sky-950/40 p-2 text-sm">
                            <div class="font-bold text-sky-700 dark:text-sky-300">🚗 السائق</div>
                            <div class="flex justify-between mt-1">
                                <span x-text="order.driver.name"></span>
                                <a
                                    class="font-semibold underline"
                                    :href="'tel:' + order.driver.phone"
                                    x-text="order.driver.phone"
                                ></a>
                            </div>
                            <template x-if="order.driver.company">
                                <div class="text-xs text-gray-500" x-text="order.driver.company"></div>
                            </template>
                        </div>
                    </template>

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

                        {{-- Assigning and dispatching are one button because
                             at the counter they are one moment: the driver is
                             standing there. Two buttons is how an order ends
                             up assigned but never marked as gone. --}}
                        <template x-if="order.needsDriver && !order.final">
                            <button
                                class="rounded-lg bg-sky-600 px-3 py-2 text-sm font-bold text-white hover:bg-sky-500"
                                @click="assignDriver(order)"
                            >تعيين السائق</button>
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
                search: '',
                filters: { status: 'all', channel: 'all', payment: 'all' },

                // Grouped rather than one chip per raw status: the counter
                // thinks in "ready" and "done", not in the four different
                // words the three fulfilment ladders end with.
                statusGroups: {
                    new:       ['قيد المراجعة'],
                    preparing: ['جاري التحضير'],
                    ready:     ['جاهز للاستلام', 'في الطريق'],
                    done:      ['تم التسليم', 'تم الاستلام'],
                    closed:    ['ملغي', 'مسترد'],
                },

                filterGroups: [
                    { key: 'status', label: 'الحالة', options: [
                        { value: 'all',       label: 'الكل' },
                        { value: 'new',       label: 'جديد' },
                        { value: 'preparing', label: 'قيد التحضير' },
                        { value: 'ready',     label: 'جاهز' },
                        { value: 'done',      label: 'مكتمل' },
                        { value: 'closed',    label: 'ملغي / مسترد' },
                    ]},
                    { key: 'channel', label: 'الاستلام', options: [
                        { value: 'all',      label: 'الكل' },
                        { value: 'dine-in',  label: '🍦 تناول الآن' },
                        { value: 'pickup',   label: '🏪 استلام' },
                        { value: 'delivery', label: '🚗 توصيل' },
                    ]},
                    { key: 'payment', label: 'الدفع', options: [
                        { value: 'all',           label: 'الكل' },
                        { value: 'cash',          label: '💵 كاش' },
                        { value: 'visa',          label: '💳 فيزا' },
                        { value: 'jawwal',        label: '📱 جوال باي' },
                        { value: 'jawwal-manual', label: '📱 جوال باي (تحويل)' },
                        { value: 'bop',           label: '🏦 بنك فلسطين' },
                        { value: 'palpay',        label: '💳 بال باي' },
                        { value: 'wallet',        label: '👛 المحفظة' },
                    ]},
                ],

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

                // ─── filtering ──────────────────────────────────────────

                /**
                 * Whether an order survives the filters.
                 *
                 * `except` lets a chip count itself against everything else,
                 * so the numbers describe what clicking would actually give.
                 */
                matches(order, except) {
                    if (this.filters.status !== 'all' && except !== 'status') {
                        const wanted = this.statusGroups[this.filters.status] || [];
                        if (!wanted.includes(order.status)) return false;
                    }

                    if (this.filters.channel !== 'all' && except !== 'channel') {
                        if (order.deliveryMethod !== this.filters.channel) return false;
                    }

                    if (this.filters.payment !== 'all' && except !== 'payment') {
                        if (order.paymentMethod !== this.filters.payment) return false;
                    }

                    return this.matchesSearch(order);
                },

                matchesSearch(order) {
                    const term = this.search.trim().toLowerCase();

                    if (!term) return true;

                    // Digits only when the term is a number, so 0599 finds a
                    // phone stored as 059-9... just the same.
                    const digits = term.replace(/\D/g, '');
                    const phone  = (order.customerPhone || '').replace(/\D/g, '');

                    return (order.reference || '').toLowerCase().includes(term)
                        || (order.customerName || '').toLowerCase().includes(term)
                        || (digits.length > 0 && phone.includes(digits));
                },

                visible() {
                    return this.orders.filter(o => this.matches(o, null));
                },

                count(key, value) {
                    return this.orders.filter(o => {
                        if (!this.matches(o, key)) return false;
                        if (value === 'all') return true;

                        if (key === 'status')  return (this.statusGroups[value] || []).includes(o.status);
                        if (key === 'channel') return o.deliveryMethod === value;

                        return o.paymentMethod === value;
                    }).length;
                },

                isFiltered() {
                    return this.search.trim() !== ''
                        || Object.values(this.filters).some(v => v !== 'all');
                },

                clearFilters() {
                    this.filters = { status: 'all', channel: 'all', payment: 'all' };
                    this.search  = '';
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

                async assignDriver(order) {
                    const drivers = await this.$wire.drivers();

                    if (!drivers.length) {
                        window.alert('لا يوجد سائقون مفعّلون — أضفهم من «السائقون» في القائمة الجانبية.');

                        return;
                    }

                    // Company, phone and whether they are already out: enough
                    // to choose without opening another screen.
                    const lines = drivers.map((d, i) => {
                        const parts = [d.name, d.company, d.phone, d.status].filter(Boolean);

                        return (i + 1) + ') ' + parts.join(' — ');
                    });

                    const choice = window.prompt('السائق:
' + lines.join('
'), '1');
                    const index  = parseInt(choice, 10) - 1;

                    if (isNaN(index) || !drivers[index]) return;

                    await this.$wire.assignDriver(order.reference, drivers[index].id);
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
                        'bop': 'بنك فلسطين', 'palpay': 'بال باي',
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
