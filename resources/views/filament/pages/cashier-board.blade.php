{{--
    شاشة إدارة الطلبات — مركز تحكم الكاشير.

    صفحة واحدة شاملة: فلاتر متعددة قابلة للدمج، بحث فوري، بطاقات طلبات
    غنية بالمعلومات، modals أنيقة لتعيين السائق وتحديث الحالة. كل شيء يتم
    بدون مغادرة هذه الصفحة.

    البيانات تأتي من /admin/receipts/queue عبر polling، والتصفية تتم محلياً
    في Alpine.js — فلا رحلة شبكة عند كل ضغطة فلتر.
--}}
<x-filament-panels::page>

    @php
        $settings = $this->settings();
        $shift    = $this->shift();
        $summary  = $this->shiftSummary();
        $user     = auth()->user();
    @endphp

    {{-- ─── custom styles ────────────────────────────────────────────────── --}}
    @push('styles')
    <style>
        /* Header gradient */
        .cashier-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8e 50%, #3b7ddd 100%);
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        .cashier-header .logo-text {
            font-size: 1.5rem;
            font-weight: 900;
            letter-spacing: -0.02em;
        }
        .cashier-header .logo-sub {
            font-size: 0.7rem;
            opacity: 0.8;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        /* Filter section */
        .filter-section {
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 0.5rem;
            border: 1px solid #e5e7eb;
        }
        .dark .filter-section {
            background: #111827;
            border-color: #374151;
        }
        .filter-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #6b7280;
            min-width: 6rem;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Filter chips */
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
            border: 2px solid transparent;
            white-space: nowrap;
            user-select: none;
        }
        .filter-chip:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .filter-chip .chip-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.4rem;
            height: 1.4rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 0 0.3rem;
        }

        /* Status chip colors */
        .chip-all          { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .chip-all.active   { background: #1e40af; color: white; border-color: #1e40af; }
        .chip-all .chip-count          { background: #bfdbfe; color: #1e40af; }
        .chip-all.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-new          { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
        .chip-new.active   { background: #ea580c; color: white; border-color: #ea580c; }
        .chip-new .chip-count          { background: #fed7aa; color: #c2410c; }
        .chip-new.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-preparing          { background: #fefce8; color: #a16207; border-color: #fde68a; }
        .chip-preparing.active   { background: #ca8a04; color: white; border-color: #ca8a04; }
        .chip-preparing .chip-count          { background: #fde68a; color: #a16207; }
        .chip-preparing.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-ready          { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
        .chip-ready.active   { background: #16a34a; color: white; border-color: #16a34a; }
        .chip-ready .chip-count          { background: #bbf7d0; color: #15803d; }
        .chip-ready.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-done          { background: #f3f4f6; color: #4b5563; border-color: #d1d5db; }
        .chip-done.active   { background: #4b5563; color: white; border-color: #4b5563; }
        .chip-done .chip-count          { background: #d1d5db; color: #4b5563; }
        .chip-done.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-closed          { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .chip-closed.active   { background: #991b1b; color: white; border-color: #991b1b; }
        .chip-closed .chip-count          { background: #fecaca; color: #991b1b; }
        .chip-closed.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        /* Channel chips */
        .chip-dine-in          { background: #fffbeb; color: #92400e; border-color: #fde68a; }
        .chip-dine-in.active   { background: #d97706; color: white; border-color: #d97706; }
        .chip-dine-in .chip-count          { background: #fde68a; color: #92400e; }
        .chip-dine-in.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-pickup          { background: #faf5ff; color: #7e22ce; border-color: #e9d5ff; }
        .chip-pickup.active   { background: #7e22ce; color: white; border-color: #7e22ce; }
        .chip-pickup .chip-count          { background: #e9d5ff; color: #7e22ce; }
        .chip-pickup.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-delivery          { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .chip-delivery.active   { background: #dc2626; color: white; border-color: #dc2626; }
        .chip-delivery .chip-count          { background: #fecaca; color: #b91c1c; }
        .chip-delivery.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        /* Payment chips */
        .chip-cash          { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .chip-cash.active   { background: #16a34a; color: white; border-color: #16a34a; }
        .chip-cash .chip-count          { background: #bbf7d0; color: #166534; }
        .chip-cash.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-jawwal          { background: #f0f9ff; color: #0369a1; border-color: #bae6fd; }
        .chip-jawwal.active   { background: #0284c7; color: white; border-color: #0284c7; }
        .chip-jawwal .chip-count          { background: #bae6fd; color: #0369a1; }
        .chip-jawwal.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-bop          { background: #fef3c7; color: #92400e; border-color: #fde68a; }
        .chip-bop.active   { background: #d97706; color: white; border-color: #d97706; }
        .chip-bop .chip-count          { background: #fde68a; color: #92400e; }
        .chip-bop.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-palpay          { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }
        .chip-palpay.active   { background: #7c3aed; color: white; border-color: #7c3aed; }
        .chip-palpay .chip-count          { background: #c4b5fd; color: #5b21b6; }
        .chip-palpay.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-visa          { background: #e0e7ff; color: #3730a3; border-color: #a5b4fc; }
        .chip-visa.active   { background: #4f46e5; color: white; border-color: #4f46e5; }
        .chip-visa .chip-count          { background: #a5b4fc; color: #3730a3; }
        .chip-visa.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-wallet          { background: #fdf4ff; color: #86198f; border-color: #f0abfc; }
        .chip-wallet.active   { background: #a21caf; color: white; border-color: #a21caf; }
        .chip-wallet .chip-count          { background: #f0abfc; color: #86198f; }
        .chip-wallet.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        .chip-jawwal-manual          { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .chip-jawwal-manual.active   { background: #059669; color: white; border-color: #059669; }
        .chip-jawwal-manual .chip-count          { background: #a7f3d0; color: #065f46; }
        .chip-jawwal-manual.active .chip-count   { background: rgba(255,255,255,0.25); color: white; }

        /* Order cards */
        .order-card {
            background: white;
            border-radius: 1rem;
            border: 2px solid #e5e7eb;
            padding: 1rem 1.15rem;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        .dark .order-card {
            background: #111827;
            border-color: #374151;
        }
        .order-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        .order-card.border-delivery { border-color: #fca5a5; }
        .order-card.border-pickup   { border-color: #c4b5fd; }
        .order-card.border-dine-in  { border-color: #fde68a; }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.7rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 800;
        }
        .badge-new       { background: #fff7ed; color: #c2410c; }
        .badge-preparing { background: #fefce8; color: #a16207; }
        .badge-ready     { background: #f0fdf4; color: #16a34a; }
        .badge-on-way    { background: #fef2f2; color: #dc2626; }
        .badge-done      { background: #f3f4f6; color: #4b5563; }
        .badge-cancelled { background: #fef2f2; color: #991b1b; }

        /* Delivery/Payment method tags */
        .method-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.6rem;
            border-radius: 0.5rem;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .tag-delivery { background: #fee2e2; color: #991b1b; }
        .tag-pickup   { background: #ede9fe; color: #5b21b6; }
        .tag-dine-in  { background: #fef3c7; color: #92400e; }

        .tag-cash          { background: #dcfce7; color: #166534; }
        .tag-jawwal        { background: #e0f2fe; color: #0369a1; }
        .tag-jawwal-manual { background: #d1fae5; color: #065f46; }
        .tag-bop           { background: #fef3c7; color: #92400e; }
        .tag-palpay        { background: #ede9fe; color: #5b21b6; }
        .tag-visa          { background: #e0e7ff; color: #3730a3; }
        .tag-wallet        { background: #fae8ff; color: #86198f; }

        /* Driver card */
        .driver-info {
            background: #f0f9ff;
            border-radius: 0.75rem;
            padding: 0.65rem 0.85rem;
            border: 1px solid #bae6fd;
        }
        .dark .driver-info {
            background: #0c4a6e22;
            border-color: #075985;
        }

        /* Payment account info */
        .payment-account-info {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.6rem;
            background: #fffbeb;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            color: #92400e;
            font-weight: 600;
        }
        .dark .payment-account-info {
            background: #78350f22;
            color: #fbbf24;
        }

        /* Modal overlay */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-content {
            background: white;
            border-radius: 1.25rem;
            width: 100%;
            max-width: 28rem;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
        }
        .dark .modal-content {
            background: #1f2937;
        }

        /* Driver select card inside modal */
        .driver-select-card {
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: all 0.15s;
        }
        .dark .driver-select-card { border-color: #374151; }
        .driver-select-card:hover { border-color: #3b82f6; background: #eff6ff; }
        .dark .driver-select-card:hover { background: #1e3a5f33; }
        .driver-select-card.selected { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px #93c5fd44; }
        .dark .driver-select-card.selected { background: #1e3a5f33; }

        /* Status option inside modal */
        .status-option {
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 0.65rem 1rem;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
        .dark .status-option { border-color: #374151; }
        .status-option:hover { border-color: #3b82f6; background: #eff6ff; }
        .dark .status-option:hover { background: #1e3a5f33; }
        .status-option.selected { border-color: #2563eb; background: #eff6ff; }
        .dark .status-option.selected { background: #1e3a5f33; }

        /* Search bar */
        .search-bar {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            padding: 0.6rem 1rem;
            font-size: 0.875rem;
            width: 100%;
            transition: border-color 0.15s;
        }
        .dark .search-bar {
            background: #111827;
            border-color: #374151;
            color: white;
        }
        .search-bar:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px #93c5fd44;
        }

        /* Clear filters button */
        .clear-filters-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            cursor: pointer;
            transition: all 0.15s;
        }
        .clear-filters-btn:hover { background: #fee2e2; }

        /* All buttons: cursor pointer */
        button, [role="button"] { cursor: pointer; }
        button:disabled { cursor: not-allowed !important; opacity: 0.6; }

        /* Action buttons */
        .btn-print {
            background: #ea580c;
            color: white;
            padding: 0.45rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s;
            cursor: pointer;
        }
        .btn-print:hover { background: #c2410c; }

        .btn-update-status {
            background: white;
            color: #374151;
            padding: 0.45rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            border: 1.5px solid #d1d5db;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s;
            cursor: pointer;
        }
        .dark .btn-update-status { background: #1f2937; color: #d1d5db; border-color: #4b5563; }
        .btn-update-status:hover { background: #f3f4f6; border-color: #9ca3af; }
        .dark .btn-update-status:hover { background: #374151; }

        .btn-assign-driver {
            background: #0284c7;
            color: white;
            padding: 0.45rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s;
            cursor: pointer;
        }
        .btn-assign-driver:hover { background: #0369a1; }

        .btn-pay {
            background: #16a34a;
            color: white;
            padding: 0.45rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s;
            cursor: pointer;
        }
        .btn-pay:hover { background: #15803d; }

        .btn-refund {
            background: #dc2626;
            color: white;
            padding: 0.45rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.15s;
            cursor: pointer;
        }
        .btn-refund:hover { background: #b91c1c; }

        /* Modal confirm/cancel buttons — force visible solid colors */
        .modal-btn-confirm {
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: white;
            transition: all 0.15s;
            cursor: pointer;
            border: none;
        }
        .modal-btn-confirm:disabled { opacity: 0.4; cursor: not-allowed; }
        .modal-btn-cancel {
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 700;
            background: #f3f4f6;
            color: #374151;
            border: 1.5px solid #d1d5db;
            cursor: pointer;
            transition: all 0.15s;
        }
        .modal-btn-cancel:hover { background: #e5e7eb; }
        .dark .modal-btn-cancel { background: #374151; color: #d1d5db; border-color: #4b5563; }
        .dark .modal-btn-cancel:hover { background: #4b5563; }

        /* Sort dropdown */
        .sort-dropdown {
            font-size: 0.8rem;
            padding: 0.4rem 0.7rem;
            border-radius: 0.5rem;
            border: 1.5px solid #d1d5db;
            background: white;
            font-weight: 600;
        }
        .dark .sort-dropdown { background: #111827; border-color: #374151; color: #d1d5db; }

        /* Shift strip */
        .shift-strip {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border-radius: 0.75rem;
            padding: 0.75rem 1.25rem;
            border: 1px solid #bae6fd;
        }
        .dark .shift-strip {
            background: linear-gradient(135deg, #0c4a6e22, #07598522);
            border-color: #075985;
        }

        /* Pulse dot */
        .pulse-dot {
            width: 0.6rem; height: 0.6rem;
            background: #22c55e;
            border-radius: 9999px;
            animation: pulse-green 2s infinite;
        }
        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
            50% { box-shadow: 0 0 0 6px rgba(34,197,94,0); }
        }
    </style>
    @endpush

    {{-- ─── page header ──────────────────────────────────────────────────── --}}
    <div class="cashier-header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-white/15">
                    <span class="text-2xl">🍦</span>
                </div>
                <div>
                    <div class="logo-text">جلاسيه الأمير</div>
                    <div class="logo-sub">GLACE AL AMIR</div>
                </div>
            </div>
            <div class="text-center">
                <div class="text-xl font-bold">إدارة الطلبات</div>
                <div class="text-xs opacity-75">مراجعة الطلبات ومعالجتها بسهولة وسرعة</div>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-end">
                    <div class="text-sm font-semibold">📅 {{ now()->translatedFormat('l j F Y') }}</div>
                    <div class="text-xs opacity-75">{{ now()->format('h:i') }} {{ now()->format('A') === 'AM' ? 'ص' : 'م' }}</div>
                </div>
                <div class="flex items-center gap-2 bg-white/15 rounded-lg px-3 py-2">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full bg-white/20">
                        <span class="text-sm">👤</span>
                    </div>
                    <div>
                        <div class="text-sm font-bold">{{ $user?->name ?? 'الكاشير' }}</div>
                        <div class="text-xs opacity-75">الكاشير</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── live queue ────────────────────────────────────────────────────── --}}
    <div
        x-data="cashierBoard({
            poll:      {{ $settings['poll'] }},
            width:     {{ $settings['width'] }},
            queueUrl:  @js(route('receipts.queue')),
            printUrl:  @js(url('admin/receipts')),
            networkPrinter: {{ ($settings['networkPrinter'] ?? false) ? 'true' : 'false' }},
        })"
        x-init="start()"
        @cashier-refresh.window="onServerRefresh()"
        wire:ignore
    >
        {{-- ─── connection warning ────────────────────────────────────────────
             A poll that fails quietly leaves a board that looks up to date and
             is not. Any failure — a dropped network, an expired login — is said
             out loud here. --}}
        <template x-if="!connection.ok">
            <div class="mb-3 rounded-xl border-2 border-rose-400 bg-rose-50 dark:bg-rose-950/40 px-4 py-3 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <div class="font-bold text-rose-700 dark:text-rose-300">⚠️ توقف تحديث الطلبات</div>
                    <div class="text-sm text-rose-600 dark:text-rose-400" x-text="connection.error"></div>
                </div>
                <button class="modal-btn-confirm" style="background:#e11d48" @click="window.location.reload()">إعادة تحميل الصفحة</button>
            </div>
        </template>

        {{-- ─── print dialog still showing ────────────────────────────────────
             A web page cannot print without the browser's dialog unless the
             browser itself was started for silent printing. When a print took
             long enough that a person must have clicked through the dialog,
             say so, and say how to switch it off. --}}
        <template x-if="previewDetected && !previewDismissed">
            <div class="mb-3 rounded-xl border-2 border-amber-400 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 flex items-start justify-between gap-3">
                <div class="text-sm">
                    <div class="font-bold text-amber-800 dark:text-amber-300">🖨️ ما زالت نافذة معاينة الطباعة تظهر</div>
                    <div class="text-amber-700 dark:text-amber-400 mt-1">
                        للطباعة المباشرة بدون معاينة، افتح شاشة الكاشير من اختصار المتصفح المُعدّ للطباعة الصامتة
                        (<span dir="ltr">--kiosk-printing</span>) — الخطوات في دليل «طباعة الكاشير».
                    </div>
                </div>
                <button class="text-amber-700 text-xl leading-none" @click="previewDismissed = true">&times;</button>
            </div>
        </template>

        {{-- ─── shift strip (live, from the same poll as the cards) ────────── --}}
        <template x-if="shiftOpen && summary">
            <div class="shift-strip mb-3">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-6 flex-wrap">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🕐</span>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">الوردية مفتوحة منذ</div>
                                <div class="text-sm font-bold" x-text="summary.openedAt"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg">📦</span>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">طلبات الوردية</div>
                                <div class="text-sm font-bold" x-text="summary.orders"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg">📈</span>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">صافي المبيعات</div>
                                <div class="text-sm font-bold" x-text="money(summary.net)"></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg">💰</span>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">النقد المتوقع</div>
                                <div class="text-sm font-bold" x-text="money(summary.expectedCash)"></div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <div class="pulse-dot"></div>
                        <span x-text="'آخر تحديث قبل ' + secondsSince() + ' ث'"></span>
                        <span>·</span>
                        <span x-text="networkPrinter ? '🖨️ شبكية' : '🖨️ المتصفح'"></span>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="!shiftOpen">
            <div class="shift-strip mb-3 text-center py-4">
                <div class="text-3xl mb-1">🔒</div>
                <div class="font-bold text-lg">لا توجد وردية مفتوحة</div>
                <div class="text-sm text-gray-500 mt-1">افتح وردية من زر «فتح وردية» أعلى الصفحة لتظهر الطلبات وتُطبع.</div>
                <template x-if="waiting > 0">
                    <div class="mt-2 inline-block rounded-full bg-amber-100 text-amber-800 px-3 py-1 text-sm font-bold" x-text="'⏳ ' + waiting + ' طلب بانتظار فتح الوردية'"></div>
                </template>
            </div>
        </template>

        {{-- ─── status filters ───────────────────────────────────────────── --}}
        <div class="filter-section">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="filter-label">📋 حالة الطلب</span>
                <template x-for="option in filterGroups[0].options" :key="option.value">
                    <button
                        class="filter-chip"
                        :class="[
                            'chip-' + option.value,
                            filters.status === option.value ? 'active' : ''
                        ]"
                        @click="filters.status = option.value"
                    >
                        <span x-text="option.icon"></span>
                        <span x-text="option.label"></span>
                        <span class="chip-count" x-text="count('status', option.value)"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- ─── channel filters ──────────────────────────────────────────── --}}
        <div class="filter-section">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="filter-label">🚗 طريقة الاستلام</span>
                <template x-for="option in filterGroups[1].options" :key="option.value">
                    <button
                        class="filter-chip"
                        :class="[
                            'chip-' + option.value,
                            filters.channel === option.value ? 'active' : ''
                        ]"
                        @click="filters.channel = option.value"
                    >
                        <span x-text="option.icon"></span>
                        <span x-text="option.label"></span>
                        <span class="chip-count" x-text="count('channel', option.value)"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- ─── payment filters ──────────────────────────────────────────── --}}
        <div class="filter-section">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="filter-label">💳 طريقة الدفع</span>
                <template x-for="option in filterGroups[2].options" :key="option.value">
                    <button
                        class="filter-chip"
                        :class="[
                            'chip-' + option.value,
                            filters.payment === option.value ? 'active' : ''
                        ]"
                        @click="filters.payment = option.value"
                    >
                        <span x-text="option.icon"></span>
                        <span x-text="option.label"></span>
                        <span class="chip-count" x-text="count('payment', option.value)"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- ─── search + controls ────────────────────────────────────────── --}}
        <div class="filter-section">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex-1 min-w-[200px] relative">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                    <input
                        type="search"
                        x-model="search"
                        placeholder="البحث برقم الطلب أو رقم الهاتف أو اسم الزبون ..."
                        class="search-bar pr-10"
                    >
                </div>
                <template x-if="isFiltered()">
                    <button class="clear-filters-btn" @click="clearFilters()">
                        🗑️ مسح الكل
                    </button>
                </template>
            </div>

            <div class="flex items-center justify-between mt-2 flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <template x-if="isFiltered()">
                        <span class="text-xs font-semibold text-blue-600">
                            الفلاتر النشطة:
                            <template x-if="filters.status !== 'all'">
                                <span class="inline-block bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs mx-0.5" x-text="statusFilterLabel()"></span>
                            </template>
                            <template x-if="filters.channel !== 'all'">
                                <span class="inline-block bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full text-xs mx-0.5" x-text="channelFilterLabel()"></span>
                            </template>
                            <template x-if="filters.payment !== 'all'">
                                <span class="inline-block bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs mx-0.5" x-text="paymentFilterLabel()"></span>
                            </template>
                        </span>
                    </template>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5">
                        <div class="pulse-dot"></div>
                        <span class="text-xs text-gray-500">
                            تم العثور على <span class="font-bold text-gray-800 dark:text-gray-200" x-text="visible().length"></span> طلب
                        </span>
                    </div>
                    <select class="sort-dropdown" x-model="sortOrder">
                        <option value="newest">↓ الأحدث أولاً</option>
                        <option value="oldest">↑ الأقدم أولاً</option>
                        <option value="total_desc">💰 الأعلى مبلغاً</option>
                        <option value="total_asc">💰 الأقل مبلغاً</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- ─── empty state ──────────────────────────────────────────────── --}}
        <template x-if="visible().length === 0">
            <div class="filter-section text-center py-10 mt-2">
                <div class="text-4xl mb-3" x-text="orders.length === 0 ? '📭' : '🔍'"></div>
                <div class="text-lg font-bold text-gray-500" x-show="orders.length === 0">لا توجد طلبات حالياً</div>
                <div class="text-lg font-bold text-gray-500" x-show="orders.length > 0">لا يوجد طلب يطابق الفلاتر المختارة</div>
                <div class="text-sm text-gray-400 mt-1" x-show="orders.length > 0">جرّب تعديل الفلاتر أو اضغط "مسح الكل"</div>
            </div>
        </template>

        {{-- ─── order cards grid ─────────────────────────────────────────── --}}
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 mt-3">
            <template x-for="order in sorted()" :key="order.reference">
                <div
                    class="order-card"
                    :class="{
                        'border-delivery': order.deliveryMethod === 'delivery',
                        'border-pickup':   order.deliveryMethod === 'pickup',
                        'border-dine-in':  order.deliveryMethod === 'dine-in',
                        'ring-4 ring-green-400': freshRefs[order.reference],
                    }"
                >
                    {{-- Card header: status + reference --}}
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <span
                            class="status-badge"
                            :class="statusBadgeClass(order)"
                            x-text="statusIcon(order) + ' ' + order.status"
                        ></span>
                        <span class="text-sm font-black text-gray-800 dark:text-gray-200" x-text="order.reference"></span>
                    </div>

                    {{-- Method tags --}}
                    <div class="flex items-center gap-1.5 flex-wrap mb-3">
                        <span
                            class="method-tag"
                            :class="channelTagClass(order)"
                            x-text="channelIcon(order) + ' ' + kindLabel(order)"
                        ></span>
                        <span
                            class="method-tag"
                            :class="paymentTagClass(order)"
                        >
                            <span x-text="paymentIcon(order.paymentMethod) + ' ' + paymentLabel(order.paymentMethod)"></span>
                        </span>
                        <span
                            class="ms-auto rounded px-1.5 py-0.5 text-xs font-bold"
                            :class="order.paid ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'"
                            x-text="order.paid ? '✅ مدفوع' : '⏳ غير مدفوع'"
                        ></span>
                    </div>

                    {{-- Customer info --}}
                    <div class="space-y-1.5 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-400">👤</span>
                            <span class="text-sm font-bold text-gray-800 dark:text-gray-200" x-text="order.customerName || '—'"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-400">📞</span>
                            <a class="text-sm text-blue-600 hover:underline" :href="'tel:' + order.customerPhone" x-text="order.customerPhone || '—'"></a>
                        </div>

                        {{-- Payment account info (for electronic payments) --}}
                        <template x-if="order.paidToAccount">
                            <div class="payment-account-info">
                                <span>💳</span>
                                <span>الدفع عبر حساب:</span>
                                <span class="font-bold" x-text="order.paidToAccount"></span>
                            </div>
                        </template>

                        {{-- Delivery area --}}
                        <template x-if="order.deliveryMethod === 'delivery' && order.area">
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-400">📍</span>
                                <span class="text-sm font-semibold" x-text="order.area"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Customer notes — only when the customer wrote something --}}
                    <template x-if="order.notes">
                        <div class="mb-3 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-950/30 dark:border-amber-700 px-2.5 py-2">
                            <div class="text-xs font-bold text-amber-700 dark:text-amber-400 mb-0.5">📝 ملاحظات الزبون</div>
                            <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line" x-text="order.notes"></div>
                        </div>
                    </template>

                    {{-- Driver info --}}
                    <template x-if="order.driver">
                        <div class="driver-info mb-3">
                            <div class="flex items-center gap-2 mb-1">
                                <span>🚗</span>
                                <span class="text-sm font-bold text-sky-700 dark:text-sky-300">في الطريق</span>
                            </div>
                            <div class="text-sm font-bold" x-text="'السائق: ' + order.driver.name"></div>
                            <template x-if="order.driver.company">
                                <div class="text-xs text-gray-500" x-text="'شركة التوصيل: ' + order.driver.company"></div>
                            </template>
                            <a class="text-sm text-blue-600 font-semibold hover:underline mt-0.5 inline-block"
                               :href="'tel:' + order.driver.phone"
                               x-text="'📞 ' + order.driver.phone"></a>
                        </div>
                    </template>

                    {{-- Table number for dine-in --}}
                    <template x-if="order.deliveryMethod === 'dine-in'">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-sm text-gray-400">🪑</span>
                            <template x-if="order.tableNumber">
                                <span class="text-sm font-bold" x-text="'طاولة ' + order.tableNumber"></span>
                            </template>
                            <template x-if="!order.tableNumber">
                                <input
                                    type="text"
                                    placeholder="رقم الطاولة"
                                    class="w-24 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-2 py-1"
                                    @keydown.enter="$wire.setTable(order.reference, $event.target.value); refresh()"
                                >
                            </template>
                        </div>
                    </template>

                    {{-- Totals row --}}
                    <div class="flex items-center justify-between mb-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex items-center gap-1.5 text-sm text-gray-500">
                            <span>🛒</span>
                            <span x-text="order.itemCount + ' صنف'"></span>
                        </div>
                        <div class="text-lg font-black text-gray-800 dark:text-gray-100" x-text="Number(order.total).toFixed(2) + ' ₪'"></div>
                    </div>

                    {{-- Print error callout --}}
                    <template x-if="order.printError">
                        <div class="text-xs text-rose-600 bg-rose-50 dark:bg-rose-900/20 rounded-lg px-2 py-1 mb-2" x-text="'⚠ ' + order.printError"></div>
                    </template>

                    {{-- Cash: what was handed over, and where the change goes --}}
                    <template x-if="!order.paid && order.paymentMethod === 'cash' && !order.final">
                        <div class="mb-3 p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm font-bold text-amber-700 dark:text-amber-400">💵 استلام نقدي</span>
                            </div>
                            <div class="flex items-center gap-2 mb-1.5">
                                <label class="text-xs text-gray-500 w-20 shrink-0">المبلغ المستلم</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :placeholder="Number(order.total).toFixed(2)"
                                    class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-2 py-1.5 font-bold"
                                    x-model.number="tendered[order.reference]"
                                    x-init="prefillTendered(order)"
                                    @input="calcChange(order)"
                                >
                                <span class="text-xs text-gray-500">₪</span>
                            </div>
                            <template x-if="getChange(order) > 0">
                                <div class="flex items-center justify-between mt-1.5 p-2 rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800">
                                    <span class="text-sm font-bold text-green-700 dark:text-green-400">الباقي:</span>
                                    <span class="text-lg font-black text-green-700 dark:text-green-400" x-text="getChange(order).toFixed(2) + ' ₪'"></span>
                                </div>
                            </template>
                            <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                                <template x-if="getChange(order) <= 0">
                                    <button class="btn-pay flex-1" @click="payCash(order)">💵 استلام</button>
                                </template>
                                <template x-if="getChange(order) > 0">
                                    <button class="btn-pay flex-1" @click="payCash(order)">👛 تحويل الباقي لمحفظة الزبون</button>
                                </template>
                                <template x-if="getChange(order) > 0">
                                    <button class="btn-refund flex-1" style="background:#d97706" @click="openRefundModal(order)">↩️ استلام + طلب استرداد</button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Visa unpaid --}}
                    <template x-if="!order.paid && order.paymentMethod === 'visa'">
                        <div class="mb-3">
                            <button class="btn-pay w-full" @click="$wire.markPaid(order.reference).then(() => refresh())">
                                💳 استلام دفع بطاقة
                            </button>
                        </div>
                    </template>

                    {{-- A transfer waiting for its receipt to be checked --}}
                    <template x-if="!order.paid && order.requiresReceipt && !order.final">
                        <div class="mb-3">
                            <button class="btn-pay w-full" style="background:#7c3aed" @click="openDetails(order)"
                                x-text="order.hasReceipt ? '📎 مراجعة إشعار الدفع وتأكيده' : '📎 بانتظار إشعار الدفع — عرض الطلب'"></button>
                        </div>
                    </template>

                    {{-- Change still owed on a paid cash order: the amount is computed --}}
                    <template x-if="order.paid && order.paymentMethod === 'cash' && order.refundableChange > 0">
                        <div class="mb-3">
                            <button class="btn-refund w-full justify-center" style="background:#d97706; padding:0.5rem 1rem; font-size:0.8rem;"
                                    @click="openStandaloneRefundModal(order)"
                                    x-text="'↩️ طلب استرداد الباقي ' + Number(order.refundableChange).toFixed(2) + ' ₪'"></button>
                        </div>
                    </template>

                    {{-- Time + actions --}}
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-1 text-xs text-gray-400">
                            <span>⏰</span>
                            <span x-text="timeAgo(order.createdAt)"></span>
                        </div>

                        <div class="flex items-center gap-1.5 flex-wrap">
                            {{-- Print: from a hidden frame, which a browser never blocks --}}
                            <button class="btn-print" @click="printOrder(order)" :disabled="printing[order.reference]">
                                <template x-if="printing[order.reference]">
                                    <span>⏳ جاري...</span>
                                </template>
                                <template x-if="!printing[order.reference]">
                                    <span>🖨️ <span x-text="order.printed ? 'إعادة' : 'طباعة'"></span></span>
                                </template>
                            </button>

                            {{-- Details: items, payment receipt, everything — without leaving --}}
                            <button class="btn-print" style="background:#475569" @click="openDetails(order)" title="تفاصيل الطلب">👁️</button>

                            {{-- Update status --}}
                            <template x-if="!order.final">
                                <button class="btn-update-status" @click="openStatusModal(order)">
                                    تحديث الحالة
                                </button>
                            </template>

                            {{-- Assign driver --}}
                            <template x-if="order.needsDriver && !order.final">
                                <button class="btn-assign-driver" @click="openDriverModal(order)">
                                    🚗 تعيين السائق
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ─── driver assignment modal ──────────────────────────────────── --}}
        <template x-if="modal.type === 'driver'">
            <div class="modal-overlay" @click.self="closeModal()">
                <div class="modal-content">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold">🚗 تعيين سائق</div>
                                <div class="text-sm text-gray-500" x-text="'الطلب: ' + modal.reference"></div>
                            </div>
                            <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                        </div>
                    </div>

                    <div class="p-5 space-y-2.5 max-h-[50vh] overflow-y-auto">
                        <template x-if="modal.drivers.length === 0">
                            <div class="text-center py-6 text-gray-500">
                                <div class="text-3xl mb-2">🚫</div>
                                <div class="font-bold">لا يوجد سائقون مفعّلون</div>
                                <button class="mt-3 modal-btn-confirm" style="background:#2563eb" @click="openCreateDriver()">➕ إضافة سائق</button>
                            </div>
                        </template>

                        <template x-for="driver in modal.drivers" :key="driver.id">
                            <div
                                class="driver-select-card"
                                :class="modal.selectedDriverId === driver.id ? 'selected' : ''"
                                @click="modal.selectedDriverId = driver.id"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-sm" x-text="driver.name"></div>
                                        <template x-if="driver.company">
                                            <div class="text-xs text-gray-500" x-text="driver.company"></div>
                                        </template>
                                        <div class="text-xs text-blue-600 mt-0.5" x-text="'📞 ' + driver.phone"></div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="w-2.5 h-2.5 rounded-full"
                                            :class="driver.busy ? 'bg-red-500' : 'bg-green-500'"
                                        ></span>
                                        <span
                                            class="text-xs font-bold"
                                            :class="driver.busy ? 'text-red-600' : 'text-green-600'"
                                            x-text="driver.status"
                                        ></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-2">
                        <button @click="openCreateDriver()" class="modal-btn-cancel me-auto">➕ سائق جديد</button>
                        <button @click="closeModal()" class="modal-btn-cancel">إلغاء</button>
                        <button
                            @click="confirmDriver()"
                            :disabled="!modal.selectedDriverId"
                            class="modal-btn-confirm"
                            :style="modal.selectedDriverId ? 'background:#2563eb' : 'background:#9ca3af'"
                        >✅ تأكيد التعيين</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- ─── status update modal ──────────────────────────────────────── --}}
        <template x-if="modal.type === 'status'">
            <div class="modal-overlay" @click.self="closeModal()">
                <div class="modal-content">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold">📋 تحديث حالة الطلب</div>
                                <div class="text-sm text-gray-500" x-text="'الطلب: ' + modal.reference"></div>
                            </div>
                            <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                        </div>
                    </div>

                    <div class="px-5 py-3">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-sm text-gray-500">الحالة الحالية:</span>
                            <span class="status-badge" :class="statusBadgeClassFromName(modal.currentStatus)" x-text="modal.currentStatus"></span>
                        </div>
                        <div class="text-sm font-bold mb-2">اختر الحالة الجديدة:</div>
                    </div>

                    <div class="px-5 pb-4 space-y-2 max-h-[55vh] overflow-y-auto">
                        <template x-for="option in modal.options" :key="option">
                            <div
                                class="status-option"
                                :class="modal.selectedStatus === option ? 'selected' : ''"
                                @click="modal.selectedStatus = option"
                            >
                                <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                                      :class="modal.selectedStatus === option ? 'border-blue-600' : 'border-gray-300'">
                                    <span x-show="modal.selectedStatus === option" class="w-2 h-2 rounded-full bg-blue-600"></span>
                                </span>
                                <span x-text="option"></span>
                            </div>
                        </template>

                        {{-- A delivery goes on the road only with a driver: choose one here --}}
                        <template x-if="modal.selectedStatus === 'في الطريق'">
                            <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-sm font-bold">🚗 اختر السائق</div>
                                    <button class="text-xs font-bold text-blue-600 hover:underline" @click="openCreateDriver()">➕ سائق جديد</button>
                                </div>
                                <template x-if="modal.drivers.length === 0">
                                    <div class="text-center py-3 text-sm text-gray-500">لا يوجد سائقون مفعّلون — أضف سائقاً</div>
                                </template>
                                <template x-for="driver in modal.drivers" :key="driver.id">
                                    <div class="driver-select-card mb-2"
                                         :class="modal.selectedDriverId === driver.id ? 'selected' : ''"
                                         @click="modal.selectedDriverId = driver.id">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="font-bold text-sm" x-text="driver.name"></div>
                                                <div class="text-xs text-gray-500" x-text="[driver.company, driver.phone].filter(Boolean).join(' — ')"></div>
                                            </div>
                                            <span class="text-xs font-bold" :class="driver.busy ? 'text-red-600' : 'text-green-600'" x-text="driver.status"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-2">
                        <button @click="closeModal()" class="modal-btn-cancel">إلغاء</button>
                        <button
                            @click="confirmStatus()"
                            :disabled="!canConfirmStatus()"
                            class="modal-btn-confirm"
                            :style="canConfirmStatus() ? 'background:#2563eb' : 'background:#9ca3af'"
                        >✅ تأكيد</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- ─── change refund request modal ───────────────────────────────── --}}
        <template x-if="modal.type === 'refund'">
            <div class="modal-overlay" @click.self="closeModal()">
                <div class="modal-content">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold">↩️ طلب استرداد الباقي</div>
                                <div class="text-sm text-gray-500" x-text="'الطلب: ' + modal.reference"></div>
                            </div>
                            <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                        </div>
                    </div>

                    <div class="p-5 space-y-3">
                        <div class="p-3 rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 text-center">
                            <div class="text-sm text-green-600">💰 مبلغ الباقي</div>
                            <div class="text-2xl font-black text-green-700" x-text="Number(modal.refundAmount).toFixed(2) + ' ₪'"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-1">👤 اسم صاحب الحساب</label>
                            <input type="text" x-model="modal.holderName"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-3 py-2">
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-1">📞 رقم جوال صاحب الحساب</label>
                            <input type="text" x-model="modal.holderPhone"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-3 py-2">
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-1">💳 وسيلة الاسترداد</label>
                            <div class="grid grid-cols-2 gap-2 mt-1">
                                <template x-for="method in refundMethods" :key="method.value">
                                    <div
                                        class="status-option text-sm"
                                        :class="modal.refundMethod === method.value ? 'selected' : ''"
                                        @click="modal.refundMethod = method.value"
                                    >
                                        <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                                              :class="modal.refundMethod === method.value ? 'border-blue-600' : 'border-gray-300'">
                                            <span x-show="modal.refundMethod === method.value" class="w-2 h-2 rounded-full bg-blue-600"></span>
                                        </span>
                                        <span x-text="method.icon + ' ' + method.label"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-1">📝 ملاحظات (اختياري)</label>
                            <textarea x-model="modal.refundNotes" rows="2"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-3 py-2"
                                placeholder="أي ملاحظات إضافية..."></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-2">
                        <button @click="closeModal()" class="modal-btn-cancel">إلغاء</button>
                        <button @click="confirmRefund()"
                            :disabled="!modal.refundMethod"
                            class="modal-btn-confirm"
                            :style="modal.refundMethod ? 'background:#d97706' : 'background:#9ca3af'"
                        >✅ استلام + إنشاء طلب الاسترداد</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- ─── standalone refund modal (for already-paid orders) ──────────── --}}
        <template x-if="modal.type === 'standalone-refund'">
            <div class="modal-overlay" @click.self="closeModal()">
                <div class="modal-content">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold">↩️ إنشاء طلب استرداد</div>
                                <div class="text-sm text-gray-500" x-text="'الطلب: ' + modal.reference + ' — الإجمالي: ' + Number(modal.orderTotal).toFixed(2) + ' ₪'"></div>
                            </div>
                            <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                        </div>
                    </div>

                    <div class="p-5 space-y-3">
                        <div class="p-3 rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 text-center">
                            <div class="text-sm text-green-600">💰 مبلغ الاسترداد — محسوب تلقائياً</div>
                            <div class="text-2xl font-black text-green-700" x-text="Number(modal.refundAmount).toFixed(2) + ' ₪'"></div>
                            <div class="text-xs text-gray-500 mt-1" x-text="'المستلم ' + Number(modal.tendered).toFixed(2) + ' − الإجمالي ' + Number(modal.orderTotal).toFixed(2)"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-1">👤 اسم صاحب الحساب</label>
                            <input type="text" x-model="modal.holderName"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-3 py-2">
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-1">📞 رقم جوال صاحب الحساب</label>
                            <input type="text" x-model="modal.holderPhone"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-3 py-2">
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-1">💳 وسيلة الاسترداد</label>
                            <div class="grid grid-cols-2 gap-2 mt-1">
                                <template x-for="method in refundMethods" :key="method.value">
                                    <div
                                        class="status-option text-sm"
                                        :class="modal.refundMethod === method.value ? 'selected' : ''"
                                        @click="modal.refundMethod = method.value"
                                    >
                                        <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center"
                                              :class="modal.refundMethod === method.value ? 'border-blue-600' : 'border-gray-300'">
                                            <span x-show="modal.refundMethod === method.value" class="w-2 h-2 rounded-full bg-blue-600"></span>
                                        </span>
                                        <span x-text="method.icon + ' ' + method.label"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold mb-1">📝 ملاحظات (اختياري)</label>
                            <textarea x-model="modal.refundNotes" rows="2"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-3 py-2"
                                placeholder="أي ملاحظات إضافية..."></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-2">
                        <button @click="closeModal()" class="modal-btn-cancel">إلغاء</button>
                        <button @click="confirmStandaloneRefund()"
                            :disabled="!modal.refundMethod || !modal.refundAmount"
                            class="modal-btn-confirm"
                            :style="(modal.refundMethod && modal.refundAmount) ? 'background:#d97706' : 'background:#9ca3af'"
                        >✅ إنشاء طلب الاسترداد</button>
                    </div>
                </div>
            </div>
        </template>

        {{-- ─── order details window ─────────────────────────────────────── --}}
        <template x-if="details">
            <div class="modal-overlay" @click.self="details = null">
                <div class="modal-content" style="max-width: 42rem;">
                    <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-lg font-bold" x-text="'👁️ الطلب ' + details.reference"></div>
                                <div class="text-sm text-gray-500" x-text="details.createdAt + ' — ' + details.status"></div>
                            </div>
                            <button @click="details = null" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                        </div>
                    </div>

                    <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto text-sm">
                        <div class="grid grid-cols-2 gap-2">
                            <div><span class="text-gray-500">الزبون: </span><span class="font-bold" x-text="details.customerName || '—'"></span></div>
                            <div><span class="text-gray-500">الهاتف: </span><a class="text-blue-600" :href="'tel:' + details.customerPhone" x-text="details.customerPhone || '—'"></a></div>
                            <div><span class="text-gray-500">الاستلام: </span><span class="font-bold" x-text="kindLabel(details)"></span></div>
                            <template x-if="details.scheduledFor">
                                <div><span class="text-gray-500">الموعد: </span><span x-text="details.scheduledFor"></span></div>
                            </template>
                        </div>

                        <template x-if="details.address && details.deliveryMethod === 'delivery'">
                            <div><span class="text-gray-500">📍 العنوان: </span><span x-text="addressText(details.address)"></span></div>
                        </template>

                        <template x-if="details.notes">
                            <div class="rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-950/30 px-3 py-2">
                                <div class="text-xs font-bold text-amber-700 mb-0.5">📝 ملاحظات الزبون</div>
                                <div class="whitespace-pre-line" x-text="details.notes"></div>
                            </div>
                        </template>

                        <div>
                            <div class="font-bold mb-1">🛒 الأصناف</div>
                            <template x-for="(item, i) in details.items" :key="i">
                                <div class="py-1.5 border-b border-gray-100 dark:border-gray-800">
                                    <div class="flex justify-between">
                                        <span class="font-semibold" x-text="item.qty + ' × ' + item.name"></span>
                                        <span class="font-bold" x-text="money(item.total)"></span>
                                    </div>
                                    <div class="text-xs text-gray-500" x-text="'سعر الوحدة ' + money(item.unit) + (item.description ? ' — ' + item.description : '')"></div>
                                </div>
                            </template>
                        </div>

                        <div class="space-y-1">
                            <div class="flex justify-between"><span class="text-gray-500">المجموع</span><span x-text="money(details.subtotal)"></span></div>
                            <template x-if="details.discount > 0">
                                <div class="flex justify-between"><span class="text-gray-500" x-text="'الخصم' + (details.couponCode ? ' (' + details.couponCode + ')' : '')"></span><span x-text="'- ' + money(details.discount)"></span></div>
                            </template>
                            <template x-if="details.deliveryFee > 0">
                                <div class="flex justify-between"><span class="text-gray-500">رسوم التوصيل</span><span x-text="money(details.deliveryFee)"></span></div>
                            </template>
                            <div class="flex justify-between text-base font-black"><span>الإجمالي</span><span x-text="money(details.total)"></span></div>
                        </div>

                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 space-y-1">
                            <div class="flex justify-between"><span class="text-gray-500">طريقة الدفع</span><span class="font-bold" x-text="paymentLabel(details.paymentMethod)"></span></div>
                            <template x-if="details.paidToAccount">
                                <div class="flex justify-between"><span class="text-gray-500">إلى حساب</span><span class="font-bold" x-text="details.paidToAccount"></span></div>
                            </template>
                            <div class="flex justify-between">
                                <span class="text-gray-500">الحالة</span>
                                <span class="font-bold" :class="details.paid ? 'text-emerald-600' : 'text-rose-600'"
                                      x-text="details.paid ? ('✅ مدفوع' + (details.paidAt ? ' — ' + details.paidAt : '') + (details.paidBy ? ' — ' + details.paidBy : '')) : '⏳ غير مدفوع'"></span>
                            </div>
                            <template x-if="details.tenderedAmount">
                                <div class="flex justify-between"><span class="text-gray-500">المستلم نقداً</span><span x-text="money(details.tenderedAmount)"></span></div>
                            </template>
                            <template x-if="details.changeCredited > 0">
                                <div class="flex justify-between"><span class="text-gray-500">باقٍ إلى المحفظة</span><span x-text="money(details.changeCredited)"></span></div>
                            </template>
                            <template x-if="details.refundableChange > 0">
                                <div class="flex justify-between"><span class="text-gray-500">باقٍ لم يُعَد بعد</span><span class="font-bold text-amber-600" x-text="money(details.refundableChange)"></span></div>
                            </template>
                        </div>

                        <template x-if="details.receiptImage || details.receiptNote">
                            <div>
                                <div class="font-bold mb-1">📎 إشعار الدفع</div>
                                <template x-if="details.receiptImage">
                                    <a :href="details.receiptImage" target="_blank" rel="noopener">
                                        <img :src="details.receiptImage" alt="إشعار الدفع" class="rounded-lg border max-h-80 mx-auto">
                                    </a>
                                </template>
                                <template x-if="details.receiptNote">
                                    <div class="mt-2 text-gray-700 dark:text-gray-300 whitespace-pre-line" x-text="details.receiptNote"></div>
                                </template>
                            </div>
                        </template>
                        <template x-if="details.requiresReceipt && !details.receiptImage && !details.receiptNote">
                            <div class="text-rose-600 font-bold">لم يرفع الزبون إشعار دفع بعد.</div>
                        </template>

                        <template x-if="details.refunds.length > 0">
                            <div>
                                <div class="font-bold mb-1">↩️ طلبات استرداد الباقي</div>
                                <template x-for="(r, i) in details.refunds" :key="i">
                                    <div class="flex justify-between items-center py-1 gap-2">
                                        <span x-text="money(r.amount) + ' — ' + r.method"></span>
                                        <span class="text-xs font-bold" x-text="({pending: 'بانتظار التحويل', completed: 'تم التحويل', rejected: 'مرفوض'})[r.status] || r.status"></span>
                                        <template x-if="r.receipt">
                                            <a class="text-xs text-blue-600" :href="r.receipt" target="_blank" rel="noopener">الإشعار</a>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="details.driver">
                            <div><span class="text-gray-500">🚗 السائق: </span><span class="font-bold" x-text="[details.driver.name, details.driver.phone].filter(Boolean).join(' — ')"></span></div>
                        </template>
                    </div>

                    <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-2">
                        <button @click="details = null" class="modal-btn-cancel">إغلاق</button>
                        <button @click="printOrder({ reference: details.reference })" class="modal-btn-confirm" style="background:#475569">🖨️ طباعة</button>
                        <template x-if="details.canConfirmTransfer">
                            <button @click="confirmTransfer()" class="modal-btn-confirm" style="background:#16a34a">✅ تأكيد الدفع</button>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        {{-- ─── change refunds waiting for their transfer ─────────────────── --}}
        <div class="mt-6" x-show="pendingRefundsList.length > 0">
            <div class="filter-section">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">↩️</span>
                        <span class="text-base font-bold">طلبات استرداد بانتظار التحويل</span>
                        <span class="rounded-full bg-amber-100 text-amber-800 px-2 text-xs font-bold" x-text="pendingRefundsList.length"></span>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="r in pendingRefundsList" :key="r.id">
                        <div class="rounded-xl border-2 border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-950/20 p-3">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-black text-sm" x-text="r.reference"></span>
                                <span class="text-lg font-black text-amber-700" x-text="money(r.amount)"></span>
                            </div>
                            <div class="text-sm font-bold" x-text="r.holderName"></div>
                            <div class="text-xs text-gray-500" x-text="[r.holderPhone, r.method, r.at].filter(Boolean).join(' — ')"></div>
                            <template x-if="r.notes">
                                <div class="text-xs text-gray-600 mt-1" x-text="r.notes"></div>
                            </template>
                            <button class="btn-pay w-full mt-2" @click="completeRefund(r.id)">📎 رفع الإشعار وتأكيد التحويل</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ─── drivers and what they are owed ────────────────────────────── --}}
        <div class="mt-6">
            <div class="filter-section">
                <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🚗</span>
                        <span class="text-base font-bold">السائقون وأرصدتهم</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <button @click="openCreateDriver()" class="text-xs font-bold text-blue-600 hover:underline">➕ سائق جديد</button>
                        <button @click="loadPanels()" class="text-xs text-blue-600 hover:underline">🔄 تحديث</button>
                    </div>
                </div>

                <template x-if="driverData.length === 0">
                    <div class="text-center py-4 text-sm text-gray-500">لا يوجد سائقون بعد — أضف أول سائق من «➕ سائق جديد».</div>
                </template>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <template x-for="d in driverData" :key="d.id">
                        <div class="rounded-xl border-2 border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <div class="font-bold text-sm" x-text="d.name"></div>
                                    <div class="text-xs text-gray-500" x-text="[d.company, d.phone].filter(Boolean).join(' — ')"></div>
                                </div>
                                <span class="text-xs font-bold" :class="d.busy ? 'text-red-600' : 'text-green-600'" x-text="d.busy ? 'في توصيل' : 'متاح'"></span>
                            </div>
                            <div class="flex items-center justify-between p-2 rounded-lg bg-white dark:bg-gray-800 mb-2">
                                <span class="text-xs text-gray-500">الرصيد المستحق (رسوم التوصيل)</span>
                                <span class="text-lg font-black" :class="d.balance > 0 ? 'text-amber-600' : 'text-gray-400'" x-text="money(d.balance)"></span>
                            </div>
                            <template x-if="d.orders.length > 0">
                                <details class="text-xs mb-2">
                                    <summary class="cursor-pointer text-blue-600 hover:underline" x-text="'📋 ' + d.orders.length + ' توصيلة غير محوّلة'"></summary>
                                    <div class="mt-1 space-y-1">
                                        <template x-for="o in d.orders" :key="o.reference">
                                            <div class="flex items-center justify-between p-1.5 rounded bg-white dark:bg-gray-800 gap-2">
                                                <span class="font-bold" x-text="o.reference"></span>
                                                <span x-text="o.at"></span>
                                                <span x-text="o.delivered ? '✅ وصل' : '🚗 في الطريق'"></span>
                                                <span class="font-bold" x-text="money(o.fee)"></span>
                                            </div>
                                        </template>
                                    </div>
                                </details>
                            </template>
                            <template x-if="d.balance > 0">
                                <button class="btn-pay w-full" @click="payDriver(d.id)">💸 رفع الإشعار — تم التحويل</button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function cashierBoard(config) {
            return {
                orders: [],
                search: '',
                sortOrder: 'newest',
                filters: { status: 'all', channel: 'all', payment: 'all' },

                // Cash tendered tracking per order
                tendered: {},
                changeCalc: {},
                printing: {},

                // Drivers and what each is owed
                driverData: [],

                // Change refunds waiting for their transfer
                pendingRefundsList: [],

                // Order details window
                details: null,

                // Live connection state: a silent poll failure is how a board
                // looks up to date while it is not.
                connection: { ok: true, error: null, lastOk: null },
                loading: false,
                clock: Date.now(),

                // Shift strip, from the same poll as the cards
                shiftOpen: true,
                summary: null,
                waiting: 0,

                // Orders already seen, so a new one can be announced
                knownRefs: null,
                freshRefs: {},

                // Receipts print one at a time: a browser runs a single print
                // job, and any other request made while one is running is
                // dropped without a word — which is why a second receipt did
                // not come out.
                printQueue: [],
                printBusy: false,

                // Set when a print took long enough that somebody must have
                // clicked through a print dialog, so the screen can explain
                // how to turn the dialog off.
                previewDetected: false,
                previewDismissed: false,

                // Refund methods for the modal
                refundMethods: [
                    { value: 'jawwal', label: 'جوال بي', icon: '📱' },
                    { value: 'bop',    label: 'بنك فلسطين', icon: '🏦' },
                    { value: 'palpay', label: 'بال بي', icon: '💳' },
                    { value: 'cash',   label: 'نقداً', icon: '💵' },
                ],

                // Modal state
                modal: { type: null, reference: null, options: [], drivers: [], selectedDriverId: null, selectedStatus: null, currentStatus: null, refundAmount: 0, holderName: '', holderPhone: '', refundMethod: null, refundNotes: '' },

                // Status groups for filtering — the counter thinks in broad
                // buckets, not in the full vocabulary.
                statusGroups: {
                    new:       ['قيد المراجعة'],
                    preparing: ['جاري التحضير'],
                    ready:     ['جاهز للاستلام'],
                    onway:     ['في الطريق'],
                    done:      ['تم التسليم', 'تم الاستلام'],
                    closed:    ['ملغي', 'مسترد'],
                },

                filterGroups: [
                    { key: 'status', label: 'حالة الطلب', options: [
                        { value: 'all',       label: 'كل الطلبات', icon: '📋' },
                        { value: 'new',       label: 'جديد',          icon: '➕' },
                        { value: 'preparing', label: 'جاري التحضير',  icon: '🔄' },
                        { value: 'ready',     label: 'جاهز للاستلام', icon: '✅' },
                        { value: 'onway',     label: 'في الطريق',     icon: '🚗' },
                        { value: 'done',      label: 'مكتمل',      icon: '✔️' },
                        { value: 'closed',    label: 'ملغي / مسترد', icon: '❌' },
                    ]},
                    { key: 'channel', label: 'طريقة الاستلام', options: [
                        { value: 'all',      label: 'الكل',            icon: '∞' },
                        { value: 'dine-in',  label: 'تناول الآن',      icon: '🍦' },
                        { value: 'pickup',   label: 'استلام من المحل', icon: '🏪' },
                        { value: 'delivery', label: 'توصيل',           icon: '🚗' },
                    ]},
                    { key: 'payment', label: 'طريقة الدفع', options: [
                        { value: 'all',           label: 'الكل',               icon: '∞' },
                        { value: 'cash',          label: 'كاش',               icon: '💵' },
                        { value: 'jawwal',        label: 'جوال بي',           icon: '📱' },
                        { value: 'bop',           label: 'بنك فلسطين',        icon: '🏦' },
                        { value: 'palpay',        label: 'بال بي',            icon: '💳' },
                        { value: 'visa',          label: 'فيزا',              icon: '💳' },
                        { value: 'jawwal-manual', label: 'جوال بي (تحويل)',   icon: '📱' },
                        { value: 'wallet',        label: 'المحفظة',           icon: '👛' },
                    ]},
                ],

                poll: config.poll,
                width: config.width,
                networkPrinter: config.networkPrinter,
                printed: new Set(),
                timer: null,

                start() {
                    this.refresh();
                    this.loadPanels();
                    this.timer = setInterval(() => this.refresh(), Math.max(2, this.poll) * 1000);
                    setInterval(() => this.loadPanels(), 20000);
                    setInterval(() => { this.clock = Date.now(); }, 1000);
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) { this.refresh(); this.loadPanels(); }
                    });
                },

                // Fired by the server after a change made from a window on this
                // screen: a driver added, a refund paid, a shift opened or closed.
                onServerRefresh() {
                    this.refresh();
                    this.loadPanels();
                    if (this.modal.type === 'status' || this.modal.type === 'driver') {
                        this.$wire.drivers().then(list => { this.modal.drivers = list; });
                    }
                },

                async refresh() {
                    if (this.loading) return;
                    this.loading = true;
                    try {
                        const res = await fetch(config.queueUrl, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            cache: 'no-store',
                        });
                        const type = res.headers.get('content-type') || '';

                        // An expired login answers with the login page, not an
                        // error — so anything that is not JSON is treated as one.
                        if (res.status === 401 || res.status === 419 || res.redirected || !type.includes('application/json')) {
                            this.connection = { ok: false, lastOk: this.connection.lastOk, error: 'انتهت جلسة الدخول — أعد تحميل الصفحة وسجّل الدخول.' };
                            return;
                        }
                        if (!res.ok) {
                            this.connection = { ok: false, lastOk: this.connection.lastOk, error: 'الخادم لا يستجيب (' + res.status + ') — تُعاد المحاولة تلقائياً.' };
                            return;
                        }

                        const data = await res.json();
                        this.shiftOpen = data.shiftOpen !== false;
                        this.summary = data.summary || null;
                        this.waiting = data.waiting || 0;
                        this.networkPrinter = !!data.networkPrinter;
                        this.announceNew(data.orders || []);
                        this.orders = data.orders || [];
                        this.connection = { ok: true, error: null, lastOk: Date.now() };
                    } catch (e) {
                        this.connection = { ok: false, lastOk: this.connection.lastOk, error: 'انقطع الاتصال بالخادم — تُعاد المحاولة تلقائياً.' };
                    } finally {
                        this.loading = false;
                    }
                },

                secondsSince() {
                    if (!this.connection.lastOk) return '—';
                    return Math.max(0, Math.round((this.clock - this.connection.lastOk) / 1000));
                },

                // A new order gets a short tone and a highlight, so it is noticed
                // while the cashier is looking at the customer, not the screen.
                announceNew(list) {
                    const refs = list.map(o => o.reference);
                    if (this.knownRefs === null) { this.knownRefs = new Set(refs); return; }
                    const fresh = refs.filter(r => !this.knownRefs.has(r));
                    fresh.forEach(r => {
                        this.knownRefs.add(r);
                        this.freshRefs[r] = true;
                        setTimeout(() => { delete this.freshRefs[r]; }, 15000);
                    });
                    if (fresh.length) this.beep();
                },

                beep() {
                    try {
                        const ctx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.frequency.value = 880;
                        gain.gain.value = 0.15;
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start();
                        setTimeout(() => { osc.stop(); ctx.close(); }, 250);
                    } catch (e) { /* no sound is not worth an error */ }
                },

                money(value) {
                    return Number(value || 0).toFixed(2) + ' ₪';
                },

                // ─── filtering ──────────────────────────────────────────────

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
                    const digits = term.replace(/\D/g, '');
                    const phone  = (order.customerPhone || '').replace(/\D/g, '');
                    return (order.reference || '').toLowerCase().includes(term)
                        || (order.customerName || '').toLowerCase().includes(term)
                        || (digits.length > 0 && phone.includes(digits));
                },

                visible() {
                    return this.orders.filter(o => this.matches(o, null));
                },

                sorted() {
                    const list = this.visible();
                    switch (this.sortOrder) {
                        case 'oldest':
                            return [...list].sort((a, b) => new Date(a.createdAt) - new Date(b.createdAt));
                        case 'total_desc':
                            return [...list].sort((a, b) => b.total - a.total);
                        case 'total_asc':
                            return [...list].sort((a, b) => a.total - b.total);
                        default: // newest — already sorted by the API
                            return list;
                    }
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

                statusFilterLabel() {
                    const found = this.filterGroups[0].options.find(o => o.value === this.filters.status);
                    return found ? found.label : '';
                },
                channelFilterLabel() {
                    const found = this.filterGroups[1].options.find(o => o.value === this.filters.channel);
                    return found ? found.label : '';
                },
                paymentFilterLabel() {
                    const found = this.filterGroups[2].options.find(o => o.value === this.filters.payment);
                    return found ? found.label : '';
                },

                // ─── printing ───────────────────────────────────────────────

                // Queue a receipt. Printed from a hidden frame on this page —
                // never a new window, which a browser blocks when it is not
                // opened straight from a click.
                print(order, auto) {
                    this.printed.add(order.reference);
                    this.printing[order.reference] = true;
                    this.printQueue.push(order.reference);
                    this.pumpPrintQueue();
                },

                async pumpPrintQueue() {
                    if (this.printBusy) return;

                    const reference = this.printQueue.shift();
                    if (!reference) return;

                    this.printBusy = true;
                    const result = await this.printInFrame(reference);
                    this.printBusy = false;
                    this.printing[reference] = false;

                    if (result.outcome === 'printed' && result.duration > 2500) {
                        this.previewDetected = true;
                    }

                    this.notifyPrint(reference, result.outcome);
                    this.refresh();
                    this.pumpPrintQueue();
                },

                /**
                 * Print one receipt and wait for it to finish.
                 *
                 * Resolves 'printed' once the receipt says the job went to the
                 * printer, 'failed' when the receipt never rendered (a server
                 * error, an expired login), and 'timeout' when printing began
                 * but no confirmation ever came back.
                 */
                printInFrame(reference) {
                    return new Promise(resolve => {
                        const url = config.printUrl + '/' + encodeURIComponent(reference)
                            + '?width=' + this.width + '&auto=1';
                        const frame = document.createElement('iframe');
                        frame.setAttribute('aria-hidden', 'true');
                        frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:420px;height:800px;border:0;';

                        let settled = false;
                        let startedAt = null;
                        let timer = null;

                        const finish = (outcome) => {
                            if (settled) return;
                            settled = true;
                            window.removeEventListener('message', onMessage);
                            clearTimeout(timer);
                            setTimeout(() => frame.remove(), 1000);
                            resolve({ outcome: outcome, duration: startedAt ? Date.now() - startedAt : 0 });
                        };

                        const onMessage = (event) => {
                            if (event.origin !== window.location.origin) return;
                            if (!event.data || event.data.receipt !== reference) return;
                            if (event.data.state === 'printing') startedAt = Date.now();
                            if (event.data.state === 'printed') finish('printed');
                        };

                        window.addEventListener('message', onMessage);

                        // A receipt that did not render never reports in: catch
                        // that a few seconds after the frame loads.
                        frame.addEventListener('load', () => {
                            setTimeout(() => { if (!startedAt) finish('failed'); }, 3000);
                        });

                        // Long enough for a person to finish with a print
                        // dialog, so a slow click is not reported as a failure.
                        timer = setTimeout(() => finish(startedAt ? 'timeout' : 'failed'), 120000);

                        frame.src = url;
                        document.body.appendChild(frame);
                    });
                },

                notifyPrint(reference, outcome) {
                    const notices = {
                        printed: ['✅ تمت الطباعة', 'أُرسلت فاتورة الطلب ' + reference + ' إلى الطابعة', 'success'],
                        failed:  ['❌ فشلت الطباعة', 'تعذّر تجهيز فاتورة الطلب ' + reference + ' — أعد المحاولة', 'danger'],
                        timeout: ['⚠️ لم تُؤكَّد الطباعة', 'لم يصل تأكيد طباعة الطلب ' + reference + ' — تحقق من الطابعة', 'warning'],
                    };
                    const [title, body, status] = notices[outcome] || notices.failed;
                    this.alert(title, body, status);
                },

                // Shown straight from the page, so a notice appears at once even
                // while another request to the server is still running.
                alert(title, body, status) {
                    if (window.FilamentNotification) {
                        const notice = new window.FilamentNotification().title(title).body(body);
                        if (status === 'success') notice.success();
                        else if (status === 'warning') notice.warning();
                        else notice.danger();
                        notice.send();
                        return;
                    }
                    this.$wire.sendPrintAlert(title, body, status);
                },

                async printOrder(order) {
                    if (!this.networkPrinter) {
                        this.print(order, false);
                        return;
                    }

                    this.printing[order.reference] = true;
                    try {
                        const result = await this.$wire.printDirect(order.reference);
                        if (result.success) {
                            this.printed.add(order.reference);
                            this.alert('✅ تمت الطباعة', 'أُرسلت فاتورة الطلب ' + order.reference + ' إلى الطابعة الشبكية', 'success');
                        } else {
                            this.alert('⚠️ الطابعة الشبكية لم تستجب', (result.error ? result.error + ' — ' : '') + 'تتم الطباعة من المتصفح', 'warning');
                            this.print(order, false);
                        }
                    } catch (e) {
                        this.alert('⚠️ الطابعة الشبكية لم تستجب', 'تتم الطباعة من المتصفح', 'warning');
                        this.print(order, false);
                    } finally {
                        if (this.printQueue.indexOf(order.reference) === -1 && !this.printBusy) {
                            this.printing[order.reference] = false;
                        }
                        this.refresh();
                    }
                },

                // ─── modals ─────────────────────────────────────────────────

                async openStatusModal(order) {
                    const options = await this.$wire.nextStatuses(order.reference);
                    if (!options.length) return;
                    const drivers = order.deliveryMethod === 'delivery' ? await this.$wire.drivers() : [];
                    this.modal = {
                        type: 'status',
                        reference: order.reference,
                        deliveryMethod: order.deliveryMethod,
                        currentStatus: order.status,
                        options: options,
                        selectedStatus: null,
                        drivers: drivers,
                        selectedDriverId: null,
                    };
                },

                canConfirmStatus() {
                    if (!this.modal.selectedStatus) return false;
                    if (this.modal.selectedStatus === 'في الطريق') return !!this.modal.selectedDriverId;
                    return true;
                },

                async confirmStatus() {
                    if (!this.canConfirmStatus()) return;
                    if (this.modal.selectedStatus === 'في الطريق') {
                        // Choosing the driver is what puts the order on the road.
                        await this.$wire.assignDriver(this.modal.reference, this.modal.selectedDriverId);
                    } else {
                        await this.$wire.advance(this.modal.reference, this.modal.selectedStatus);
                    }
                    this.closeModal();
                    this.refresh();
                    this.loadPanels();
                },

                openCreateDriver() {
                    this.$wire.mountAction('createDriver');
                },

                async openDriverModal(order) {
                    const drivers = await this.$wire.drivers();
                    this.modal = {
                        type: 'driver',
                        reference: order.reference,
                        currentStatus: order.status,
                        options: [],
                        drivers: drivers,
                        selectedDriverId: null,
                        selectedStatus: null,
                    };
                },

                async confirmDriver() {
                    if (!this.modal.selectedDriverId) return;
                    await this.$wire.assignDriver(this.modal.reference, this.modal.selectedDriverId);
                    this.closeModal();
                    this.refresh();
                },

                closeModal() {
                    this.modal = { type: null, reference: null, options: [], drivers: [], selectedDriverId: null, selectedStatus: null, currentStatus: null, refundAmount: 0, holderName: '', holderPhone: '', refundMethod: null, refundNotes: '' };
                },

                // ─── cash change & refund ────────────────────────────────────

                calcChange(order) {
                    const t = parseFloat(this.tendered[order.reference]) || 0;
                    this.changeCalc[order.reference] = Math.max(0, +(t - order.total).toFixed(2));
                },

                getChange(order) {
                    return this.changeCalc[order.reference] || 0;
                },

                // Pre-fill what the customer declared in the app, if anything.
                prefillTendered(order) {
                    if (this.tendered[order.reference] === undefined && order.tenderedAmount) {
                        this.tendered[order.reference] = Number(order.tenderedAmount);
                        this.calcChange(order);
                    }
                },

                // Take the cash; anything above the total goes to the wallet.
                async payCash(order) {
                    const t = parseFloat(this.tendered[order.reference]) || 0;
                    await this.$wire.markPaidToWallet(order.reference, t);
                    delete this.tendered[order.reference];
                    delete this.changeCalc[order.reference];
                    this.refresh();
                },

                openRefundModal(order) {
                    const change = this.getChange(order);
                    this.modal = {
                        type: 'refund',
                        reference: order.reference,
                        refundAmount: change,
                        holderName: order.customerName || '',
                        holderPhone: order.customerPhone || '',
                        refundMethod: null,
                        refundNotes: '',
                        options: [], drivers: [], selectedDriverId: null, selectedStatus: null, currentStatus: null,
                    };
                },

                async confirmRefund() {
                    if (!this.modal.refundMethod) return;
                    const tendered = parseFloat(this.tendered[this.modal.reference]) || 0;
                    await this.$wire.markPaidWithChange(
                        this.modal.reference,
                        tendered,
                        {
                            holder_name: this.modal.holderName,
                            holder_phone: this.modal.holderPhone,
                            refund_method: this.modal.refundMethod,
                            notes: this.modal.refundNotes,
                        }
                    );
                    this.closeModal();
                    this.refresh();
                },

                // Change still owed on a paid cash order. The amount comes from the
                // server's own figures and cannot be edited here.
                openStandaloneRefundModal(order) {
                    this.modal = {
                        type: 'standalone-refund',
                        reference: order.reference,
                        refundAmount: Number(order.refundableChange) || 0,
                        tendered: Number(order.tenderedAmount) || 0,
                        holderName: order.customerName || '',
                        holderPhone: order.customerPhone || '',
                        refundMethod: null,
                        refundNotes: '',
                        orderTotal: order.total,
                        options: [], drivers: [], selectedDriverId: null, selectedStatus: null, currentStatus: null,
                    };
                },

                async confirmStandaloneRefund() {
                    if (!this.modal.refundMethod || !this.modal.refundAmount) return;
                    await this.$wire.createStandaloneRefund(
                        this.modal.reference,
                        {
                            holder_name: this.modal.holderName,
                            holder_phone: this.modal.holderPhone,
                            refund_method: this.modal.refundMethod,
                            notes: this.modal.refundNotes,
                        }
                    );
                    this.closeModal();
                    this.refresh();
                    this.loadPanels();
                },

                // ─── panels & windows ───────────────────────────────────────

                async loadPanels() {
                    try {
                        this.driverData = await this.$wire.driverBalances();
                        this.pendingRefundsList = await this.$wire.pendingRefunds();
                    } catch (e) { /* the next tick retries */ }
                },

                async openDetails(order) {
                    this.details = await this.$wire.orderDetails(order.reference);
                },

                async confirmTransfer() {
                    if (!this.details) return;
                    await this.$wire.confirmTransferPayment(this.details.reference);
                    this.details = await this.$wire.orderDetails(this.details.reference);
                    this.refresh();
                },

                completeRefund(id) {
                    this.$wire.mountAction('completeRefund', { id: id });
                },

                payDriver(id) {
                    this.$wire.mountAction('payDriver', { driver: id });
                },

                addressText(address) {
                    if (!address) return '';
                    return [address.city, address.area, address.street, address.landmark].filter(Boolean).join('، ');
                },

                // ─── labels & styling helpers ───────────────────────────────

                kindLabel(order) {
                    return {
                        'dine-in':  'تناول الآن' + (order.tableNumber ? ' · طاولة ' + order.tableNumber : ''),
                        'pickup':   'استلام من المحل',
                        'delivery': 'توصيل',
                    }[order.deliveryMethod] || order.deliveryMethod;
                },

                paymentLabel(method) {
                    return {
                        'cash': 'كاش', 'visa': 'فيزا', 'wallet': 'محفظة',
                        'jawwal': 'جوال بي', 'jawwal-manual': 'جوال بي (تحويل)',
                        'bop': 'بنك فلسطين', 'palpay': 'بال بي',
                    }[method] || method;
                },

                channelIcon(order) {
                    return { 'dine-in': '🍦', 'pickup': '🏪', 'delivery': '🚗' }[order.deliveryMethod] || '';
                },

                paymentIcon(method) {
                    return {
                        'cash': '💵', 'visa': '💳', 'wallet': '👛',
                        'jawwal': '📱', 'jawwal-manual': '📱',
                        'bop': '🏦', 'palpay': '💳',
                    }[method] || '💰';
                },

                statusIcon(order) {
                    const s = order.status;
                    if (s === 'قيد المراجعة')   return '➕';
                    if (s === 'جاري التحضير')   return '🔄';
                    if (s === 'جاهز للاستلام')  return '✅';
                    if (s === 'في الطريق')      return '🚗';
                    if (s === 'تم التسليم')     return '✔️';
                    if (s === 'تم الاستلام')    return '✔️';
                    if (s === 'ملغي')           return '❌';
                    if (s === 'مسترد')          return '↩️';
                    return '';
                },

                statusBadgeClass(order) {
                    const s = order.status;
                    if (s === 'قيد المراجعة')   return 'badge-new';
                    if (s === 'جاري التحضير')   return 'badge-preparing';
                    if (s === 'جاهز للاستلام')  return 'badge-ready';
                    if (s === 'في الطريق')      return 'badge-on-way';
                    if (s === 'تم التسليم' || s === 'تم الاستلام') return 'badge-done';
                    return 'badge-cancelled';
                },

                statusBadgeClassFromName(name) {
                    if (name === 'قيد المراجعة')   return 'badge-new';
                    if (name === 'جاري التحضير')   return 'badge-preparing';
                    if (name === 'جاهز للاستلام')  return 'badge-ready';
                    if (name === 'في الطريق')      return 'badge-on-way';
                    if (name === 'تم التسليم' || name === 'تم الاستلام') return 'badge-done';
                    return 'badge-cancelled';
                },

                channelTagClass(order) {
                    return {
                        'dine-in':  'tag-dine-in',
                        'pickup':   'tag-pickup',
                        'delivery': 'tag-delivery',
                    }[order.deliveryMethod] || '';
                },

                paymentTagClass(order) {
                    return {
                        'cash': 'tag-cash', 'visa': 'tag-visa', 'wallet': 'tag-wallet',
                        'jawwal': 'tag-jawwal', 'jawwal-manual': 'tag-jawwal-manual',
                        'bop': 'tag-bop', 'palpay': 'tag-palpay',
                    }[order.paymentMethod] || '';
                },

                timeAgo(iso) {
                    if (!iso) return '';
                    const minutes = Math.floor((Date.now() - new Date(iso)) / 60000);
                    if (minutes < 1)  return 'الآن';
                    if (minutes < 60) return 'منذ ' + minutes + ' دقيقة';
                    const hours = Math.floor(minutes / 60);
                    const rem   = minutes % 60;
                    if (rem === 0) return 'منذ ' + hours + ' ساعة';
                    return 'منذ ' + hours + ' ساعة و ' + rem + ' دقيقة';
                },
            };
        }
    </script>
    @endpush

</x-filament-panels::page>
