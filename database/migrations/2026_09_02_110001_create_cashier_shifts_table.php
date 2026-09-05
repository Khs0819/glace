<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cashier's session at the till, from opening the drawer to counting it.
     *
     * This is what makes the cash number auditable. Without it "how much cash
     * came in today" is a query over a time range and a guess about who was on;
     * with it, every cash payment is attributed to one named person's shift,
     * and the difference between what the system expected and what was actually
     * in the drawer is a recorded number rather than an argument.
     */
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();

            // The person accountable for the drawer. Not nullable: an
            // unattributed shift defeats the entire point.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            // What was in the drawer at the start, so the expected closing
            // figure is float + takings rather than takings alone.
            $table->decimal('opening_float', 10, 2)->default(0);

            // Filled at closing time.
            //
            // `expected_cash` is computed by the system and frozen here rather
            // than recomputed on every report view: an order edited afterwards
            // must not silently rewrite a shift that was already signed off.
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();

            // counted − expected. Stored, not derived, for the same reason.
            $table->decimal('difference', 10, 2)->nullable();

            // Snapshot of the shift's takings by method, so the report stands
            // on its own even after refunds land later.
            $table->json('totals')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'opened_at']);
            $table->index('closed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
    }
};
