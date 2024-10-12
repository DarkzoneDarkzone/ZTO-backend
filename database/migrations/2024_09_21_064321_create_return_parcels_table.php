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
        Schema::create('return_parcels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parcel_id');
            $table->unsignedBigInteger('income_expenses_id');
            $table->string('car_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->float('weight')->nullable();
            $table->float('refund_amount_lak')->nullable();
            $table->float('refund_amount_cny')->nullable();
            $table->string('created_by');

            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
            $table->softDeletes();

            $table->foreign('parcel_id')->references('id')->on('parcels');
            $table->foreign('income_expense_id')->references('id')->on('income_expenses');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_parcels');
    }
};
