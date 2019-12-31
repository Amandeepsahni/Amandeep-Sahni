<?php

namespace App\Http\Controllers;
use Illuminate\Pagination\Paginator;
use Illuminate\Http\Request;
use App\Models\admin\Properties;
use App\Models\admin\Amenitie;
use App\Models\admin\Contactdetail;
use App\Models\admin\Users;
use App\Models\admin\Rooms;
use App\Models\admin\Adminshare;
use App\Models\admin\Extraoptions;
use App\Models\admin\Servicelist;
use App\Models\admin\Surgeonservices;
use App\Models\admin\Surgerytransactions;
use App\Models\admin\Message_center;
use App\Models\admin\Patient_questionaire;


use Illuminate\Support\Facades\Hash;
use App\Models\Orders;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\FeedbackMail;
use Auth;
use Stripe\Stripe;

use Session;

use Redirect;

use Stripe\Error\Card;
use Srmklive\PayPal\Services\ExpressCheckout;
use Srmklive\PayPal\Services\AdaptivePayments;


class FrontUserController extends Controller
{
     /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
   
   protected $provider;
    public function __construct() {
        $this->provider = new AdaptivePayments();
    }

    public function index(Request $request)
    {
        if (Auth::check())
            {   
                
                $userdetail = Users::find(Auth::user()->id);
                $contactdetail = Contactdetail::find('1');
                    // The user is logged in...         
                return view('useraccount.userprofile',compact('userdetail' , 'contactdetail'));

            }else{

                return redirect('home');
            }


        

    }
    public function unlinkImage($filepath,$fileName)
    {
            $old_image = $filepath.$fileName;
            if (file_exists($old_image)) {
               @unlink($old_image);
            }
    }

    public function updateUserProfileImage(Request $request)
    {
        // echo "<pre>";
        // print_r($_FILES);
        // echo "</pre>";

         $this->validate($request,[
             'image'    => 'required'
        ]);

        $imageName ='';
        if ($request->hasFile('image')) {

            //Old Profile Image remove Code
            $User= Users::find($request->userid);
                $proimage =$User->profile_image; 
                $filepath =public_path('public/uploads/users');      
                
            if($filepath){

                    $this->unlinkImage($filepath,$proimage);
            }

            $image = $request->file('image');
            $name = str_slug($request->username).'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/uploads/users');
            $imagePath = $destinationPath. "/".  $name;
            $image->move($destinationPath, $name);
            $imageName = $name;
            
         }
        
        $users = users::find($request->userid); 
        $users->name = $request->username;
        $users->profile_image = $imageName;
        $users->update();
        return redirect(route('user-account'))->with('success', 'User Profile Updated Successfully');
    }

    public function updateUserInfo(Request $request)
    {
        $this->validate($request,[
             'username'    => 'required'
        ]);     
        
        $users = users::find($request->userid);  
        $users->name = $request->username;       
        $users->update();
       return redirect(route('user-account'))->with('success', 'User Profile Updated Successfully');
    }
    
    public function UpdateWorkInfo(Request $request)
    {
        $users = users::find($request->userid);  
        $users->work_place = $request->work_place;       
        $users->work_designation = $request->designation;
        $users->work_description = $request->description;
        $users->update();
       return redirect(route('user-account'))->with('success', 'User Profile Updated Successfully');
    }

    public function UpdatePaypalEmail(Request $request)
    {
        $users = users::find($request->userid);  
        $users->paypal_email = $request->paypal_email;     
        $users->update();
       return redirect(route('user-account'))->with('success', 'Paypal Email Updated Successfully');
    }

    public function updateUserPassword(Request $request)
    {
        
        $this->validate($request,[
             'newpassword'    => 'required'
        ]);     
        
        $users = users::find($request->userid);  
        $users->password = bcrypt($request->newpassword);       
        $users->update();
       return redirect(route('user-account'))->with('success', 'User Profile Update Successfully');       
    }

    public function ajaxUpdateUserstripe(Request $request)
    {
        //dd($request);
        // You also need to lookup the user email at the same time.
        $currentUserEmail = $request->input('currentemail');
        // YOU_NEED_TO_ADD_CODE

        // Get the action type from the form submission.
        $actionType = $request->input('action_type');


        // More info about this on setup.php
        \Stripe\Stripe::setApiKey(config('constants.STRIPE_SECRET_KEY'));
        $stripeAccountId =$request->input('stripeconnetid');
        if(isset($_GET['stripeconnetid']) && empty($request->input('stripeconnetid')))
        {
            // Create a new Stripe Connect Account object.
            // For more info: https://stripe.com/docs/api#create_account
            $result = \Stripe\Account::create(array(
                                                "type" => "custom",
                                                "country" => "US",
                                                "email" => $currentUserEmail,
                                                ));
            $stripeAccountId = $result->id;  

            Session::put('stripeAccountId',$stripeAccountId);
            // Accept the TOS
            $stripeAccountObj = \Stripe\Account::retrieve($stripeAccountId);
            $stripeAccountObj->tos_acceptance->date = time();
            $stripeAccountObj->tos_acceptance->ip = $_SERVER['REMOTE_ADDR'];
            $stripeAccountObj->save();
        }
        // update the account  address

        // Special case for address
        $line = $request->input('line_textbox');
        $line2 = $request->input('line2_textbox');
        $city = $request->input('city_textbox');
        $state = $request->input('state_textbox');
        $country = $request->input('country_textbox');
        $postal = $request->input('postal_textbox') ;

        $stripeAccountObj = \Stripe\Account::retrieve($stripeAccountId);


        $stripeAccountObj->legal_entity->address->line1 = $line;
        $stripeAccountObj->save();

        if ($line2 != "") 
        {
            $stripeAccountObj->legal_entity->address->line2 = $line2;
            $stripeAccountObj->save();
        }

        $stripeAccountObj->legal_entity->address->city = $city;
        $stripeAccountObj->save();
        $stripeAccountObj->legal_entity->address->state = $state;
        $stripeAccountObj->save();
        $stripeAccountObj->legal_entity->address->country = $country;
        $stripeAccountObj->save();
        $stripeAccountObj->legal_entity->address->postal_code = $postal;
        $result = $stripeAccountObj->save(); 

        // date of birth
        if(!empty($request->input('dob')))
        {
            $valueInPieces = explode('-', $request->input('dob'));
            // If invalid format, hence not 3 -
            if (count($valueInPieces) != 3) {
            // So not array of 3
            die('Error: Invalid format for date, must be YYYY-MM-DD');
            }

            // Otherwise good, save it!
            $stripeAccountObj->legal_entity->dob->year = $valueInPieces[0];
            $stripeAccountObj->legal_entity->dob->month = $valueInPieces[1];
            $stripeAccountObj->legal_entity->dob->day = $valueInPieces[2];
            $stripeAccountObj->save();
        }
        // first name
        if(!empty($request->input('first_name')))
        {
            $stripeAccountObj->legal_entity->first_name = $request->input('first_name');      
            $stripeAccountObj->save();
        }

        //last name
        if(!empty($request->input('last_name')))
        {
            $stripeAccountObj->legal_entity->last_name = $request->input('last_name');      
            $stripeAccountObj->save();
        }

        // type
        if(!empty($request->input('type')))
        {
            $stripeAccountObj->legal_entity->type = $request->input('type');      
            $stripeAccountObj->save();
        }

        if(!empty($request->input('ssn_last_4')))
        {
            $stripeAccountObj->legal_entity->ssn_last_4 = $request->input('ssn_last_4');      
            $stripeAccountObj->save();
        }

        if(!empty($request->input('personal_id_number')))
        {
            $stripeAccountObj->legal_entity->personal_id_number = $request->input('personal_id_number');     
            $stripeAccountObj->save();
        }

        // `stripeconnectid`, `stripeamil`, `stripeDOB`, `stripfirst_lastname`, `stripe_address`, `stripe_ssn_last4`, `stripe_personal_id_number`, `stripe_status`, 
        $users = users::find(Auth::user()->id);  
        $users->stripeconnectid = $stripeAccountId;
        $users->stripeamil = $currentUserEmail;
        $users->stripeDOB = $request->input('dob');
        $users->stripfirst_lastname = $request->input('first_name').','.$request->input('last_name');
        $users->stripe_address = $line.','.$line2.','.$city.','.$state.','.$country.','.$postal;
        $users->stripe_ssn_last4 = $request->input('ssn_last_4');
        $users->stripe_personal_id_number = $request->input('personal_id_number');          
        $users->update();

        echo json_encode('stripe account connected successfully!');

         
    }


         public function updateBankaddress(Request $request)
             {

              \Stripe\Stripe::setApiKey(config('constants.STRIPE_SECRET_KEY'));
               // Accept the TOS
              $stripeAccountId = "";

          if(!empty($request->input('stripeconnetid'))){

            $stripeAccountId =$request->input('stripeconnetid');
          }

              if ($request->session()->has('stripeAccountId')) {

                  $stripeAccountId = Session::get('stripeAccountId');

              }           
              
            $token = $request->input('token');
            if ($token == '') {
             $response['success'] = false;
             $response['message'] = 'No token';
             echo json_encode($response);
             die('');
             }

            $stripeAccountObj = \Stripe\Account::retrieve($stripeAccountId);
            $stripeAccountObj->external_accounts->create(array("external_account" => $token['id']));
            $stripeAccountObj->save(); 

        // `routing_number`, `account_number`, `bankaccount_holder_type`, `account_holder_type`,
        $users = users::find(Auth::user()->id);  
        $users->routing_number =$request->input('routing_number');
        $users->account_number = $request->input('account_number');
        $users->account_holder_name = $request->input('account_holder_name');
        $users->account_holder_type = $request->input('account_holder_type');
        $users->update();
          echo 'stripe account Bank detail successfully Updated!';    
            }

 public function uploadDocumentWithStripe(Request $request)
    {


     // print_r($_FILES);

      \Stripe\Stripe::setApiKey(config('constants.STRIPE_SECRET_KEY'));
       // Accept the TOS
      $stripeAccountId = "";
      if(!empty($_POST['stripeconnetid'])){

        $stripeAccountId =$_POST['stripeconnetid'];
      }

      if($request->session()->has('stripeAccountId')) {

        $stripeAccountId = Session::get('stripeAccountId');

      } 

      $file = $_FILES["fileToUpload"]['tmp_name'];
      $fp = fopen($file, 'r');

      /*echo "<pre>";
      print_r($fp);
      echo "</pre>";
      die();*/
      $fileResponse = \Stripe\FileUpload::create(array(
       'purpose' => 'identity_document',
       'file' => $fp
      ));

      $stripeAccountObj = \Stripe\Account::retrieve($stripeAccountId);
      $stripeAccountObj->legal_entity->verification->document = $fileResponse->id;
      $stripeAccountObj->save();
      $imageName ='';
      if ($request->hasFile('fileToUpload')) {

          //Old Profile Image remove Code
            $User= Users::find(Auth::user()->id);
            $documentimage =$User->document; 
            $filepath =public_path('public/uploads/users/stripedocument');      
            
          if($filepath){

                $this->unlinkImage($filepath,$documentimage);
            }

            $image = $request->file('fileToUpload');
            $name = 'Userid_'.str_slug(Auth::user()->id).'.'.$image->getClientOriginalExtension();
            $destinationPath = public_path('/uploads/users/stripedocument');
            $imagePath = $destinationPath. "/".  $name;
            $image->move($destinationPath, $name);
            $imageName = $name;
            
         }



      $users = users::find(Auth::user()->id);  
      $users->document =$imageName;
      $users->stripe_status  ='1';                     
      $users->update(); 

      
      return redirect()->back()->with('success', 'Verification Documents uploaded successfully');
    
    }


    public function orderpay(Request $request)
    {
        $userdetail = Users::find($request->input('propertyownderid'));
        $propertyownerstripeconnectID = $userdetail->stripeconnectid;


        \Stripe\Stripe::setApiKey(config('constants.STRIPE_SECRET_KEY'));
        try {
          $transfer_group = "order_".$request->input('orderid')."_".Str::random(6);

            // Create a Charge:
            $charge = \Stripe\Charge::create(array(
              "amount" => $request->input('amount') * 100,
              "currency" => "usd",
              "source" => $request->input('stripeToken'),
              "transfer_group" => $transfer_group,
            ));


            $order = Orders::find($request->input('orderid'));  
           $order->status ='1';
           $order->guest_name =$request->input('guest_firstname');
           $order->guest_lastname =$request->input('guest_lastname');
           $order->guest_email =$request->input('guest_email');
           $order->guest_phone =$request->input('guest_phone');
           $order->guest_special_request =$request->input('guest_request');
           $order->update();

           $admin_share = Adminshare::where('id', '1')->first();
           $percentage = 100 - $admin_share['admin_per'];
           $totalWidth = $request->input('amount') * 100;
           $transferamount = round(($percentage / 100) * $totalWidth);

            // Create a Transfer to a connected account (later):
            $transfer = \Stripe\Transfer::create([
              "amount" => $transferamount,
              "currency" => "usd",
              "destination" => $propertyownerstripeconnectID,
              "transfer_group" => $transfer_group,
            ]);
            
            $order = Orders::find($request->input('orderid')); 
           $order->status ='1';
           $order->payment_method = 'stripe';
           $order->update();

           /*//get order details
             $order_data = Orders::where('id' ,'=', $request->input('orderid'))->first(); 

            //get owner email
            $property = Properties::where('id' ,'=', $order_data->property_id)->first(); 
            $owner = Users::where('id','=',$property->user_id)->first(); 
            $data = array(
                          'guest_firstname'   => $request->input('guest_firstname'),
                          'guest_lastname'    => $request->input('guest_lastname'),
                          'property'          => $order_data->propertyname,
                          'amount'            => $request->input('amount'),
                          'from_date'         => date('d-m-y' , $order_data->checkindate),
                          'to_date'           => date('d-m-y' , $order_data->checkoutoutdate),
                        );

            $toEmail = array(
                            'user_email' => $request->input('guest_email'),
                            'from_email' => config('constants.EMAIL'),
                            'owner_email' => $owner->email
                          );


            $this->send_booking_email($data , $toEmail);*/
            $this->send_booking_email($request->input('orderid'));

          
            return redirect('/payment/success');
        } 
        catch ( \Exception $e ) {
           Session::flash ('fail-message', "Error! Please Try again.");
        }
    }

    public function paypal_pay(Request $request)
    {

       
        $userdetail = Users::find($request->input('propertyownderid'));
        $propertyowneremail = $userdetail->email;

        $order = Orders::find($request->input('orderid'));  
        //$order->status ='1';
        $order->guest_name =$request->input('firstname');
        $order->guest_lastname =$request->input('lastname');
        $order->guest_email =$request->input('email');
        $order->guest_phone =$request->input('phone');
        $order->guest_special_request =$request->input('request');
        $order->update();

        $admin_share = Adminshare::where('id', '1')->first();
        $percentage = 100 - $admin_share['admin_per'];
        $totalWidth = $request->input('amount');
        $transferamount = round(($percentage / 100) * $totalWidth);

        //get order detail 
        $order_data = Orders::where( 'id' ,'=' ,$request->input('orderid'))->first();

        $provider = new AdaptivePayments; 
        $data = [
                    'receivers'  => [
                        [
                            'email' => 'developertester142@gmail.com',
                            'amount' => $request->input('amount'),
                            'primary' => true
                        ],
                        [
                            'email' => $userdetail['paypal_email'],//$userdetail->paypal_email,
                            'amount' => $transferamount,
                            'secondary' => true,
                        ]
                        
                    ],
                    //'itemName' => $data['item_name'],
                    'custom' => 'Book Room-'.$request->input('orderid'),
                    'payer' => 'EACHRECEIVER', // (Optional) Describes who pays PayPal fees. Allowed values are: 'SENDER', 'PRIMARYRECEIVER', 'EACHRECEIVER' (Default), 'SECONDARYONLY'
                    'return_url' => url('payment/success'), 
                    'cancel_url' => url('home'),
                ];

        $response = $provider->createPayRequest($data);
        
        if(isset($response['error']))
        {
            Session::put('error','Some Error Occured. Please try again later');
            return redirect('/completeOrder');
        }
        else
        {
            $redirect_url = $provider->getRedirectUrl('approved', $response['payKey']);

            return redirect($redirect_url);
        }
        die();
       
    }

    public function postNotify(Request $request)
    {

        $provider = new ExpressCheckout;

          $file = '/var/www/html/success.txt'; 
         file_put_contents($file , "--------------------------------------------------------------<br>" , FILE_APPEND);     

        $post = [
            'cmd' => '_notify-validate',
        ];
        $data = $request->all();
        foreach ($data as $key => $value) {
            $post[$key] = $value;
        }

        file_put_contents($file , "IPN Data" , FILE_APPEND);                   
        file_put_contents($file, print_r($post , true), FILE_APPEND);
  

        $response = (string) $provider->verifyIPN($post);
        file_put_contents($file , "validate ipn response = " , FILE_APPEND);                   
        file_put_contents($file, print_r($response , true), FILE_APPEND);

       if($response == 'VERIFIED')
       {
           $custom = explode("-",$post['memo']);
           if($custom[0] == 'Book Room')
           {
              $order_id = $custom[1];
              $order = Orders::find($order_id);  
              $order->status ='1';
              $order->payment_method = 'paypal';
              $order->update();
              $this->send_booking_email($order_id);
              
           }
           else if($custom[0] == 'Service payment')
           {
              
              $chat_id = $custom[1];
              $chat = Message_center::find($chat_id); 
              $chat->payment_status ='1';
              $chat->payment_method = 'paypal';
              $chat->update();
              $this->send_surgery_payment_email($chat_id);
           }
           else
           {
              file_put_contents($file , "Inside Else = " , FILE_APPEND);  
           }
       }
    }  

    public function payment_success(Request $request)
    {
        Session::forget('error');
        Session::forget('orderid');
        Session::forget('propertyownderid');
        Session::forget('ordertotalamount');
        $contactdetail = Contactdetail::find('1');
        return view('thankyou' , compact('contactdetail'));
    }

    public function send_booking_email($order_id)
    {
        //get order details
            $order_data = Orders::select('orders.*' , 'rooms.name' , 'properties.slug' , 'properties.rooms' , 'properties.address' , 'properties.zipcode' , 'properties.gallery_images')
                        ->join('properties', 'properties.id', '=', 'orders.property_id')
                        ->join('users', 'users.id', '=', 'orders.propertyownerid')
                        ->join('rooms', 'rooms.id', '=', 'orders.roomtype')
                        ->where('orders.id', '=', $order_id)
                        ->where('orders.status', '=', '1')->first();

            //get owner email
            $property = Properties::where('id' ,'=', $order_data->property_id)->first(); 
            $owner = Users::where('id','=',$property->user_id)->first(); 
            $data = array(
                          'order'   => $order_data,
                        );

            $toEmail = array(
                            'user_email' => $order_data->guest_email,//$order_data->guest_email,
                            'from_email' => config('constants.EMAIL'),
                            'owner_email' => $owner->email,//$owner->email    //email of the owner of the property 
                          );
            //email to property owner
           Mail::send('emails.booking_info_owner',$data, function ($message) use ($toEmail) {
            $message->from($toEmail['from_email'],'Surgical rejuvenate');
            $message->to($toEmail['owner_email']);
            $message->subject('Room Booked Successfully');
          });
           
           //email to the user
          Mail::send('emails.booking_blade_user',$data, function ($message) use ($toEmail) {
            $message->from($toEmail['from_email'],'Surgical rejuvenate');
            $message->to($toEmail['user_email']);
            $message->subject('Room Booked Successfully');
          });
    }    

    public function send_surgery_payment_email($chat_id)
    {
      //Mail Data
        $message = Message_center::where('id' , '=' ,$chat_id)->first();

        $surgeon_detail = Users::where('email' , '=' , $message->surgeon)->first();

        $patient_detail = Users::where('email' , '=' , $message->patient)->first();

        $service = Servicelist::where('id' , '=' , $message->service)->first();

        $data = array(
                        'patient_name' => $patient_detail->name,
                        'surgeon_name' => $surgeon_detail->name,
                        'service_name'      => $service->service_name,
                        'service_price'      => $service->service_price,

                      );

        $toEmail = array(
                          'patient_email' => $message->patient,
                          'surgeon_email' => $message->surgeon,
                          'from_email' => config('constants.EMAIL')
                        );
        // mail to surgeon regarding payment notification
        Mail::send('emails.patient_paid_doctor',$data, function ($message) use ($toEmail) {
                          $message->from($toEmail['from_email'],'Surgical rejuvenate');
                          $message->to($toEmail['surgeon_email']);//'developertester478@gmail.com'
                          $message->subject('Payment Received');
                        });
        //*************************************************

        //mail to patient regarding payment notification
        Mail::send('emails.patient_payment',$data, function ($message) use ($toEmail) {
                          $message->from($toEmail['from_email'],'Surgical rejuvenate');
                          $message->to($toEmail['patient_email']); //$toEmail['patient_email']
                          $message->subject('Payment Successfull');
                        });
        //***************************************************
    }

    
    public function mail_surgeon(Request $request)
    {
        echo json_encode($request->email);
        die();
    }

}
?>

