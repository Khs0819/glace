<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add role column to users and create store_settings table.
 *
 * Roles:
 *   - manager: full access (delete shifts, view payment accounts, etc.)
 *   - accountant: limited access (no deleting, no sensitive financial data)
 *
 * Store settings:
 *   - store_open: whether the store accepts new orders
 *   - delivery_open: whether delivery is available
 *   - auto_confirm_minutes: auto-confirm delivery after N minutes
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('manager')->after('email');
        });

        if (! Schema::hasColumn('payment_accounts', 'account_number')) {
            Schema::table('payment_accounts', function (Blueprint $table) {
                $table->string('account_number')->nullable()->after('bank_name');
            });
        }

        Schema::create('store_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        DB::table('store_settings')->insert([
            ['key' => 'store_open',            'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'delivery_open',         'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'auto_confirm_minutes',  'value' => '30', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'closed_message',        'value' => 'المتجر مغلق حالياً — نراكم قريباً!', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::dropIfExists('store_settings');
    }
};
