<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── حالة المتجر ──────────────────────────────────────────── --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white mb-4 flex items-center gap-2">
                🏪 حالة المتجر
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Store Open Toggle --}}
                <div class="flex items-center justify-between p-4 rounded-lg border-2"
                     :class="$wire.store_open ? 'border-green-300 bg-green-50 dark:bg-green-950/20' : 'border-red-300 bg-red-50 dark:bg-red-950/20'">
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white">المتجر</h3>
                        <p class="text-sm text-gray-500" x-text="$wire.store_open ? 'مفتوح — يقبل الطلبات' : 'مغلق — لا يقبل طلبات جديدة'"></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="store_open" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700
                                    peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full
                                    peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5
                                    after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full
                                    after:h-6 after:w-6 after:transition-all peer-checked:bg-green-600"></div>
                    </label>
                </div>

                {{-- Delivery Toggle --}}
                <div class="flex items-center justify-between p-4 rounded-lg border-2"
                     :class="$wire.delivery_open ? 'border-blue-300 bg-blue-50 dark:bg-blue-950/20' : 'border-orange-300 bg-orange-50 dark:bg-orange-950/20'">
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white">التوصيل</h3>
                        <p class="text-sm text-gray-500" x-text="$wire.delivery_open ? 'متاح — يقبل طلبات التوصيل' : 'متوقف — فقط استلام من المحل'"></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="delivery_open" class="sr-only peer">
                        <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700
                                    peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full
                                    peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5
                                    after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full
                                    after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>

            {{-- Closed Message --}}
            <div class="mt-4" x-show="!$wire.store_open" x-transition>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">رسالة الإغلاق (تظهر للزبائن)</label>
                <input type="text" wire:model="closed_message"
                       class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm">
            </div>

            {{-- Auto Confirm --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    ⏱️ تأكيد الاستلام التلقائي بعد (دقيقة)
                </label>
                <p class="text-xs text-gray-500 mb-2">إذا لم يؤكد الزبون استلام طلب التوصيل، يتم تأكيده تلقائياً بعد هذه المدة.</p>
                <input type="number" wire:model="auto_confirm_minutes" min="10" max="120" step="5"
                       class="w-32 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm">
            </div>

            <div class="mt-4">
                <button wire:click="saveStoreSettings"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 text-white font-bold rounded-lg
                               hover:bg-primary-700 transition-all shadow-sm">
                    💾 حفظ الإعدادات
                </button>
            </div>
        </div>

        {{-- ── الملف الشخصي ──────────────────────────────────────────── --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white mb-4 flex items-center gap-2">
                👤 الملف الشخصي
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الاسم</label>
                    <input type="text" wire:model="profile_name"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">البريد الإلكتروني</label>
                    <input type="email" wire:model="profile_email"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm">
                </div>
            </div>

            <div class="mt-4">
                <button wire:click="saveProfile"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white font-bold rounded-lg
                               hover:bg-blue-700 transition-all shadow-sm">
                    💾 حفظ الملف الشخصي
                </button>
            </div>
        </div>

        {{-- ── تغيير كلمة السر ──────────────────────────────────────── --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white mb-4 flex items-center gap-2">
                🔒 تغيير كلمة السر
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">كلمة السر الحالية</label>
                    <input type="password" wire:model="current_password"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">كلمة السر الجديدة</label>
                    <input type="password" wire:model="new_password"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">تأكيد كلمة السر</label>
                    <input type="password" wire:model="new_password_confirmation"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm">
                </div>
            </div>

            <div class="mt-4">
                <button wire:click="changePassword"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 text-white font-bold rounded-lg
                               hover:bg-red-700 transition-all shadow-sm">
                    🔑 تغيير كلمة السر
                </button>
            </div>
        </div>

    </div>
</x-filament-panels::page>
