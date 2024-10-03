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
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('income_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('payment_id')->references('id')->on('payments')->nullable()->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('income_id')->references('id')->on('income_expenses')->nullable()->nullOnDelete()->cascadeOnUpdate();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
