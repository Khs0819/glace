<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where the shop is paid, for the manual-transfer methods. Replaces the
        // frontend's merchantPaymentAccounts.ts, whose placeholder account
        // numbers looked real enough to be transferred to (handoff 13).
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();

            // One account per method: bop | jawwal-manual | paypal.
            $table->string('method', 20)->unique();

            $table->string('qr_image')->nullable();
            $table->string('holder_name');
            $table->string('bank_name')->nullable();

            $table->string('primary_label');
            $table->string('primary_value');
            $table->string('secondary_label')->nullable();
            $table->string('secondary_value')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            // Slug, because the frontend uses the id as a stable anchor for the
            // open accordion panel ("how-to-order").
            $table->string('id', 60)->primary();

            $table->string('question');

            // Paragraphs separated by a blank line; a paragraph beginning "- "
            // renders as a bullet list (handoff 15).
            $table->text('answer');

            $table->string('link_href')->nullable();
            $table->string('link_label')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Long-form HTML pages edited in the dashboard: terms, privacy.
        Schema::create('site_contents', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_contents');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('payment_accounts');
    }
};
