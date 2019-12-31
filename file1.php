<?php

namespace App\Http\Controllers;
use Illuminate\Pagination\Paginator;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\admin\Properties;
use App\Models\admin\Amenitie;
use App\Models\admin\Contactdetail;
use App\Models\admin\Servicelist;
use App\Models\admin\Surgeonservices;
use App\Models\admin\Surgerytransactions;
use App\Models\admin\Users;
use App\Models\admin\Message_center;
use App\Models\admin\Patient_questionaire;
use App\Models\admin\Rooms;
use App\Models\admin\Extraoptions;
use Illuminate\Support\Facades\Hash;
use App\Models\Orders;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\FeedbackMail;
use Auth;
use DB;
use Stripe\Stripe;

use Session;

use Redirect;

use Srmklive\PayPal\Services\ExpressCheckout;
use Srmklive\PayPal\Services\AdaptivePayments;

//use Cartalyst\Stripe\Laravel\Facades\Stripe;

use Stripe\Error\Card;


class MessagecenterController extends Controller
{
    public function index()
    {
    }

    public function send_request(Request $request)
    {
    	$userdetail = Message_center::where('surgeon' , '=' , $request->input('email'))
    								->where('patient' , '=' , $request->input('user_email'))
                    ->where('chat_status' , '=' ,'1')
    								->get();

    	if($userdetail->count() > 0)
    	{
    		echo json_encode(0);
    		die();
    	}
    	else
    	{
    		$msg = array(
    						Array(
    						'message' => $request->input('user_message'),
    						'sender' => 'patient',
    						'date' => date('d-m-Y h:i:s')
    						)
    					);

    		$thread_id = Str::random(8);
			$message                  = new Message_center();
			$message->surgeon           = $request->input('email');
			$message->thread_id           = $thread_id;
			$message->patient           = $request->input('user_email');
			$message->subject           = $request->input('user_subject');
			$message->message             = serialize($msg);
      $message->service           = $request->input('service');
			$message->save();

      $surgeon_detail = Users::where('email' , '=' , $request->input('email'))->first();

      $patient_detail = Users::where('email' , '=' , $request->input('user_email'))->first();

      $service = Servicelist::where('id' , '=' , $request->input('service'))->first();

      $data = array(
                      'patient_name' => $patient_detail->name,
                      'surgeon_name' => $surgeon_detail->name,
                      'subject'      => $request->input('user_subject'),
                      'service'      => $service->service_name
                    );

      $toEmail = array(
                        'user_email' => $request->input('email'),
                        'from_email' => config('constants.EMAIL')
                      );

      Mail::send('emails.patient_requested_surgeon',$data, function ($message) use ($toEmail) {
        $message->from($toEmail['from_email'],'Surgical rejuvenate');
        $message->to($toEmail['user_email']); //
        $message->subject('New Patient Request');
      });

			echo json_encode(1);
			die();
    	}


    }

    public function get_surgeon_services(Request $request)
    {
      $services = DB::table('surgeonservices')
                  ->select('surgeonserviceslist.service_name' , 'surgeonservices.id' , 'surgeonservices.service_price')
                  ->join('surgeonserviceslist', 'surgeonservices.service_id', '=', 'surgeonserviceslist.id')
                  ->where('surgeonservices.surgeon_id', '=', $request->input('id'))
                  ->get();

      echo json_encode($services);
      die();
    }

    public function patients()
    {
    	if (Auth::check() && Auth::user()->type == 'doctor')
      	{ 
      		$pending_patients = users::select('surgeonserviceslist.service_name', 'message_center.*' , 'users.name' , 'users.email')
                            ->join('message_center' , 'users.email' , '=' , 'message_center.patient')
                            ->join('surgeonservices' , 'surgeonservices.id' , '=' , 'message_center.service')
                            ->join('surgeonserviceslist' , 'surgeonservices.service_id' , '=' , 'surgeonserviceslist.id')
      										  ->where('message_center.accept' ,'=' , '0')
      										  ->where('message_center.surgeon' , '=' , Auth::user()->email) 
      										  ->get();

      		$patients = users::join('message_center' , 'users.email' , '=' , 'message_center.patient')
									  ->where('message_center.accept' ,'=' , '1')
									  ->where('message_center.surgeon' , '=' , Auth::user()->email) 
                    ->where('message_center.chat_status' , '=' ,'1')
                    ->orderby('message_center.id' , 'desc')
									  ->get();

			

			$contactdetail = Contactdetail::find('1');
			$userdetail = Users::find(Auth::user()->id);

			return view('useraccount.patientlist' , compact('pending_patients' , 'patients' , 'contactdetail' , 'userdetail'));
      	}
      	else
		{
			return redirect('user-account');
		}
    }

    public function surgeons()
    {
    	if (Auth::check() && Auth::user()->type == 'patient')
      	{ 
      		$pending_requests = users::join('message_center' , 'users.email' , '=' , 'message_center.surgeon')
      										  ->where('message_center.accept' ,'=' , '0')
      										  ->where('message_center.patient' , '=' , Auth::user()->email) 
      										  ->get();

      		$surgeons = users::join('message_center' , 'users.email' , '=' , 'message_center.surgeon')
									  ->where('message_center.accept' ,'=' , '1')
									  ->where('message_center.patient' , '=' , Auth::user()->email) 
                    ->where('message_center.chat_status' , '=' ,'1')
                    ->orderby('message_center.id' , 'desc')
									  ->get();



			$contactdetail = Contactdetail::find('1');
			$userdetail = Users::find(Auth::user()->id);

			return view('useraccount.surgeonslist' , compact('pending_requests' , 'surgeons' , 'contactdetail' , 'userdetail'));
      	}
      	else
		{
			return redirect('user-account');
		}
    }

    public function update($id)
    {
    	$message                  = Message_center::find($id);
		$message->accept           = '1';
		$message->update();

      $message = Message_center::where('id' , '=' , $id)->first();

      $surgeon_detail = Users::where('email' , '=' , $message->surgeon)->first();

      $patient_detail = Users::where('email' , '=' , $message->patient)->first();


      $data = array(
                      'patient_name' => $patient_detail->name,
                      'surgeon_name' => $surgeon_detail->name,
                     // 'service'      => $service->service_name
                    );

      $toEmail = array(
                        'user_email' => $message->patient,
                        'from_email' => config('constants.EMAIL')
                      );

      Mail::send('emails.patient_requested_accepted',$data, function ($message) use ($toEmail) {
        $message->from($toEmail['from_email'],'Surgical rejuvenate');
        $message->to($toEmail['user_email']); //$toEmail['user_email']
        $message->subject('Request Accepted');
      });



		return redirect('/patients')->with('success', 'Patient Request Successfully Accepted');
    }

    public function chat($id)
    {
    	$chat = Message_center::where('thread_id' , '=' , $id)->first();
      if(Auth::user()->email == $chat['surgeon'])
      {
        $receiver_data = Users::where('email' , '=' , $chat['patient'])->first();
      }
      else
      {
        $receiver_data = Users::where('email' , '=' , $chat['surgeon'])->first(); 
      }

    	$contactdetail = Contactdetail::find('1');
		$userdetail = Users::find(Auth::user()->id);


    //get surgeon id
    $user = Users::where('email' , '=' , $chat['surgeon'])->first();
    $surgeon_id = $user['id'];

    $service = DB::table('surgeonservices')
                  ->select('surgeonserviceslist.service_name' , 'surgeonservices.id' , 'surgeonservices.service_price')
                  ->join('surgeonserviceslist', 'surgeonservices.service_id', '=', 'surgeonserviceslist.id')
                  ->where('surgeonservices.surgeon_id', '=', $surgeon_id)
                  ->where('surgeonservices.id', '=', $chat['service'])
                  ->first();

    $surgeon_detail = Users::where('email' , '=' , $chat['surgeon'])->first();

   


		return view('useraccount.chat' , compact('chat' , 'contactdetail' , 'userdetail' , 'receiver_data' , 'service' , 'surgeon_detail'));

    }

    public function update_message(Request $request)
    {
    	$chat = Message_center::where('thread_id' , '=' , $request->input('thread'))->first();
    	$msg = unserialize($chat['message']);

    	if(Auth::user()->email == $chat['surgeon'])
    	{
    		$sender = 'surgeon';
    	}
    	else
    	{
    		$sender = 'patient';
    	}
    	$new_msg = array(
    						'message' => $request->input('msg'),
    						'sender' => $sender,
    						'date' => date('d-m-Y h:i:s')
    					);



    	array_push($msg, $new_msg);

    	$msg = serialize($msg);
    
    	Message_center::where('thread_id', $request->input('thread'))
          ->update(['message' => $msg]);

         echo json_encode(1);
         die();
    }

     public function upload_document(Request $request)
    {
      $chat = Message_center::where('thread_id' , '=' , $request->input('thread'))->first();
      $msg = unserialize($chat['message']);
      /*echo json_encode($msg);
      die();*/
      if(Auth::user()->email == $chat['surgeon'])
      {
        $sender = 'surgeon';
      }
      else
      {
        $sender = 'patient';
      }
      $new_msg = array(
                'message' => '/public/uploads/chat_docs/'.$request->input('msg'),
                'ext' => $request->input('extention'),
                'sender' => $sender,
                'date' => date('d-m-Y h:i:s')
              );



      array_push($msg, $new_msg);

      $msg = serialize($msg);
    
      Message_center::where('thread_id', $request->input('thread'))
          ->update(['message' => $msg]);

         echo json_encode(1);
         die();
    }

    public function auto_load_msgs(Request $request)
    {
    	$chat = Message_center::where('thread_id' , '=' , $request->input('thread'))->first();
    	$right = $request->input('right');

      if(Auth::user()->email == $chat['surgeon'])
      {
        $receiver_data = Users::where('email' , '=' , $chat['patient'])->first();
      }
      else
      {
        $receiver_data = Users::where('email' , '=' , $chat['surgeon'])->first(); 
      }

    	$view = view("useraccount.autoloadmsgs",compact('chat' , 'right' , 'receiver_data'))->render();

      $data = Message_center::where('thread_id', $request->input('thread'))
          ->update(['thread_id' => $request->input('thread')]);


    	echo json_encode($view);
    	die();
    }

    public function save_report(Request $request)
    {
      Message_center::where('thread_id', $request->input('thread_id'))
          ->update(['patient_report' => $request->input('report')]);

      return redirect()->back()->with('success','Report Saved Successfully');
    }

    public function service_paypal(Request $request)
    {
        $userdetail = Users::where('email' , '=' , $request->input('surgeon_email'))->first();
        $adminshare = $userdetail->surgeon_admin_share;
        $surgeon_share = 100 - $adminshare;
        $surgeon_price = ($request->input('surgery_price') * 90)/100;
        $provider = new AdaptivePayments; 
        $data = [
                    'receivers'  => [
                        [
                            'email' => 'developertester142@gmail.com',
                            'amount' => $request->input('surgery_price'),
                            'primary' => true
                        ],
                        [
                            'email' => $userdetail['paypal_email'],//$userdetail->paypal_email,
                            'amount' => $surgeon_price,
                            'secondary' => true,
                        ]
                        
                    ],
                    //'itemName' => $data['item_name'],
                    'custom' => 'Service payment-'.$request->input('chat_id'),
                    'payer' => 'EACHRECEIVER', // (Optional) Describes who pays PayPal fees. Allowed values are: 'SENDER', 'PRIMARYRECEIVER', 'EACHRECEIVER' (Default), 'SECONDARYONLY'
                    'return_url' => url('chat/'.$request->input('thread_id')), 
                    'cancel_url' => url('chat/'.$request->input('thread_id')),
                ];

        $response = $provider->createPayRequest($data);
        
        if(isset($response['error']))
        {
            return redirect('chat/'.$request->input('thread_id'))->with('error','Some Error Occured');
        }
        else
        {
            $redirect_url = $provider->getRedirectUrl('approved', $response['payKey']);

            return redirect($redirect_url);
        }
        die();
    }

    public function surgeon_payment(Request $request)
    {
      $userdetail = Users::where('email' , '=' , $request->input('surgeon_email'))->first();
      $connectid = $userdetail->stripeconnectid;
      $adminshare = $userdetail->surgeon_admin_share;
      $surgeon_share = 100 - $adminshare;
      \Stripe\Stripe::setApiKey(config('constants.STRIPE_SECRET_KEY'));
      try 
      {
        $transfer_group = "surgeon_payment_".Str::random(6);

        // Create a Charge:
        $charge = \Stripe\Charge::create(array(
                                                "amount" => $request->input('surgery_price') * 100,
                                                "currency" => "usd",
                                                "source" => $request->input('token'),
                                                "transfer_group" => $transfer_group,
                                              ));

        $totalWidth = $request->input('surgery_price') * 100;
        $transferamount = round(($surgeon_share / 100) * $totalWidth);

        // Create a Transfer to a connected account (later):
        $transfer = \Stripe\Transfer::create([
                                                "amount" => $transferamount,
                                                "currency" => "usd",
                                                "destination" => $connectid,
                                                "transfer_group" => $transfer_group,
                                              ]);

        $chat = Message_center::find($request->input('chat_id')); 
        $chat->payment_method = 'stripe';
        $chat->payment_status ='1';
        $chat->update();

        //Mail Data
        $message = Message_center::where('id' , '=' , $request->input('chat_id'))->first();

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


        $transactions                  = new Surgerytransactions();
        $transactions->total_amount    = $request->input('surgery_price');
        $transactions->thread_id       = $request->input('thread_id');
        $transactions->save();

        $data = array(
                        'status' => 1,
                        'msg' => 'Payment Successfully Done'
                      );
        echo json_encode($data);
        die();
      } 
      catch ( \Exception $e ) 
      {
        $data = array(
                        'status' => 0,
                        'msg' => 'Some Error Occured. Try Again Later'
                      );
      echo json_encode($data);
      die();
      }
    }

    public function end_chat($thread_id)
    {
        Message_center::where('thread_id', $thread_id)
          ->update(['chat_status' => '0']);

        return redirect('patients');
    }

    public function view_questionaire($thread_id)
    {
      if (Auth::check() && Auth::user()->type == 'doctor')
        { 
            $health_questionaire = Users::select('users.id')
            ->join('message_center' , 'users.email' , '=' , 'message_center.patient')
            ->where('message_center.thread_id' ,'=' , $thread_id)
            ->where('message_center.surgeon' , '=' , Auth::user()->email) 
            ->first();
           
            $patient_questionaire = Patient_questionaire::where('userid' , '=' , $health_questionaire->id)->first();
            $contactdetail = Contactdetail::find('1');
            $userdetail = Users::find(Auth::user()->id);

            return view('useraccount.patient_questionaire' , compact('patient_questionaire' ,'contactdetail' , 'userdetail'));
        }
        else
        {
          return redirect('user-account');
        }
    }
}
