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
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('address');
            $table->unsignedBigInteger('customer_level_id');
            $table->boolean('active')->default(true);
            $table->boolean('verify')->default(true);
            $table->boolean('create_by');
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
