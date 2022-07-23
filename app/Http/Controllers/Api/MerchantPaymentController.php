<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\{MerchantPayment,
     Merchant};

class MerchantPaymentController extends Controller
{   public $successStatus      = 200;
    public $failedStatus = 400;

    public function checkPaymentStatus(Request $request)
    {
        $orderID = $request->input('order_id');
        $merchantName = $request->input('merchantName'); //$result->merchant->business_name
        if (empty($orderID)||empty($merchantName)) {
            $success['status']  = $this->failedStatus;
            $success['message']  = 'orderId or merchantName is empty!';
            $success['MerchantPayment'] = null;
            return response()->json(['success' => $success], $this->failedStatus);
        }
        $merchant = Merchant::where('business_name', $merchantName)->first();
        if ($merchant) {
            $merchantId = $merchant->id;
            $merchantPayment = MerchantPayment::where([['order_no',$orderID],['merchant_id',$merchantId]])->first();

            $success['status']  = $this->successStatus;
            $success['MerchantPayment'] = $merchantPayment;
            return response()->json(['success' => $success], $this->successStatus);
        }

        $success['status']  = $this->failedStatus;
        $success['message']  = 'Merchant not found';
        $success['MerchantPayment'] = null;
        return response()->json(['success' => $success], $this->failedStatus);

    }
}