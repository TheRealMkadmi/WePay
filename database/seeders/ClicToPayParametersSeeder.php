<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use App\Models\CurrencyPaymentMethod;

class ClicToPayParametersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        CurrencyPaymentMethod::insert([
            [
                'currency_id'                 => 5,
                'method_id'                   => 10,
                'activated_for'               => '{"deposit":""}',
                'method_data'                 => '{"username":"0503241010","password":"pF3H7Mw4j","mode":"sandbox"}',
                'processing_time'             => '',
            ]
        ]);
    }
}
