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
        Schema::create('zto_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->float('amount_lak')->default(0);
            $table->float('balance_amount_lak')->default(0);
            $table->string('description')->nullable();
            $table->string('bank_name')->nullable();

            $table->float('pay_cash')->nullable();
            $table->float('pay_transfer')->nullable();
            $table->float('pay_alipay')->nullable();
            $table->float('pay_wechat')->nullable();

            $table->timestamp('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zto_balance_transactions');
    }
};
