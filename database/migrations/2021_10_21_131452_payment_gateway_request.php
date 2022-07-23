<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class PaymentGatewayRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('payment_gateway_request', function (Blueprint $table)
        {
            $table->increments('id');
            $table->integer('currency_id');
            $table->text('currency');
            $table->integer('payment_method_id');
            $table->text('method');
            $table->decimal('amount', 20, 8)->nullable()->default(0.00000000);
            $table->integer('merchant');
            $table->text('item_name');
            $table->text('order_no');
            $table->text('gateway_reference');
            $table->timestamps();
            $table->text('unique_code')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('payment_gateway_request');
    }
}