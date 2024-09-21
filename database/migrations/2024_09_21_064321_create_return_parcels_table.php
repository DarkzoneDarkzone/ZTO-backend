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
        Schema::create('return_parcels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parcel_id');
            $table->string('car_number');
            $table->string('driver_name');
            $table->float('weigth');
            $table->float('refund_amount_lak');
            $table->float('refund_amount_cny');
            $table->boolean('verify')->default(false);
            $table->string('create_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parcel_id')->references('id')->on('parcels');
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
