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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // $table->unsignedBigInteger('bill_id')->default(0)->change();
            $table->string('payment_no');
            $table->float('amount_lak');
            $table->float('amount_cny');
            $table->enum('method', ['cash', 'transffer', 'airpay', 'wechat_pay']);
            $table->enum('status', ['paid', 'pending']);
            $table->boolean('active')->default(true);
            $table->string('created_by');

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
        Schema::dropIfExists('payments');
    }
};
