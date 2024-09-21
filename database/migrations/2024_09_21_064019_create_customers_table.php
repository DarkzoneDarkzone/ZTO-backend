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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->unique()->nullable();
            $table->string('address')->nullable();
            $table->unsignedBigInteger('customer_level_id');
            $table->boolean('active')->default(false);
            $table->boolean('verify')->default(false);
            $table->string('create_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_level_id')->references('id')->on('customer_levels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
