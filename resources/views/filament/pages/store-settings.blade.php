<x-filament-panels::page>
    @php
        $canManage = $this->canManage();
        $scopes = [
            'store'    => ['title' => '🏪 المتجر',  'hint' => 'متى يقبل المتجر الطلبات.'],
            'delivery' => ['title' => '🚗 التوصيل', 'hint' => 'متى تُقبل طلبات التوصيل. لا يعمل التوصيل والمتجر مغلق.'],
        ];
    @endphp

    <style>
        .hours-card { border-radius: .75rem; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.05); border: 1px solid rgba(0,0,0,.06); padding: 1.25rem; }
        .dark .hours-card { background: #111827; border-color: rgba(255,255,255,.1); }
        .hours-status { border-radius: .6rem; padding: .85rem 1rem; border: 2px solid; }
        .hours-status.is-open { background: #f0fdf4; border-color: #86efac; color: #166534; }
        .hours-status.is-closed { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
        .dark .hours-status.is-open { background: rgba(22,101,52,.2); color: #86efac; }
        .dark .hours-status.is-closed { background: rgba(153,27,27,.2); color: #fca5a5; }
        .hours-btn { display: inline-flex; align-items: center; gap: .35rem; padding: .5rem .9rem; border-radius: .5rem; font-weight: 700; font-size: .85rem; color: #fff; cursor: pointer; }
        .hours-btn:disabled { opacity: .45; cursor: not-allowed; }
        .hours-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .hours-table td, .hours-table th { padding: .45rem .5rem; border-bottom: 1px solid rgba(0,0,0,.06); text-align: start; }
        .dark .hours-table td, .dark .hours-table th { border-color: rgba(255,255,255,.08); }
        .hours-input { border-radius: .5rem; border: 1px solid #d1d5db; padding: .3rem .5rem; font-size: .9rem; background: #fff; }
        .dark .hours-input { background: #1f2937; border-color: #374151; color: #e5e7eb; }
    </style>

    <div class="space-y-6">

        <div class="text-sm text-gray-500 dark:text-gray-400">
            🕐 الوقت الآن: <span class="font-bold">{{ $this->nowLabel() }}</span>
            @unless ($canManage)
                — <span class="text-amber-600 font-bold">تعديل المواعيد والحالة متاح للمدير فقط</span>
            @endunless
        </div>

        @foreach ($scopes as $scope => $meta)
            @php $status = $this->statusFor($scope); @endphp

            <div class="hours-card" wire:key="scope-{{ $scope }}">
                <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
                    <h2 class="text-lg font-bold text-gray-950 dark:text-white">{{ $meta['title'] }}</h2>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ $meta['hint'] }}</p>

                {{-- Live state — refreshed every minute, since the schedule moves on its own --}}
                <div class="hours-status {{ $status['open'] ? 'is-open' : 'is-closed' }} mb-4" wire:poll.60s>
                    <div class="font-bold text-base">
                        {{ $status['open'] ? '✅ مفتوح الآن' : '🔴 مغلق الآن' }} — {{ $status['label'] }}
                    </div>
                    <div class="text-sm mt-1">{{ $status['next'] }}</div>
                </div>

                {{-- Manual override --}}
                <div class="flex items-center gap-2 flex-wrap mb-5">
                    <select wire:model="durations.{{ $scope }}" class="hours-input" @disabled(! $canManage)>
                        @foreach (\App\Services\Storefront\StoreHours::DURATIONS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <button type="button" class="hours-btn" style="background:#16a34a"
                            wire:click="forceOpen('{{ $scope }}')" @disabled(! $canManage)>
                        🔓 فتح استثنائي
                    </button>
                    <button type="button" class="hours-btn" style="background:#dc2626"
                            wire:click="forceClose('{{ $scope }}')" @disabled(! $canManage)>
                        🔒 إغلاق استثنائي
                    </button>

                    @if ($status['source'] === 'override')
                        <button type="button" class="hours-btn" style="background:#475569"
                                wire:click="resumeSchedule('{{ $scope }}')" @disabled(! $canManage)>
                            ↩️ العودة للجدول
                        </button>
                    @endif
                </div>

                {{-- Weekly schedule --}}
                <div class="overflow-x-auto">
                    <table class="hours-table">
                        <thead>
                            <tr class="text-gray-500">
                                <th>اليوم</th>
                                <th>يعمل</th>
                                <th>من</th>
                                <th>إلى</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (\App\Services\Storefront\StoreHours::DAYS as $dow => [$key, $dayLabel])
                                <tr wire:key="{{ $scope }}-{{ $dow }}">
                                    <td class="font-bold">{{ $dayLabel }}</td>
                                    <td>
                                        <input type="checkbox" wire:model.live="schedules.{{ $scope }}.{{ $dow }}.enabled"
                                               class="rounded" @disabled(! $canManage)>
                                    </td>
                                    <td>
                                        <input type="time" wire:model.live="schedules.{{ $scope }}.{{ $dow }}.open" class="hours-input"
                                               @disabled(! $canManage || ! ($schedules[$scope][$dow]['enabled'] ?? false))>
                                    </td>
                                    <td>
                                        <input type="time" wire:model.live="schedules.{{ $scope }}.{{ $dow }}.close" class="hours-input"
                                               @disabled(! $canManage || ! ($schedules[$scope][$dow]['enabled'] ?? false))>
                                    </td>
                                    <td class="text-xs text-gray-500">
                                        @php $day = $schedules[$scope][$dow] ?? null; @endphp
                                        @if (! ($day['enabled'] ?? false))
                                            مغلق طوال اليوم
                                        @elseif (($day['open'] ?? '') === ($day['close'] ?? ''))
                                            24 ساعة
                                        @elseif (($day['close'] ?? '') < ($day['open'] ?? ''))
                                            يمتد لبعد منتصف الليل
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-gray-500 mt-2">
                    نفس الساعة في «من» و«إلى» تعني 24 ساعة. ساعة إغلاق أصغر من ساعة الفتح تعني أن الدوام يمتد لليوم التالي (مثال: من 14:00 إلى 02:00).
                </p>

                <div class="mt-4">
                    <button type="button" class="hours-btn" style="background:#d97706"
                            wire:click="saveSchedule('{{ $scope }}')" @disabled(! $canManage)>
                        💾 حفظ مواعيد {{ $scope === 'store' ? 'المتجر' : 'التوصيل' }}
                    </button>
                </div>
            </div>
        @endforeach

        {{-- ── رسائل وإعدادات عامة ─────────────────────────────────────── --}}
        <div class="hours-card">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white mb-4">💬 رسائل الإغلاق والتأكيد التلقائي</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">رسالة إغلاق المتجر (تظهر للزبائن)</label>
                    <input type="text" wire:model="closed_message" class="hours-input w-full" @disabled(! $canManage)>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">رسالة توقف التوصيل</label>
                    <input type="text" wire:model="delivery_closed_message" class="hours-input w-full" @disabled(! $canManage)>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium mb-1">⏱️ تأكيد استلام التوصيل تلقائياً بعد (دقيقة)</label>
                <input type="number" wire:model="auto_confirm_minutes" min="5" max="240" step="5" class="hours-input w-32" @disabled(! $canManage)>
            </div>

            <div class="mt-5">
                <button type="button" class="hours-btn" style="background:#d97706" wire:click="saveStoreSettings" @disabled(! $canManage)>
                    💾 حفظ الإعدادات
                </button>
            </div>
        </div>

        {{-- ── الملف الشخصي ──────────────────────────────────────────── --}}
        <div class="hours-card">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white mb-4">👤 الملف الشخصي</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">الاسم</label>
                    <input type="text" wire:model="profile_name" class="hours-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">البريد الإلكتروني</label>
                    <input type="email" wire:model="profile_email" class="hours-input w-full">
                </div>
            </div>

            <div class="mt-5">
                <button type="button" class="hours-btn" style="background:#2563eb" wire:click="saveProfile">
                    💾 حفظ الملف الشخصي
                </button>
            </div>
        </div>

        {{-- ── تغيير كلمة السر ──────────────────────────────────────── --}}
        <div class="hours-card">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white mb-4">🔒 تغيير كلمة السر</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">كلمة السر الحالية</label>
                    <input type="password" wire:model="current_password" class="hours-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">كلمة السر الجديدة</label>
                    <input type="password" wire:model="new_password" class="hours-input w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">تأكيد كلمة السر</label>
                    <input type="password" wire:model="new_password_confirmation" class="hours-input w-full">
                </div>
            </div>

            <div class="mt-5">
                <button type="button" class="hours-btn" style="background:#dc2626" wire:click="changePassword">
                    🔑 تغيير كلمة السر
                </button>
            </div>
        </div>

    </div>
</x-filament-panels::page>
