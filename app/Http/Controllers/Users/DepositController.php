<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{DB, 
    Validator, 
    Session
};
use Illuminate\Http\Request;
use App\Http\Helpers\Common;
use App\Models\{CurrencyPaymentMethod,
    CoinpaymentLogTrx,
    PaymentMethod,
    PayoutSetting,
    Transaction,
    FeesLimit,
    Currency,
    Setting,
    Deposit,
    Country,
    Wallet,
    Bank,
    File
};
use Exception;
use Auth;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\BadResponseException;

use Carbon\Carbon;
use App\Repositories\{StripeRepository, CoinPaymentRepository};

class DepositController extends Controller
{
    protected $helper;
    protected $stripeRepository, $coinPayment;

    public function __construct()
    {
        $this->helper  = new Common();
        $this->deposit = new Deposit();
        $this->client = new \GuzzleHttp\Client();
        $this->stripeRepository = new StripeRepository();
        $this->coinPayment = new CoinPaymentRepository();
    }

    public function create(Request $request)
    {
        //set the session for validate the action
        setActionSession(); 

        $data['menu']          = 'deposit';
        $data['content_title'] = 'Deposit';
        $data['icon']          = 'university';

        $activeCurrency             = Currency::where(['status' => 'Active'])->get(['id', 'code', 'status']);
        $feesLimitCurrency          = FeesLimit::where(['transaction_type_id' => Deposit, 'has_transaction' => 'Yes'])->get(['currency_id', 'has_transaction']);
        $data['activeCurrencyList'] = $this->currencyList($activeCurrency, $feesLimitCurrency);
        $data['defaultWallet']      = $defaultWallet      = Wallet::where(['user_id' => auth()->user()->id, 'is_default' => 'Yes'])->first(['currency_id']);

        //check Decimal Thousand Money Format Preference
        $data['preference'] = getDecimalThousandMoneyFormatPref(['decimal_format_amount']);

        if ($request->isMethod('post'))
        {
            $rules = array(
                'amount'         => 'required|numeric',
                'currency_id'    => 'required|integer',
                'payment_method' => 'required|integer',
            );
            $fieldNames = array(
                'amount'         => __("Amount"),
                'currency_id'    => __("Currency"),
                'payment_method' => __("Payment Method"),
            );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);
            if ($validator->fails())
            {
                return back()->withErrors($validator)->withInput();
            }

            //backend validation ends
            $currency_id       = (int) $request->currency_id;
            $amount            = (double) $request->amount;
            // $coinpaymentAmount = $amount;
            Session::put('coinpaymentAmount', $amount);

            $data['active_currency']    = $activeCurrency    = Currency::where(['status' => 'Active'])->get(['id', 'code', 'status']);
            $feesLimitCurrency          = FeesLimit::where(['transaction_type_id' => Deposit, 'has_transaction' => 'Yes'])->get(['currency_id', 'has_transaction']);
            $data['activeCurrencyList'] = $this->currencyList($activeCurrency, $feesLimitCurrency);
            $data['walletList']         = $activeCurrency;
            $data['payment_met']        = PaymentMethod::where(['status' => 'Active'])->get(['id', 'name']);
            $currency                   = Currency::where(['id' => $currency_id, 'status' => 'Active'])->first(['symbol']);
            $request['currSymbol']      = $currency->symbol;
            $data['payMtd']             = $payMtd             = PaymentMethod::where(['id' => $request->payment_method, 'status' => 'Active'])->first(['name']);
            $request['payment_name']    = $payMtd->name;
            $calculatedFee              = $this->getDepositFeesLimit($request);
            $request['fee']             = $calculatedFee->getData()->success->totalFees;
            $request['totalAmount']     = $request['amount'] + $request['fee'];
            session(['transInfo' => $request->all()]);
            $data['transInfo']           = $transInfo           = $request->all();
            $data['transInfo']['wallet'] = $request->currency_id;
            Session::put('payment_method_id', $request->payment_method);
            Session::put('wallet_currency_id', $request->currency_id);

            //Code for FeesLimit starts here
            $feesDetails = $this->helper->getFeesLimitObject([], Deposit, $currency_id, $transInfo['payment_method'], 'Yes', ['min_limit', 'max_limit']);
            if (@$feesDetails->max_limit == null)
            {
                if ((@$amount < @$feesDetails->min_limit))
                {
                    $data['error'] = __('Minimum amount ') . formatNumber($feesDetails->min_limit);
                    return view('user_dashboard.deposit.create', $data);
                }
            }
            else
            {
                if ((@$amount < @$feesDetails->min_limit) || (@$amount > @$feesDetails->max_limit))
                {
                    $data['error'] = __('Minimum amount ') . formatNumber($feesDetails->min_limit) . __(' and Maximum amount ') . formatNumber($feesDetails->max_limit);
                    return view('user_dashboard.deposit.create', $data);
                }
            }
            //Code for FeesLimit ends here

            if ($payMtd->name == 'Bank')
            {
                $banks                  = Bank::where(['currency_id' => $currency_id])->get(['id', 'bank_name', 'is_default', 'account_name', 'account_number']);
                $currencyPaymentMethods = CurrencyPaymentMethod::where('currency_id', $request->currency_id)->where('activated_for', 'like', "%deposit%")->where('method_data', 'like', "%bank_id%")->get(['method_data']);
                $data['banks']          = $bankList          = $this->bankList($banks, $currencyPaymentMethods);
                if (empty($bankList))
                {
                    $this->helper->one_time_message('error', __('Banks Does Not Exist For Selected Currency!'));
                    return redirect('deposit');
                }
                return view('user_dashboard.deposit.bank_confirmation', $data);
            } else if($payMtd->name == 'Clictopay')
            {
                return $this->clicToPayDeposit($request);
            }
            else if($payMtd->name == 'CompteStb')
            {
                return $this->depositSetting();
            }
            else if($payMtd->name == 'CarteStb')
            {
                return $this->depositCartSetting();
            }
            return view('user_dashboard.deposit.confirmation', $data);
        }
        return view('user_dashboard.deposit.create', $data);
    }

    // clic to pay deposit

    /* ClicToPay Merchant Payment starts*/

    public function compteStbDeposit(){
        return redirect('deposit');
        // return view('user_dashboard.deposit.setting');

    }

    
    public function stbDepositSms(){
        
        return view('user_dashboard.deposit.sms');

    }

    public function cartStbDepositSms(){
        
        return view('user_dashboard.deposit.CartSms');

    }
    


    public function depositSetting()
    {
        setActionSession();
        $amount = Session::get('coinpaymentAmount');

        $data['menu']           = 'payout';
        $data['payoutSettings'] = PayoutSetting::with(['paymentMethod:id,name'])
        ->where(['user_id' => auth()->user()->id , 'bank_name' => "STB"])
        ->paginate(10);
        $data['countries']      = Country::get(['id', 'name']);
        $data['paymentMethods'] = PaymentMethod::whereNotIn('id', [1, 2, 4, 5, 7, 8, 9])->where(['status' => 'Active'])->get(['id', 'name']);
        $data['amount'] = $amount;

        return view('user_dashboard.deposit.setting', $data);
    }

    public function depositCartSetting()
    {
        setActionSession();
        $amount = Session::get('coinpaymentAmount');

        $data['menu']           = 'payout';
        $data['payoutSettings'] = PayoutSetting::with(['paymentMethod:id,name'])
        ->where(['user_id' => auth()->user()->id , 'bank_name' => "CartSTB"])
        ->paginate(10);
        $data['countries']      = Country::get(['id', 'name']);
        $data['paymentMethods'] = PaymentMethod::whereNotIn('id', [1, 2, 4, 5, 7, 8, 9])->where(['status' => 'Active'])->get(['id', 'name']);
        $data['amount'] = $amount;
        return view('user_dashboard.deposit.cartSetting', $data);
    }


    public function depositSettingStore(Request $request)
    {

            $payoutSetting                      = new PayoutSetting();
            $payoutSetting->type                = 6;
            $payoutSetting->user_id             = auth()->user()->id;
            $payoutSetting->account_name        = $request->account_name;
            $payoutSetting->account_number      = $request->account_number;
            $payoutSetting->swift_code          = $request->swift_code;
            $payoutSetting->bank_name           = "STB";
            $payoutSetting->bank_branch_name    = "STB";
            $payoutSetting->bank_branch_city    = $request->branch_city;
            $payoutSetting->bank_branch_address = $request->branch_address;
            $payoutSetting->country             = 217;
            $payoutSetting->save();

        $this->helper->one_time_message('success','Paramètre de dépôt ajouté avec succès!');
        return redirect('deposit/setting');
    }


    public function depositCartSettingStore(Request $request)
    {

            $payoutSetting                      = new PayoutSetting();
            $payoutSetting->type                = 6;
            $payoutSetting->user_id             = auth()->user()->id;
            $payoutSetting->account_name        = $request->account_name;
            $payoutSetting->account_number      = $request->account_number;
            $payoutSetting->swift_code          = $request->swift_code;
            $payoutSetting->bank_name           = "CartSTB";
            $payoutSetting->bank_branch_name    = "CartSTB";
            $payoutSetting->bank_branch_city    = $request->branch_city;
            $payoutSetting->bank_branch_address = $request->branch_address;
            $payoutSetting->country             = 217;
            $payoutSetting->save();

        $this->helper->one_time_message('success','Paramètre de dépôt ajouté avec succès!');
        return redirect('deposit/cart-setting');
    }

    

    public function depositSettingUpdate(Request $request)
    {
        $id      = $request->setting_id;
        $setting = PayoutSetting::find($id);
        if (!$setting)
        {
            $this->helper->one_time_message('error', __('Payout Setting not found !'));
            return redirect('deposit/setting');
        }
        $setting->account_name        = $request->account_name;
        $setting->account_number      = $request->account_number;
        $setting->bank_branch_city    = $request->branch_city;
        $setting->bank_branch_address = $request->branch_address;
        $setting->swift_code          = $request->swift_code;
        $setting->save();

        $this->helper->one_time_message('success','Paramètre de dépôt mis à jour avec succès!');
        return redirect('deposit/setting');
    }
    

    public function depositCartSettingUpdate(Request $request)
    {
        $id      = $request->setting_id;
        $setting = PayoutSetting::find($id);
        if (!$setting)
        {
            $this->helper->one_time_message('error', __('Payout Setting not found !'));
            return redirect('deposit/cart-setting');
        }
        $setting->account_name        = $request->account_name;
        $setting->account_number      = $request->account_number;
        $setting->bank_branch_city    = $request->branch_city;
        $setting->bank_branch_address = $request->branch_address;
        $setting->swift_code          = $request->swift_code;
        $setting->save();

        $this->helper->one_time_message('success','Paramètre de dépôt mis à jour avec succès!');
        return redirect('deposit/cart-setting');
    }

    public function depositSettingDestroy(Request $request)
    {
        $id = $request->id;
        $payout = auth()->user()->payoutSettings->where('id', $id)->first();
        $payout->delete();

        $this->helper->one_time_message('success','Paramètre de dépôt supprimé avec succès!');
        return redirect('deposit/setting');
    }

    
    public function depositCartSettingDestroy(Request $request)
    {
        $id = $request->id;
        $payout = auth()->user()->payoutSettings->where('id', $id)->first();
        $payout->delete();

        $this->helper->one_time_message('success','Paramètre de dépôt supprimé avec succès!');
        return redirect('deposit/cart-setting');
    }

    public function stbDeposit(Request $request)
    {
        setActionSession();
        $amount = Session::get('coinpaymentAmount');
       
        $data['menu'] = 'withdrawal';
        
        if (!$request->isMethod('post'))
        {   
            $payment_methods = PayoutSetting::with(['paymentMethod:id,name'])
                ->where(['user_id' => auth()->user()->id , 'bank_name' => 'STB'])
                ->get(['id', 'type', 'email', 'account_name', 'account_number', 'bank_name']);
            $data['payment_methods'] = $payment_methods;

            $data['coinpaymentAmount'] = $amount;

            $data['defaultCurrency'] = Wallet::where('user_id', auth()->user()->id)->where('is_default', 'Yes')->first(['id', 'currency_id']);

            //check Decimal Thousand Money Format Preference
            $data['preference'] = getDecimalThousandMoneyFormatPref(['decimal_format_amount']);
            
            if(count($payment_methods) == 0){
                $this->helper->one_time_message('error',' Veuillez ajouter un paramètre de dépôt !');
                return redirect('deposit/setting');
            }else{
                return redirect('deposit');
                // return view('user_dashboard.deposit.add', $data);

            }

            
        }
        else
        {
                $payoutSetting         = PayoutSetting::where(['id' => $request->payout_setting_id])->first(['account_number', 'swift_code']);
                $PaymentMethod         = PaymentMethod::where(['name' => 'CompteStb'])->first(['id', 'name']);
                $currencyInfo          = CurrencyPaymentMethod::where(['method_id' => $PaymentMethod->id])->first();
                $methodData            = json_decode($currencyInfo->method_data);
                $accountNumber         = $payoutSetting->account_number;
                $cin                   = $payoutSetting->swift_code;

                try {
                $url='https://stbclientb2c.b2clogin.com/stbclientb2c.onmicrosoft.com/b2c_1_signin_ropc/oauth2/v2.0/token';
                $res = $this->client->post($url, [
                    'form_params' => [
                        'username'          => $methodData->username,
                        'password'          => $methodData->password,
                        'grant_type'        => $methodData->grant_type,
                        'scope'             => $methodData->scope,
                        'client_id'         => $methodData->client_id,
                        'response_type'     => $methodData->response_type,
                    ]
                ]);
                $req=json_decode($res->getBody()->getContents(), true);
                $statusCode = $res->getStatusCode();

                if($statusCode == 200){
                    $id_token=$req["id_token"];
                    
                    try {

                    $client = new \GuzzleHttp\Client();
                    
                    $response =$client->get('https://stbgestionapi.azure-api.net/api/account/beta/stb/getpkbyrib?RIB='.$accountNumber.'&CIN='.$cin, [
                        'headers' => [
                            'Ocp-Apim-Subscription-Key' => '84bdb97d98be4bb387e4e8d4c80fac3a',
                            'Authorization' => 'Bearer '.$id_token
                        ]
                    ]);
                    
                    $repCode=json_decode((string) $response->getBody(), true);
                    $status = $response->getStatusCode();

                    if($status == 200){

                        if(!isset($repCode["OTP"])){
                            $this->helper->one_time_message('error','Mobile n\'existe pas .');
                            return redirect('deposit/setting');
                        }

                        Session::put('id_token', $id_token);
                        //Session::put('IdTransaction', $repCode["id"]);
                        Session::put('OTP', $repCode["OTP"]);
                        Session::put('currency_id',$request->currency_id);
                        Session::put('accountNumber',$accountNumber);
                        Session::put('coinpaymentAmount',$request->amount);
                        Session::save();
                        return redirect('deposit/stb-deposit-sms');

                    }else{

                        $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                        return redirect('deposit/setting');

                    }

                } catch (RequestException $e) {
                    $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                    return redirect('deposit/setting');
                }
                    

                }else{

                    $this->helper->one_time_message('error','Problème de connexion STB .');
                    return redirect('deposit/setting');

                }
            } catch (RequestException $e) {
                $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                return redirect('deposit/setting');
            }
                
        }
    }

    

    public function cartStbDeposit(Request $request)
    {
        setActionSession();
        $amount = Session::get('coinpaymentAmount');
       
        $data['menu'] = 'withdrawal';
        
        if (!$request->isMethod('post'))
        {   
            $payment_methods = PayoutSetting::with(['paymentMethod:id,name'])
                ->where(['user_id' => auth()->user()->id , 'bank_name' => 'CartSTB'])
                ->get(['id', 'type', 'email', 'account_name', 'account_number', 'bank_name']);
            $data['payment_methods'] = $payment_methods;

            $data['coinpaymentAmount'] = $amount;

            $data['defaultCurrency'] = Wallet::where('user_id', auth()->user()->id)->where('is_default', 'Yes')->first(['id', 'currency_id']);

            //check Decimal Thousand Money Format Preference
            $data['preference'] = getDecimalThousandMoneyFormatPref(['decimal_format_amount']);
            
            if(count($payment_methods) == 0){
                $this->helper->one_time_message('error',' Veuillez ajouter un paramètre de dépôt !');
                return redirect('deposit/cart-setting');
            }else{
                return redirect('deposit');
                // return view('user_dashboard.deposit.setting', $data);

            }

            
        }
        else
        {
                $payoutSetting         = PayoutSetting::where(['id' => $request->payout_setting_id])->first(['account_number', 'swift_code']);
                $PaymentMethod         = PaymentMethod::where(['name' => 'CarteStb'])->first(['id', 'name']);
                $currencyInfo          = CurrencyPaymentMethod::where(['method_id' => $PaymentMethod->id])->first();
                $methodData            = json_decode($currencyInfo->method_data);
                $accountNumber         = $payoutSetting->account_number;
                $cin                   = $payoutSetting->swift_code;

                try {
                $url='https://stbclientb2c.b2clogin.com/stbclientb2c.onmicrosoft.com/b2c_1_signin_ropc/oauth2/v2.0/token';
                $res = $this->client->post($url, [
                    'form_params' => [
                        'username'          => $methodData->username,
                        'password'          => $methodData->password,
                        'grant_type'        => $methodData->grant_type,
                        'scope'             => $methodData->scope,
                        'client_id'         => $methodData->client_id,
                        'response_type'     => $methodData->response_type,
                    ]
                ]);
                $req=json_decode($res->getBody()->getContents(), true);
                $statusCode = $res->getStatusCode();

                if($statusCode == 200){
                    $id_token=$req["id_token"];
                    
                    try {

                    $client = new \GuzzleHttp\Client();
                    
                    $response =$client->get('https://stbgestionapi.azure-api.net/api/account/beta/stb/GetPkByCardNumber?CardNumber='.$accountNumber.'&CIN='.$cin, [
                        'headers' => [
                            'Ocp-Apim-Subscription-Key' => '84bdb97d98be4bb387e4e8d4c80fac3a',
                            'Authorization' => 'Bearer '.$id_token
                        ]
                    ]);
                    
                    $repCode=json_decode((string) $response->getBody(), true);
                    $status = $response->getStatusCode();

                    if($status == 200){

                        if(!isset($repCode["OTP"])){
                            $this->helper->one_time_message('error','Mobile n\'existe pas .');
                            return redirect('deposit/setting');
                        }
                        Session::put('id_token', $id_token);
                        //Session::put('IdTransaction', $repCode["id"]);
                        Session::put('OTP', $repCode["OTP"]);
                        Session::put('currency_id',$request->currency_id);
                        Session::put('accountNumber',$accountNumber);
                        Session::put('coinpaymentAmount',$request->amount);
                        Session::save();
                        return redirect('deposit/cart-stb-deposit-sms');

                    }else{

                        $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                        return redirect('deposit/cart-setting');

                    }

                } catch (RequestException $e) {
                    $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                    return redirect('deposit/cart-setting');
                }
                    

                }else{

                    $this->helper->one_time_message('error','Problème de connexion STB .');
                    return redirect('deposit/cart-setting');

                }

            } catch (RequestException $e) {
                $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                return redirect('deposit/cart-setting');
            }
                
            
        }
    }

    public function stbDepositCartConfirm(Request $request)
    {   
    setActionSession();
    $amount             = Session::get('coinpaymentAmount');
    $id_token           = Session::get('id_token');
    $IdTransaction      = Session::get('IdTransaction');
    $otp                = Session::get('OTP');
    // $currency_id        = Session::get('currency_id');
    // $currency_id        = '1';
    $currency_id        = Session::get('wallet_currency_id');
    $accountNumber      = Session::get('accountNumber');
    $Currency = Currency::where(['id' => $currency_id])->first(['id', 'code']);

    if($Currency->code != "TND"){
        $this->helper->one_time_message('error',' Problème de Devise .');
        return redirect('deposit/cart-setting');
    }

    if($otp != $request->otp){
        $this->helper->one_time_message('error',' Le code de vérification est invalide .');
        return redirect('deposit/cart-setting');
    }
        
       
        if ($request->isMethod('post'))
        {   
            $validation = Validator::make($request->all(), [
                'otp' => 'required',
            ]);
            if ($validation->fails())
            {
                return redirect()->back()->withErrors($validation->errors());
            }

            try {
            $url='https://stbgestionapi.azure-api.net/api/transaction/stb/CashoutCard';
                $res = $this->client->post($url,
                    [
                    'headers' => [
                                    'Ocp-Apim-Subscription-Key' => '291096539f864610b9aa0ac191b08303',
                                    'Content-Type' => 'application/json',
                                    'Authorization' => 'Bearer '.$id_token
                    ],
                    'body'  => json_encode([ 
                        'Amount'=>$amount,
                        'CardNumber'=>$accountNumber,
                        'IdTransaction'=> unique_code(),
                        ])
                ]);
                $req=json_decode($res->getBody()->getContents(), true);
                $statusCode = $res->getStatusCode();
                
                if($statusCode == 200){

                    $PaymentMethod     = PaymentMethod::where(['name' => 'CarteStb'])->first(['id', 'name']);
                    $user_id           = auth()->user()->id;
                    $wallet            = Wallet::where(['currency_id' => $currency_id, 'user_id' => $user_id])->first(['id', 'currency_id']);

                    if (empty($wallet))
                    {
                        $walletInstance              = new Wallet();
                        $walletInstance->user_id     = $user_id;
                        $walletInstance->currency_id = $currency_id;
                        $walletInstance->balance     = 0.00000000;
                        $walletInstance->is_default  = 'No';
                        $walletInstance->save();
                    }

                    $uuid    = unique_code();
                    $feeInfo = $this->helper->getFeesLimitObject([], Deposit, $currency_id, $PaymentMethod->id, null, ['charge_percentage', 'charge_fixed']);
                    $p_calc  = $amount * (@$feeInfo->charge_percentage / 100);

                    try
                    {
                        DB::beginTransaction();
                        $deposit                    = new Deposit();
                        $deposit->uuid              = $uuid;
                        $deposit->charge_percentage = @$feeInfo->charge_percentage ? $p_calc : 0;
                        $deposit->charge_fixed      = @$feeInfo->charge_fixed ? @$feeInfo->charge_fixed : 0;
                        $deposit->status            = 'Success';
                        $deposit->user_id           = $user_id;
                        $deposit->currency_id       = $currency_id;
                        $deposit->payment_method_id = $PaymentMethod->id;
                        $deposit->amount            = $present_amount            = ($amount - ($p_calc+@$feeInfo->charge_fixed));
                        $deposit->save();

                        //Transaction
                        $transaction                           = new Transaction();
                        $transaction->user_id                  = $user_id;
                        $transaction->currency_id              = $currency_id;
                        $transaction->payment_method_id        = $PaymentMethod->id;
                        $transaction->transaction_reference_id = $deposit->id;
                        $transaction->transaction_type_id      = Deposit;
                        $transaction->uuid                     = $uuid;
                        $transaction->subtotal                 = $present_amount;
                        $transaction->percentage               = @$feeInfo->charge_percentage ? @$feeInfo->charge_percentage : 0;
                        $transaction->charge_percentage        = $deposit->charge_percentage;
                        $transaction->charge_fixed             = $deposit->charge_fixed;
                        $total_fees                            = $deposit->charge_percentage + $deposit->charge_fixed;
                        $transaction->total                    = $amount + $total_fees;
                        $transaction->status                   = 'Success';
                        $transaction->save();
                        //Wallet
                        $wallet          = Wallet::where(['user_id' => $user_id, 'currency_id' => $currency_id])->first(['id', 'balance']);
                        $wallet->balance = ($wallet->balance + $present_amount);
                        $wallet->save();
                        DB::commit();
                        // Send notification to admin
                        $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $deposit]);
                        $data['transaction'] = $transaction;
                        return \Redirect::route('deposit.stripe.compteStb')->with(['data' => $data]);
                    
                    }
                    catch (Exception $e)
                    {
                        DB::rollBack();
                        session()->forget(['coinpaymentAmount', 'id_token', 'IdTransaction', 'OTP', 'currency_id', 'accountNumber']);
                        clearActionSession();
                        $this->helper->one_time_message('error', $e->getMessage());
                        return redirect('deposit');
                    }



                }else{

                $this->helper->one_time_message('error',' Le code de vérification est invalide .');
                return redirect('deposit/cart-setting');

                }
            } catch (RequestException $e) {
                $this->helper->one_time_message('error',' Problème de connexion STB .');
                return redirect('deposit/cart-setting');
            }

        }
    }

   public function stbDepositConfirm(Request $request)
    {   
    setActionSession();
    $amount             = Session::get('coinpaymentAmount');
    $id_token           = Session::get('id_token');
    $IdTransaction      = Session::get('IdTransaction');
    $otp                = Session::get('OTP');
    // $currency_id        = Session::get('currency_id');
    // $currency_id        = '1';
    $currency_id        = Session::get('wallet_currency_id');
    
    $accountNumber      = Session::get('accountNumber');
    $Currency = Currency::where(['id' => $currency_id])->first(['id', 'code']);

    if($Currency->code != "TND"){
        $this->helper->one_time_message('error',' Problème de Devise .');
        return redirect('deposit/setting');
    }

    if($otp != $request->otp){
        $this->helper->one_time_message('error',' Le code de vérification est invalide .');
        return redirect('deposit/setting');
    }
        
       
        if ($request->isMethod('post'))
        {   
            $validation = Validator::make($request->all(), [
                'otp' => 'required',
            ]);
            if ($validation->fails())
            {
                return redirect()->back()->withErrors($validation->errors());
            }

            try {
            $url='https://stbgestionapi.azure-api.net/api/transaction/stb/CashOutAccount';
                $res = $this->client->post($url,
                    [
                    'headers' => [
                                    'Ocp-Apim-Subscription-Key' => '291096539f864610b9aa0ac191b08303',
                                    'Content-Type' => 'application/json',
                                    'Authorization' => 'Bearer '.$id_token
                    ],
                    'body'  => json_encode([ 
                        'Amount'=>$amount,
                        'Rib'=>$accountNumber,
                        'IdTransaction'=> unique_code(),
                        ])
                ]);
                $req=json_decode($res->getBody()->getContents(), true);
                $statusCode = $res->getStatusCode();
                if($statusCode == 200){

                    $PaymentMethod     = PaymentMethod::where(['name' => 'CompteStb'])->first(['id', 'name']);
                    $user_id           = auth()->user()->id;
                    $wallet            = Wallet::where(['currency_id' => $currency_id, 'user_id' => $user_id])->first(['id', 'currency_id']);

                    if (empty($wallet))
                    {
                        $walletInstance              = new Wallet();
                        $walletInstance->user_id     = $user_id;
                        $walletInstance->currency_id = $currency_id;
                        $walletInstance->balance     = 0.00000000;
                        $walletInstance->is_default  = 'No';
                        $walletInstance->save();
                    }

                    $uuid    = unique_code();
                    $feeInfo = $this->helper->getFeesLimitObject([], Deposit, $currency_id, $PaymentMethod->id, null, ['charge_percentage', 'charge_fixed']);
                    $p_calc  = $amount * (@$feeInfo->charge_percentage / 100);

                    try
                    {
                        DB::beginTransaction();
                        $deposit                    = new Deposit();
                        $deposit->uuid              = $uuid;
                        $deposit->charge_percentage = @$feeInfo->charge_percentage ? $p_calc : 0;
                        $deposit->charge_fixed      = @$feeInfo->charge_fixed ? @$feeInfo->charge_fixed : 0;
                        $deposit->status            = 'Success';
                        $deposit->user_id           = $user_id;
                        $deposit->currency_id       = $currency_id;
                        $deposit->payment_method_id = $PaymentMethod->id;
                        $deposit->amount            = $present_amount            = ($amount - ($p_calc+@$feeInfo->charge_fixed));
                        $deposit->save();

                        //Transaction
                        $transaction                           = new Transaction();
                        $transaction->user_id                  = $user_id;
                        $transaction->currency_id              = $currency_id;
                        $transaction->payment_method_id        = $PaymentMethod->id;
                        $transaction->transaction_reference_id = $deposit->id;
                        $transaction->transaction_type_id      = Deposit;
                        $transaction->uuid                     = $uuid;
                        $transaction->subtotal                 = $present_amount;
                        $transaction->percentage               = @$feeInfo->charge_percentage ? @$feeInfo->charge_percentage : 0;
                        $transaction->charge_percentage        = $deposit->charge_percentage;
                        $transaction->charge_fixed             = $deposit->charge_fixed;
                        $total_fees                            = $deposit->charge_percentage + $deposit->charge_fixed;
                        $transaction->total                    = $amount + $total_fees;
                        $transaction->status                   = 'Success';
                        $transaction->save();
                        //Wallet
                        $wallet          = Wallet::where(['user_id' => $user_id, 'currency_id' => $currency_id])->first(['id', 'balance']);
                        $wallet->balance = ($wallet->balance + $present_amount);
                        $wallet->save();
                        DB::commit();
                        // Send notification to admin
                        $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $deposit]);
                        $data['transaction'] = $transaction;
                        return \Redirect::route('deposit.stripe.compteStb')->with(['data' => $data]);
                    
                    }
                    catch (Exception $e)
                    {
                        DB::rollBack();
                        session()->forget(['coinpaymentAmount', 'id_token', 'IdTransaction', 'OTP', 'currency_id', 'accountNumber']);
                        clearActionSession();
                        $this->helper->one_time_message('error', $e->getMessage());
                        return redirect('deposit');
                    }



                }else{

                $this->helper->one_time_message('error',' Le code de vérification est invalide .');
                return redirect('deposit/setting');

                }
            } catch (RequestException $e) {
                $this->helper->one_time_message('error',' Problème de connexion STB .');
                return redirect('deposit/setting');
            }

        }
    }

    public function clicToPayDeposit($request)
    {

        $amount        = $request->amount;
        // convert amount to millimes
        $amount = $amount * 1000;
        $currency      = 'TND'; // TND is the only allowed currency for clic to pay payments
        $PaymentMethod = PaymentMethod::where(['name' => 'Clictopay'])->first(['id', 'name']);
        $currencyInfo  = Currency::where(['code' => $currency])->first(['id', 'code']);

        if ($currencyInfo)
        {
            $currencyCode = $currencyInfo->code;
        }
        else
        {
            $currencyCode = "TND";
        }

        $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $currencyInfo->id, 'method_id' => $PaymentMethod->id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
        $methodData            = json_decode($currencyPaymentMethod->method_data);
        if (empty($methodData))
        {
            $this->helper->one_time_message('error', 'For currency' . $currency . ' credential not found!');
            return redirect('payment/fail');
        }

        $unique_code       = unique_code(); 
        $url = 'https://test.clictopay.com/payment/rest/register.do?userName='.$methodData->username.'&password='.$methodData->password.'&amount='.$amount.'&currency=788&language=fr&orderNumber='.$unique_code.'&returnUrl=https://feiz.kycdigigo.online/deposit/clictopay-finish&failUrl=https://feiz.kycdigigo.online/deposit/clictopay-finish&pageView=DESKTOP';

        $response = $this->client->get($url);
        $results = $response->getBody();
        $results = json_decode($results);
        if(!$results->formUrl){
                return redirect('payment/fail');

        }else{
            DB::table('payment_gateway_request')->insert(
                [
                    'currency_id' => $currencyInfo->id,
                    'currency' => $currencyCode,
                    'payment_method_id' => $PaymentMethod->id,
                    'method' => $PaymentMethod->name,
                    'amount' => $amount,
                    'merchant' => Auth::user()->id,
                    'item_name' => 'deposit',
                    'order_no' => $unique_code,
                    'unique_code' => $unique_code,
                    'created_at' =>  Carbon::now(),
                    'gateway_reference' => $results->orderId,
                ]
            );
            return redirect($results->formUrl);
        }
    }

    public function clictopayFinish(Request $request){
        $orderId=$request->orderId;
        $order = DB::table('payment_gateway_request')->where('gateway_reference', $orderId)->get();
        if(count($order->all()) > 0){
            $amount            = $order[0]->amount / 1000;
            $payment_method_id = $order[0]->payment_method_id;
            $user_id           = auth()->user()->id;
            $wallet            = Wallet::where(['currency_id' => $order[0]->currency_id, 'user_id' => $user_id])->first(['id', 'currency_id']);

            if (empty($wallet))
            {
                $walletInstance              = new Wallet();
                $walletInstance->user_id     = $user_id;
                $walletInstance->currency_id = $order[0]->currency_id;
                $walletInstance->balance     = 0.00000000;
                $walletInstance->is_default  = 'No';
                $walletInstance->save();
            }

            $currencyId = isset($wallet->currency_id) ? $wallet->currency_id : $walletInstance->currency_id;
            $currency   = Currency::find($currencyId, ['id', 'code']);
            if ($currency)
            {
                $currencyCode = $currency->code;
            }
            else
            {
                $currencyCode = "TND";
            }

            $uuid    = unique_code();
            $feeInfo = $this->helper->getFeesLimitObject([], Deposit, $currencyId, $payment_method_id, null, ['charge_percentage', 'charge_fixed']);
            $p_calc  = $amount * (@$feeInfo->charge_percentage / 100); //correct calc

            try
            {
                DB::beginTransaction();
                $deposit                    = new Deposit();
                $deposit->uuid              = $uuid;
                $deposit->charge_percentage = @$feeInfo->charge_percentage ? $p_calc : 0;
                $deposit->charge_fixed      = @$feeInfo->charge_fixed ? @$feeInfo->charge_fixed : 0;
                $deposit->status            = 'Success';
                $deposit->user_id           = $user_id;
                $deposit->currency_id       = $currencyId;
                $deposit->payment_method_id = $payment_method_id;
                $deposit->amount            = $present_amount            = ($amount - ($p_calc+@$feeInfo->charge_fixed));
                $deposit->save();

                //Transaction
                $transaction                           = new Transaction();
                $transaction->user_id                  = $user_id;
                $transaction->currency_id              = $currencyId;
                $transaction->payment_method_id        = $payment_method_id;
                $transaction->transaction_reference_id = $deposit->id;
                $transaction->transaction_type_id      = Deposit;
                $transaction->uuid                     = $uuid;
                $transaction->subtotal                 = $present_amount;
                $transaction->percentage               = @$feeInfo->charge_percentage ? @$feeInfo->charge_percentage : 0;
                $transaction->charge_percentage        = $deposit->charge_percentage;
                $transaction->charge_fixed             = $deposit->charge_fixed;
                $total_fees                            = $deposit->charge_percentage + $deposit->charge_fixed;
                $transaction->total                    = $amount + $total_fees;
                $transaction->status                   = 'Success';
                $transaction->save();
                //Wallet
                $wallet          = Wallet::where(['user_id' => $user_id, 'currency_id' => $currencyId])->first(['id', 'balance']);
                $wallet->balance = ($wallet->balance + $present_amount);
                $wallet->save();
                DB::commit();
                // Send notification to admin
                $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $deposit]);
                $data['transaction'] = $transaction;
                DB::table('payment_gateway_request')->where('gateway_reference', $orderId)->delete();
            return \Redirect::route('deposit.stripe.clictopay')->with(['data' => $data]);
            
            }
            catch (Exception $e)
            {
                DB::rollBack();
                session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo']);
                clearActionSession();
                $this->helper->one_time_message('error', $e->getMessage());
                return redirect('deposit');
            }
        }else{
            return redirect('deposit');
        }
    }
    public function compteStbDepositPaymentSuccess()
    {
        if (empty(session('data'))) {
            return redirect('deposit');
        } else {
            $data['transaction'] = session('data')['transaction'];
            //clearing session
            session()->forget(['coinpaymentAmount', 'id_token', 'IdTransaction', 'OTP', 'currency_id', 'accountNumber' , 'data']);
            clearActionSession();
            return view('user_dashboard.deposit.compteStb', $data);
        }
    }
    public function clictopayDepositPaymentSuccess()
    {
        if (empty(session('data'))) {
            return redirect('deposit');
        } else {
            $data['transaction'] = session('data')['transaction'];
            //clearing session
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo', 'data']);
            clearActionSession();
            return view('user_dashboard.deposit.success', $data);
        }
    }

    /**
     * [Extended Function] - starts
     */
    public function currencyList($activeCurrency, $feesLimitCurrency)
    {
        $selectedCurrency = [];
        foreach ($activeCurrency as $aCurrency)
        {
            foreach ($feesLimitCurrency as $flCurrency)
            {
                if ($aCurrency->id == $flCurrency->currency_id && $aCurrency->status == 'Active' && $flCurrency->has_transaction == 'Yes')
                {
                    $selectedCurrency[$aCurrency->id]['id']   = $aCurrency->id;
                    $selectedCurrency[$aCurrency->id]['code'] = $aCurrency->code;
                }
            }
        }
        return $selectedCurrency;
    }
    /**
     * [Extended Function] - ends
     */

    public function bankList($banks, $currencyPaymentMethods)
    {
        $selectedBanks = [];
        $i             = 0;
        foreach ($banks as $bank)
        {
            foreach ($currencyPaymentMethods as $cpm)
            {
                if ($bank->id == json_decode($cpm->method_data)->bank_id)
                {
                    $selectedBanks[$i]['id']             = $bank->id;
                    $selectedBanks[$i]['bank_name']      = $bank->bank_name;
                    $selectedBanks[$i]['is_default']     = $bank->is_default;
                    $selectedBanks[$i]['account_name']   = $bank->account_name;
                    $selectedBanks[$i]['account_number'] = $bank->account_number;
                    $i++;
                }
            }
        }
        return $selectedBanks;
    }

    public function getBankDetailOnChange(Request $request)
    {
        $bank = Bank::with('file:id,filename')->where(['id' => $request->bank])->first(['bank_name', 'account_name', 'account_number', 'file_id']);
        if ($bank)
        {
            $data['status'] = true;
            $data['bank']   = $bank;

            if (!empty($bank->file_id))
            {
                $data['bank_logo'] = $bank->file->filename;
            }
        }
        else
        {
            $data['status'] = false;
            $data['bank']   = "Bank Not FOund!";
        }
        return $data;
    }

    public function getDepositMatchedFeesLimitsCurrencyPaymentMethodsSettingsPaymentMethods(Request $request)
    {
        $feesLimits = FeesLimit::with([
            'currency'       => function ($query)
            {
                $query->where(['status' => 'Active']);
            },
            'payment_method' => function ($q)
            {
                $q->where(['status' => 'Active']);
            },
        ])
            ->where(['transaction_type_id' => $request->transaction_type_id, 'has_transaction' => 'Yes', 'currency_id' => $request->currency_id])
            ->get(['payment_method_id']);

        $currencyPaymentMethods                       = CurrencyPaymentMethod::where('currency_id', $request->currency_id)->where('activated_for', 'like', "%deposit%")->get(['method_id']);
        $currencyPaymentMethodFeesLimitCurrenciesList = $this->currencyPaymentMethodFeesLimitCurrencies($feesLimits, $currencyPaymentMethods);
        $success['paymentMethods']                    = $currencyPaymentMethodFeesLimitCurrenciesList;

        return response()->json(['success' => $success]);
    }

    public function currencyPaymentMethodFeesLimitCurrencies($feesLimits, $currencyPaymentMethods)
    {
        $selectedCurrencies = [];
        foreach ($feesLimits as $feesLimit)
        {
            foreach ($currencyPaymentMethods as $currencyPaymentMethod)
            {
                if ($feesLimit->payment_method_id == $currencyPaymentMethod->method_id)
                {
                    $selectedCurrencies[$feesLimit->payment_method_id]['id']   = $feesLimit->payment_method_id;
                    $selectedCurrencies[$feesLimit->payment_method_id]['name'] = $feesLimit->payment_method->name;
                }
            }
        }
        return $selectedCurrencies;
    }

    //getDepositFeesLimit
    public function getDepositFeesLimit(Request $request)
    {
        $amount  = (double) $request->amount;
        $user_id = auth()->user()->id;
        if (is_null($request->payment_method_id)) {
            $request->payment_method_id = (int) $request->payment_method;
        }
        $feesDetails = $this->helper->getFeesLimitObject([], Deposit, $request->currency_id, $request->payment_method_id, null, ['min_limit', 'max_limit', 'charge_percentage', 'charge_fixed']);
        if (@$feesDetails->max_limit == null) {
            $success['status'] = 200;
            if ((@$amount < @$feesDetails->min_limit)) {
                $success['message'] = __('Minimum amount ') . formatNumber($feesDetails->min_limit);
                $success['status']  = '401';
            }
        } else {
            $success['status'] = 200;
            if ((@$amount < @$feesDetails->min_limit) || (@$amount > @$feesDetails->max_limit)) {
                $success['message'] = __('Minimum amount ') . formatNumber($feesDetails->min_limit) . __(' and Maximum amount ') . formatNumber($feesDetails->max_limit);
                $success['status']  = '401';
            }
        }
        //Code for Amount Limit ends here

        //Code for Fees Limit Starts here
        if (empty($feesDetails))
        {
            $feesPercentage            = 0;
            $feesFixed                 = 0;
            $totalFess                 = $feesPercentage + $feesFixed;
            $totalAmount               = $amount + $totalFess;
            $success['feesPercentage'] = $feesPercentage;
            $success['feesFixed']      = $feesFixed;
            $success['totalFees']      = $totalFess;
            $success['totalFeesHtml']  = formatNumber($totalFess);
            $success['totalAmount']    = $totalAmount;
            $success['pFees']          = $feesPercentage;
            $success['fFees']          = $feesFixed;
            $success['pFeesHtml']      = formatNumber($feesPercentage); 
            $success['fFeesHtml']      = formatNumber($feesFixed);      
            $success['min']            = 0;
            $success['max']            = 0;
            $success['balance']        = 0;
        }
        else
        {
            $feesPercentage            = $amount * ($feesDetails->charge_percentage / 100);
            $feesFixed                 = $feesDetails->charge_fixed;
            $totalFess                 = $feesPercentage + $feesFixed;
            $totalAmount               = $amount + $totalFess;
            $success['feesPercentage'] = $feesPercentage;
            $success['feesFixed']      = $feesFixed;
            $success['totalFees']      = $totalFess;
            $success['totalFeesHtml']  = formatNumber($totalFess);
            $success['totalAmount']    = $totalAmount;
            $success['pFeesHtml']      = formatNumber($feesDetails->charge_percentage); //2.3
            $success['fFeesHtml']      = formatNumber($feesDetails->charge_fixed);      //2.3
            $success['min']            = $feesDetails->min_limit;
            $success['max']            = $feesDetails->max_limit;
            $wallet                    = Wallet::where(['currency_id' => $request->currency_id, 'user_id' => $user_id])->first(['balance']);
            $success['balance']        = @$wallet->balance ? @$wallet->balance : 0;
        }
        return response()->json(['success' => $success]);
    }

    public function store(Request $request)
    {
        //to check action whether action is valid or not
        actionSessionCheck();

        $userid = auth()->user()->id;
        $rules  = [
            'amount' => 'required|numeric',
        ];
        $fieldNames = [
            'amount' => __('Amount'),
        ];
        $validator = Validator::make($request->all(), $rules);
        $validator->setAttributeNames($fieldNames);
        if ($validator->fails())
        {
            return back()->withErrors($validator)->withInput();
        }
        
        $methodId              = $request->method;
        $amount                = $request->amount;
        $PaymentMethod         = PaymentMethod::find($methodId, ['id', 'name']);
        $method                = ucfirst(strtolower($PaymentMethod->name));
        $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => session('wallet_currency_id'), 'method_id' => $methodId])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
        $methodData            = json_decode($currencyPaymentMethod->method_data);
        if (empty($methodData))
        {
            $this->helper->one_time_message('error', __('Payment gateway credentials not found!'));
            return back();
        }
        Session::put('method', $method);
        Session::put('payment_method_id', $methodId);
        Session::put('amount', $amount);
        Session::save();

        $currencyId = session('wallet_currency_id');
        $currency   = Currency::find($currencyId, ['id', 'code']);
        if ($method == 'Paypal')
        {
            if (!isset($currency->code)) {
                $this->helper->one_time_message('error', __("You do not have the requested currency"));
                return redirect()->back();
            }
            if (!isset($methodData->client_id)) {
                $this->helper->one_time_message('error', __('Payment gateway credentials not found!'));
                return redirect()->back();
            }
            $sessionValue         = Session::get('transInfo');
            $data['clientId']     = $methodData->client_id;
            $data['amount']       = (double) $sessionValue['totalAmount'];
            $data['currencyCode'] = $currency->code;
            return view('user_dashboard.deposit.paypal', $data);
        }
        else if ($method == 'Stripe')
        {
            $publishable = $methodData->publishable_key;
            Session::put('publishable', $publishable);
            return redirect('deposit/stripe_payment');
        }
        else if ($method == '2checkout')
        {
            $transInfo             = Session::get('transInfo');
            $currencyId            = $transInfo['currency_id'];
            $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $currencyId, 'method_id' => $methodId])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
            $methodData            = json_decode($currencyPaymentMethod->method_data);

            $seller_id = $methodData->seller_id;
            Session::put('seller_id', $seller_id);
            Session::put('wallet_currency_id', $currencyId);
            Session::put('2Checkout_mode', $methodData->mode);
            return redirect('deposit/checkout/payment');
        }
        else if ($method == 'Payumoney')
        {
            $transInfo = Session::get('transInfo');
            $currencyId            = $transInfo['currency_id'];
            $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $currencyId, 'method_id' => $methodId])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
            $methodData            = json_decode($currencyPaymentMethod->method_data);
            Session::put('mode', $methodData->mode);
            Session::put('key', $methodData->key);
            Session::put('salt', $methodData->salt);
            return redirect('deposit/payumoney_payment');
        }
        else if ($method == 'Coinpayments')
        {
            $data = [];
           
            $this->coinPayment->Setup($methodData->private_key, $methodData->public_key);


            $rates = $this->coinPayment->GetRates(0)['result'];
            $rateofFiatCurrency = $rates[$currency->code]['rate_btc'];
            $rateAmount   = $rateofFiatCurrency * $amount;
       
            $formattedCurrencyList = getFormatedCurrencyList($rates, $rateAmount);

            // dd($formattedCurrencyList['coins'], $formattedCurrencyList['coins_accept'], $formattedCurrencyList['fiat'], $formattedCurrencyList['aliases']);

            $coinPaymentTransaction['coinList'] = $formattedCurrencyList['coins_accept'];
            $coinPaymentTransaction['currencyCode'] = $currency->code;
            $coinPaymentTransaction['type'] = 'deposit';
            Session::put('coinPaymentTransaction', $coinPaymentTransaction);
            
            $data = ['coins' => $formattedCurrencyList['coins'], 'coin_accept' => $formattedCurrencyList['coins_accept'], 'encoded_coin_accept' => json_encode($formattedCurrencyList['coins_accept']), 'fiat' => $formattedCurrencyList['fiat'], 'aliases' => $formattedCurrencyList['aliases']];
            
            $data['amount'] = $amount;
            $data['currencyCode'] = $currency->code;
   
            return view('user_dashboard.deposit.coinpayment', $data);

        }
        else if ($method == 'Payeer')
        {
            $transInfo             = Session::get('transInfo');
            $currencyId            = $transInfo['currency_id'];
            $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $currencyId, 'method_id' => $methodId])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
            $payeer                = json_decode($currencyPaymentMethod->method_data);
            Session::put('payeer_merchant_id', $payeer->merchant_id);
            Session::put('payeer_secret_key', $payeer->secret_key);
            Session::put('payeer_encryption_key', $payeer->encryption_key);
            Session::put('payeer_merchant_domain', $payeer->merchant_domain);
            return redirect('deposit/payeer/payment');
        }
        else
        {
            $this->helper->one_time_message('error', __('Please check your payment method!'));
        }
        return redirect()->back();
    }

    /* Start of Stripe */
    /**
     * Showing Stripe view Page
     */
    public function stripePayment()
    {
        $data['menu']              = 'deposit';
        $data['amount']            = Session::get('amount');
        $data['payment_method_id'] = $method_id = Session::get('payment_method_id');
        $data['content_title']     = 'Deposit';
        $data['icon']              = 'university';
        $sessionValue              = session('transInfo');
        $currencyId                = $sessionValue['currency_id'];
        $currencyPaymentMethod     = CurrencyPaymentMethod::where(['currency_id' => $currencyId, 'method_id' => $method_id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
        $methodData                = json_decode($currencyPaymentMethod->method_data);
        $data['publishable']       = $methodData->publishable_key;
        $data['secretKey']         = $methodData->secret_key;
        if (!isset($data['publishable']) || !isset($data['secretKey'])) {
            $msg = __("Payment gateway credentials not found!");
            $this->helper->one_time_message('error', $msg);
        }
        return view('user_dashboard.deposit.stripe', $data);
    }
    
    public function stripeMakePayment(Request $request)
    {
        $data = [];
        $data['status']  = 200;
        $data['message'] = "Success";
        $validation = Validator::make($request->all(), [
            'cardNumber' => 'required',
            'month'      => 'required|digits_between:1,12|numeric',
            'year'       => 'required|numeric',
            'cvc'        => 'required|numeric',
        ]);
        if ($validation->fails()) {
            $data['message'] = $validation->errors()->first();
            $data['status']  = 401;
            return response()->json([
                'data' => $data
            ]);
        }
        $sessionValue      = session('transInfo');
        $amount            = (double) $sessionValue['totalAmount'];
        $payment_method_id = $method_id = Session::get('payment_method_id');
        $currencyId        = (int) $sessionValue['currency_id'];
        $currency          = Currency::find($currencyId, ["id", "code"]);
        $currencyPaymentMethod     = CurrencyPaymentMethod::where(['currency_id' => $currencyId, 'method_id' => $method_id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
        $methodData        = json_decode($currencyPaymentMethod->method_data);
        $secretKey         = $methodData->secret_key;
        if (!isset($secretKey)) {
            $data['message']  = __("Payment gateway credentials not found!");
            return response()->json([
                'data' => $data
            ]);
        }
        $response = $this->stripeRepository->makePayment($secretKey, round($amount, 2), strtolower($currency->code), $request->cardNumber, $request->month, $request->year, $request->cvc);
        if ($response->getData()->status != 200) {
            $data['status']  = $response->getData()->status;
            $data['message'] = $response->getData()->message;
        } else {
            $data['paymentIntendId'] = $response->getData()->paymentIntendId;
            $data['paymentMethodId'] = $response->getData()->paymentMethodId;
        }
        return response()->json([
            'data' => $data
        ]);
    }
    
    public function stripeConfirm(Request $request)
    {
        $data = [];
        $data['status']  = 401;
        $data['message'] = "Fail";
        try {
            DB::beginTransaction();
            $validation = Validator::make($request->all(), [
                'paymentIntendId'  => 'required',
                'paymentMethodId'  => 'required',
            ]);
            if ($validation->fails()) {
                $data['message'] = $validation->errors()->first();
                return response()->json([
                    'data' => $data
                ]);
            }
            $sessionValue      = session('transInfo');
            $amount            = (double) $sessionValue['totalAmount'];
            $payment_method_id = $method_id                 = Session::get('payment_method_id');
            $currencyId        = (int) $sessionValue['currency_id'];
            $currency          = Currency::find($currencyId, ["id", "code"]);
            $currencyPaymentMethod     = CurrencyPaymentMethod::where(['currency_id' => $currencyId, 'method_id' => $method_id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
            $methodData        = json_decode($currencyPaymentMethod->method_data);
            if (!isset($methodData->secret_key)) {
                $data['message']  = __("Payment gateway credentials not found!");
                return response()->json([
                    'data' => $data
                ]);
            }
            $secretKey = $methodData->secret_key;
            $response  = $this->stripeRepository->paymentConfirm($secretKey, $request->paymentIntendId, $request->paymentMethodId);
            if ($response->getData()->status != 200) {
                $data['message'] = $response->getData()->message;
                return response()->json([
                    'data' => $data
                ]);
            }
            $user_id           = auth()->user()->id;
            $wallet            = Wallet::where(['currency_id' => $sessionValue['currency_id'], 'user_id' => $user_id])->first(['id', 'currency_id']);
            if (empty($wallet)) {
                $walletInstance = Wallet::createWallet($user_id, $sessionValue['currency_id']);
            }
            $currencyId = isset($wallet->currency_id) ? $wallet->currency_id : $walletInstance->currency_id;
            $currency   = Currency::find($currencyId, ['id', 'code']);

            $depositConfirm      = Deposit::success($currencyId, $payment_method_id, $user_id, $sessionValue);
            $response            = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $depositConfirm['deposit']]);
            $data['status']      = 200;
            $data['message']     = "Success";
            $data['transaction'] = $depositConfirm['transaction'];
            Session::put('transaction', $depositConfirm['transaction']);
            DB::commit();
            return response()->json([
                'data' => $data
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Session::forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'publishable', 'transInfo']);
            $data['message'] =  $e->getMessage();
            // $data['transaction'] = $transaction;
            return response()->json([
                'data' => $data
            ]);
        }
    }

    public function stripePaymentSuccess()
    {
        if (empty(session('transaction'))) {
            return redirect('deposit');
        } else {
            $data['transaction'] = session('transaction');
            //clearing session
            session()->forget(['transaction', 'coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'publishable', 'transInfo', 'data']);
            clearActionSession();
            return view('user_dashboard.deposit.success', $data);
        }
    }

    /* End of Stripe */

    /* Start of PayPal */
    
    public function paypalDepositPaymentSuccess($amount)
    {
        try {
            DB::beginTransaction();
            actionSessionCheck();
            if (empty(session('transInfo'))) {
                return redirect('deposit');
            }
            $sessionValue      = session('transInfo');
            // $sessionValue['amount'] = (double) base64_decode($amount);
            $payment_method_id = (int) $sessionValue['payment_method'];
            $user_id           = auth()->user()->id;
            $currencyId        = (int) $sessionValue['currency_id'];
            $wallet            = Wallet::where(['currency_id' => $currencyId, 'user_id' => $user_id])->first(['id', 'currency_id']);
            if (empty($wallet)) {
                $walletInstance = Wallet::createWallet($user_id, $sessionValue['currency_id']);
            }
            $currencyId = isset($wallet->currency_id) ? $wallet->currency_id : $walletInstance->currency_id;
            $currency   = Currency::find($currencyId, ['id', 'code']);
            if (!isset($currency->code)) {
                $this->helper->one_time_message("error", __("You do not have the requested currency"));
                return redirect()->back();
            }
            $depositConfirm      = Deposit::success($currencyId, $payment_method_id, $user_id, $sessionValue);
            $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $depositConfirm['deposit']]);
            $data['transaction'] = $depositConfirm['transaction'];
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo', 'data']);
            DB::commit();
            return view('user_dashboard.deposit.success', $data);
        } catch (Exception $e) {
            DB::rollBack();
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo']);
            clearActionSession();
            $this->helper->one_time_message('error', $e->getMessage());
            return redirect('deposit');
        }
    }

    public function paymentCancel()
    {
        clearActionSession();
        $this->helper->one_time_message('error', __('You have cancelled your payment'));
        return back();
    }
    /* End of PayPal */

    /* Start of 2Checkout */
    public function checkoutPayment()
    {
        $data['menu']              = 'deposit';
        $amount                    = Session::get('amount');
        $data['amount']            = number_format((float) $amount, 2, '.', ''); //2Checkout accepts 2 decimal places only - if not rounded to 2 decimal places, 2Checkout will throw ERROR CODE:PE103.
        $data['payment_method_id'] = Session::get('payment_method_id');
        $data['seller_id']         = Session::get('seller_id');
        $currencyId                = Session::get('wallet_currency_id');
        $data['currency']          = Currency::find($currencyId, ['id', 'code']);
        $data['mode']              = Session::get('2Checkout_mode');
        return view('user_dashboard.deposit.2checkout', $data);
    }

    public function checkoutPaymentConfirm(Request $request)
    {
        actionSessionCheck();

        $payment_method_id = Session::get('payment_method_id');
        $sessionValue      = session('transInfo');
        $user_id           = auth()->user()->id;
        $wallet            = Wallet::where(['currency_id' => $sessionValue['currency_id'], 'user_id' => $user_id])->first(['id', 'currency_id']);
        if (empty($wallet))
        {
            $walletInstance              = new Wallet();
            $walletInstance->user_id     = $user_id;
            $walletInstance->currency_id = $sessionValue['currency_id'];
            $walletInstance->balance     = 0;
            $walletInstance->is_default  = 'No';
            $walletInstance->save();
        }
        $currencyId = isset($wallet->currency_id) ? $wallet->currency_id : $walletInstance->currency_id;
        if ($request->all())
        {
            $amount     = Session::get('amount');
            $uuid       = unique_code();
            $feeInfo    = $this->helper->getFeesLimitObject([], Deposit, $currencyId, $payment_method_id, null, ['charge_percentage', 'charge_fixed']);
            $p_calc     = $sessionValue['amount'] * (@$feeInfo->charge_percentage / 100);
            $total_fees = $p_calc+@$feeInfo->charge_fixed;

            try
            {
                DB::beginTransaction();
                //Deposit
                $deposit                    = new Deposit();
                $deposit->user_id           = $user_id;
                $deposit->currency_id       = $currencyId;
                $deposit->payment_method_id = $payment_method_id;
                $deposit->uuid              = $uuid;
                $deposit->charge_percentage = @$feeInfo->charge_percentage ? $p_calc : 0;
                $deposit->charge_fixed      = @$feeInfo->charge_fixed ? @$feeInfo->charge_fixed : 0;
                $deposit->amount            = $present_amount            = $amount - $total_fees;
                $deposit->status            = 'Success';
                $deposit->save();

                //Transaction
                $transaction                           = new Transaction();
                $transaction->user_id                  = $user_id;
                $transaction->currency_id              = $currencyId;
                $transaction->payment_method_id        = $payment_method_id;
                $transaction->transaction_reference_id = $deposit->id;
                $transaction->transaction_type_id      = Deposit;
                $transaction->uuid                     = $uuid;
                $transaction->subtotal                 = $present_amount;
                $transaction->percentage               = @$feeInfo->charge_percentage ? @$feeInfo->charge_percentage : 0;
                $transaction->charge_percentage        = $deposit->charge_percentage;
                $transaction->charge_fixed             = $deposit->charge_fixed;
                $transaction->total                    = $sessionValue['amount'] + $total_fees;
                $transaction->status                   = 'Success';
                $transaction->save();

                //Wallet
                $wallet          = Wallet::where(['user_id' => $user_id, 'currency_id' => $currencyId])->first(['id', 'balance']);
                $wallet->balance = ($wallet->balance + $present_amount);
                $wallet->save();

                DB::commit();

                // Send mail to admin
                $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $deposit]);

                $data['transaction'] = $transaction;

                return \Redirect::route('deposit.checkout.success')->with(['data' => $data]);
            }
            catch (Exception $e)
            {
                DB::rollBack();
                session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo']);
                clearActionSession();
                $this->helper->one_time_message('error', $e->getMessage());
                return redirect('deposit');
            }
        }
        else
        {
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo']);
            clearActionSession();
            $this->helper->one_time_message('error', __('Please try again later!'));
            return back();
        }
    }

    public function checkoutPaymentSuccess()
    {
        if (empty(session('data')))
        {
            return redirect('deposit');
        }
        else
        {
            $data['transaction'] = session('data')['transaction'];

            //clearing session
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo', 'data']);
            clearActionSession();
            return view('user_dashboard.deposit.success', $data);
        }
    }
    /* End of 2Checkout */

    /* Start of Payumoney */
    public function payumoneyPayment()
    {
        $data['menu'] = 'deposit';

        //Check Currency Code - starts - pm_v2.3
        $currency_id  = session('transInfo')['currency_id'];
        $currencyCode = Currency::where(['id' => $currency_id])->first(['code'])->code;
        if ($currencyCode !== 'INR')
        {
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo']);
            clearActionSession();
            $this->helper->one_time_message('error', __('PayUMoney only supports Indian Rupee(INR)'));
            return redirect('deposit');
        }
        $amount            = session('transInfo')['amount'];             //fixed - was getting total - should get amount
        $data['amount']    = number_format((float) $amount, 2, '.', ''); //Payumoney accepts 2 decimal places only - if not rounded to 2 decimal places, Payumoney will throw.
        $data['mode']      = Session::get('mode');
        $data['key']       = Session::get('key');
        $data['salt']      = Session::get('salt');
        $data['email']     = auth()->user()->email;
        $data['txnid']     = unique_code();
        $data['firstname'] = auth()->user()->first_name;
        return view('user_dashboard.deposit.payumoney', $data);
    }

    public function payumoneyPaymentConfirm()
    {
        actionSessionCheck();

        $sessionValue = session('transInfo');
        $user_id      = auth()->user()->id;
        $amount       = Session::get('amount');
        $uuid         = unique_code();

        if ($_POST['status'] == 'success')
        {
            $feeInfo    = $this->helper->getFeesLimitObject([], Deposit, $sessionValue['currency_id'], $sessionValue['payment_method'], null, ['charge_percentage', 'charge_fixed']);
            $p_calc     = $sessionValue['amount'] * (@$feeInfo->charge_percentage / 100);
            $total_fees = $p_calc+@$feeInfo->charge_fixed;

            try
            {
                DB::beginTransaction();

                //Deposit
                $deposit                    = new Deposit();
                $deposit->user_id           = $user_id;
                $deposit->currency_id       = $sessionValue['currency_id'];
                $deposit->payment_method_id = Session::get('payment_method_id');
                $deposit->uuid              = $uuid;
                $deposit->charge_percentage = @$feeInfo->charge_percentage ? $p_calc : 0;
                $deposit->charge_fixed      = @$feeInfo->charge_fixed ? @$feeInfo->charge_fixed : 0;
                $deposit->amount            = $present_amount            = $amount - $total_fees;
                $deposit->status            = 'Success';
                $deposit->save();

                //Transaction
                $transaction                           = new Transaction();
                $transaction->user_id                  = $user_id;
                $transaction->currency_id              = $sessionValue['currency_id'];
                $transaction->payment_method_id        = Session::get('payment_method_id');
                $transaction->transaction_reference_id = $deposit->id;
                $transaction->transaction_type_id      = Deposit;
                $transaction->uuid                     = $uuid;
                $transaction->subtotal                 = $present_amount;
                $transaction->percentage               = @$feeInfo->charge_percentage ? @$feeInfo->charge_percentage : 0;
                $transaction->charge_percentage        = $deposit->charge_percentage;
                $transaction->charge_fixed             = $deposit->charge_fixed;
                $transaction->total                    = $sessionValue['amount'] + $total_fees;
                $transaction->status                   = 'Success';
                $transaction->save();

                //Wallet
                $chkWallet = Wallet::where(['user_id' => $user_id, 'currency_id' => $sessionValue['currency_id']])->first(['id', 'balance']);
                if (empty($chkWallet))
                {
                    $wallet              = new Wallet();
                    $wallet->user_id     = $user_id;
                    $wallet->currency_id = $sessionValue['currency_id'];
                    $wallet->balance     = $present_amount;
                    $wallet->is_default  = 'No';
                    $wallet->save();
                }
                else
                {
                    $chkWallet->balance = ($chkWallet->balance + $present_amount);
                    $chkWallet->save();
                }
                DB::commit();

                // Send mail to admin
                $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $deposit]);

                $data['transaction'] = $transaction;

                return \Redirect::route('deposit.payumoney.success')->with(['data' => $data]);
            }
            catch (Exception $e)
            {
                DB::rollBack();
                session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'mode', 'key', 'salt', 'transInfo']);
                clearActionSession();
                $this->helper->one_time_message('error', $e->getMessage());
                return redirect('deposit');
            }
        }
    }

    public function payumoneyPaymentSuccess()
    {
        if (empty(session('data')))
        {
            return redirect('deposit');
        }
        else
        {
            $data['transaction'] = session('data')['transaction'];

            //Transaction
            $transaction                           = new Transaction();
            $transaction->user_id                  = auth()->user()->id;
            $transaction->currency_id              = $sessionValue['currency_id'];
            $transaction->payment_method_id        = $sessionValue['payment_method'];
            $transaction->bank_id                  = $request->bank;
            $transaction->file_id                  = $file->id;
            $transaction->uuid                     = $uuid;
            $transaction->transaction_reference_id = $deposit->id;
            $transaction->transaction_type_id      = Deposit;
            $transaction->subtotal                 = $deposit->amount;
            $transaction->percentage               = @$feeInfo->charge_percentage ? @$feeInfo->charge_percentage : 0;
            $transaction->charge_percentage        = $deposit->charge_percentage;
            $transaction->charge_fixed             = $deposit->charge_fixed;
            $transaction->total                    = $sessionValue['amount'] + $deposit->charge_percentage + $deposit->charge_fixed;
            $transaction->status                   = 'Pending'; //in bank deposit, status will be pending
            $transaction->save();

            //Wallet
            $wallet = Wallet::where(['user_id' => auth()->user()->id, 'currency_id' => $sessionValue['currency_id']])->first(['id']);
            if (empty($wallet))
            {
                $wallet              = new Wallet();
                $wallet->user_id     = auth()->user()->id;
                $wallet->currency_id = $sessionValue['currency_id'];
                $wallet->balance     = 0; // as initially, transaction status will be pending
                $wallet->is_default  = 'No';
                $wallet->save();
            }
            DB::commit();

            // Send mail to admin
            $response = $this->helper->sendTransactionNotificationToAdmin('payout', ['data' => $deposit]);

            //For print
            $data['transaction'] = $transaction;

            //clearing session
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'mode', 'key', 'salt', 'transInfo', 'data']);
            clearActionSession();
            return view('user_dashboard.deposit.success', $data);
        }
    }

    public function payumoneyPaymentFail(Request $request)
    {
        if ($_POST['status'] == 'failure')
        {
            clearActionSession();
            $this->helper->one_time_message('error', __('You have cancelled your payment'));
            return redirect('deposit');
        }
    }
    /* End of Payumoney */

    /* Start of CoinPayment */
    public function makeCoinPaymentTransaction(Request $request)
    {
        actionSessionCheck();
       
        $acceptedCoin = Session::get('coinPaymentTransaction')['coinList'];
        $acceptedCoinIso = array_column( $acceptedCoin, 'iso');

        if (empty($request->selected_coin) || !in_array($request->selected_coin, $acceptedCoinIso)) {
            $this->helper->one_time_message('error', __('Please select a crypto coin.'));
            return redirect('deposit');
        }

        // Payment method 
        $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => session('wallet_currency_id'), 'method_id' => Session::get('payment_method_id')])->where('activated_for', 'like', "%deposit%")->first(['method_data']);

        if (! empty($currencyPaymentMethod)) {
            $methodData = json_decode($currencyPaymentMethod->method_data);
        } else {
            $this->helper->one_time_message('error', __('Payment method not found.'));
            return redirect('deposit');
        }
        
        $this->coinPayment->Setup($methodData->private_key, $methodData->public_key);
        
        $uuid = unique_code();

        $transactionData = [
            'amount' => session('transInfo')['totalAmount'],
            'currency1' => Session::get('coinPaymentTransaction')['currencyCode'],
            'currency2' => $request->selected_coin,
            'buyer_email' => auth()->user()->email,
            'address' => '', 
            'buyer_name' => auth()->user()->first_name .' '. auth()->user()->last_name,
            'item_name' => 'Deposit via coinpayment',
            'invoice' => $uuid,
            'ipn_url' => url("coinpayment/check"),
            'cancel_url' => url("deposit/coinpayments/cancel"),
            'success_url' => url("deposit/payment_success"),
        ];

        
        $makeTransaction =  $this->coinPayment->CreateTransaction($transactionData);
        
        if ( $makeTransaction['error'] !== 'ok' ) {
            $this->helper->one_time_message('error', __('Deposit via coinpayment not successfull'));
            return redirect('deposit');
        } 

        $makeTransaction['payload'] = ['type' => Session::get('coinPaymentTransaction')['type'], 'currency' => Session::get('coinPaymentTransaction')['currencyCode']];

        $transactionInfo = $this->getCoinPaymentTransactionInfo($makeTransaction['result']['txn_id']);

        Session::put('transactionDetails', $makeTransaction);
        Session::put('transactionInfo', $transactionInfo);

        if (auth()->check()) {

            $user = auth()->user();

            if ($transactionInfo['error'] == 'ok') {

                $data    = $transactionInfo['result'];
                $payload = $makeTransaction['payload'];

                $saved = [
                    'payment_id'         => $makeTransaction['result']['txn_id'],
                    'payment_address'    => $data['payment_address'],
                    'coin'               => $data['coin'],
                    'fiat'               => $payload['currency'],
                    'status_text'        => $data['status_text'],
                    'status'             => $data['status'],
                    'payment_created_at' => date('Y-m-d H:i:s', $data['time_created']),
                    'expired'            => date('Y-m-d H:i:s', $data['time_expires']),
                    'amount'             => $data['amountf'],
                    'confirms_needed'    => empty($makeTransaction['result']['confirms_needed']) ? 0 : $makeTransaction['result']['confirms_needed'],
                    'qrcode_url'         => empty($makeTransaction['result']['qrcode_url']) ? '' : $makeTransaction['result']['qrcode_url'],
                    'status_url'         => empty($makeTransaction['result']['status_url']) ? '' : $makeTransaction['result']['status_url'],
                ];

                if (isset($makeTransaction['payload']['type']) && $makeTransaction['payload']['type'] == "deposit")
                {
                    //insert into deposit
                    $payment_method_id = Session::get('payment_method_id');
                    $coinpaymentAmount = Session::get('coinpaymentAmount');

                    //charge percentage calculation
                    $curr       = Currency::where('code', $makeTransaction['payload']['currency'])->first(['id']);
                    $currencyId = $curr->id;
                    $feeInfo    = FeesLimit::where(['transaction_type_id' => Deposit, 'currency_id' => $currencyId, 'payment_method_id' => $payment_method_id])->first(['charge_percentage', 'charge_fixed']);

                    $p_calc     = $coinpaymentAmount * (@$feeInfo->charge_percentage / 100);

                    try
                    {
                        DB::beginTransaction();
                        //Deposit
                        $deposit                    = new Deposit();
                        $deposit->uuid              = $uuid;
                        $deposit->charge_percentage = @$feeInfo->charge_percentage ? $p_calc : 0;
                        $deposit->charge_fixed      = @$feeInfo->charge_fixed ? @$feeInfo->charge_fixed : 0;
                        $deposit->amount            = $coinpaymentAmount;
                        $deposit->status            = 'Pending';
                        $deposit->user_id           = auth()->user()->id;
                        $deposit->currency_id       = $currencyId;
                        $deposit->payment_method_id = $payment_method_id;
                        $deposit->save();

                        //Transaction
                        $transaction                           = new Transaction();
                        $transaction->user_id                  = auth()->user()->id;
                        $transaction->currency_id              = $currencyId;
                        $transaction->payment_method_id        = $payment_method_id;
                        $transaction->uuid                     = $uuid;
                        $transaction->transaction_reference_id = $deposit->id;
                        $transaction->transaction_type_id      = Deposit;
                        $transaction->subtotal                 = $coinpaymentAmount;
                        $transaction->percentage               = @$feeInfo->charge_percentage;
                        $transaction->charge_percentage        = $deposit->charge_percentage;
                        $transaction->charge_fixed             = $deposit->charge_fixed;
                        $transaction->total                    = $coinpaymentAmount + $deposit->charge_percentage + $deposit->charge_fixed;
                        $transaction->status                   = 'Pending';
                        $transaction->save();

                        //Wallet creation if request currency wallet does not exist
                        $wallet = Wallet::where(['user_id' => auth()->user()->id, 'currency_id' => $currencyId])->first(['id']);
                        if (empty($wallet))
                        {
                            $wallet              = new Wallet();
                            $wallet->user_id     = auth()->user()->id;
                            $wallet->currency_id = $currencyId;
                            $wallet->balance     = 0;
                            $wallet->is_default  = 'No';
                            $wallet->save();
                        }

                        $payload                   = empty($makeTransaction['payload']) ? [] : $makeTransaction['payload'];
                        $payload['deposit_id']     = $deposit->id;
                        $payload['transaction_id'] = $transaction->id;
                        $payload['uuid']           = $uuid;
                        $payload['receivedf']      = $data['receivedf']; 
                        $payload['time_expires']   = $data['time_expires']; 
                        $payload                   = json_encode($payload);
                        $saved['payload']          = $payload;
                        $user->coinpayment_transactions()->create($saved);

                        DB::commit();

                        
                        // Mail To Admin
                        $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $deposit]);

                        session()->forget(['coinPaymentTransaction', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo']);
                        clearActionSession();

                        return redirect('deposit/coinpayment-transaction-info');

                        
                    }
                    catch (\Exception $e)
                    {
                        DB::rollBack();
                        session()->forget(['coinPaymentTransaction','wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo']);
                        clearActionSession();
                        $exception          = [];
                        $exception['error'] = json_encode($e->getMessage());
                        return $exception;
                    }
                }
            }
        }
    }

    public function getCoinPaymentTransactionInfo($txn_id)
    {
        return  $this->coinPayment->getTransactionInfo(['txid' => $txn_id]);
    }

    public function viewCoinpaymentTransactionInfo()
    {
        $data['transactionDetails'] = Session::get('transactionDetails');
        $data['transactionInfo'] = Session::get('transactionInfo');

        session()->forget(['transactionDetails', 'transactionInfo']);

        return view('user_dashboard.deposit.coinpayment_summery', $data);
    }

    public function coinpaymentCheckStatus(Request $request)
    {
        $responseArray = $request->all();

        $ipn_type = $responseArray['ipn_type'];
        $txn_id = $responseArray['txn_id'];
        $item_name = $responseArray['item_name'];
        $amount1 = floatval($responseArray['amount1']);
        $amount2 = floatval($responseArray['amount2']);
        $currency1 = $responseArray['currency1'];
        $currency2 = $responseArray['currency2'];
        $status = intval($responseArray['status']);
        $status_text = $responseArray['status_text'];

        $coinLog = CoinpaymentLogTrx::where(['status' => 0, 'payment_id' => $txn_id])->first(['id', 'payload', 'payment_id', 'status_text', 'status', 'confirmation_at']);

        $coinLogResponse = isset($coinLog->payload) ? json_decode($coinLog->payload) : ''; 
        
        if (isset($coinLogResponse->type) && isset($coinLogResponse->deposit_id) && ($coinLogResponse->type) == 'deposit') {

            $deposit = Deposit::where(['uuid' => $coinLogResponse->uuid])->first();

            $coinLog->status_text     = $status_text;
            $coinLog->status          = $status;
            $coinLog->confirmation_at = ((INT) $status === 100 || (INT) $status == 2) ? date('Y-m-d H:i:s', time()) : null;
            $coinLog->save();

            if ($status == 100 || $status == 2) {

                try {
                    DB::beginTransaction();

                    if (! empty($deposit)) {
                        $deposit->status = "Success";
                        $deposit->save();
                    }

                    $transaction = Transaction::where(['uuid' => $coinLogResponse->uuid, 'transaction_type_id' => Deposit])->first(['id', 'status']);

                    if (! empty($transaction)) {
                        $transaction->status = "Success";
                        $transaction->save();
                    }

                    $wallet = Wallet::where(['user_id' => $deposit->user_id, 'currency_id' => $deposit->currency_id])->first(['id', 'balance']);

                    if (!empty($wallet)) {
                        $wallet->balance = ($wallet->balance + $deposit->amount);
                        $wallet->save();
                    } else {
                        $wallet              = new Wallet();
                        $wallet->user_id     = $deposit->user_id;
                        $wallet->currency_id = $deposit->currency_id;
                        $wallet->balance     = $deposit->amount;
                        $wallet->is_default  = 'No';
                        $wallet->save();
                    }
                    DB::commit();

                    // Send mail to admin
                    $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $deposit]);
                    
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->helper->one_time_message('error', $e->getMessage());
                }
            }
        } else if (isset($coinLogResponse->type) && isset($coinLogResponse->merchant_payment_id) && ($coinLogResponse->type) == 'merchant') {

            $merchantPayment = MerchantPayment::where(['id' => $coinLogResponse->merchant_payment_id, 'gateway_reference' => $txn_id])->first();

            $coinLog->status_text     = $status_text;
            $coinLog->status          = $status;
            $coinLog->confirmation_at = ((INT) $status === 100 || (INT) $status == 2) ? date('Y-m-d H:i:s', time()) : null;
            $coinLog->save();

            if ($status == 100 || $status == 2) {

                try {
                    \DB::beginTransaction();

                    if (! empty($merchantPayment)) {
                        $merchantPayment->status = "Success";
                        $merchantPayment->save();
                    }

                    $merchantInfo = Merchant::find($merchantPayment->merchant_id, ['id', 'user_id', 'fee']);

                    if (! empty($merchantInfo)) {
                        $transaction = Transaction::where("transaction_reference_id", $coinLogResponse->merchant_payment_id)->where('transaction_type_id', Payment_Received)->first(['id', 'status']);

                        if (! empty($transaction)) {
                            $transaction->status = "Success";
                            $transaction->save();
                        }

                    }
                    
                    $merchantWallet = Wallet::where(['user_id' => $merchantInfo->user_id, 'currency_id' => $merchantPayment->currency_id])->first(['id', 'balance']);

                    if (! empty($merchantWallet)) {
                        $merchantWallet->balance = ($merchantWallet->balance + $merchantPayment->amount);
                        $merchantWallet->save();
                    } else {
                        $merchantWallet              = new Wallet();
                        $merchantWallet->user_id     = $merchantInfo->user_id;
                        $merchantWallet->currency_id = $merchantPayment->currency_id;
                        $merchantWallet->balance     = $merchantPayment->amount;
                        $merchantWallet->is_default  = 'No';
                        $merchantWallet->save();
                    }
                    \DB::commit();

                    // Send mail to admin
                    $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $merchantPayment]);
                    
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->helper->one_time_message('error', $e->getMessage());
                }
            }
        }
    }
    /* End of CoinPayment */

    /* Start of Payeer */
    public function payeerPayement()
    {
        $data['menu']       = 'deposit';
        $amount             = Session::get('amount');
        $transInfo          = Session::get('transInfo');
        $currency           = Currency::where(['id' => $transInfo['currency_id']])->first(['code']);
        $payeer_merchant_id = Session::get('payeer_merchant_id');
        $data['m_shop']     = $m_shop     = $payeer_merchant_id;
        $data['m_orderid']  = $m_orderid  = six_digit_random_number();
        $data['m_amount'] = $m_amount = number_format((float) $amount, 2, '.', ''); //Payeer might throw error, if 2 decimal place amount is not sent to Payeer server

        // $data['m_amount'] = $m_amount = "0.01"; // for test purpose

        $data['m_curr']             = $m_curr             = $currency->code;
        $data['form_currency_code'] = $form_currency_code = $currency->code;
        $data['m_desc']             = $m_desc             = base64_encode('Deposit');
        $payeer_secret_key          = Session::get('payeer_secret_key');
        $m_key                      = $payeer_secret_key;
        $arHash                     = array(
            $m_shop,
            $m_orderid,
            $m_amount,
            $m_curr,
            $m_desc,
        );
        $merchantDomain = Session::get('payeer_merchant_domain');
        $arParams       = array(
            'success_url' => url('/') . '/deposit/payeer/payment/confirm',
            'status_url'  => url('/') . '/deposit/payeer/payment/status',
            'fail_url'    => url('/') . '/deposit/payeer/payment/fail',
            'reference'   => array(
                'email' => auth()->user()->email,
                'name'  => auth()->user()->first_name . ' ' . auth()->user()->last_name,
            ),
            'submerchant' => $merchantDomain,
        );
        $cipher                = 'AES-256-CBC';
        $merchantEncryptionKey = Session::get('payeer_encryption_key');
        $key                   = md5($merchantEncryptionKey . $m_orderid);                                                            //key from (payeer.com->merchant settings->Key for encryption additional parameters)
        $m_params              = @urlencode(base64_encode(openssl_encrypt(json_encode($arParams), $cipher, $key, OPENSSL_RAW_DATA))); // this throws error if '@' symbol is not used
        $arHash[]              = $data['m_params']              = $m_params;
        $arHash[]              = $m_key;
        $data['sign']          = strtoupper(hash('sha256', implode(":", $arHash)));
        return view('user_dashboard.deposit.payeer', $data);

        // return redirect('deposit/payeer/payment/confirm');
    }

    public function payeerPayementConfirm(Request $request)
    {
        if (isset($request['m_operation_id']) && isset($request['m_sign']))
        {
            $payeer_secret_key = Session::get('payeer_secret_key');

            $m_key  = $payeer_secret_key;
            $arHash = array(
                $request['m_operation_id'],
                $request['m_operation_ps'],
                $request['m_operation_date'],
                $request['m_operation_pay_date'],
                $request['m_shop'],
                $request['m_orderid'],
                $request['m_amount'],
                $request['m_curr'],
                $request['m_desc'],
                $request['m_status'],
            );

            //additional parameters
            if (isset($request['m_params']))
            {
                $arHash[] = $request['m_params'];
            }

            $arHash[]  = $m_key;
            $sign_hash = strtoupper(hash('sha256', implode(':', $arHash)));

            if ($request['m_sign'] == $sign_hash && $request['m_status'] == 'success')
            {
                actionSessionCheck();
                $sessionValue = session('transInfo');

                $user_id           = auth()->user()->id;
                $uuid              = unique_code();
                $feeInfo           = $this->helper->getFeesLimitObject([], Deposit, $sessionValue['currency_id'], $sessionValue['payment_method'], null, ['charge_percentage', 'charge_fixed']);
                $p_calc            = $sessionValue['amount'] * (@$feeInfo->charge_percentage / 100);
                $total_fees        = $p_calc+@$feeInfo->charge_fixed;
                $payment_method_id = $sessionValue['payment_method'];
                $sessionAmount     = Session::get('amount');
                $amount            = $sessionAmount;

                try
                {
                    DB::beginTransaction();
                    //Deposit
                    $deposit                    = new Deposit();
                    $deposit->user_id           = auth()->user()->id;
                    $deposit->currency_id       = $sessionValue['currency_id'];
                    $deposit->payment_method_id = $payment_method_id;
                    $deposit->uuid              = $uuid;
                    $deposit->charge_percentage = @$feeInfo->charge_percentage ? $p_calc : 0;
                    $deposit->charge_fixed      = @$feeInfo->charge_fixed ? @$feeInfo->charge_fixed : 0;
                    $deposit->amount            = $present_amount            = ($amount - ($p_calc + (@$feeInfo->charge_fixed)));
                    $deposit->status            = 'Success';
                    $deposit->save();

                    //Transaction
                    $transaction                           = new Transaction();
                    $transaction->user_id                  = auth()->user()->id;
                    $transaction->currency_id              = $sessionValue['currency_id'];
                    $transaction->payment_method_id        = $payment_method_id;
                    $transaction->transaction_reference_id = $deposit->id;
                    $transaction->transaction_type_id      = Deposit;
                    $transaction->uuid                     = $uuid;
                    $transaction->subtotal                 = $present_amount;
                    $transaction->percentage               = @$feeInfo->charge_percentage ? @$feeInfo->charge_percentage : 0;
                    $transaction->charge_percentage        = $deposit->charge_percentage;
                    $transaction->charge_fixed             = $deposit->charge_fixed;
                    $transaction->total                    = $sessionValue['amount'] + $total_fees;
                    $transaction->status                   = 'Success';
                    $transaction->save();

                    //Wallet
                    $chkWallet = Wallet::where(['user_id' => auth()->user()->id, 'currency_id' => $sessionValue['currency_id']])->first(['id', 'balance']);
                    if (empty($chkWallet))
                    {
                        //if wallet does not exist, create it
                        $wallet              = new Wallet();
                        $wallet->user_id     = auth()->user()->id;
                        $wallet->currency_id = $sessionValue['currency_id'];
                        $wallet->balance     = $deposit->amount;
                        $wallet->is_default  = 'No';
                        $wallet->save();
                    }
                    else
                    {
                        //add deposit amount to existing wallet
                        $chkWallet->balance = ($chkWallet->balance + $deposit->amount);
                        $chkWallet->save();
                    }
                    DB::commit();

                    // Send mail to admin
                    $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $deposit]);

                    $data['transaction'] = $transaction;

                    return \Redirect::route('deposit.payeer.success')->with(['data' => $data]);
                }
                catch (Exception $e)
                {
                    DB::rollBack();
                    session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'payeer_merchant_id', 'payeer_secret_key',
                    'payeer_encryption_key', 'payeer_merchant_domain','transInfo']);
                    $this->helper->one_time_message('error', $e->getMessage());
                    return redirect('deposit');
                }
            }
            else
            {
                session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'payeer_merchant_id', 'payeer_secret_key',
                'payeer_encryption_key', 'payeer_merchant_domain','transInfo']);
                clearActionSession();
                $this->helper->one_time_message('error', __('Please try again later!'));
                return back();
            }
        }
    }

    public function payeerPayementSuccess()
    {
        if (empty(session('data')))
        {
            return redirect('deposit');
        }
        else
        {
            $data['transaction'] = session('data')['transaction'];

            //clearing session
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'payeer_merchant_id', 'payeer_secret_key',
                'payeer_encryption_key', 'payeer_merchant_domain','transInfo', 'data']);
            clearActionSession();
            return view('user_dashboard.deposit.success', $data);
        }
    }

    public function payeerPayementStatus(Request $request)
    {
        return 'Payeer Status Page =>'.$request->all();
    }

    public function payeerPayementFail()
    {
        $this->helper->one_time_message('error', __('You have cancelled your payment'));
        return redirect('deposit');
    }
    /* End of Payeer */

    /* Start of Bank Payment Method */
    public function bankPaymentConfirm(Request $request)
    {
        actionSessionCheck();
        $sessionValue = session('transInfo');
        if (empty(session('transInfo'))) {
            return redirect('deposit');
        }
        try {
            DB::beginTransaction();
            if ($request->hasFile('attached_file')) {
                $fileName     = $request->file('attached_file');
                $originalName = $fileName->getClientOriginalName();
                $uniqueName   = strtolower(time() . '.' . $fileName->getClientOriginalExtension());
                $file_extn    = strtolower($fileName->getClientOriginalExtension());
                $path         = 'uploads/files/bank_attached_files';
                $uploadPath   = public_path($path);
                $fileName->move($uploadPath, $uniqueName);

                $file               = new File();
                $file->user_id      = auth()->user()->id;
                $file->filename     = $uniqueName;
                $file->originalname = $originalName;
                $file->type         = $file_extn;
                $file->save();
            }
            $depositConfirm = Deposit::success($sessionValue['currency_id'], $sessionValue['payment_method'], auth()->user()->id, $sessionValue, "Pending", "bank", $file->id, $request->bank);
            $wallet = Wallet::where(['user_id' => auth()->user()->id, 'currency_id' => $sessionValue['currency_id']])->first(['id']);
            if (empty($wallet)) {
                $wallet = Wallet::createWallet($user_id, $sessionValue['currency_id']);
            }
            DB::commit();
            $response = $this->helper->sendTransactionNotificationToAdmin('deposit', ['data' => $depositConfirm['deposit']]);
            $data['transaction'] = $depositConfirm['transaction'];
            return \Redirect::route('deposit.bank.success')->with(['data' => $data]);
        } catch (Exception $e) {
            DB::rollBack();
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo']);
            clearActionSession();
            $this->helper->one_time_message('error', $e->getMessage());
            return redirect('deposit');
        }
    }

    public function bankPaymentSuccess()
    {
        if (empty(session('data')))
        {
            return redirect('deposit');
        }
        else
        {
            $data['transaction'] = session('data')['transaction'];

            //clearing session
            session()->forget(['coinpaymentAmount', 'wallet_currency_id', 'method', 'payment_method_id', 'amount', 'transInfo', 'data']);
            clearActionSession();
            return view('user_dashboard.deposit.success', $data);
        }
    }
    /* End of Bank Payment Method */

    public function depositPrintPdf($trans_id)
    {
        $data['companyInfo'] = Setting::where(['type' => 'general', 'name' => 'logo'])->first(['value']);

        $data['transactionDetails'] = Transaction::with(['payment_method:id,name', 'currency:id,symbol,code'])
            ->where(['id' => $trans_id])
            ->first(['uuid', 'created_at', 'status', 'currency_id', 'payment_method_id', 'subtotal', 'charge_percentage', 'charge_fixed', 'total']);

        $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/tmp']);
        $mpdf = new \Mpdf\Mpdf([
            'mode'        => 'utf-8',
            'format'      => 'A3',
            'orientation' => 'P',
        ]);
        $mpdf->autoScriptToLang         = true;
        $mpdf->autoLangToFont           = true;
        $mpdf->allow_charset_conversion = false;
        $mpdf->SetJS('this.print();');
        $mpdf->WriteHTML(view('user_dashboard.deposit.depositPaymentPdf', $data));
        $mpdf->Output('sendMoney_' . time() . '.pdf', 'I'); //
    }
}
