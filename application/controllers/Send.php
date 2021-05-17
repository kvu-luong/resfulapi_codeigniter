<?php
defined('BASEPATH') OR exist('No direct script access allowed');

define('SMS_API_UTF','http://124.158.14.49/CMC_RF/api/sms/sendUTF');
define('SMS_API_NO_UTF', 'http://124.158.14.49/CMC_RF/api/sms/Send');

class Send extends CI_Controller {
  private $requestMethod;

  public function __construct() {
    parent::__construct();
    $this->load->model('sms_model');
    $this->load->helper('url');
    $this->requestMethod = !empty($_SERVER['REQUEST_METHOD'])? $_SERVER['REQUEST_METHOD'] : 'GET';
  }

  function index() {
    if($this->requestMethod == 'POST'){
      $data = json_decode(file_get_contents('php://input'), true);
      $token = isset($data['token']) ? $data['token'] : '';
      $brandname = isset($data['brandname']) ? $data['brandname'] : '';
      $phonenumber = isset($data['phonenumber']) ? $data['phonenumber'] : '';
      $message = isset($data['message']) ? $data['message'] : '';

      $userPostData = array(
        'token' => $token,
        'brandname' => $brandname,
        'phonenumber' => $phonenumber,
        'message' => $message,
        'org_id' => null,
      );

      $response = array(
        'status' => -2,
        'description' => $this->sms_model->response_description(-2),
        'data' => array(
          'token' => $userPostData['token'],
          'brandname' => $userPostData['brandname'],
          'phonenumber' => $userPostData['phonenumber'],
          'message' => $userPostData['message'],
        ),
      );

      $log = array(
        'time' => date('Y-m-d H:i:s'), 
        'token' => '['.$userPostData['token'].']',
        'status' => "BEFORE REQUEST",
        'url' => '', 
        'data' => "\n\t\t\t".json_encode($userPostData), 
      );
      $this->lib->writeLog('send_sms.log',$log);
      // check input 
      $isAllright = $this->isAllInputRight($userPostData);

      //update phonenumber
      $userPostData['phonenumber'] = $this->formPhone($phonenumber);
      
      //insert data
      $isUtfMessage = $this->isUTF($userPostData['message']);
      $typeUTF = ($isUtfMessage['status'])?1:0;
      $lengthOfMsg = $this->getLengthOfMessage($userPostData['message']);
      $numOfMsg = $this->getNumberOfMessage($lengthOfMsg, $isUtfMessage);
      
      $dataInsert = array(
        'phone' => $userPostData['phonenumber'],
        'sms' => $userPostData['message'],
        'date_time' => date('Y-m-d H:i:s'),
        'result'=> $isAllright['code'],
        'type_msg'=> $typeUTF,
        'msg_length' => $lengthOfMsg,
        'num_msg' => $numOfMsg,
        'is_done' => 1,
        'is_get' => 1,
      );

      $lastId = $this->sms_model->saveResponse($dataInsert);
      //insert data - end

      if($isAllright['status'] == false){
        $log = array(
          'time' => date('Y-m-d H:i:s'), 
          'token' => '['.$userPostData['token'].']',
          'status' => "BEFORE REQUEST - INVALID INPUT ",
          'url' => '', 
          'data' => "\n\t\t\t".json_encode($userPostData),
          'message' => $isAllright['message'],
        );
        $this->lib->writeLog('send_sms.log',$log);
        $response['status'] = $isAllright['code'];
        $response['description'] = $isAllright['message'];
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      $exceedLengthMessage = ($numOfMsg == -1) ? TRUE : FALSE;
      $userInfor = $this->getUserInformation($userPostData);
      if($userInfor['status'] == false || $exceedLengthMessage){
        $log['status'] = "BEFOR REQUEST - INVALID INFORMATION";
        $log['message'] = ($exceedLengthMessage) ? 'INVALID_LENGTH' : $userInfor['message'];
        $this->lib->writeLog('send_sms.log',$log);

        $response['status'] = $userInfor['code'];
        $response['description'] = $userInfor['message'];
        if($exceedLengthMessage){
          $response['status'] = -4;
          $response['description'] = $this->sms_model->response_description(-4);
        }
        $this->lib->generateOutput(200, json_encode($response));
        exit();
      }

      //start send sms;
      $smsApi = $isUtfMessage['api'];
      $inputData = json_encode(array(
        'brandname' => $userInfor['data']['data']->brand_name,
        'phonenumber' => $userPostData['phonenumber'],
        'message' => $userPostData['message'],
        'user' => $userInfor['data']['data']->username,
        'pass' => $userInfor['data']['data']->password
      ));
      $header = array(
          "Content-Type:application/json",
          "Authorization: Basic ".$token,
      );
      $result = $this->postSms($smsApi, $inputData, $header);
      //end-------------------
      if(gettype($result) != 'object'){
        $this->lib->generateOutput(200, json_encode($response));
        exit();
      }
    
      //modify response
      $outputArray = get_object_vars($result);
      $detailData = get_object_vars($outputArray['Data']);
      $response['status'] = $detailData['Status'];
      $response['description'] = $this->sms_model->response_description($detailData['Status']);
      $this->lib->generateOutput(200, json_encode($response));

      //update data
      $dataUpdate = array(
        'result'=> $detailData['Status'],
        'org_id' => $userInfor['data']['data']->org_id,
        'code' => $outputArray['Code'],
      );
      $isUpdateOk = $this->sms_model->updateResponse($dataUpdate, $lastId);
      //update data - end

       $log = array(
        'time' => date('Y-m-d H:i:s'),
        'token' => '['.$userPostData['token'].']',
        'status' => "AFTER REQUEST",
        'url' => $smsApi, 
        'data' => "\n\t\t\t".$inputData,
        'rs' => "\n\t\t\t".json_encode($result),
        'insert' => ($isUpdateOk == true)?'OK':'FAILED',
      );
      $this->lib->writeLog('send_sms.log',$log);
    } else {
      $response = json_encode(array('status' => '405', 'description'=> 'WRONG_METHOD_REQUEST'));
      $this->lib->generateOutput(405, $response);
    }
  }

  function getUserInformation($data){
    $response = array(
      'status' => false,
      'message' => '',
      'data' => null, 
      'code' => 1,
    );
    $isRightUser = $this->sms_model->is_right_token($data);
    if($isRightUser['total'] == 0){
      $response['message'] = $this->sms_model->response_description(-2);
      $response['code'] = -2;
      return $response;
    }

    $data['org_id'] = $isRightUser['data']->org_id;
    $isRightBrandName = $this->sms_model->is_right_brandname($data);

    if($isRightBrandName['total'] == 0){
      $response['message'] = $this->sms_model->response_description(-9);
      $response['code'] = -9;
      return $response;
    }

    //success
    $response = array(
      'status' => true,
      'message' => 'OK',
      'data' => $isRightBrandName,
      'code' => 1,
    );
    return $response;
  }

  function isUTF($msg){
    if(mb_detect_encoding($msg)== 'UTF-8'){
      return array(
        'api' => SMS_API_UTF,
        'status' => true,
      );
    }
    return array(
      'api' => SMS_API_NO_UTF,
      'status' => false,
    );
  }

  private function getLengthOfMessage($msg){
    $originLength = mb_strlen($msg, 'UTF-8');
    $msgClean = $this->removeSpecialChar($msg);
    $afterClean = mb_strlen($msgClean, 'UTF-8');

    //rule
    $lenghtOfSpecialChar = ($originLength - $afterClean) * 2;
    return ($afterClean + $lenghtOfSpecialChar);
  }

  private function removeSpecialChar($str){
      $res = preg_replace('/[€\[\]{}\|^\t\n~]/s','',$str);
      return $res;
  }

  private function formPhone($phone){
    $first_number = substr($phone, 0, 1);
    if($first_number == '0'){
      return substr_replace($phone, 84, 0, 1);
    }
    return $phone;
  }

 

  private function isPhoneNumber($phone){
    $isNumber = is_numeric($phone);
    if(strlen($phone) < 10 || strlen($phone) > 15 || $isNumber == false){
      return false;
    }
    return true;
  }

 
  
  private function isAllInputRight($arrData){
    $token = $arrData['token'];
    if($this->lib->isValidStr($token) == false) return array('status' => false, 'message' => 'INVALID_TOKEN', 'code' => -2);
    $brandname = $arrData['brandname'];
    if($this->lib->isValidStr($brandname) == false) return array('status' => false, 'message' => $this->sms_model->response_description(-9), 'code' => -9);
    $phonenumber = $arrData['phonenumber'];
    if($this->lib->isValidStr($phonenumber) == false || $this->isPhoneNumber($phonenumber) == false) return array('status' => false, 'message' => $this->sms_model->response_description(-3), 'code' => -3);
    $message = $arrData['message'];
    if($this->lib->isValidStr($message) == false) return array('status' => false, 'message' => $this->sms_model->response_description(-4), 'code' => -4);
    return array('status' => true, 'message' => 'OK', 'code' => 1);
  }

  function getNumberOfMessage($msgLength, $typeMsg){
    
    $conditionUTFOne = ($msgLength <= 70)? true: false;
    $conditionUTFSecond = ($msgLength > 70 && $msgLength <=134)? true: false;
    $conditionUTFThird = ($msgLength > 134 && $msgLength <= 201)? true: false;
    $conditionUTFFour = ($msgLength > 201 && $msgLength <= 268)? true: false;
    $conditionUTFFive = ($msgLength > 268 && $msgLength >= 355)? true: false;

    $conditionNoUTFOne = ($msgLength <= 160)? true: false;
    $conditionNoUTFSecond = ($msgLength > 160 && $msgLength <= 306)? true: false;
    $conditionNoUTFThird = ($msgLength > 306 && $msgLength <= 458)? true: false;
    $conditionNoUTFFour = ($msgLength > 458 && $msgLength <= 612)? true: false;
    
    $isUTF = $typeMsg['status'];
    if($isUTF){
      if($conditionUTFOne) return 1;
      if($conditionUTFSecond) return 2;
      if($conditionUTFThird) return 3;
      if($conditionUTFFour) return 4;
      if($conditionUTFFive) return 5;
      return -1;
    }else{
      if($conditionNoUTFOne) return 1;
      if($conditionNoUTFSecond) return 2;
      if($conditionNoUTFThird) return 3;
      if($conditionNoUTFFour) return 4;
      return -1;
    }
  }

  private function postSms($url, $data, $headers){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $output = curl_exec($ch);

    $log = array(
      'time' => date('Y-m-d H:i:s'), 
      'url' => $url, 
      'data' => "\n\t\t\t".$data, 
    );

    if (!$output) {
      $err = curl_error($ch);
      $log['status'] = "CURL ERROR";
      $log['rs'] = "\n\t\t\t".$err;
      $this->lib->writeLog('send_sms.log',$log);
      curl_close($ch);
      return -1;
    }else{
      $log['status'] = "CURL OK";
      $log['rs'] = "\n\t\t\t".$output;
      $this->lib->writeLog('send_sms.log',$log);
      curl_close($ch);
      return json_decode($output);//object
    }
  }

  // function insert() {
  //   $this->form_validation->set_rules("first_name", "First Name", "required");
  //   $this->form_validation->set_rules("last_name", "Last Name", "required");
  //   $response = array();
  //   if($this->form_validation->run()){
  //     $data = array(
  //       'first_name' => trim($this->input->post('first_name')),
  //       'last_name' => trim($this->input->post('last_name')),
  //     );
  //     $this->api_model->insert_api($data);
  //     $response = array(
  //       'success' => true,
  //     );
  //   } else {
  //     $response = array(
  //       'error' => true,
  //       'first_name_error' => form_error('first_name'),
  //       'last_name_error' => form_error('last_name'),
  //     );
  //   }
  //   echo json_encode($response);
  // }

  // function fetch_single() {
  //   $id = $this->input->post('id');
  //   if ( isset($id) ) {
  //     $data = $this->api_model->fetch_single_user($id);
  //     foreach ($data AS $row) {
  //       $output['first_name'] = $row['first_name'];
  //       $output['last_name'] = $row['last_name'];
  //     }
  //     echo json_encode($output);
  //   }
  // }

  // function update(){
  //   $this->form_validation->set_rules("first_name", "First Name", "required");
  //   $this->form_validation->set_rules("last_name", "Last Name", "required");
  //   $response = array();
  //   if($this->form_validation->run()){
  //     $data = array(
  //       'first_name' => trim($this->input->post('first_name')),
  //       'last_name' => trim($this->input->post('last_name')),
  //     );
  //     $this->api_model->update_api($this->input->post('id'), $data);
  //     $response = array(
  //       'success' => true,
  //     );
  //   } else {
  //     $response = array(
  //       'error' => true,
  //       'first_name_error' => form_error('first_name'),
  //       'last_name_error' => form_error('last_name'),
  //     );
  //   }
  //   echo json_encode($response);
  // }

  // function delete() {
  //   $id = $this->input->post('id');
  //   if ( isset($id) ) {
  //     if($this->api_model->delete_single_user($id)){
  //       $response = array(
  //         'success' => true,
  //       );
  //     } else {
  //       $response = array(
  //         'success' => true,
  //       );
  //     }
  //     echo json_encode($response);
  //   }
  // }

}