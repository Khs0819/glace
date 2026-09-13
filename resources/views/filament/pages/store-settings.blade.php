<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── حالة المتجر ──────────────────────────────────────────── --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white mb-4 flex items-center gap-2">
                🏪 حالة المتجر
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Store Open Toggle --}}
                <div class="flex items-center justify-between p-4 rounded-lg border-2 cursor-pointer"
                     :class="$wire.store_open ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50'"
                     @click="$wire.store_open = !$wire.store_open">
                    <div>
                        <h3 class="font-bold text-gray-900">المتجر</h3>
                        <p class="text-sm text-gray-500" x-text="$wire.store_open ? '✅ مفتوح — يقبل الطلبات' : '🔴 مغلق — لا يقبل طلبات جديدة'"></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="store_open" class="sr-only peer" @click.stop>
                        <div class="w-14 h-7 bg-gray-300 rounded-full peer
                                    peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full
                                    peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5
                                    after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full
                                    after:h-6 after:w-6 after:transition-all peer-checked:bg-green-600"></div>
                    </label>
                </div>

                {{-- Delivery Toggle --}}
                <div class="flex items-center justify-between p-4 rounded-lg border-2 cursor-pointer"
                     :class="$wire.delivery_open ? 'border-blue-300 bg-blue-50' : 'border-orange-300 bg-orange-50'"
                     @click="$wire.delivery_open = !$wire.delivery_open">
                    <div>
                        <h3 class="font-bold text-gray-900">التوصيل</h3>
                        <p class="text-sm text-gray-500" x-text="$wire.delivery_open ? '✅ متاح — يقبل طلبات التوصيل' : '🟠 متوقف — فقط استلام من المحل'"></p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="delivery_open" class="sr-only peer" @click.stop>
                        <div class="w-14 h-7 bg-gray-300 rounded-full peer
                                    peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full
                                    peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5
                                    after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full
                                    after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>

            {{-- Closed Message --}}
            <div class="mt-4" x-show="!$wire.store_open" x-transition>
                <label class="block text-sm font-medium text-gray-700 mb-1">رسالة الإغلاق (تظهر للزبائن)</label>
                <input type="text" wire:model="closed_message"
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
            </div>

            {{-- Auto Confirm --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    ⏱️ تأكيد الاستلام التلقائي بعد (دقيقة)
                </label>
                <p class="text-xs text-gray-500 mb-2">إذا لم يؤكد الزبون استلام طلب التوصيل، يتم تأكيده تلقائياً بعد هذه المدة.</p>
                <input type="number" wire:model="auto_confirm_minutes" min="10" max="120" step="5"
                       class="w-32 rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
            </div>

            <div class="mt-5">
                <button wire:click="saveStoreSettings"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-bold text-white cursor-pointer
                               transition-all duration-200 shadow-md hover:shadow-lg active:scale-95"
                        style="background-color: #d97706; hover:background-color: #b45309;">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">الاسم</label>
                    <input type="text" wire:model="profile_name"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" wire:model="profile_email"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div class="mt-5">
                <button wire:click="saveProfile"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-bold text-white cursor-pointer
                               transition-all duration-200 shadow-md hover:shadow-lg active:scale-95"
                        style="background-color: #2563eb;">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة السر الحالية</label>
                    <input type="password" wire:model="current_password"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">كلمة السر الجديدة</label>
                    <input type="password" wire:model="new_password"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة السر</label>
                    <input type="password" wire:model="new_password_confirmation"
                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500">
                </div>
            </div>

            <div class="mt-5">
                <button wire:click="changePassword"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg font-bold text-white cursor-pointer
                               transition-all duration-200 shadow-md hover:shadow-lg active:scale-95"
                        style="background-color: #dc2626;">
                    🔑 تغيير كلمة السر
                </button>
            </div>
        </div>

    </div>
</x-filament-panels::page>
