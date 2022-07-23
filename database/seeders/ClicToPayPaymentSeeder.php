<?php

namespace Database\Seeders;


use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class ClicToPayPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        PaymentMethod::insert([
            ['id' => 10, 'name' => 'Clictopay', 'status' => 'Active'],
        ]);
    }
}
