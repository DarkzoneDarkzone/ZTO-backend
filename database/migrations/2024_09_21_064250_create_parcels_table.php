<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->string('track_no');
            $table->string('phone')->nullable();
            $table->float('weight');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('customer_id');
            $table->string('status');
            $table->timestamps();
            $table->timestamp('receipt_at')->nullable();
            $table->timestamp('payment_at')->nullable();
            $table->timestamp('shipping_at')->nullable();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
