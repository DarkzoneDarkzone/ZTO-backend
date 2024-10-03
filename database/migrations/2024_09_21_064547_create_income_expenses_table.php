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
        Schema::create('income_expenses', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expenses']);
            $table->enum('sub_type', ['return', 'refund', 'other']);
            // $table->unsignedBigInteger('parcel_id');
            $table->string('description');
            $table->float('amount_lak');
            $table->float('amount_cny');
            $table->timestamps();
            $table->softDeletes();

            // $table->foreign('parcel_id')->references('id')->on('parcels');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_expenses');
    }
};
