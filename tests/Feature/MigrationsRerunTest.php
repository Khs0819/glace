<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recent migrations must survive being run again.
 *
 * MySQL cannot roll a schema change back, so a migration that fails half way
 * leaves its first columns behind and is not recorded as run. The next
 * `migrate` then fails on those columns and never reaches anything after them
 * — which is how production ended up without `payment_accounts.account_number`
 * and every save of a payment account failed.
 */

function runMigration(string $file): void
{
    (require database_path('migrations/' . $file))->up();
}

it('can run each recent migration again on a database that already has it', function (string $file) {
    runMigration($file);

    expect(true)->toBeTrue();
})->with([
    '2026_09_09_100001_create_drivers_table.php',
    '2026_09_09_100002_add_payment_account_to_orders.php',
    '2026_09_12_200001_create_change_refund_requests_table.php',
    '2026_09_12_200002_create_driver_settlements_table.php',
    '2026_09_13_200001_add_roles_and_store_settings.php',
    '2026_09_15_100001_cashier_board_upgrades.php',
    '2026_09_15_100002_add_captain_note_to_orders.php',
    '2026_09_15_100003_add_sender_account_name.php',
]);

it('finishes the roles migration when it had stopped after adding the role column', function () {
    // The production state: `role` exists, `account_number` does not.
    Schema::table('payment_accounts', fn (Blueprint $table) => $table->dropColumn('account_number'));

    expect(Schema::hasColumn('users', 'role'))->toBeTrue()
        ->and(Schema::hasColumn('payment_accounts', 'account_number'))->toBeFalse();

    runMigration('2026_09_13_200001_add_roles_and_store_settings.php');

    expect(Schema::hasColumn('payment_accounts', 'account_number'))->toBeTrue();
});

it('does not reopen a store the dashboard closed when the migration runs again', function () {
    DB::table('store_settings')->where('key', 'store_open')->update(['value' => '0']);

    runMigration('2026_09_13_200001_add_roles_and_store_settings.php');

    expect(DB::table('store_settings')->where('key', 'store_open')->value('value'))->toBe('0');
});
