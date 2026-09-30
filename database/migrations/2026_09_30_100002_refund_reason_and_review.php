<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Why the money went back, and who sent it.
 *
 * A refund is the one thing the counter does that takes money out, and until
 * now it could be done without saying why. The reason lived on the request row
 * — so a refund paid in cash or onto a wallet, which makes no request, recorded
 * nothing at all beyond a date and an amount.
 *
 *   orders.refund_reason  — written on every refund, whichever way it is paid.
 *   change_refund_requests.review_note — why a request was refused, which was
 *   "مرفوض" and nothing else.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'refund_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('refund_reason')->nullable()->after('refund_method');
            });
        }

        if (! Schema::hasColumn('change_refund_requests', 'review_note')) {
            Schema::table('change_refund_requests', function (Blueprint $table) {
                $table->text('review_note')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('refund_reason'));
        Schema::table('change_refund_requests', fn (Blueprint $table) => $table->dropColumn('review_note'));
    }
};
