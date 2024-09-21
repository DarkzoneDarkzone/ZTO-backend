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
        Schema::create('customer_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->rate('rate');
            $table->boolean('active')->default(true);
            $table->string('create_by');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_levels');
    }
};