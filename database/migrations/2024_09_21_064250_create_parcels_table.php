<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->float('weight');
            $table->float('price');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('bill_id')->nullable()->default(null);
            $table->enum('status', ['pending', 'ready', 'success', 'return']);
            $table->timestamp('receipt_at')->nullable();
            $table->timestamp('payment_at')->nullable();
            $table->timestamp('shipping_at')->nullable();
            $table->softDeletes();

            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));

            $table->foreign('bill_id')->references('id')->on('bills')->nullable()->cascadeOnUpdate();;
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
