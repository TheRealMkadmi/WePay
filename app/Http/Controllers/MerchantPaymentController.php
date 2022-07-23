<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\BadResponseException;

use Illuminate\Http\Request;
use App\Http\Helpers\Common;
use Carbon\Carbon;
use App\Models\{Transaction,
    CurrencyPaymentMethod,
    MerchantPayment,
    PaymentMethod,
    Preference,
    FeesLimit,
    Merchant,
    Currency,
    Setting,
    Wallet,
    User,
    CoinpaymentLogTrx,
    ActivityLog,
    EmailTemplate,
    UserDetail,
    VerifyUser,
    DeviceLog
};
use Illuminate\Support\Facades\{DB,
    Validator,
    Session,
    Auth,
    Http
};
use Exception;
use App\Repositories\{CoinPaymentRepository, StripeRepository};

class MerchantPaymentController extends Controller
{
    protected $helper;
    protected $stripeRepository;

    public function __construct()
    {
        $this->helper = new Common();
        $this->client = new \GuzzleHttp\Client();
    }

    public function index(Request $request)
    {
        $merchant_id          = $request->merchant_id;
        $merchant_uuid        = $request->merchant;
        $merchant_currency_id = $request->currency_id;

        $data['merchant'] = $merchant = Merchant::with(['currency:id,code','user:id,status'])->where(['id' => $merchant_id, 'merchant_uuid' => $merchant_uuid, 'currency_id' => $merchant_currency_id])
        ->first(['id', 'user_id', 'currency_id']);
        if (!$merchant) {
            $this->helper->one_time_message('error', __('Merchant not found!'));
            return redirect('payment/fail');
        }

        //Check whether merchant is suspended - starts
        $checkStandardMerchantUser = $this->helper->getUserStatus($merchant->user->status);
        if ($checkStandardMerchantUser == 'Suspended') {
            $data['message'] = __('Merchant is suspended!');
            return view('merchantPayment.user_suspended', $data);
        }
        //Check whether merchant is suspended - ends
        //Check whether merchant is Inactive - starts
        elseif ($checkStandardMerchantUser == 'Inactive') {
            $data['message'] = __('Merchant is inactive!');
            return view('merchantPayment.user_inactive', $data);
        }
        //Check whether merchant is Inactive - ends

        //for payUmoney
        if ($merchant->currency->code == "INR") {
            Session::put('payumoney_merchant_currency_code', $merchant->currency->code);
        }

        //For showing the message that merchant available or not
        $data['isMerchantAvailable'] = true;
        if (!$merchant) {
            $data['isMerchantAvailable'] = false;
        }
        $data['paymentInfo'] = $request->all();

        //get only the activated and existing payment methods for the default currency
        //payeer removed
        $data['payment_methods'] = PaymentMethod::where(['status' => 'Active'])->whereNotIn('name', ['Payeer'])->get(['id', 'name'])->toArray();
        $cpmWithoutMts           = CurrencyPaymentMethod::where(['currency_id' => $merchant->currency->id])->where('activated_for', 'like', "%deposit%")->pluck('method_id')->toArray();
        $paymoney = PaymentMethod::where(['name' => 'Mts'])->first(['id']);
        array_push($cpmWithoutMts, $paymoney->id);
        $data['cpm'] = $cpmWithoutMts;

        //get stripe publishable key from CurrencyPaymentMethod
        $stripe                = PaymentMethod::where(['name' => 'Stripe'])->first(['id']);
        $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $merchant->currency->id, 'method_id' => $stripe->id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
        if (!empty($currencyPaymentMethod)) {
            $data['publishable'] = json_decode($currencyPaymentMethod->method_data)->publishable_key;
        }
        //get Paypal Client Id from CurrencyPaymentMethod
        $paypal                = PaymentMethod::where(['name' => 'Paypal'])->first(['id']);
        $paypalCurrencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $merchant->currency->id, 'method_id' => $paypal->id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
        if (!empty($paypalCurrencyPaymentMethod)) {
            $data['clientId']     = json_decode($paypalCurrencyPaymentMethod->method_data)->client_id;
            $data['currencyCode'] = $merchant->currency->code;
        }
        return view('merchantPayment.app', $data);
    }


    public function wepayPaymentData($request) 
    {
        $merchantChk = Merchant::find($request->merchant, ['id', 'user_id', 'status', 'fee','business_name']);
        if ($merchantChk->status == 'Approved') {
            $data['wallets'] = $wallets = Wallet::with('currency:id,type,logo,code,status')->where(['user_id' => Auth::user()->id])->orderBy('balance', 'ASC')->get(['id', 'currency_id', 'balance', 'is_default']);
            $data['first_name']=Auth::user()->first_name;
            $data['last_name']=Auth::user()->last_name;
            $data['business_name']= $merchantChk->business_name;
            $data['order_no']= $request->order_no;
            $data['item_name']= $request->item_name;
            $data['amount']= $request->amount;
            $data['merchant']= $request->merchant;
            $data['currency']= $request->currency;
            return view('merchantPayment.wepay', $data);
           }
    }


    public function execute2fa()
    {
        $six_digit_random_number                = six_digit_random_number();
        $userDetail                             = UserDetail::where(['user_id' => auth()->user()->id])->first();
        $userDetail->two_step_verification_code = $six_digit_random_number;
        $userDetail->save();

        if (auth()->user()->user_detail->two_step_verification_type == 'phone')
        {
            //sms
            $message = $six_digit_random_number . ' is your ' . getCompanyName() . ' 2-factor authentication code. ';

            if (!empty(auth()->user()->carrierCode) && !empty(auth()->user()->phone))
            {
                if (checkAppSmsEnvironment() == true)
                {
                    sendSMS(auth()->user()->carrierCode . auth()->user()->phone, $message);
                }
            }
        }
        elseif (auth()->user()->user_detail->two_step_verification_type == 'email')
        {
            //email
            if (checkAppMailEnvironment())
            {
                $twoStepVerification = EmailTemplate::where([
                    'temp_id'     => 19,
                    'language_id' => getDefaultLanguage(),
                    'type'        => 'email',
                ])->select('subject', 'body')->first();

                $englishtwoStepVerification = EmailTemplate::where(['temp_id' => 19, 'lang' => 'en', 'type' => 'email'])->select('subject', 'body')->first();

                if (!empty($twoStepVerification->subject) && !empty($twoStepVerification->body))
                {
                    $twoStepVerification_sub = $twoStepVerification->subject;
                    $twoStepVerification_msg = str_replace('{user}', auth()->user()->first_name . ' ' . auth()->user()->last_name, $twoStepVerification->body);
                }
                else
                {
                    $twoStepVerification_sub = $englishtwoStepVerification->subject;
                    $twoStepVerification_msg = str_replace('{user}', auth()->user()->first_name . ' ' . auth()->user()->last_name, $englishtwoStepVerification->body);
                }
                $twoStepVerification_msg = str_replace('{code}', $six_digit_random_number, $twoStepVerification_msg);
                $twoStepVerification_msg = str_replace('{soft_name}', getCompanyName(), $twoStepVerification_msg);
                $this->email->sendEmail(auth()->user()->email, $twoStepVerification_sub, $twoStepVerification_msg);
            }
        }
    }

    
    
    public function wepayPayment(Request $request) 
    {
        $data        = $request->only('email', 'password');
        if (Auth::attempt($data) || Auth::check())
           {
                   $preferences = Preference::where('field', '!=', 'dflt_lang')->get();
                   if (!empty($preferences))
                   {
                       foreach ($preferences as $pref)
                       {
                           $pref_arr[$pref->field] = $pref->value;
                       }
                   }
                   if (!empty($preferences))
                   {
                       Session::put($pref_arr);
                   }

                   // default_currency
                   $default_currency = Setting::where('name', 'default_currency')->first();
                   if (!empty($default_currency))
                   {
                       Session::put('default_currency', $default_currency->value);
                   }

                   //default_timezone
                   $default_timezone = User::with(['user_detail:id,user_id,timezone'])->where(['id' => auth()->user()->id])->first(['id'])->user_detail->timezone;
                   if (!$default_timezone)
                   {
                       Session::put('dflt_timezone_user', session('dflt_timezone'));
                   }
                   else
                   {
                       Session::put('dflt_timezone_user', $default_timezone);
                   }

                   // default_language
                   $default_language = Setting::where('name', 'default_language')->first();
                   if (!empty($default_language))
                   {
                       Session::put('default_language', $default_language->value);
                   }

                   // company_name
                   $company_name = Setting::where('name', 'name')->first();
                   if (!empty($company_name))
                   {
                       Session::put('name', $company_name->value);
                   }

                   // company_logo
                   $company_logo = Setting::where(['name' => 'logo', 'type' => 'general'])->first();
                   if (!empty($company_logo))
                   {
                       Session::put('company_logo', $company_logo->value);
                   }


                   try
                   {
                       DB::beginTransaction();

                       //check default wallet
                       $chkWallet = Wallet::where(['user_id' => Auth::user()->id, 'currency_id' => $default_currency->value])->first();
                       if (empty($chkWallet))
                       {
                           $wallet              = new Wallet();
                           $wallet->user_id     = Auth::user()->id;
                           $wallet->currency_id = $default_currency->value;
                           $wallet->balance     = 0.00;
                           $wallet->is_default  = 'No'; //fixed
                           $wallet->save();
                       }
                       $log                  = [];
                       $log['user_id']       = Auth::check() ? Auth::user()->id : null;
                       $log['type']          = 'User';
                       $log['ip_address']    = $request->ip();
                       $log['browser_agent'] = $request->header('user-agent');
                       ActivityLog::create($log);

                       //user_detail - adding last_login_at and last_login_ip
                       auth()->user()->user_detail()->update([
                           'last_login_at' => Carbon::now()->toDateTimeString(),
                           'last_login_ip' => $request->getClientIp(),
                       ]);

                       DB::commit();

                       //2fa
                       $two_step_verification = Preference::where(['category' => 'preference', 'field' => 'two_step_verification'])->first(['value'])->value;
                       $checkDeviceLog        = DeviceLog::where(['user_id' => auth()->user()->id, 'browser_fingerprint' => $request->browser_fingerprint])->first(['browser_fingerprint']);

                       Session::put('browser_fingerprint', $request->browser_fingerprint); //putting browser_fingerprint on session to restrict users accessing dashboard

                       if (auth()->user()->user_detail->two_step_verification_type != "disabled" && $two_step_verification != "disabled")
                       {
                           if (auth()->user()->user_detail->two_step_verification_type == "google_authenticator")
                           {
                               if (!auth()->user()->user_detail->two_step_verification || empty($checkDeviceLog))
                               {
                                   $google2fa                             = app('pragmarx.google2fa');
                                   $registration_data                     = $request->all();
                                   $registration_data["google2fa_secret"] = $google2fa->generateSecretKey();

                                   $request->session()->flash('registration_data', $registration_data);

                                   $QR_Image = $google2fa->getQRCodeInline(
                                       config('app.name'),
                                       auth()->user()->email,
                                       $registration_data['google2fa_secret']
                                   );
                                   $data = [
                                       'QR_Image' => $QR_Image,
                                       'secret'   => $registration_data['google2fa_secret'],
                                   ];
                                   return \Redirect::route('google2fa')->with(['data' => $data]);
                               }
                               else
                               {
                                   return $this->wepayPaymentData($request);
                               }
                           }
                           else
                           {
                               if (!auth()->user()->user_detail->two_step_verification || empty($checkDeviceLog))
                               {
                                   $this->execute2fa();
                                   return redirect('2fa');
                               }
                               else
                               {
                                   return $this->wepayPaymentData($request);
                               }
                           }
                       }
                       else
                       {
                           return $this->wepayPaymentData($request);
                       }
                   }
                   catch (Exception $e)
                   {
                       DB::rollBack();
                       $this->helper->one_time_message('danger', $e->getMessage());
                       return redirect('payment/fail');
                   }
           } else {
               return redirect('payment/fail');
           }
       return redirect('payment/fail');
    }

    /*System Merchant Payment Starts*/
    public function mtsPayment(Request $request)
    {
        $unique_code = unique_code();
        $data        = $request->only('email', 'password');
        $merchantChk = Merchant::find($request->merchant, ['id', 'user_id', 'status', 'fee']);
        $curr = Currency::where('code', $request->currency)->first(['id', 'code']);

        //Deposit + Merchant Fee (starts)
        $checkDepositFeesLimit            = $this->checkDepositFeesPaymentMethod($curr->id, 1, $request->amount, $merchantChk->fee);
        $feeInfoChargePercentage          = $checkDepositFeesLimit['feeInfoChargePercentage'];
        $feeInfoChargeFixed               = $checkDepositFeesLimit['feeInfoChargeFixed'];
        $depositCalcPercentVal            = $checkDepositFeesLimit['depositCalcPercentVal'];
        $depositTotalFee                  = $checkDepositFeesLimit['depositTotalFee'];
        $merchantCalcPercentValOrTotalFee = $checkDepositFeesLimit['merchantCalcPercentValOrTotalFee'];
        $totalFee                         = $checkDepositFeesLimit['totalFee'];
        //Deposit + Merchant Fee (ends)

        try {
            DB::beginTransaction();

            if (!$merchantChk) {
                DB::rollBack();
                $this->helper->one_time_message('error', __('Merchant not found!')); //TODO - translations
                return redirect('payment/fail');
            }

            //Check currency exists in system or not
            if (!$curr) {
                DB::rollBack();
                $this->helper->one_time_message('error', __('Currency does not exist in the system!')); //TODO - translations
                return redirect('payment/fail');
            }

            if (Auth::attempt($data) && $merchantChk->status == 'Approved') {
                //Merchant cannot make payment to himself
                if ($merchantChk->user_id == auth()->user()->id) {
                    auth()->logout();
                    DB::rollBack();
                    $this->helper->one_time_message('error', __('Merchant cannot make payment to himself!'));
                    return redirect('payment/fail');
                }

                //Check whether user is suspended - starts
                $checkPaidByUser = $this->helper->getUserStatus(auth()->user()->status);
                if ($checkPaidByUser == 'Suspended') {
                    DB::rollBack();
                    $data['message'] = __('You are suspended to do any kind of transaction!');
                    return view('merchantPayment.user_suspended', $data);
                }
                //Check whether user is suspended - ends

                //Check whether user is inactive - starts
                elseif ($checkPaidByUser == 'Inactive') {
                    DB::rollBack();
                    $data['message'] = __('Your account is inactivated. Please try again later!');
                    return view('merchantPayment.user_inactive', $data);
                }
                //Check whether user is inactive - ends

                //Check whether merchant is suspended - starts
                $checkStandardMerchantUser = $this->helper->getUserStatus($merchantChk->user->status);
                if ($checkStandardMerchantUser == 'Suspended') {
                    DB::rollBack();
                    $data['message'] = __('Merchant is suspended!');
                    return view('merchantPayment.user_suspended', $data);
                }
                //Check whether merchant is suspended - ends

                //Check whether merchant is Inactive - starts
                elseif ($checkStandardMerchantUser == 'Inactive') {
                    DB::rollBack();
                    $data['message'] = __('Merchant is inactive!');
                    return view('merchantPayment.user_inactive', $data);
                }
                //Check whether merchant is Inactive - ends

                $senderWallet = Wallet::where(['user_id' => auth()->user()->id, 'currency_id' => $curr->id])->first(['id', 'balance']);
                //Check User has the wallet or not
                if (!$senderWallet) {
                    auth()->logout();
                    DB::rollBack();
                    $this->helper->one_time_message('error', __('User does not have the wallet - ') . $curr->code . '. ' . __('Please exchange to wallet - ') . $curr->code . '!'); //TODO - translations
                    return redirect('payment/fail');
                }

                //Check user balance
                if ($senderWallet->balance < $request->amount) {
                    auth()->logout();
                    $this->helper->one_time_message('error', __("User does not have sufficient balance!"));
                    return redirect('payment/fail');
                }

                $this->setDefaultSessionValues(); //Set Necessary Session Values

                //MerchantPayment - Add on merchant
                $merchantPayment                    = new MerchantPayment();
                $merchantPayment->merchant_id       = $request->merchant;
                $merchantPayment->currency_id       = $curr->id;
                $merchantPayment->payment_method_id = 1;
                $merchantPayment->user_id           = Auth::user()->id;
                $merchantPayment->gateway_reference = $unique_code;
                $merchantPayment->order_no          = $request->order_no;
                $merchantPayment->item_name         = $request->item_name;
                $merchantPayment->uuid              = $unique_code;
                $merchantPayment->charge_percentage = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee; //new
                $merchantPayment->charge_fixed      = @$feeInfoChargeFixed;                                       //new
                $merchantPayment->amount            = $request->amount - $totalFee;                               //new
                $merchantPayment->total             = $request->amount;
                $merchantPayment->status            = 'Success';
                $merchantPayment->save();

                //Wallet - User - Payment Sent - Amount deducted from user wallet
                $senderWallet->balance = ($senderWallet->balance - $request->amount);
                $senderWallet->save();

                //Transaction - A - Payment_Sent
                $transaction_A                           = new Transaction();
                $transaction_A->user_id                  = Auth::user()->id;
                $transaction_A->end_user_id              = $merchantChk->user_id;
                $transaction_A->currency_id              = $curr->id;
                $transaction_A->payment_method_id        = 1;
                $transaction_A->merchant_id              = $request->merchant;
                $transaction_A->uuid                     = $unique_code;
                $transaction_A->transaction_reference_id = $merchantPayment->id;
                $transaction_A->transaction_type_id      = Payment_Sent;
                $transaction_A->subtotal                 = $request->amount;
                $transaction_A->percentage               = $merchantChk->fee+@$feeInfoChargePercentage; //new
                $transaction_A->charge_percentage        = 0;
                $transaction_A->charge_fixed             = 0;
                $transaction_A->total                    = '-' . ($transaction_A->charge_percentage + $transaction_A->charge_fixed + $transaction_A->subtotal); //new
                $transaction_A->status                   = 'Success';
                $transaction_A->save();

                //Transaction - B - Payment_Received
                $transaction_B                           = new Transaction();
                $transaction_B->user_id                  = $merchantChk->user_id;
                $transaction_B->end_user_id              = Auth::user()->id;
                $transaction_B->currency_id              = $curr->id;
                $transaction_B->payment_method_id        = 1;
                $transaction_B->uuid                     = $unique_code;
                $transaction_B->transaction_reference_id = $merchantPayment->id;
                $transaction_B->transaction_type_id      = Payment_Received;
                $transaction_B->subtotal                 = $request->amount - $totalFee;                                                                //new
                $transaction_B->percentage               = $merchantChk->fee+@$feeInfoChargePercentage;                                                 //new
                $transaction_B->charge_percentage        = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee;                                  //new
                $transaction_B->charge_fixed             = @$feeInfoChargeFixed;                                                                        //new
                $transaction_B->total                    = $transaction_B->charge_percentage + $transaction_B->charge_fixed + $transaction_B->subtotal; //new
                $transaction_B->status                   = 'Success';
                $transaction_B->merchant_id              = $request->merchant;
                $transaction_B->save();

                //Wallet - Merchant - Payment Received - pm_v2.3
                $merchantWallet = Wallet::where(['user_id' => $merchantChk->user_id, 'currency_id' => $curr->id])->first(['id', 'balance']);
                if (empty($merchantWallet)) {
                    $wallet              = new Wallet();
                    $wallet->user_id     = $merchantChk->user_id;
                    $wallet->currency_id = $curr->id;
                    $wallet->balance     = ($request->amount - $totalFee); // if wallet does not exist - merchant's wallet is created and balance also added - when user makes a merchant payment
                    $wallet->is_default  = 'No';
                    $wallet->save();
                } else {
                    $merchantWallet->balance = $merchantWallet->balance + ($request->amount - $totalFee); //new
                    $merchantWallet->save();
                }
                DB::commit();

                // Send mail to admin
                $response = $this->helper->sendTransactionNotificationToAdmin('payment', ['data' => $merchantPayment]);

                return redirect('payment/success');
            } else {
                DB::rollBack();
                return redirect('payment/fail');
            }
        } catch (Exception $e) {
            DB::rollBack();
            $this->helper->one_time_message('error', $e->getMessage());
            return redirect('payment/fail');
        }
    }

    protected function setDefaultSessionValues()
    {
        $preferences = Preference::where('field', '!=', 'dflt_lang')->get();
        if (!empty($preferences))
        {
            foreach ($preferences as $pref)
            {
                $pref_arr[$pref->field] = $pref->value;
            }
        }
        if (!empty($preferences))
        {
            Session::put($pref_arr);
        }

        // default_currency
        $default_currency = Setting::where('name', 'default_currency')->first(['value']);
        if (!empty($default_currency))
        {
            Session::put('default_currency', $default_currency->value);
        }

        //default_timezone
        $default_timezone = User::with(['user_detail:id,user_id,timezone'])->where(['id' => auth()->user()->id])->first(['id'])->user_detail->timezone;
        if (!$default_timezone)
        {
            Session::put('dflt_timezone_user', session('dflt_timezone'));
        }
        else
        {
            Session::put('dflt_timezone_user', $default_timezone);
        }

        // default_language
        $default_language = Setting::where('name', 'default_language')->first(['value']);
        if (!empty($default_language))
        {
            Session::put('default_language', $default_language->value);
        }

        // company_name
        $company_name = Setting::where('name', 'name')->first(['value']);
        if (!empty($company_name))
        {
            Session::put('name', $company_name->value);
        }

        // company_logo
        $company_logo = Setting::where(['name' => 'logo', 'type' => 'general'])->first(['value']);
        if (!empty($company_logo))
        {
            Session::put('company_logo', $company_logo->value);
        }
    }
    /*System Merchant Payment ends*/

    /*Stripe Merchant Payment Starts*/

    public function paypalPayment(Request $request)
    {
        $rules = array(
            'amount'   => 'required|numeric',
            'merchant' => 'required',
        );
        $validator   = Validator::make($request->all(), $rules);
        $merchantChk = Merchant::find($request->merchant, ['id', 'user_id', 'status', 'fee']);
        if (!$merchantChk)
        {
            $this->helper->one_time_message('error', 'Merchant not found');
            return redirect('payment/fail');
        }

        if ($validator->fails() || $merchantChk->status != 'Approved')
        {
            $this->helper->one_time_message('error', 'Validation failed');
            return redirect('payment/fail');
        }
        else
        {
            $amount        = $request->amount;
            $currency      = $request->currency;
            $merchant      = $request->merchant;
            $item_name     = $request->item_name;
            $order_no      = $request->order_no;
            $PaymentMethod = PaymentMethod::where(['name' => 'Paypal'])->first(['id', 'name']);
            $currencyInfo  = Currency::where(['code' => $currency])->first(['id', 'code']);
            if ($currencyInfo)
            {
                $currencyCode = $currencyInfo->code;
            }
            else
            {
                $currencyCode = "USD";
            }
            $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $currencyInfo->id, 'method_id' => $PaymentMethod->id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
            $methodData            = json_decode($currencyPaymentMethod->method_data);
            if (empty($methodData))
            {
                $this->helper->one_time_message('error', 'For currency' . $currency . ' credential not found!');
                return redirect('payment/fail');
            }
            Session::put('currency', $currencyCode);
            Session::put('currency_id', $currencyInfo->id);
            Session::put('payment_method_id', $PaymentMethod->id);
            Session::put('method', $PaymentMethod->name);
            Session::put('amount', $amount);
            Session::put('merchant', $merchant);
            Session::put('item_name', $item_name);
            Session::put('order_no', $order_no);
            Session::save();

            //paypal setup is a custom function to setup paypal api credentials
            $depo       = new DepositController();
            $apiContext = $depo->paypalSetup($methodData->client_id, $methodData->client_secret, $methodData->mode);
            $payer      = new Payer();
            $payer->setPaymentMethod('paypal');

            $pAmount = new Amount();
            $pAmount->setTotal(number_format($amount, 2, '.', '')); //PayPal accepts 2 decimal places only - if not rounded to 2 decimal places, PayPal will throw error.
            $pAmount->setCurrency($currencyCode);

            $transaction = new \PayPal\Api\Transaction();
            $transaction->setAmount($pAmount);

            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl(url("payment/paypal_payment_success"))
                ->setCancelUrl(url("payment/fail"));

            $payment = new Payment();
            $payment->setIntent('sale')
                ->setPayer($payer)
                ->setTransactions(array($transaction))
                ->setRedirectUrls($redirectUrls);
            try {
                $payment->create($apiContext);
                return redirect()->to($payment->getApprovalLink());
            }
            catch (PayPalConnectionException $ex)
            {
                $this->helper->one_time_message('error', $ex->getData());
                return redirect('payment/fail');
            }
            return redirect('payment/fail');
        }
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
            'currency'   => 'required',
            'merchant'   => 'required',
            'amount'     => 'required',
        ]);
        if ($validation->fails()) {
            $data['message'] = $validation->errors()->first();
            $data['status']  = 401;
            return response()->json([
                'data' => $data
            ]);
        }
        $amount            = $request->amount;
        $paymentMethod     = PaymentMethod::where(['name'=> "Stripe"])->first(['id', 'name']);
        $payment_method_id = $method_id = $paymentMethod['id'];
        $currencyCode      = $request->currency;
        $currency          = Currency::where(['code'=> $currencyCode])->first(['id', 'code']);
        $currencyPaymentMethod     = CurrencyPaymentMethod::where(['currency_id' => $currency['id'], 'method_id' => $method_id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
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
    
    public function stripePayment(Request $request)
    {
        $data['message'] = "Fail";
        $data['status']  = 401;
        try {
            $validation = Validator::make($request->all(), [
                'amount'      => 'required|numeric',
                'merchant'    => 'required',
                'paymentIntendId'  => 'required',
                'paymentMethodId'  => 'required',
            ]);
            if ($validation->fails()) {
                $data['message'] = $validation->errors()->first();
                return response()->json(['data' => $data]);
            }
            $merchantChk = Merchant::find($request->merchant, ['id', 'user_id', 'status', 'fee']);
            if (!$merchantChk) {
                $data['message'] = __('Merchant not found!');
                return response()->json(['data' => $data]);
            }
            if ($merchantChk->status != 'Approved') {
                $data['message'] = __('Merchant not approved!');
                return response()->json(['data' => $data]);
            }
            DB::beginTransaction();
            $amount                = (double) $request->amount;
            $currencyCode          = $request->currency;
            $merchant              = $request->merchant;
            $item_name             = $request->item_name;
            $order_no              = $request->order_no;
            $unique_code           = unique_code();
            $currency              = Currency::where('code', $currencyCode)->first(['id', 'code']);
            $PaymentMethod         = PaymentMethod::where(['name' => 'Stripe'])->first(['id']);
            $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $currency->id, 'method_id' => $PaymentMethod->id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
            $methodData            = json_decode($currencyPaymentMethod->method_data);

            if (empty($methodData) || !isset($methodData->secret_key)) {
                $data['message'] = 'method data of currency' . $currencyCode . ' not found!';
                return response()->json(['data' => $data]);
            }
            $response = $this->stripeRepository->paymentConfirm($methodData->secret_key, $request->paymentIntendId, $request->paymentMethodId);
            if ($response->getData()->status != 200) {
                $data['message'] = $response->getData()->message;
                return response()->json(['data' => $data]);
            }
            $token = $response->getData()->id;
            //Deposit + Merchant Fee (starts)
            $checkDepositFeesLimit            = $this->checkDepositFeesPaymentMethod($currency->id, $PaymentMethod->id, $amount, $merchantChk->fee);
            $feeInfoChargePercentage          = $checkDepositFeesLimit['feeInfoChargePercentage'];
            $feeInfoChargeFixed               = $checkDepositFeesLimit['feeInfoChargeFixed'];
            $depositCalcPercentVal            = $checkDepositFeesLimit['depositCalcPercentVal'];
            $depositTotalFee                  = $checkDepositFeesLimit['depositTotalFee'];
            $merchantCalcPercentValOrTotalFee = $checkDepositFeesLimit['merchantCalcPercentValOrTotalFee'];
            $totalFee                         = $checkDepositFeesLimit['totalFee'];
            //Deposit + Merchant Fee (ends)

            $merchantPayment                    = new MerchantPayment();
            $merchantPayment->merchant_id       = $merchant;
            $merchantPayment->currency_id       = $currency->id;
            $merchantPayment->payment_method_id = $PaymentMethod->id;
            $merchantPayment->gateway_reference = $token;
            $merchantPayment->order_no          = $order_no;
            $merchantPayment->item_name         = $item_name;
            $merchantPayment->uuid              = $unique_code;
            $merchantPayment->charge_percentage = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee; //new
            $merchantPayment->charge_fixed      = $feeInfoChargeFixed;                                        //new
            $merchantPayment->amount            = $amount - $totalFee;                                        //new
            $merchantPayment->total             = $amount;
            $merchantPayment->status            = 'Success';
            $merchantPayment->save();

            $transaction                           = new Transaction();
            $transaction->user_id                  = $merchantChk->user_id;
            $transaction->currency_id              = $currency->id;
            $transaction->payment_method_id        = $PaymentMethod->id;
            $transaction->merchant_id              = $merchant;
            $transaction->uuid                     = $unique_code;
            $transaction->transaction_reference_id = $merchantPayment->id;
            $transaction->transaction_type_id      = Payment_Received;
            $transaction->subtotal                 = $amount - $totalFee;                                                                             //new
            $transaction->percentage               = $merchantChk->fee + $feeInfoChargePercentage;                                                    //new
            $transaction->charge_percentage        = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee;                                      //new
            $transaction->charge_fixed             = $feeInfoChargeFixed;                                                                             //new
            $transaction->total                    = $merchantPayment->charge_percentage + $merchantPayment->charge_fixed + $merchantPayment->amount; //new
            $transaction->status                   = 'Success';
            $transaction->save();

            //Add Amount to Merchant Wallet
            $merchantWallet = Wallet::where(['user_id' => $merchantChk->user_id, 'currency_id' => $currency->id])->first(['id', 'balance']);
            if (empty($merchantWallet)) {
                $wallet              = new Wallet();
                $wallet->user_id     = $merchantChk->user_id;
                $wallet->currency_id = $currency->id;
                $wallet->balance     = $merchantPayment->amount; // if wallet does not exist - merchant's wallet is created and balance also added - when user makes a merchant payment
                $wallet->is_default  = 'No';
                $wallet->save();
            } else {
                $merchantWallet->balance = ($merchantWallet->balance + $merchantPayment->amount);
                $merchantWallet->save();
            }
            DB::commit();
            $response = $this->helper->sendTransactionNotificationToAdmin('payment', ['data' => $merchantPayment]);
            Session::put('merchant_amount', $amount);
            Session::put('merchant_currency_code', $currencyCode);
            $data['message'] = "Success";
            $data['status']  = 200;
        } catch (Exception $e) {
            DB::rollBack();
            $data['message'] =  $e->getMessage();
        }
        return response()->json(['data' => $data]);
    }
    /*Stripe Merchant Payment Starts*/

    /*PayPal Merchant Payment Starts*/

    public function paypalPaymentSuccess(Request $request)
    {
        $data = [];
        $data['status']        = 401;
        $data['redirectedUrl'] = "/payment/fail";
        try {
            $unique_code       = unique_code();
            $amount            = (double) base64_decode($request->amount);
            $paymentMethod     = PaymentMethod::where(['name'=> "Paypal"])->first(['id', 'name']);
            $payment_method_id = $paymentMethod['id'];
            $merchant          = $request->merchant;
            $item_name         = $request->item_name;
            $order_no          = $request->order_no;
            $currencyCode      = $request->currency;
            $currency          = Currency::where(['code'=> $currencyCode])->first(['id', 'code']);
            $currencyId        = $currency['id'];
            // Payment Received
            $merchantInfo = Merchant::find($merchant, ['id', 'user_id', 'fee']);
            //Deposit + Merchant Fee (starts)
            \Log::info('ok 1');
            $checkDepositFeesLimit            = $this->checkDepositFeesPaymentMethod($currencyId, $payment_method_id, $amount, $merchantInfo->fee);
            $feeInfoChargePercentage          = $checkDepositFeesLimit['feeInfoChargePercentage'];
            $feeInfoChargeFixed               = $checkDepositFeesLimit['feeInfoChargeFixed'];
            $depositCalcPercentVal            = $checkDepositFeesLimit['depositCalcPercentVal'];
            $depositTotalFee                  = $checkDepositFeesLimit['depositTotalFee'];
            $merchantCalcPercentValOrTotalFee = $checkDepositFeesLimit['merchantCalcPercentValOrTotalFee'];
            $totalFee                         = $checkDepositFeesLimit['totalFee'];
            //Deposit + Merchant Fee (ends)
            \Log::info('ok 2');

            $merchantPayment                    = new MerchantPayment();
            $merchantPayment->merchant_id       = $merchant;
            $merchantPayment->currency_id       = $currencyId;
            $merchantPayment->payment_method_id = $payment_method_id;
            $merchantPayment->gateway_reference = $request->payment_id;
            $merchantPayment->order_no          = $order_no;
            $merchantPayment->item_name         = $item_name;
            $merchantPayment->uuid              = $unique_code;
            $merchantPayment->charge_percentage = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee; 
            $merchantPayment->charge_fixed      = $feeInfoChargeFixed;                                        
            $merchantPayment->amount            = $amount - $totalFee;                                        
            $merchantPayment->total             = $amount;
            $merchantPayment->status            = 'Success';
            $merchantPayment->save();
            \Log::info('ok 3');

            $transaction                           = new Transaction();
            $transaction->user_id                  = $merchantInfo->user_id;
            $transaction->currency_id              = $currencyId;
            $transaction->payment_method_id        = $payment_method_id;
            $transaction->merchant_id              = $merchant;
            $transaction->uuid                     = $unique_code;
            $transaction->transaction_reference_id = $merchantPayment->id;
            $transaction->transaction_type_id      = Payment_Received;
            $transaction->subtotal                 = $amount - $totalFee;                                                                             
            $transaction->percentage               = $merchantInfo->fee + $feeInfoChargePercentage;                                                   
            $transaction->charge_percentage        = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee;                                      
            $transaction->charge_fixed             = $feeInfoChargeFixed;                                                                             
            $transaction->total                    = $merchantPayment->charge_percentage + $merchantPayment->charge_fixed + $merchantPayment->amount; 
            $transaction->status                   = 'Success';
            $transaction->save();
            \Log::info('ok 4');

            $merchantWallet = Wallet::where(['user_id' => $merchantInfo->user_id, 'currency_id' => $currencyId])->first(['id', 'balance']);
            if (empty($merchantWallet)) {
                $wallet              = new Wallet();
                $wallet->user_id     = $merchantInfo->user_id;
                $wallet->currency_id = $currencyId;
                $wallet->balance     = $merchantPayment->amount; // if wallet does not exist - merchant's wallet is created and balance also added - when user makes a merchant payment
                $wallet->is_default  = 'No';
                $wallet->save();
            } else {
                $merchantWallet->balance = ($merchantWallet->balance + $merchantPayment->amount);
                $merchantWallet->save();
            }
            \Log::info('ok 5');

            DB::commit();
            $response = $this->helper->sendTransactionNotificationToAdmin('payment', ['data' => $merchantPayment]);
            $data["redirectedUrl"] = "/payment/success";
            $data['status']        = 200;
        } catch (Exception $e) {
            DB::rollBack();
            $data['message'] = $e->getMessage();
        }
        return response()->json(['data' => $data]);
    }
    /*PayPal Merchant Payment ends*/


    /* ClicToPay Merchant Payment starts*/
    public function clicToPayPayment(Request $request)
    {
        $rules = array(
            'amount'   => 'required|numeric',
            'merchant' => 'required',
        );
        $validator   = Validator::make($request->all(), $rules);
        $merchantChk = Merchant::find($request->merchant, ['id', 'user_id', 'status', 'fee']);
        if (!$merchantChk)
        {
            $this->helper->one_time_message('error', 'Merchant not found');
            return redirect('payment/fail');
        }

        if ($validator->fails() || $merchantChk->status != 'Approved')
        {
            $this->helper->one_time_message('error', 'Validation failed');
            return redirect('payment/fail');
        }

        else
        {
            $amount        = $request->amount;
            // convert amount to millimes
            $amount = $amount * 1000;
            $currency      = 'TND'; // TND is the only allowed currency for clic to pay payments
            $merchant      = $request->merchant;
            $item_name     = $request->item_name;
            $order_no      = $request->order_no;
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
           $url = 'https://'.env('CLICTOPAY_MODE').'.clictopay.com/payment/rest/register.do?userName='.$methodData->username.'&password='.$methodData->password.'&amount='.$amount.'&currency=788&language=fr&orderNumber='.$unique_code.'&returnUrl='.env('APP_URL').'/payment/clictopay-finish&failUrl='.env('APP_URL').'/payment/clictopay-finish&pageView=DESKTOP';
           // for testing locally 
           // $url = 'https://test.clictopay.com/payment/rest/register.do?userName='.$methodData->username.'&password='.$methodData->password.'&amount='.$amount.'&currency=788&language=fr&orderNumber='.$unique_code.'&returnUrl=https://www.google.com&failUrl=https://www.google.com&pageView=DESKTOP';

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
                        'merchant' => $merchant,
                        'item_name' => $item_name,
                        'order_no' => $order_no,
                        'unique_code' => $unique_code,
                        'created_at' =>  Carbon::now(),
                        'gateway_reference' => $results->orderId,
                    ]
                );
                return redirect($results->formUrl);
           }

        }
    }

    public function clicToPayFinish(Request $request)
    {   
        $orderId=$request->orderId;
        $order = DB::table('payment_gateway_request')->where('gateway_reference', $orderId)->get();

        if (!isset($request->orderId) || !$order || empty($order) || $request->orderId != $order[0]->gateway_reference)
        {
            // Session::flush();
            return redirect('payment/fail');
        }

        // get session data
        $unique_code        = $order[0]->gateway_reference;
        $amount             = $order[0]->amount;
        $created_at             = $order[0]->created_at;
        // convert back amount to dinars
        $amount = $amount / 1000;
        // $amount            = 20;
        $payment_method_id = $order[0]->payment_method_id;
        // $payment_method_id = 10;
        $paymentMethod     = PaymentMethod::where(['id' => $payment_method_id])->first(['id', 'name']);
        $merchant          = $order[0]->merchant;
        // $merchant          = 3;
        $item_name         = $order[0]->item_name;
        // $item_name         = "";
        $order_no          = $order[0]->order_no;
        // $order_no          = "123465";
        $gateway_reference = $order[0]->gateway_reference;
        // $gateway_reference = '';
        $currencyId        = $order[0]->currency_id;
        // $currencyId        = '5';
        // $currency          = Session::get('currency');
        $currency      = Currency::where(['id' => $currencyId])->first(['id', 'code']);

        $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $currencyId, 'method_id' => $paymentMethod->id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
        $methodData            = json_decode($currencyPaymentMethod->method_data);
        if (empty($methodData))
        {
            $this->helper->one_time_message('error', 'For currency' . $currency->code . ' credential not found!');
            return redirect('payment/fail');
        }

        $response = $this->client->get('https://'.env('CLICTOPAY_MODE').'.clictopay.com/payment/rest/getOrderStatusExtended.do', [
            'query' => [
                    'userName' => $methodData->username,
                    'password' => $methodData->password,
                    'language' => 'fr',
                    'orderId' => $gateway_reference
                ]
        ]);
        $results = $response->getBody();
        $results = json_decode($results);
        // var_dump($results);
        
        // Order status is valid: Merchant Payment starts
        if($results->orderStatus == 2) {
            // Payment Received
            $merchantInfo = Merchant::find($merchant, ['id', 'user_id', 'fee','business_name']);
            if (!$merchantInfo)
            {
                // Session::flush();
                $this->helper->one_time_message('error', __('Merchant not found!'));
                return redirect('payment/fail');
            }

            //Deposit + Merchant Fee (starts)
            $checkDepositFeesLimit            = $this->checkDepositFeesPaymentMethod($currency->id, $paymentMethod->id, $amount, $merchantInfo->fee);
            $feeInfoChargePercentage          = $checkDepositFeesLimit['feeInfoChargePercentage'];
            $feeInfoChargeFixed               = $checkDepositFeesLimit['feeInfoChargeFixed'];
            $depositCalcPercentVal            = $checkDepositFeesLimit['depositCalcPercentVal'];
            $depositTotalFee                  = $checkDepositFeesLimit['depositTotalFee'];
            $merchantCalcPercentValOrTotalFee = $checkDepositFeesLimit['merchantCalcPercentValOrTotalFee'];
            $totalFee                         = $checkDepositFeesLimit['totalFee'];
            //Deposit + Merchant Fee (ends)

            // deposit starts
            try
                {
                    DB::beginTransaction();

                    //MerchantPayment
                    $merchantPayment                    = new MerchantPayment();
                    $merchantPayment->merchant_id       = $merchant;
                    $merchantPayment->currency_id       = $currency->id;
                    $merchantPayment->payment_method_id = $paymentMethod->id;
                    $merchantPayment->gateway_reference = $gateway_reference;
                    $merchantPayment->order_no          = $order_no;
                    $merchantPayment->item_name         = $item_name;
                    $merchantPayment->uuid              = $unique_code;
                    $merchantPayment->charge_percentage = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee; //new
                    $merchantPayment->charge_fixed      = $feeInfoChargeFixed;                                        //new
                    $merchantPayment->amount            = $amount - $totalFee;                                        //new
                    $merchantPayment->total             = $amount;
                    $merchantPayment->status            = 'Success';
                    $merchantPayment->payer_email       = $results->payerData->email;
                    $merchantPayment->cardholderName    = $results->cardAuthInfo->cardholderName;
                    $merchantPayment->save();

                    //Transaction
                    $transaction                           = new Transaction();
                    $transaction->user_id                  = $merchantInfo->user_id;
                    $transaction->currency_id              = $currency->id;
                    $transaction->payment_method_id        = $paymentMethod->id;
                    $transaction->merchant_id              = $merchant;
                    $transaction->uuid                     = $unique_code;
                    $transaction->transaction_reference_id = $merchantPayment->id;
                    $transaction->transaction_type_id      = Payment_Received;
                    $transaction->subtotal                 = $amount - $totalFee;                                                                             //new
                    $transaction->percentage               = $merchantInfo->fee + $feeInfoChargePercentage;                                                   //new
                    $transaction->charge_percentage        = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee;                                      //new
                    $transaction->charge_fixed             = $feeInfoChargeFixed;                                                                             //new
                    $transaction->total                    = $merchantPayment->charge_percentage + $merchantPayment->charge_fixed + $merchantPayment->amount; //new
                    $transaction->status                   = 'Success';
                    $transaction->save();

                    //Wallet
                    $merchantWallet = Wallet::where(['user_id' => $merchantInfo->user_id, 'currency_id' => $currency->id])->first(['id', 'balance']);
                    $merchantWallet->balance = $merchantWallet->balance + $merchantPayment->amount;
                    $merchantWallet->save();

                    DB::commit();

                    // Send mail to admin
                    $response = $this->helper->sendTransactionNotificationToAdmin('payment', ['data' => $merchantPayment]);
                    
                    $data['orderDetails'] = $merchantPayment;
                    $data['unique_code'] = $unique_code;
                    $data['amountItem']=$amount;
                    $data['business_name']=$merchantInfo->business_name;
                    $data['PaymentMethod']="Clictopay";
                    $data['created_at']=date('d/m/Y h:i', strtotime($created_at));

                    return view('merchantPayment.success',$data);

                    // Session::flush();
                    // return redirect('payment/success');
                }
                catch (Exception $e)
                {
                    DB::rollBack();
                    // Session::flush();
                    $this->helper->one_time_message('error', $e->getMessage());
                    return redirect('payment/fail');
                }
        }

        // clic to pay response not valid
        // Session::flush();
        return redirect('payment/fail');
    }

    /* ClicToPay Merchant Payment ends*/


    /*PayUMoney Merchant Payment Starts*/
    public function payumoney(Request $request)
    {
        if (session('payumoney_merchant_currency_code') != 'INR')
        {
            $this->helper->one_time_message('error', __('PayUMoney only supports Indian Rupee(INR)'));
            return redirect('payment/fail');
        }
        else
        {
            $paymentMethod         = PaymentMethod::where(['name' => 'PayUmoney'])->first(['id']);
            $currency              = Currency::where(['code' => session('payumoney_merchant_currency_code')])->first(['id']);
            $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $currency->id, 'method_id' => $paymentMethod->id])->where('activated_for', 'like', "%deposit%")->first();
            if (empty($currencyPaymentMethod))
            {
                return redirect('payment/fail');
            }
            $methodData        = json_decode($currencyPaymentMethod->method_data);
            $data['amount']    = number_format((float) $request->amount, 2, '.', ''); //Payumoney accepts 2 decimal places only - if not rounded to 2 decimal places, Payumoney will throw.
            $data['mode']      = $methodData->mode;
            $data['key']       = $methodData->key;
            $data['salt']      = $methodData->salt;
            $data['txnid']     = unique_code();
            $data['email']     = '';
            $data['firstname'] = '';
            Session::put('amount', $request->amount);
            Session::put('merchant', $request->merchant);
            Session::put('item_name', $request->item_name);
            Session::put('order_no', $request->order_no);
            Session::save();
            return view('merchantPayment.payumoney', $data);
        }
    }

    public function payuPaymentSuccess(Request $request)
    {
        if (session('payumoney_merchant_currency_code') !== 'INR')
        {
            $this->helper->one_time_message('error', __('PayUMoney only supports Indian Rupee(INR)'));
            // Session::flush();
            return redirect('payment/fail');
        }
        else
        {
            $paymentMethod = PaymentMethod::where(['name' => 'PayUmoney'])->first(['id']);
            $currency      = Currency::where(['code' => session('payumoney_merchant_currency_code')])->first(['id', 'code']);
            $unique_code   = unique_code();
            $amount        = Session::get('amount');
            $merchant      = Session::get('merchant');
            $item_name     = Session::get('item_name');
            $order_no      = Session::get('order_no');

            // Payment Received
            $merchantInfo = Merchant::find($merchant, ['id', 'user_id', 'fee']);
            if (!$merchantInfo)
            {
                // Session::flush();
                $this->helper->one_time_message('error', __('Merchant not found!'));
                return redirect('payment/fail');
            }

            //Deposit + Merchant Fee (starts)
            $checkDepositFeesLimit            = $this->checkDepositFeesPaymentMethod($currency->id, $paymentMethod->id, $amount, $merchantInfo->fee);
            $feeInfoChargePercentage          = $checkDepositFeesLimit['feeInfoChargePercentage'];
            $feeInfoChargeFixed               = $checkDepositFeesLimit['feeInfoChargeFixed'];
            $depositCalcPercentVal            = $checkDepositFeesLimit['depositCalcPercentVal'];
            $depositTotalFee                  = $checkDepositFeesLimit['depositTotalFee'];
            $merchantCalcPercentValOrTotalFee = $checkDepositFeesLimit['merchantCalcPercentValOrTotalFee'];
            $totalFee                         = $checkDepositFeesLimit['totalFee'];
            //Deposit + Merchant Fee (ends)

            if ($request->all())
            {
                try
                {
                    DB::beginTransaction();

                    //MerchantPayment
                    $merchantPayment                    = new MerchantPayment();
                    $merchantPayment->merchant_id       = $merchant;
                    $merchantPayment->currency_id       = $currency->id;
                    $merchantPayment->payment_method_id = $paymentMethod->id;
                    $merchantPayment->gateway_reference = $request['key'];
                    $merchantPayment->order_no          = $order_no;
                    $merchantPayment->item_name         = $item_name;
                    $merchantPayment->uuid              = $unique_code;
                    $merchantPayment->charge_percentage = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee; //new
                    $merchantPayment->charge_fixed      = $feeInfoChargeFixed;                                        //new
                    $merchantPayment->amount            = $amount - $totalFee;                                        //new
                    $merchantPayment->total             = $amount;
                    $merchantPayment->status            = 'Success';
                    $merchantPayment->save();

                    //Transaction
                    $transaction                           = new Transaction();
                    $transaction->user_id                  = $merchantInfo->user_id;
                    $transaction->currency_id              = $currency->id;
                    $transaction->payment_method_id        = $paymentMethod->id;
                    $transaction->merchant_id              = $merchant;
                    $transaction->uuid                     = $unique_code;
                    $transaction->transaction_reference_id = $merchantPayment->id;
                    $transaction->transaction_type_id      = Payment_Received;
                    $transaction->subtotal                 = $amount - $totalFee;                                                                             //new
                    $transaction->percentage               = $merchantInfo->fee + $feeInfoChargePercentage;                                                   //new
                    $transaction->charge_percentage        = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee;                                      //new
                    $transaction->charge_fixed             = $feeInfoChargeFixed;                                                                             //new
                    $transaction->total                    = $merchantPayment->charge_percentage + $merchantPayment->charge_fixed + $merchantPayment->amount; //new
                    $transaction->status                   = 'Success';
                    $transaction->save();

                    //Wallet
                    $merchantWallet = Wallet::where(['user_id' => $merchantInfo->user_id, 'currency_id' => $currency->id])->first(['id', 'balance']);
                    if (empty($merchantWallet))
                    {
                        $wallet              = new Wallet();
                        $wallet->user_id     = $merchantInfo->user_id;
                        $wallet->currency_id = $currency->id;
                        $wallet->balance     = $merchantPayment->amount; // if wallet does not exist - merchant's wallet is created and balance also added - when user makes a merchant payment
                        $wallet->is_default  = 'No';
                        $wallet->save();
                    }
                    else
                    {
                        $merchantWallet->balance = $merchantWallet->balance + $merchantPayment->amount;
                        $merchantWallet->save();
                    }
                    // DB::commit();

                    // Send mail to admin
                    $response = $this->helper->sendTransactionNotificationToAdmin('payment', ['data' => $merchantPayment]);

                    clearActionSession();
                    return redirect('payment/success');
                }
                catch (Exception $e)
                {
                    DB::rollBack();
                    clearActionSession();
                    $this->helper->one_time_message('error', $e->getMessage());
                    return redirect('payment/fail');
                }
            }
            else
            {
                clearActionSession();
                return redirect('payment/fail');
            }
        }
    }

    //fixed in pm_v2.3
    public function merchantPayumoneyPaymentFail(Request $request)
    {
        if ($_POST['status'] == 'failure')
        {
            clearActionSession();
            $this->helper->one_time_message('error', __('You have cancelled your payment'));
            return redirect('/');
        }
    }
    /*PayUMoney Merchant Payment Ends*/

    /*CoinPayments Merchant Payment Starts*/
    public function coinPayments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount'   => 'required|numeric',
            'merchant' => 'required',
        ]);
        $merchantChk = Merchant::find($request->merchant);

        if (! $merchantChk) {
            return redirect('payment/fail');
        }
        if ($validator->fails() || $merchantChk->status != 'Approved') {
            return redirect('payment/fail');
        }

        $amount        = $request->amount;
        $currencyCode  = $request->currency;
        $merchant      = $request->merchant;
        $item_name     = $request->item_name;
        $order_no      = $request->order_no;
        $currency      = Currency::where('code', $currencyCode)->first(['id', 'code']);
        $paymentMethod = PaymentMethod::where(['name' => 'Coinpayments'])->first(['id']);

        $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => $currency->id, 'method_id' => $paymentMethod->id])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
        $methodData            = json_decode($currencyPaymentMethod->method_data);

        $data = $coins = $aliases = [];

        $coinPayment = new CoinPaymentRepository();
        $coinPayment->Setup($methodData->private_key, $methodData->public_key);


        $rates = $coinPayment->GetRates(0)['result'];

        $rateBtc = $rates['BTC']['rate_btc'];

        $rateofFiatCurrency = $rates[$currency->code]['rate_btc'];
        $rateAmount   = $rateofFiatCurrency * $amount;
        $fiat         = $coins_accept = [];

        foreach ($rates as $coin => $coinDetails) {
            if ((INT) $coinDetails['is_fiat'] === 0) {
                if ($rates[$coin]['rate_btc'] != 0) {
                    $rate = ($rateAmount / $rates[$coin]['rate_btc']);
                }
                else {
                    $rate = $rateAmount;
                }
                $coins[] = [
                    'name'     => $coinDetails['name'],
                    'rate'     => number_format($rate, 8, '.', ''),
                    'iso'      => $coin,
                    'icon'     => 'https://www.coinpayments.net/images/coins/' . $coin . '.png',
                    'selected' => $coin == 'BTC' ? true : false,
                    'accepted' => $coinDetails['accepted'],
                ];
                $aliases[$coin] = $coinDetails['name'];
            }

            if ((INT) $coinDetails['is_fiat'] === 0 && $coinDetails['accepted'] == 1) {
                $renamedCoin = explode('.', $coin);

                $rate           = ($rateAmount / $rates[$coin]['rate_btc']);
                $coins_accept[] = [
                    'name'     => $coinDetails['name'],
                    'rate'     => number_format($rate, 8, '.', ''),
                    'iso'      => $coin,
                    'icon'     => 'https://www.coinpayments.net/images/coins/' . ((count($renamedCoin) > 1) ? $renamedCoin[0] : $coin)  . '.png',
                    'selected' => $coin == 'BTC' ? true : false,
                    'accepted' => $coinDetails['accepted'],
                ];
            }

            if ((INT) $coinDetails['is_fiat'] === 1) {
                $fiat[$coin] = $coinDetails;
            }
        }

        $coinPaymentTransaction['coinList'] = $coins_accept;
        $coinPaymentTransaction['currencyCode'] = $currencyCode;
        $coinPaymentTransaction['type'] = 'merchant';
        $coinPaymentTransaction['amount'] = $amount;
        $coinPaymentTransaction['merchant'] = $merchant;
        $coinPaymentTransaction['currency_id'] =  $currency->id;
        $coinPaymentTransaction['payment_method'] =  $paymentMethod->id;
        $coinPaymentTransaction['item_name'] =  $item_name;
        $coinPaymentTransaction['order_no'] =  $order_no;
        Session::put('coinPaymentTransaction', $coinPaymentTransaction);

        $data = ['coin' => $coins, 'coin_accept' => $coins_accept, 'encoded_coin_accept' => json_encode($coins_accept), 'aliases' => $aliases, 'fiats' => $fiat];
        $data['amount'] = $amount;
        $data['currencyCode'] = $currency->code;

        return view('merchantPayment.coinpayment', $data);
    }

    public function coinPaymentMakeTransaction(Request $request)
    {
        $acceptedCoin = Session::get('coinPaymentTransaction')['coinList'];
        $acceptedCoinIso = array_column( $acceptedCoin, 'iso');

        if (empty($request->selected_coin) || !in_array($request->selected_coin, $acceptedCoinIso)) {
            $this->helper->one_time_message('error', __('Please select a crypto coin.'));
            return redirect('payment/fail');
        }

        // Payment method
        $currencyPaymentMethod = CurrencyPaymentMethod::where(['currency_id' => Session::get('coinPaymentTransaction')['currency_id'], 'method_id' => Session::get('coinPaymentTransaction')['payment_method']])->where('activated_for', 'like', "%deposit%")->first(['method_data']);
        $methodData            = json_decode($currencyPaymentMethod->method_data);

        $coinPayment = new CoinPaymentRepository();
        $coinPayment->Setup($methodData->private_key, $methodData->public_key);;

        $uuid       = unique_code();

        $transactionData = [
            'amount' => Session::get('coinPaymentTransaction')['amount'],
            'currency1' => Session::get('coinPaymentTransaction')['currencyCode'],
            'currency2' => $request->selected_coin,
            'buyer_email' => 'test.techvill@gmail.com',
            'address' => '',
            'buyer_name' => 'Test User',
            'item_name' => 'Payment via coinpayment',
            'invoice' => $uuid,
            'ipn_url' => url("coinpayment/check"),
            'cancel_url' => url("payment/fail"),
            'success_url' => url('payment/success'),
        ];


        $makeTransaction =  $coinPayment->CreateTransaction($transactionData);
        $makeTransaction['params'] = [];

        $makeTransaction['payload'] = ['type' => Session::get('coinPaymentTransaction')['type'], 'merchant'=> Session::get('coinPaymentTransaction')['merchant'], 'currency' => Session::get('coinPaymentTransaction')['currencyCode']];


        $transactionInfo = $coinPayment->getTransactionInfo(['txid' => $makeTransaction['result']['txn_id']]);

        Session::put('transactionDetails', $makeTransaction);
        Session::put('transactionInfo', $transactionInfo);


        if ($makeTransaction['error'] == 'ok') {

            $saved['merchant_id'] = $makeTransaction['payload']['merchant'];
            $data    = $transactionInfo['result'];
            $payload = $makeTransaction['payload'];

            try {
                DB::beginTransaction();

                //MerchantPayment
                $merchantPayment                    = new MerchantPayment();
                $merchantPayment->merchant_id       = $makeTransaction['payload']['merchant'];
                $merchantInfo                       = Merchant::find($merchantPayment->merchant_id, ['id', 'fee', 'user_id']);
                $merchantPayment->currency_id       = Session::get('coinPaymentTransaction')['currency_id'];
                $merchantPayment->payment_method_id = Session::get('coinPaymentTransaction')['payment_method'];
                $merchantPayment->gateway_reference = $makeTransaction['result']['txn_id'];
                $merchantPayment->item_name         = Session::get('coinPaymentTransaction')['item_name'];
                $merchantPayment->order_no          = Session::get('coinPaymentTransaction')['order_no'];
                $merchantPayment->uuid              = $uuid;
                $merchantPayment->total             = Session::get('coinPaymentTransaction')['amount'];
                //Deposit + Merchant Fee (starts)
                $feeInfo = FeesLimit::with('currency:id,code')
                ->where(['transaction_type_id' => Deposit, 'currency_id' => Session::get('coinPaymentTransaction')['currency_id'], 'payment_method_id' => $merchantPayment->payment_method_id])
                ->first(['charge_percentage', 'charge_fixed', 'has_transaction', 'currency_id']);

                if ($feeInfo->has_transaction == "Yes") {
                    //if fees limit is not active, both merchant fee and deposit fee will be added
                    $feeInfoChargePercentage          = @$feeInfo->charge_percentage;
                    $feeInfoChargeFixed               = @$feeInfo->charge_fixed;
                    $depositCalcPercentVal            = $merchantPayment->total * (@$feeInfoChargePercentage / 100);
                    $depositTotalFee                  = $depositCalcPercentVal+@$feeInfoChargeFixed;
                    $merchantCalcPercentValOrTotalFee = $merchantPayment->total * ($merchantInfo->fee / 100);
                    $totalFee                         = $depositTotalFee + $merchantCalcPercentValOrTotalFee;
                } else {
                    //if fees limit is not active, only merchant fee will be added
                    $feeInfoChargePercentage          = 0;
                    $feeInfoChargeFixed               = 0;
                    $depositCalcPercentVal            = 0;
                    $depositTotalFee                  = 0;
                    $merchantCalcPercentValOrTotalFee = $merchantPayment->total * ($merchantInfo->fee / 100);
                    $totalFee                         = $depositTotalFee + $merchantCalcPercentValOrTotalFee;
                }

                //Deposit + Merchant Fee (ends)
                $merchantPayment->amount            = $merchantPayment->total - $totalFee;
                $merchantPayment->charge_percentage = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee;
                $merchantPayment->charge_fixed      = $feeInfoChargeFixed;                                        
                $merchantPayment->status            = 'Pending';
                $merchantPayment->save();

                //Transaction
                $transaction                           = new Transaction();
                $transaction->user_id                  = $merchantInfo->user_id;
                $transaction->currency_id              = $merchantPayment->currency_id;
                $transaction->payment_method_id        = $merchantPayment->payment_method_id;
                $transaction->merchant_id              = $merchantPayment->merchant_id;
                $transaction->uuid                     = $merchantPayment->uuid;
                $transaction->transaction_reference_id = $merchantPayment->id;
                $transaction->transaction_type_id      = Payment_Received;
                $transaction->subtotal                 = $merchantPayment->total - $totalFee;                                                             
                $transaction->percentage               = $merchantInfo->fee + $feeInfoChargePercentage;                                                   
                $transaction->charge_percentage        = $depositCalcPercentVal + $merchantCalcPercentValOrTotalFee;                                      
                $transaction->charge_fixed             = $feeInfoChargeFixed;                                                                            
                $transaction->total                    = $merchantPayment->charge_percentage + $merchantPayment->charge_fixed + $merchantPayment->amount; 
                $transaction->status                   = 'Pending';
                $transaction->save();

                //No wallet change at first cause transaction status is pending, when real payment will occur, transaction will be success.
                $merchantWallet = Wallet::where(['user_id' => $merchantInfo->user_id, 'currency_id' => $merchantPayment->currency_id])->first(['id']);
                if (empty($merchantWallet))
                {
                    $wallet              = new Wallet();
                    $wallet->user_id     = $merchantInfo->user_id;
                    $wallet->currency_id = $merchantPayment->currency_id;
                    $wallet->balance     = 0; // as initially, transaction status will be pending
                    $wallet->is_default  = 'No';
                    $wallet->save();
                }

                $payload                        = empty($makeTransaction['payload']) ? [] : $makeTransaction['payload'];
                $payload['merchant_payment_id'] = $merchantPayment->id;
                $payload                        = json_encode($payload);

                $saved                          = [
                    'merchant_id'        => $makeTransaction['payload']['merchant'],
                    'payment_id'         => $makeTransaction['result']['txn_id'],
                    'payment_address'    => $data['payment_address'],
                    'coin'               => $data['coin'],
                    'fiat'               => $makeTransaction['payload']['currency'],
                    'status_text'        => $data['status_text'],
                    'status'             => $data['status'],
                    'payment_created_at' => date('Y-m-d H:i:s', $data['time_created']),
                    'expired'            => date('Y-m-d H:i:s', $data['time_expires']),
                    'amount'             => $data['amountf'],
                    'confirms_needed'    => empty($makeTransaction['result']['confirms_needed']) ? 0 : $makeTransaction['result']['confirms_needed'],
                    'qrcode_url'         => empty($makeTransaction['result']['qrcode_url']) ? '' : $makeTransaction['result']['qrcode_url'],
                    'status_url'         => empty($makeTransaction['result']['status_url']) ? '' : $makeTransaction['result']['status_url'],
                    'payload'            => $payload,
                ];
                CoinpaymentLogTrx::create($saved);

                DB::commit();

                // Send notification to admin
                $response = $this->helper->sendTransactionNotificationToAdmin('payment', ['data' => $merchantPayment]);

                return redirect('payment/coinpayments/coinpayment-transaction-info');

            } catch (\Exception $e) {
                DB::rollBack();
                // $this->helper->one_time_message('error', $e->getMessage());
                // return back();
                $exception          = [];
                $exception['error'] = json_encode($e->getMessage());
                return $exception;
            }
        }
    }

    public function viewCoinpaymentTransactionInfo()
    {
        $data['transactionDetails'] = Session::get('transactionDetails');
        $data['transactionInfo'] = Session::get('transactionInfo');

        return view('merchantPayment.coinpayment_summery', $data);
    }


    public function coinPaymentsCheck()
    {
        $coinLog = CointpaymentLogTrx::where('status', 0)->get(['id', 'payload', 'status_text', 'status', 'confirmation_at']);
        foreach ($coinLog as $data)
        {
            $obj = json_decode($data->payload);

            if (isset($obj->type) && $obj->type == "merchant" && isset($obj->merchant_payment_id))
            {
                $merchantPayment = MerchantPayment::find($obj->merchant_payment_id);
                if (isset($merchantPayment->gateway_reference))
                {
                    //
                    $session['payment_method'] = $merchantPayment->payment_method_id;
                    $session['currency_id']    = $merchantPayment->currency_id;
                    session(['transInfo' => $session]);
                    //

                    $payment = CoinPayment::api_call('get_tx_info', [
                        'txid' => $merchantPayment->gateway_reference,
                    ]);

                    if ($payment['error'] == "ok")
                    {
                        $result = $payment['result'];
                        if ($result['status'] == 100 || $result['status'] == 2)
                        {
                            try
                            {
                                DB::beginTransaction();

                                $data->status_text     = $result['status_text'];
                                $data->status          = $result['status'];
                                $data->confirmation_at = ((INT) $result['status'] === 100 || (INT) $result['status'] === 2) ? date('Y-m-d H:i:s', $result['time_completed']) : null;
                                $data->save();

                                //merchantPayment / Payment Received
                                $merchantPayment->status = "Success";
                                $merchantPayment->save();

                                $merchantInfo = Merchant::find($merchantPayment->merchant_id, ['id', 'user_id', 'fee']);
                                if (!empty($merchantInfo))
                                {
                                    //transaction
                                    $transaction = Transaction::where("transaction_reference_id", $obj->merchant_payment_id)->where('transaction_type_id', Payment_Received)->first(['id', 'status']);
                                    if (!empty($transaction))
                                    {
                                        $transaction->status = "Success";
                                        $transaction->save();
                                    }

                                    //Wallet
                                    $merchantWallet = Wallet::where(['user_id' => $merchantInfo->user_id, 'currency_id' => $merchantPayment->currency_id])->first(['id', 'balance']);
                                    if (!empty($merchantWallet))
                                    {
                                        $merchantWallet->balance = ($merchantWallet->balance + $merchantPayment->amount);
                                        $merchantWallet->save();
                                    }
                                }

                                DB::commit();

                                // Send mail to admin
                                $response = $this->helper->sendTransactionNotificationToAdmin('payment', ['data' => $merchantPayment]);

                            }
                            catch (Exception $e)
                            {
                                DB::rollBack();
                                $this->helper->one_time_message('error', $e->getMessage());
                                return redirect('payment/fail');
                            }
                        }
                        else if ($result['status'] == 0)
                        {
                            echo "<pre>";
                            echo "Waiting for CoinPayments buyer funds for txid- " . $merchantPayment->gateway_reference;
                            echo "<br>";
                        }
                        else if ($result['status'] < 0)
                        {
                            //payment error, this is usually final but payments will sometimes be reopened if there was no exchange rate conversion or with seller consent
                            echo "<pre>";
                            echo "Payment Error for txid- " . $merchantPayment->gateway_reference;
                            echo "<br>";
                        }
                        else
                        {
                            echo "<pre>";
                            echo "Payment not complete for txid- " . $merchantPayment->gateway_reference;
                            echo "<br>";
                        }
                    }
                }
            }
        }
    }
    
    /*CoinPayments Merchant Payment Ends*/

    public function success()
    {
        $data['amount']        = Session::get('merchant_amount');
        $data['currency_code'] = Session::get('merchant_currency_code');
        return view('merchantPayment.success', $data);
    }

    public function fail()
    {
        return view('merchantPayment.fail');
    }

    /**
     * [Extended Function] - Checks Deposit Fees Of each Payment Method(if fees limit is active) with Merchant fee - starts
     */
    protected function checkDepositFeesPaymentMethod($currencyId, $paymentMethodId, $amount, $merchantFee)
    {
        $feeInfo = FeesLimit::where(['transaction_type_id' => Deposit, 'currency_id' => $currencyId, 'payment_method_id' => $paymentMethodId])
            ->first(['charge_percentage', 'charge_fixed', 'has_transaction']);
        if (!empty($feeInfo) && $feeInfo->has_transaction == "Yes")
        {
            //if fees limit is not active, both merchant fee and deposit fee will be added
            $feeInfoChargePercentage          = @$feeInfo->charge_percentage;
            $feeInfoChargeFixed               = @$feeInfo->charge_fixed;
            $depositCalcPercentVal            = $amount * (@$feeInfoChargePercentage / 100);
            $depositTotalFee                  = $depositCalcPercentVal+@$feeInfoChargeFixed;
            $merchantCalcPercentValOrTotalFee = $amount * ($merchantFee / 100);
            $totalFee                         = $depositTotalFee + $merchantCalcPercentValOrTotalFee;
        }
        else
        {
            //if fees limit is not active, only merchant fee will be added
            $feeInfoChargePercentage          = 0;
            $feeInfoChargeFixed               = 0;
            $depositCalcPercentVal            = 0;
            $depositTotalFee                  = 0;
            $merchantCalcPercentValOrTotalFee = $amount * ($merchantFee / 100);
            $totalFee                         = $depositTotalFee + $merchantCalcPercentValOrTotalFee;
        }
        $data = [
            'feeInfoChargePercentage'          => $feeInfoChargePercentage,
            'feeInfoChargeFixed'               => $feeInfoChargeFixed,
            'depositCalcPercentVal'            => $depositCalcPercentVal,
            'depositTotalFee'                  => $depositTotalFee,
            'merchantCalcPercentValOrTotalFee' => $merchantCalcPercentValOrTotalFee,
            'totalFee'                         => $totalFee,
        ];
        return $data;
    }
    /**
     * [Extended Function] - ends
     */
}
