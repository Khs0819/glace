<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The order gains a second, independent axis.
     *
     * Until now `status` meant "how far has the money got" (pending → paid).
     * Handoff 12 introduces a fulfilment lifecycle — قيد المراجعة → جاري التحضير
     * → في الطريق → تم الاستلام — which is not the same question and cannot share
     * a column with it: a cash order is unpaid for its entire life yet must still
     * travel the whole fulfilment path, and a paid order still has to be prepared.
     *
     * So `status` becomes the fulfilment status the storefront shows, and the old
     * vocabulary moves to `payment_status`, where the Jawwal Pay machinery keeps
     * using it untouched.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Null = a guest order placed before accounts existed, or one taken
            // over the counter. Those must survive, so this is not required.
            $table->foreignId('customer_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();

            $table->string('payment_status')->default('pending')->after('status')->index();

            $table->string('payment_method', 20)->default('cash')->after('payment_status');
            $table->string('delivery_method', 20)->default('pickup')->after('payment_method');

            // A snapshot, not a join. The customer may edit or delete the saved
            // address afterwards; what was on the order when it was placed is a
            // record and must not move — including `area`, the zone's name at
            // the time (handoff 12).
            $table->json('address')->nullable()->after('delivery_method');
            $table->string('address_id', 40)->nullable()->after('address');

            $table->string('coupon_code', 40)->nullable()->after('subtotal');
            $table->decimal('discount', 10, 2)->default(0)->after('coupon_code');
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('discount');

            // Manual-transfer proof. A path on the public disk, served as an
            // absolute URL — never base64 in a column (handoff 12/13).
            $table->string('receipt_image')->nullable()->after('total');
            $table->text('receipt_note')->nullable()->after('receipt_image');

            // Minutes. Set by the shop, never by the client.
            $table->unsignedSmallInteger('preparation_time')->nullable();
            $table->unsignedSmallInteger('estimated_delivery_time')->nullable();

            // Who is carrying it, once someone is. Snapshotted for the same
            // reason as the address.
            $table->json('driver')->nullable();
            $table->timestamp('driver_assigned_at')->nullable();

            // When the customer asked for it — pickup/dine-in scheduling. The
            // slot arithmetic stays on the frontend for now (handoff 12 §2).
            $table->timestamp('scheduled_for')->nullable();

            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->index(['customer_id', 'created_at']);
        });

        // Carry the old meaning across before the column is reinterpreted.
        DB::table('orders')->update(['payment_status' => DB::raw('status')]);

        // Everything that was not explicitly cancelled needs a human to look at
        // it, which is exactly what "قيد المراجعة" means. A failed payment keeps
        // that fact in payment_status rather than being silently binned here.
        DB::table('orders')->where('status', 'cancelled')->update(['status' => 'ملغي']);
        DB::table('orders')->where('status', '!=', 'ملغي')->update(['status' => 'قيد المراجعة']);
    }

    public function down(): void
    {
        DB::table('orders')->update(['status' => DB::raw('payment_status')]);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id', 'created_at']);

            $table->dropColumn([
                'customer_id', 'payment_status', 'payment_method', 'delivery_method',
                'address', 'address_id', 'coupon_code', 'discount', 'delivery_fee',
                'receipt_image', 'receipt_note', 'preparation_time',
                'estimated_delivery_time', 'driver', 'driver_assigned_at',
                'scheduled_for', 'cancel_reason', 'cancelled_at', 'received_at',
                'delivered_at',
            ]);
        });
    }
};
