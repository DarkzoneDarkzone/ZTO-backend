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
        Schema::create('income_expenses', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expenses']);
            $table->enum('sub_type', ['return', 'refund', 'other', 'top_up']);
            // $table->enum('pay_type', ['cash', 'transffer']);
            $table->enum('status', ['pending', 'verify']);
            $table->float('pay_cash');
            $table->float('pay_transfer');
            $table->float('pay_alipay');
            $table->float('pay_wechat');

            $table->string('description')->nullable();
            $table->float('amount_lak')->nullable();
            $table->float('amount_cny')->nullable();
            // $table->boolean('verify')->default(false);

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
        Schema::dropIfExists('income_expenses');
    }
};
