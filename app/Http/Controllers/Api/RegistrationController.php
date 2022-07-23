<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Users\EmailController;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{DB, 
    Validator
};
use Illuminate\Http\Request;
use App\Models\{RoleUser,
    Setting,
    User,
    Role,
    Country,
    EmailTemplate,
    RequestPayment,
    QrCode,
    Transaction,
    Transfer,
    UserDetail,
    VerifyUser,
    Wallet
};
use Exception;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    public $successStatus      = 200;
    public $unauthorisedStatus = 401;
    public $email;
    protected $user;

    public function __construct()
    {
        $this->email = new EmailController();
        $this->user  = new User();
    }

    public function getMerchantUserRoleExistence()
    {
        $data['checkMerchantRole'] = $checkMerchantRole = Role::where(['user_type' => 'User', 'customer_type' => 'merchant', 'is_default' => 'Yes'])->first(['id']);
        $data['checkUserRole']     = $checkUserRole     = Role::where(['user_type' => 'User', 'customer_type' => 'user', 'is_default' => 'Yes'])->first(['id']);

        return response()->json([
            'status'            => $this->successStatus,
            'checkMerchantRole' => $checkMerchantRole,
            'checkUserRole'     => $checkUserRole,
        ]);
    }

    public function duplicateEmailCheckApi(Request $request)
    {
        $email = User::where(['email' => $request->email])->exists();
        if ($email)
        {
            $data['status'] = true;
            $data['fail']   = 'The email has already been taken!';
        }
        else
        {
            $data['status']  = false;
            $data['success'] = "Email Available!";
        }
        return json_encode($data);
    }

    public function duplicatePhoneNumberCheckApi(Request $request)
    {
        $req_id = $request->id;
        if (isset($req_id))
        {
            $phone = User::where(['phone' => preg_replace("/[\s-]+/", "", $request->phone)])->where(function ($query) use ($req_id)
            {
                $query->where('id', '!=', $req_id);
            })->exists();
        }
        else
        {
            $phone = User::where(['phone' => preg_replace("/[\s-]+/", "", $request->phone)])->exists();
        }

        if ($phone) {
            $data['status'] = true;
            $data['fail']   = "The phone number has already been taken!";
        } else {
            $data['status']  = false;
            $data['success'] = "The phone number is Available!";
        }
        return json_encode($data);
    }

    public function getDefaultCountryShortName()
    {
        $defaultCountryShortName = getDefaultCountry();

        $success['status']  = $this->successStatus;
        $success['defaultCountryShortName'] = $defaultCountryShortName;
        return response()->json(['success' => $success], $this->successStatus);
    }

    public function registration(Request $request)
    {
        $rules = array(
            'first_name' => 'required',
            'last_name'  => 'required',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required',
        );

        $fieldNames = array(
            'first_name' => 'First Name',
            'last_name'  => 'Last Name',
            'email'      => 'Email',
            'password'   => 'Password',
        );

        $validator = Validator::make($request->all(), $rules);
        $validator->setAttributeNames($fieldNames);
        if ($validator->fails())
        {
            $response['message'] = "Email/Phone already exist.";
            $response['status']  = $this->unauthorisedStatus;
            return response()->json(['success' => $response], $this->successStatus);
        }
        else
        {
            //default_currency
            $default_currency = Setting::where('name', 'default_currency')->first(['value']);

            try
            {
                DB::beginTransaction();

                //Create user
                $user = $this->user->createNewUser($request, 'user');
                
                //Assign user type and role to new user
                RoleUser::insert(['user_id' => $user->id, 'role_id' => $user->role_id, 'user_type' => 'User']);

                // Create user detail
                $this->user->createUserDetail($user->id);

                // Create user's default wallet
                $this->user->createUserDefaultWallet($user->id, $default_currency->value);

                // Entry for User's QrCode Generation - starts
                $this->saveUserQrCodeApi($user);
                // Entry for User's QrCode Generation - ends

                // Create user's crypto wallet/wallets address
                $this->user->generateUserCryptoWalletAddress($user);

                // Create user's crypto wallet/wallets address
                $generateUserCryptoWalletAddress = $this->user->generateUserCryptoWalletAddress($user);

                if ($generateUserCryptoWalletAddress['status'] == 401)
                {
                    DB::rollBack();
                    $success['status']  = $this->successStatus;
                    $success['reason']  = 'create-wallet-address-failed';
                    $success['message'] = $generateUserCryptoWalletAddress['message'];
                    return response()->json(['success' => $success], $this->successStatus);
                }

                $userEmail          = $user->email;
                $userFormattedPhone = $user->formattedPhone;

                // Process Registered User Transfers
                $this->user->processUnregisteredUserTransfers($userEmail, $userFormattedPhone, $user, $default_currency->value);

                // Process Registered User Request Payments
                $this->user->processUnregisteredUserRequestPayments($userEmail, $userFormattedPhone, $user, $default_currency->value);

                // Email verification
                if(!$user->user_detail->email_verification && !$user->user_detail->phone_verification_code) {
                    if (checkVerificationMailStatus() == "Enabled" && checkPhoneVerification() == "Enabled") {
                        if (checkAppMailEnvironment() && checkAppSmsEnvironment()) {
                            $emailVerificationArr = $this->user->processUserEmailVerification($user);
                            try {
                                $this->email->sendEmail($emailVerificationArr['email'], $emailVerificationArr['subject'], $emailVerificationArr['message']);
                                RegistrationController::sendPhoneCode($user->id);
                                DB::commit();
                                $success['status']  = $this->successStatus;
                                $success['reason']  = 'email_sms_verification';
                                $success['message'] = 'We sent you an activation code. Check your email and click on the link to verify.\r\n  We sent you an activation code. Check your phone and enter the code to verify.';
                                $success['user_id'] =  $user->id;  
                                return response()->json(['success' => $success], $this->successStatus);
                            } catch (Exception $e) {
                                DB::rollBack();
                                $success['status']  = $this->unauthorisedStatus;
                                $success['message'] = $e->getMessage();
                                $success['user_id'] =  $user->id;  
                                return response()->json(['success' => $success], $this->unauthorisedStatus);
                            }
                        }
                    }
                }

                elseif(!$user->user_detail->email_verification && $user->user_detail->phone_verification_code) {
                    if (checkVerificationMailStatus() == "Enabled" && !checkPhoneVerification() == "Enabled") {
                        if (checkAppMailEnvironment()) {
                            $emainVerificationArr = $this->user->processUserEmailVerification($user);
                            try {
                                $this->email->sendEmail($emainVerificationArr['email'], $emainVerificationArr['subject'], $emainVerificationArr['message']);

                               DB::commit();
                                $success['status']  = $this->successStatus;
                                $success['reason']  = 'email_verification';
                                $success['message'] = 'We sent you an activation code. Check your email and click on the link to verify.';
                                $success['user_id'] =  $user->id;  
                                return response()->json(['success' => $success], $this->successStatus);
                            } catch (Exception $e) {
                                DB::rollBack();
                                $success['status']  = $this->unauthorisedStatus;
                                $success['message'] = $e->getMessage();
                                $success['user_id'] =  $user->id;  
                                return response()->json(['success' => $success], $this->unauthorisedStatus);
                            }
                        }
                    }
                }
                // elseif(!$user->user_detail->email_verification && !$user->user_detail->phone_verification_code) {
                //     if (checkVerificationMailStatus() == "Enabled" && checkPhoneVerification() == "Enabled") {
                //     }
                // }

                //
                DB::commit();
                $success['status']  = $this->successStatus;
                $success['message'] = "Registration Successfull!";
                $success['user_id'] =  $user->id;  
                return response()->json(['success' => $success], $this->successStatus);
            } catch (Exception $e) {
                DB::rollBack();
                $success['status']  = $this->unauthorisedStatus;
                $success['message'] = $e->getMessage();
                $success['user_id'] =  $user->id;  
                return response()->json(['success' => $success], $this->unauthorisedStatus);
            }
        }
    }

    protected function saveUserQrCodeApi($user)
    {
        $qrCode = QrCode::where(['object_id' => $user->id, 'object_type' => 'user', 'status' => 'Active'])->first(['id']);
        if (empty($qrCode))
        {
            $createInstanceOfQrCode              = new QrCode();
            $createInstanceOfQrCode->object_id   = $user->id;
            $createInstanceOfQrCode->object_type = 'user';
            if (!empty($user->formattedPhone))
            {
                $createInstanceOfQrCode->secret = convert_string('encrypt', $createInstanceOfQrCode->object_type . '-' . $user->email . '-' . $user->formattedPhone . '-' . Str::random(6));
            }
            else
            {
                $createInstanceOfQrCode->secret = convert_string('encrypt', $createInstanceOfQrCode->object_type . '-' . $user->email . '-' . Str::random(6));
            }
            $createInstanceOfQrCode->status = 'Active';
            $createInstanceOfQrCode->save();
        }
    }

     public function verificationPhoneCode(Request $request)
    {
        $phone_code = $request->input('phone_code');
        $user_id = $request->input('user_id');

        $user = User::find($user_id);
        if(!$user)
        {
            $response['message'] = "User introuvable";
            $response['status']  = $this->unauthorisedStatus;
            return response()->json(['success' => $response], $this->successStatus);
        }
        if ($user->user_detail->phone_verification_code == $phone_code)
        {
            UserDetail::where('user_id' , $user_id)->update([
                'phone_verified' => 1
                // 'phone_verification_code' => 1
            ]);

            $success['status']  = $this->successStatus;
            $success['message'] = "Numéro du téléphone est verifié avec succés !";
            return response()->json(['success' => $success], $this->successStatus);
        }
        $response['message'] = "Code incorrect";
        $response['status']  = $this->unauthorisedStatus;
        return response()->json(['success' => $response], $this->successStatus);
    }



    public static function sendPhoneCode($user_id)
    {

    $user = User::find($user_id);
	if(!$user)
        {
            $response['message'] = "User introuvable";
            $response['status']  = '401';
            return response()->json(['success' => $response], '200');
        }

        $phone_code = six_digit_random_number();
        UserDetail::where('user_id' , $user->id)->update([
            'phone_verification_code' => $phone_code
        ]);

        // return UserDetail::find($user_id);
        $message = 'Code de vérification : '.$phone_code;
	try {
            $test = sendSMS($user->carrierCode . $user->phone, $message);
            // return $test;
            // $test = sendSMS(21652826414, "hi");
            // $test = sendSMSwithWbm(21652826414, "hi");
            $success['status']  = '200';
            $success['message'] = "Code de vérification a été envoyé avec succés !";
            $success['code'] = $message; 
            return response()->json(['success' => $success], '200');
 
	}catch (Exception $e){
            DB::rollBack();
            $success['status']  = '200';
            $success['message'] = $e->getMessage();
            return response()->json(['success' => $success], '401');
        }

    }

    public static function sendPhoneCodeRequest(Request $request)
    {
        $user_id = $request->user_id;
        sendPhoneCode($user_id);
    }



    public static function getOptions()
    {
        if( checkVerificationMailStatus() == "Enabled" && checkPhoneVerification() == "Enabled")
        {
            $response['VERIFY_USERS_ENABLE'] = "on";
            $response['VERIFY_USERS_PHONE_ENABLE'] = "on";

            return response()->json(['data' => $response], '200');
        }
        elseif( checkVerificationMailStatus() == "Enabled" && checkPhoneVerification() != "Enabled")
        {    
            $response['VERIFY_USERS_ENABLE'] = "on";
            $response['VERIFY_USERS_PHONE_ENABLE'] = "off";

        }
        elseif( checkVerificationMailStatus() != "Enabled" && checkPhoneVerification() == "Enabled")
        {    
            $response['VERIFY_USERS_ENABLE'] = "off";
            $response['VERIFY_USERS_PHONE_ENABLE'] = "on";

        }
        else {
            $response['VERIFY_USERS_ENABLE'] = "off";
            $response['VERIFY_USERS_PHONE_ENABLE'] = "off";

        }
        // $response['PHONE_NUMBER_PREFIX'] ;
        return response()->json(['data' => $response]);

    }


    public static function test(Request $request)
    {
        // $user_id = $request->user_id;
        // $user = User::find($user_id);
        // return $user->user_detail->phone_verification_code;

    //     // return $user->user_detail;
    //     // return checkAppSmsEnvironment() . checkAppMailEnvironment();
    }
}
