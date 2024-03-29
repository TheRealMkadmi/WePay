<?php

namespace App\Http\Controllers\Users;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\BadResponseException;

use Guzzle\Http\EntityBody;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Stream;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Helpers\Common;
use App\Models\{Currency,
    NotificationSetting,
    EmailTemplate,
    PaymentMethod,
    CurrencyPaymentMethod,
    PayoutSetting,
    Transaction,
    Withdrawal,
    FeesLimit,
    Setting,
    Country,
    Wallet,
    User
};
use Illuminate\Support\Facades\{Validator,
    Session,
    Auth
};



use App\Rules\CheckWalletBalance;
   

class WithdrawalController extends Controller
{
    protected $helper;
    protected $withdrawal;
    protected $email;

    public function __construct()
    {
        $this->helper     = new Common();
        $this->email      = new EmailController();
        $this->withdrawal = new Withdrawal();
        $this->client = new \GuzzleHttp\Client();

    }

    //Payout Setting starts
    public function payouts()
    {
        setActionSession();
        $data['menu']    = 'payout';
        $data['payouts'] = Withdrawal::with(['payment_method:id,name', 'withdrawal_detail:id,withdrawal_id,account_name,account_number,bank_name', 'currency:id,code']) //optimized by parvez
            ->where(['user_id' => auth()->user()->id])->orderBy('withdrawals.created_at', 'desc')
            ->select('id', 'created_at', 'payment_method_id', 'amount', 'subtotal', 'currency_id', 'status', 'payment_method_info')
            ->paginate(10);

        // if no payout setting
        $data['payoutSettings'] = PayoutSetting::where(['user_id' => auth()->user()->id])->get(['id']);
        return view('user_dashboard.withdrawal.payouts', $data);
    }

    public function payoutSetting()
    {
        $data['menu']           = 'payout';
        $data['payoutSettings'] = PayoutSetting::with(['paymentMethod:id,name'])
        ->where(['user_id' => auth()->user()->id])
        ->paginate(10);
        $data['countries']      = Country::get(['id', 'name']);
        $data['paymentMethods'] = PaymentMethod::whereNotIn('id', [1, 2, 4, 5, 7, 8, 9])->where(['status' => 'Active'])->get(['id', 'name']);
        return view('user_dashboard.withdrawal.payoutSetting', $data);
    }

    public function payoutSettingStore(Request $request)
    {
        
        if($request->type != "CompteStb" && $request->type != "CarteStb")
        {

            $rules = array(
                'type'  => 'required',
                'email' => 'nullable|email',
            );
            $fieldNames = array(
                'type'  => 'Type',
                'email' => 'Email',
            );
    
            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails())
        {
            return back()->withErrors($validator)->withInput();
        }
        else
        {

            $type                   = $request->type;
            $payoutSetting          = new PayoutSetting();
            $payoutSetting->type    = $type;
            $payoutSetting->user_id = auth()->user()->id;
            if ($type == 6)
            {
                $payoutSetting->account_name        = $request->account_name;
                $payoutSetting->account_number      = $request->account_number;
                $payoutSetting->swift_code          = $request->swift_code;
                $payoutSetting->bank_name           = $request->bank_name;
                $payoutSetting->bank_branch_name    = $request->branch_name;
                $payoutSetting->bank_branch_city    = $request->branch_city;
                $payoutSetting->bank_branch_address = $request->branch_address;
                $payoutSetting->country             = $request->country;
            }
            elseif ($type == 8)
            {
                $payoutSetting->account_number = $request->payeer_account_no;
            }
            elseif ($type == 9)
            {
                $payoutSetting->account_number = $request->perfect_money_account_no;
            }
            else
            {
                $payoutDuplicateEmailCheck = PayoutSetting::where(['user_id' => auth()->user()->id, 'email' => $request->email])->exists();
                
                if ($payoutDuplicateEmailCheck) {
                    $this->helper->one_time_message('error', __('You can not add same email again as payout settings!'));
                    return back();
                }
                $payoutSetting->email = $request->email;
            }
            $payoutSetting->save();

            $this->helper->one_time_message('success', __('Payout Setting Created Successfully!'));
            return back();
        }

        }else{

            if($request->type == "CompteStb"){

            $payoutSetting                      = new PayoutSetting();
            $payoutSetting->type                = 6;
            $payoutSetting->user_id             = auth()->user()->id;
            $payoutSetting->account_name        = $request->account_name1;
            $payoutSetting->account_number      = $request->account_number1;
            $payoutSetting->swift_code          = $request->swift_code1;
            $payoutSetting->bank_name           = "STB";
            $payoutSetting->bank_branch_name    = "STB";
            $payoutSetting->bank_branch_city    = $request->branch_city1;
            $payoutSetting->bank_branch_address = $request->branch_address1;
            $payoutSetting->country             = 217;
            $payoutSetting->save();
            $this->helper->one_time_message('success', __('Payout Setting Created Successfully!'));
            return back();

            }

            if($request->type == "CarteStb"){

                $payoutSetting                      = new PayoutSetting();
                $payoutSetting->type                = 6;
                $payoutSetting->user_id             = auth()->user()->id;
                $payoutSetting->account_name        = $request->account_name2;
                $payoutSetting->account_number      = $request->account_number2;
                $payoutSetting->swift_code          = $request->swift_code2;
                $payoutSetting->bank_name           = "CartSTB";
                $payoutSetting->bank_branch_name    = "CartSTB";
                $payoutSetting->bank_branch_city    = $request->branch_city2;
                $payoutSetting->bank_branch_address = $request->branch_address2;
                $payoutSetting->country             = 217;
                $payoutSetting->save();
                $this->helper->one_time_message('success', __('Payout Setting Created Successfully!'));
                return back();
    
                }
            
        }
        

        
    }

    public function payoutSettingUpdate(Request $request)
    {
        $id      = $request->setting_id;
        $setting = PayoutSetting::find($id);
        if (!$setting)
        {
            $this->helper->one_time_message('error', __('Payout Setting not found !'));
            return back();
        }
        if ($setting->type == 6)
        {
            

            if($setting->bank_name == "STB"){

                $setting->account_name        = $request->account_name1;
                $setting->account_number      = $request->account_number1;
                $setting->bank_branch_city    = $request->branch_city1;
                $setting->bank_branch_address = $request->branch_address1;
                $setting->swift_code          = $request->swift_code1;

            }else if($setting->bank_name == "CartSTB")
            {
                $setting->account_name        = $request->account_name2;
                $setting->account_number      = $request->account_number2;
                $setting->bank_branch_city    = $request->branch_city2;
                $setting->bank_branch_address = $request->branch_address2;
                $setting->swift_code          = $request->swift_code2;

            }else{

                $setting->account_name        = $request->account_name;
                $setting->account_number      = $request->account_number;
                $setting->bank_branch_name    = $request->bank_name;
                $setting->bank_branch_city    = $request->branch_city;
                $setting->bank_branch_address = $request->branch_address;
                $setting->country             = $request->country;
                $setting->swift_code          = $request->swift_code;
                $setting->bank_name           = $request->bank_name;

            }
            

        }
        elseif ($setting->type == 8)
        {
            $setting->account_number = $request->payeer_account_no;
        }
        elseif ($setting->type == 9)
        {
            $setting->account_number = $request->perfect_money_account_no;
        }
        else
        {
            $payoutDuplicateEmailCheck = PayoutSetting::where(['user_id' => auth()->user()->id, 'email' => $request->email])
                                            ->where(function($query) use ($id){
                                                $query->where('id', '!=', $id);
                                            })
                                            ->exists();
            
            if ($payoutDuplicateEmailCheck) {
                $this->helper->one_time_message('error', __('You can not add same email again as payout settings!'));
                return back();
            }
            $setting->email = $request->email;
        }
        $setting->save();

        $this->helper->one_time_message('success', __('Payout Setting Updated Successfully!'));
        return back();
    }

    public function payoutSettingDestroy(Request $request)
    {
        $id = $request->id;
        //used auth to verify payout of auth user
        $payout = auth()->user()->payoutSettings->where('id', $id)->first();
        $payout->delete();

        $this->helper->one_time_message('success', __('Payout Setting Deleted Successfully!'));
        return back();
    }
    //Payout Setting ends

    //Payout - starts
    public function withdrawalCreate(Request $request)
    {
        setActionSession();
        $data['menu'] = 'withdrawal';

        if (!$request->isMethod('post'))
        {
            $data['payment_methods'] = $payment_methods = PayoutSetting::with(['paymentMethod:id,name'])
                ->where(['user_id' => auth()->user()->id])
                ->get(['id', 'type', 'email', 'account_name', 'account_number', 'bank_name']);

            $data['defaultCurrency'] = Wallet::where('user_id', auth()->user()->id)->where('is_default', 'Yes')->first(['id', 'currency_id']);

            //check Decimal Thousand Money Format Preference
            $data['preference'] = getDecimalThousandMoneyFormatPref(['decimal_format_amount']);

            return view('user_dashboard.withdrawal.create', $data);
        }
        else
        {
            $rules = array(
                'amount'            => ['required','numeric', new CheckWalletBalance],
                'payout_setting_id' => 'required',
                'currency_id'       => 'required',
            );
            $fieldNames = array(
                'amount'            => 'Amount',
                'payout_setting_id' => 'Payment method',
                'currency_id'       => 'Currency',
            );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails())
            {
                return back()->withErrors($validator)->withInput();
            }
            else
            {
                //backend validation starts
                $request['transaction_type_id'] = Withdrawal;
                $myResponse                     = $this->withdrawalAmountLimitCheck($request);
                if ($myResponse) {
                    if ($myResponse->getData()->success->status == 200) {
                        if ($myResponse->getData()->success->totalAmount > $myResponse->getData()->success->balance) {
                            return back()->withErrors(__("Not have enough balance !"))->withInput();
                        }
                    } elseif ($myResponse->getData()->success->status == 401) {
                        return back()->withErrors($myResponse->getData()->success->message)->withInput();
                    }
                }
                //backend valdation ends

                $wallet = Wallet::with(['currency:id,symbol'])->where(['user_id' => auth()->user()->id, 'currency_id' => $request->currency_id])->first(['currency_id']);
                if ($wallet) {
                    $data['transInfo']['currSymbol'] = $wallet->currency->symbol;
                    $data['transInfo']['amount']     = $request->amount;

                    $feesInfo = FeesLimit::where(['transaction_type_id' => Withdrawal, 'currency_id' => $wallet->currency_id, 'payment_method_id' => $request->payment_method_id])
                        ->first(['charge_percentage', 'charge_fixed']);

                    $percentageCalc = $request->amount * ($feesInfo->charge_percentage / 100);
                    $fee            = $percentageCalc + $feesInfo->charge_fixed;

                    $data['transInfo']['fee']            = $fee;
                    $data['transInfo']['totalAmount']    = $request->amount + $fee;
                    $data['transInfo']['payout_setting'] = $payout_setting = PayoutSetting::find($request->payout_setting_id);

                    //saving in sessions
                    $withdrawalData['payout_setting_id']   = $request->payout_setting_id;
                    $withdrawalData['currency_id']         = $request->currency_id;
                    $withdrawalData['totalAmount']         = $request->amount + $fee;
                    $withdrawalData['amount']              = $request->amount;
                    $withdrawalData['payment_method_info'] = $request->payment_method_info;
                    $withdrawalData['payment_method_id']   = $request->payment_method_id;
                    session(['withdrawalData' => $withdrawalData]);
                    if($payout_setting->bank_name == "STB"){
                        return $this->compteWithdrawalSetting();
                    }

                    if($payout_setting->bank_name == "CartSTB"){
                        return $this->cartWithdrawalSetting();
                    }
                    
                    return view('user_dashboard.withdrawal.confirmation', $data);
                }
            }
        }
    }

    //get Withdrawal FeesLimits Active Currencies
    public function getWithdrawalFeesLimitsActiveCurrencies(Request $request)
    {
        $payment_met_id      = $request->payment_method_id;
        $transaction_type_id = $request->transaction_type_id;

        $wallets = Wallet::where(['user_id' => auth()->user()->id])->whereHas('active_currency', function ($q) use ($payment_met_id, $transaction_type_id)
        {
            $q->whereHas('fees_limit', function ($query) use ($payment_met_id, $transaction_type_id)
            {
                $query->where('has_transaction', 'Yes')->where('transaction_type_id', $transaction_type_id)->where('payment_method_id', $payment_met_id);
            });
        })
            ->with(['active_currency:id,code', 'active_currency.fees_limit:id,currency_id']) //Optimized
            ->get(['currency_id', 'is_default']);

        $arr        = [];
        $currencies = $wallets->map(function ($wallet) //map acts as foreach but we can customize the index as preferred
            {
                $arr['id']             = $wallet->active_currency->id;
                $arr['code']           = $wallet->active_currency->code;
                $arr['default_wallet'] = $wallet->is_default;
                return $arr;
            });
        $success['currencies'] = $currencies;
        return response()->json(['success' => $success]);
    }

    //Code for withdrawal Amount Limit Check
    public function withdrawalAmountLimitCheck(Request $request)
    {
        $amount      = $request->amount;
        $user_id     = auth()->user()->id;
        $feesDetails = FeesLimit::where(['transaction_type_id' => $request->transaction_type_id, 'payment_method_id' => $request->payment_method_id, 'currency_id' => $request->currency_id])
            ->first(['max_limit', 'min_limit', 'charge_percentage', 'charge_fixed']);

        if (@$feesDetails->max_limit == null)
        {
            if ((@$amount < @$feesDetails->min_limit))
            {
                $success['message'] = __('Minimum amount ') . formatNumber($feesDetails->min_limit);
                $success['status']  = '401';
            }
            else
            {
                $success['status'] = 200;
            }
        }
        else
        {
            if ((@$amount < @$feesDetails->min_limit) || (@$amount > @$feesDetails->max_limit))
            {
                $success['message'] = __('Minimum amount ') . formatNumber($feesDetails->min_limit) . __(' and Maximum amount ') . formatNumber($feesDetails->max_limit);
                $success['status']  = '401';
            }
            else
            {
                $success['status'] = 200;
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
            $success['totalHtml']      = formatNumber($totalFess);
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
            $success['totalHtml']      = formatNumber($totalFess);
            $success['totalAmount']    = $totalAmount;
            $success['pFees']          = $feesDetails->charge_percentage;
            $success['fFees']          = $feesDetails->charge_fixed;
            $success['pFeesHtml']      = formatNumber($feesDetails->charge_percentage);
            $success['fFeesHtml']      = formatNumber($feesDetails->charge_fixed);
            $success['min']            = $feesDetails->min_limit;
            $success['max']            = $feesDetails->max_limit;
            $wallet                    = Wallet::where(['currency_id' => $request->currency_id, 'user_id' => $user_id])->first(['balance']);
            $success['balance']        = @$wallet->balance ? @$wallet->balance : 0;
        }
        return response()->json(['success' => $success]);
    }

    public function stbPayoutSms(){
        
        return view('user_dashboard.withdrawal.sms');

    }
    
    public function cartStbPayoutSms(){
        
        return view('user_dashboard.withdrawal.cartSms');

    }

    public function compteWithdrawalSetting()
    {
        $sessionValue = Session::get('withdrawalData');
        if (empty($sessionValue))
        {
            return redirect('payout');
        } 
        actionSessionCheck();
        $amount                = $sessionValue['amount'];
        $payout_setting_id     = $sessionValue['payout_setting_id'];
        $currency_id           = $sessionValue['currency_id'];
        $payoutSetting         = PayoutSetting::where(['id' => $payout_setting_id])->first(['account_number', 'swift_code']);
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
                
                $response = $client->get('https://stbgestionapi.azure-api.net/api/account/beta/stb/getpkbyrib?RIB='.$accountNumber.'&CIN='.$cin, [
                    'headers' => [
                        'Ocp-Apim-Subscription-Key' => '84bdb97d98be4bb387e4e8d4c80fac3a',
                        'Authorization' => 'Bearer '.$id_token
                    ]
                ]);

                $repCode = json_decode((string) $response->getBody(), true);
                $status  = $response->getStatusCode();
                
                if($status == 200){
                    if(!isset($repCode["OTP"])){
                        $this->helper->one_time_message('error','Mobile n\'existe pas .');
                        return redirect('payout');
                    }
                    Session::put('id_token', $id_token);
                    Session::put('IdTransaction', $repCode["id"]);
                    Session::put('OTP', $repCode["OTP"]);
                    Session::put('currency_id',$currency_id);
                    Session::put('accountNumber',$accountNumber);
                    Session::save();
                    return redirect('payout/stb-payout-sms');

                }else{

                    $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                    return redirect('payout');

                }

            } catch (RequestException $e) {
                $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                return redirect('payout');
            }
                

            }else{

                $this->helper->one_time_message('error','Problème de connexion STB .');
                return redirect('payout');

            }
        } catch (RequestException $e) {
            $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
            return redirect('payout');
        }



       
    }

    
    public function cartWithdrawalSetting()
    {
        $sessionValue = Session::get('withdrawalData');
        if (empty($sessionValue))
        {
            return redirect('payout');
        } 
        actionSessionCheck();
        $amount                = $sessionValue['amount'];
        $payout_setting_id     = $sessionValue['payout_setting_id'];
        $currency_id           = $sessionValue['currency_id'];
        $payoutSetting         = PayoutSetting::where(['id' => $payout_setting_id])->first(['account_number', 'swift_code']);
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
                
                $response = $client->get('https://stbgestionapi.azure-api.net/api/account/beta/stb/GetPkByCardNumber?CardNumber='.$accountNumber.'&CIN='.$cin, [
                    'headers' => [
                        'Ocp-Apim-Subscription-Key' => '84bdb97d98be4bb387e4e8d4c80fac3a',
                        'Authorization' => 'Bearer '.$id_token
                    ]
                ]);
                
                $repCode = json_decode((string) $response->getBody(), true);
                $status  = $response->getStatusCode();

                if($status == 200){
                    if(!isset($repCode["OTP"])){
                        $this->helper->one_time_message('error','Mobile n\'existe pas .');
                        return redirect('payout');
                    }
                    Session::put('id_token', $id_token);
                    Session::put('IdTransaction', $repCode["id"]);
                    Session::put('OTP', $repCode["OTP"]);
                    Session::put('currency_id',$currency_id);
                    Session::put('accountNumber',$accountNumber);
                    Session::put('coinpaymentAmount',$amount);
                    Session::save();
                    return redirect('payout/cart-stb-payout-cartSms');

                }else{

                    $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                    return redirect('payout');

                }

            } catch (RequestException $e) {
                $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
                return redirect('payout');
            }
                

            }else{

                $this->helper->one_time_message('error','Problème de connexion STB .');
                return redirect('payout');

            }

        } catch (RequestException $e) {
            $this->helper->one_time_message('error',' Votre paramètre du compte est invalide .');
            return redirect('payout');
        }



       
    }

    
    public function stbPayoutConfirm(Request $request){
        $sessionValue = Session::get('withdrawalData');
        if (empty($sessionValue))
        {
            return redirect('payout');
        }

    $amount             = $sessionValue['amount'];
    $id_token           = Session::get('id_token');
    $IdTransaction      = Session::get('IdTransaction');
    $otp                = Session::get('OTP');
    $currency_id        = Session::get('currency_id');
    $accountNumber      = Session::get('accountNumber');
    

    $Currency = Currency::where(['id' => $currency_id])->first(['id', 'code']);

    if($Currency->code != "TND"){
        $this->helper->one_time_message('error',' Problème de Devise .');
        return redirect('payout');
    }

    if($otp != $request->otp){
        $this->helper->one_time_message('error',' Le code de vérification est invalide .');
        return redirect('payout');
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
            $url='https://stbgestionapi.azure-api.net/api/transaction/stb/CashInAccount';
                $res = $this->client->post($url,
                    [
                    'headers' => [
                                    'Ocp-Apim-Subscription-Key' => '291096539f864610b9aa0ac191b08303',
                                    'Content-Type' => 'application/json',
                                    'Authorization' => 'Bearer '.$id_token
                    ],
                    'body'  => json_encode([ 
                        'Id'=>$IdTransaction,
                        'Amount'=>$amount,
                        'Otp'=>$otp,
                        'IdTransaction'=> unique_code(),
                        ])
                ]);
                $req=json_decode($res->getBody()->getContents(), true);
                $statusCode = $res->getStatusCode();
                
                if($statusCode == 200){
                    $PaymentMethod       = PaymentMethod::where(['name' => 'CompteStb'])->first(['id', 'name']);
                    $user_id             = auth()->user()->id;
                    $uuid                = unique_code();
                    $payout_setting_id   = $sessionValue['payout_setting_id'];
                    $currency_id         = $sessionValue['currency_id'];
                    $totalAmount         = $sessionValue['totalAmount'];
                    $amount              = $sessionValue['amount'];
                    $payment_method_info = $sessionValue['payment_method_info'];
                    $payment_method_id   = $sessionValue['payment_method_id']; //new
                    $payoutSetting       = $this->helper->getPayoutSettingObject(['paymentMethod:id'], ['id' => $payout_setting_id], ['*']);
                    $wallet              = $this->helper->getUserWallet(['currency:id,symbol'], ['user_id' => $user_id, 'currency_id' => $currency_id], ['id', 'balance', 'currency_id']);
                    $feeInfo             = $this->helper->getFeesLimitObject([], Withdrawal, $wallet->currency_id, $payment_method_id, null, ['charge_percentage', 'charge_fixed']);
                    $feePercentage       = $amount * (@$feeInfo->charge_percentage / 100); //correct calc
                    $arr                 = [
                        'user_id'             => $user_id,
                        'wallet'              => $wallet,
                        'currency_id'         => $wallet->currency_id,
                        'payment_method_id'   => $PaymentMethod->id,
                        'payoutSetting'       => $payoutSetting,
                        'uuid'                => $uuid,
                        'percentage'          => $feeInfo->charge_percentage,
                        'charge_percentage'   => $feePercentage,
                        'charge_fixed'        => $feeInfo->charge_fixed,
                        'amount'              => $amount,
                        'totalAmount'         => $totalAmount,
                        'subtotal'            => $amount - ($feePercentage + $feeInfo->charge_fixed),
                        'payment_method_info' => $payment_method_info,
                    ];
                    $data['currencySymbol'] = $wallet->currency->symbol;
                    $data['amount']         = $arr['amount'];
            
                    //Get response
                    $response = $this->withdrawal->processPayoutMoneyConfirmation($arr, 'web');
                    if ($response['status'] != 200)
                    {
                        if (empty($response['withdrawalTransactionId']))
                        {
                            Session::forget('withdrawalData');
                            $this->helper->one_time_message('error', $response['ex']['message']);
                            return redirect('payout');
                        }
                    }
                    $data['transactionId'] = $response['withdrawalTransactionId'];
            
                    //clear session
                    Session::forget('withdrawalData');
                    Session::forget(['coinpaymentAmount', 'id_token', 'IdTransaction', 'OTP', 'currency_id', 'accountNumber']);
                    clearActionSession();
                    return view('user_dashboard.withdrawal.success', $data);                  


                }else{

                $this->helper->one_time_message('error',' Le code de vérification est invalide .');
                return redirect('payout');

                }
            } catch (RequestException $e) {
                $this->helper->one_time_message('error',' Problème de connexion STB .');
                return redirect('payout');
            }

        }





    }

    public function cartStbPayoutConfirm(Request $request){
        
        
        $sessionValue = Session::get('withdrawalData');
        if (empty($sessionValue))
        {
            return redirect('payout');
        }

    $amount             = $sessionValue['amount'];
    $id_token           = Session::get('id_token');
    $IdTransaction      = Session::get('IdTransaction');
    $otp                = Session::get('OTP');
    $currency_id        = Session::get('currency_id');
    $accountNumber      = Session::get('accountNumber');
    

    $Currency = Currency::where(['id' => $currency_id])->first(['id', 'code']);

    if($Currency->code != "TND"){
        $this->helper->one_time_message('error',' Problème de Devise .');
        return redirect('payout');
    }

    if($otp != $request->otp){
        $this->helper->one_time_message('error',' Le code de vérification est invalide .');
        return redirect('payout');
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
            $url='https://stbgestionapi.azure-api.net/api/transaction/stb/CashInCard';
                $res = $this->client->post($url,
                    [
                    'headers' => [
                                    'Ocp-Apim-Subscription-Key' => '291096539f864610b9aa0ac191b08303',
                                    'Content-Type' => 'application/json',
                                    'Authorization' => 'Bearer '.$id_token
                    ],
                    'body'  => json_encode([ 
                        'Id'=>$IdTransaction,
                        'Amount'=>$amount,
                        'Otp'=>$otp,
                        'IdTransaction'=> unique_code(),
                        ])
                ]);
                $req=json_decode($res->getBody()->getContents(), true);
                $statusCode = $res->getStatusCode();
                
                if($statusCode == 200){
                    $PaymentMethod       = PaymentMethod::where(['name' => 'CarteStb'])->first(['id', 'name']);
                    $user_id             = auth()->user()->id;
                    $uuid                = unique_code();
                    $payout_setting_id   = $sessionValue['payout_setting_id'];
                    $currency_id         = $sessionValue['currency_id'];
                    $totalAmount         = $sessionValue['totalAmount'];
                    $amount              = $sessionValue['amount'];
                    $payment_method_info = $sessionValue['payment_method_info'];
                    $payment_method_id   = $sessionValue['payment_method_id']; //new
                    $payoutSetting       = $this->helper->getPayoutSettingObject(['paymentMethod:id'], ['id' => $payout_setting_id], ['*']);
                    $wallet              = $this->helper->getUserWallet(['currency:id,symbol'], ['user_id' => $user_id, 'currency_id' => $currency_id], ['id', 'balance', 'currency_id']);
                    $feeInfo             = $this->helper->getFeesLimitObject([], Withdrawal, $wallet->currency_id, $payment_method_id, null, ['charge_percentage', 'charge_fixed']);
                    $feePercentage       = $amount * (@$feeInfo->charge_percentage / 100); //correct calc
                    $arr                 = [
                        'user_id'             => $user_id,
                        'wallet'              => $wallet,
                        'currency_id'         => $wallet->currency_id,
                        'payment_method_id'   => $PaymentMethod->id,
                        'payoutSetting'       => $payoutSetting,
                        'uuid'                => $uuid,
                        'percentage'          => $feeInfo->charge_percentage,
                        'charge_percentage'   => $feePercentage,
                        'charge_fixed'        => $feeInfo->charge_fixed,
                        'amount'              => $amount,
                        'totalAmount'         => $totalAmount,
                        'subtotal'            => $amount - ($feePercentage + $feeInfo->charge_fixed),
                        'payment_method_info' => $payment_method_info,
                    ];
                    $data['currencySymbol'] = $wallet->currency->symbol;
                    $data['amount']         = $arr['amount'];
            
                    //Get response
                    $response = $this->withdrawal->processPayoutMoneyConfirmation($arr, 'web');
                    if ($response['status'] != 200)
                    {
                        if (empty($response['withdrawalTransactionId']))
                        {
                            Session::forget('withdrawalData');
                            $this->helper->one_time_message('error', $response['ex']['message']);
                            return redirect('payout');
                        }
                    }
                    $data['transactionId'] = $response['withdrawalTransactionId'];
            
                    //clear session
                    Session::forget('withdrawalData');
                    Session::forget(['coinpaymentAmount', 'id_token', 'IdTransaction', 'OTP', 'currency_id', 'accountNumber']);
                    clearActionSession();
                    return view('user_dashboard.withdrawal.success', $data);                  


                }else{

                $this->helper->one_time_message('error',' Le code de vérification est invalide .');
                return redirect('payout');

                }
            } catch (RequestException $e) {
                $this->helper->one_time_message('error',' Problème de connexion STB .');
                return redirect('payout');
            }

        }

    }


    public function withdrawalConfirmation(Request $request)
    {
        $sessionValue = Session::get('withdrawalData');
        if (empty($sessionValue))
        {
            return redirect('payout');
        }

        actionSessionCheck();

        $user_id             = auth()->user()->id;
        $uuid                = unique_code();
        $payout_setting_id   = $sessionValue['payout_setting_id'];
        $currency_id         = $sessionValue['currency_id'];
        $totalAmount         = $sessionValue['totalAmount'];
        $amount              = $sessionValue['amount'];
        $payment_method_info = $sessionValue['payment_method_info'];
        $payment_method_id   = $sessionValue['payment_method_id']; //new
        $payoutSetting       = $this->helper->getPayoutSettingObject(['paymentMethod:id'], ['id' => $payout_setting_id], ['*']);
        $wallet              = $this->helper->getUserWallet(['currency:id,symbol'], ['user_id' => $user_id, 'currency_id' => $currency_id], ['id', 'balance', 'currency_id']);
        $feeInfo             = $this->helper->getFeesLimitObject([], Withdrawal, $wallet->currency_id, $payment_method_id, null, ['charge_percentage', 'charge_fixed']);
        $feePercentage       = $amount * (@$feeInfo->charge_percentage / 100); //correct calc
        $arr                 = [
            'user_id'             => $user_id,
            'wallet'              => $wallet,
            'currency_id'         => $wallet->currency_id,
            'payment_method_id'   => $payoutSetting->paymentMethod->id,
            'payoutSetting'       => $payoutSetting,
            'uuid'                => $uuid,
            'percentage'          => $feeInfo->charge_percentage,
            'charge_percentage'   => $feePercentage,
            'charge_fixed'        => $feeInfo->charge_fixed,
            'amount'              => $amount,
            'totalAmount'         => $totalAmount,
            'subtotal'            => $amount - ($feePercentage + $feeInfo->charge_fixed),
            'payment_method_info' => $payment_method_info,
        ];
        $data['currencySymbol'] = $wallet->currency->symbol;
        $data['amount']         = $arr['amount'];

        //Get response
        $response = $this->withdrawal->processPayoutMoneyConfirmation($arr, 'web');
        if ($response['status'] != 200)
        {
            if (empty($response['withdrawalTransactionId']))
            {
                Session::forget('withdrawalData');
                $this->helper->one_time_message('error', $response['ex']['message']);
                return redirect('payout');
            }
            // $data['errorMessage'] = $response['ex']['message'];
        }
        $data['transactionId'] = $response['withdrawalTransactionId'];

        //clear session
        Session::forget('withdrawalData');
        clearActionSession();
        return view('user_dashboard.withdrawal.success', $data);
    }

    public function withdrawalPrintPdf($trans_id)
    {
        $data['companyInfo']        = Setting::where(['type' => 'general', 'name' => 'logo'])->first(['value']);
        $data['transactionDetails'] = Transaction::with(['payment_method:id,name', 'currency:id,symbol'])
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
        $mpdf->WriteHTML(view('user_dashboard.withdrawal.withdrawalPaymentPdf', $data));
        $mpdf->Output('sendMoney_' . time() . '.pdf', 'I'); //
    }
    //Payout - ends
}
