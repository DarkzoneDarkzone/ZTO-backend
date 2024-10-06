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
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->string('address')->nullable();
            $table->float('weight');
            $table->float('price');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('bill_id')->nullable()->default(null);
            $table->string('status');
            $table->timestamp('receipt_at')->nullable();
            $table->timestamp('payment_at')->nullable();
            $table->timestamp('shipping_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('bill_id')->references('id')->on('bills');
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
