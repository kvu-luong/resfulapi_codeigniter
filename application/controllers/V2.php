<?php
define('PER_PAGE', 1000);
define('TOKEN_MSG', 'INVALID_TOKEN');
define('DATETIME_MSG', 'INVALID_DATETIME');
define('STATUS_MSG', 'INVALID_STATUS');
define('PHONE_MSG', 'INVALID_PHONE');
define('FAIL_MSG', 'FAIL');
define('SUCCESS_MSG', 'SUCCESS');
define('OVER_MSG', 'REACH_LIMIT_REQUEST');

define('SMS_API_UTF','http://124.158.14.49/CMC_RF/api/sms/sendUTF');
define('SMS_API_NO_UTF', 'http://124.158.14.49/CMC_RF/api/sms/Send');

class V2 extends CI_Controller {
  private $requestMethod;

  public function __construct() {
    parent::__construct();
    $this->load->model('v2_model');
    $this->load->helper('url');
    $this->requestMethod = !empty($_SERVER['REQUEST_METHOD'])? $_SERVER['REQUEST_METHOD'] : 'GET';
  }

  function send(){
    if($this->requestMethod == 'POST'){
      $data = json_decode(file_get_contents('php://input'), true);
      $token = isset($data['token']) ? $data['token'] : '';
      $brandname = isset($data['brandname']) ? $data['brandname'] : '';
      $phonenumber = isset($data['phonenumber']) ? $data['phonenumber'] : '';
      $message = isset($data['message']) ? $data['message'] : '';
      $ref_id = isset($data['ref_id']) ? $data['ref_id'] : '';

      $userPostData = array(
        'token' => $token,
        'brandname' => $brandname,
        'phonenumber' => $phonenumber,
        'message' => $message,
        'org_id' => null,
        'ref_id' => $ref_id,
      );

      $response = array(
        'status' => -2,
        'description' => $this->v2_model->response_description(-2),
        'data' => array(
          'token' => $userPostData['token'],
          'brandname' => $userPostData['brandname'],
          'phonenumber' => $userPostData['phonenumber'],
          'message' => $userPostData['message'],
          'ref_id' => $ref_id,
          'utf' => false,
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
      $isUtfMessage = $this->lib->isUTF($userPostData['message']);
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

      $lastId = $this->v2_model->saveResponse($dataInsert);
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
        $response['data']['utf'] = ($typeUTF == 1)?true:false;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      $exceedLengthMessage = ($numOfMsg == -1) ? TRUE : FALSE;
      $userInfor = $this->v2_model->getUserInformation($userPostData);
      if($userInfor['status'] == false || $exceedLengthMessage){
        $log['status'] = "BEFOR REQUEST - INVALID INFORMATION";
        $log['message'] = ($exceedLengthMessage) ? 'INVALID_LENGTH' : $userInfor['message'];
        $this->lib->writeLog('send_sms.log',$log);

        $response['status'] = $userInfor['code'];
        $response['description'] = $userInfor['message'];
        if($exceedLengthMessage){
          $response['status'] = -4;
          $response['description'] = $this->v2_model->response_description(-4);
        }
        $response['data']['utf'] = ($typeUTF == 1)?true:false;
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
        'pass' => $userInfor['data']['data']->password,
        // "SendTime" => "2021-05-18 13:45:00",
      ));
      $header = array(
          "Content-Type:application/json",
          "Authorization: Basic ".$token,
      );
      $result = $this->postSms($smsApi, $inputData, $header);

      $log = array(
        'time' => date('Y-m-d H:i:s'),
        'token' => '['.$userPostData['token'].']',
        'status' => "AFTER REQUEST",
        'url' => $smsApi, 
        'data' => "\n\t\t\t".$inputData,
        'rs' => "\n\t\t\t".json_encode($result),
      );
      $this->lib->writeLog('send_sms.log',$log);

      //end-------------------
      if(gettype($result) != 'object'){
        $response['data']['utf'] = ($typeUTF == 1)?true:false;
        $this->lib->generateOutput(200, json_encode($response));
        exit();
      }

      if($result->Code != 1 || $result->Data->Status == -5){
        $response['status'] = -1;
        $response['description'] = $this->v2_model->response_description(-1);
        $response['data']['utf'] = ($typeUTF == 1)?true:false;
        $this->lib->generateOutput(200, json_encode($response));
        exit();
      }
    
      //modify response
      $outputArray = get_object_vars($result);
      $detailData = get_object_vars($outputArray['Data']);
      $response['status'] = $detailData['Status'];
      $response['description'] = $this->v2_model->response_description($detailData['Status']);
      $response['data']['utf'] = ($typeUTF == 1)?true:false;
      $this->lib->generateOutput(200, json_encode($response));

      //update data
      $dataUpdate = array(
        'result'=> $detailData['Status'],
        'org_id' => $userInfor['data']['data']->org_id,
        'code' => $outputArray['Code'],
      );
      $isUpdateOk = $this->v2_model->updateResponse($dataUpdate, $lastId);
      //update data - end
    } else {
      $response = json_encode(array('status' => '405', 'description'=> 'WRONG_METHOD_REQUEST'));
      $this->lib->generateOutput(405, $response);
    }
  }

  function report() {
    if($this->requestMethod == 'GET'){
      $response = array(
        'result' => -1,
        'description' => FAIL_MSG,
      );
      $token = isset($_GET['token']) ? $_GET['token'] : NULL;
      if($token == NULL || $token == ''){
        $response['result'] = -1;
        $response['description'] = TOKEN_MSG;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }
      $isValidToken = $this->lib->isValidStr($token);
      if($isValidToken == false){
        $response['result'] = -1;
        $response['description'] = TOKEN_MSG;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : NULL;
      $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : NULL;
      $isNotValidInputDate = ($start_date == NULL || $end_date == NULL);
      if($isNotValidInputDate){
        $response['result'] = -1;
        $response['description'] = DATETIME_MSG;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      $isValidStartDate = $this->lib->isValidStr($start_date);
      $isValidEndDate = $this->lib->isValidStr($end_date);
      if($isValidStartDate == false || $isValidEndDate == false){
        $response['result'] = -1;
        $response['description'] = DATETIME_MSG;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }
      //need to check format to add input date;
      $start_date = $this->lib->coverStringToDate($start_date, '00:00:00');
      $end_date =  $this->lib->coverStringToDate($end_date, '23:59:59'); 

      if($start_date == false || $end_date == false) {
        $response['result'] = -1;
        $response['description'] = DATETIME_MSG;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      $isValidDateTime = $this->lib->isValidDateTime($start_date, $end_date);
      if($isValidDateTime == FALSE){
        $response['result'] = -1;
        $response['description'] = DATETIME_MSG;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      //check phone and status 
      $phone = isset($_GET['phone']) ? $_GET['phone'] : '';
      $status = isset($_GET['status']) ? $_GET['status'] : '';

      $isValidPhone = $this->lib->isValidNumber($phone);
      if($isValidPhone == FALSE){
        $response['result'] = -1;
        $response['description'] = PHONE_MSG;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }
      
      $isValidStatus = $this->lib->isValidNumber($status);
      if($isValidStatus == FALSE){
        $response['result'] = -1;
        $response['description'] = STATUS_MSG;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      // continue checking token
      $isRightToken = $this->v2_model->is_right_token($token);
      if($isRightToken['status'] == FALSE){
        $response['result'] = -1;
        $response['description'] = TOKEN_MSG;
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      $log = array(
        'time' => date('Y-m-d H:i:s'),
        'token' => '['.$token.']',
        'message' => "BEFORE REQUEST",
        'start_date' => '['.$start_date.']',
        'end_date' => '['.$end_date.']',
        'phone' => $phone,
        'status' => $status,
        'description' => 'good input request',
      );
      $this->lib->writeLog('report_sms.log',$log);
      //checking request and quota
      // $isCanRequest = $this->v2_model->is_can_request($token);
      // if($isCanRequest['status'] == FALSE){
      //   $response['result'] = -1;
      //   $response['description'] = OVER_MSG;
      //   $this->lib->generateOutput($isCanRequest['code'], json_encode($response));
      //   exit();
      // }

      $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
      if($page == 0 || $page < 0) $page = 1;
      $per_page = isset($_GET['per_page']) ? (intval($_GET['per_page']) > PER_PAGE) ? PER_PAGE : intval($_GET['per_page']) : PER_PAGE;
      if($per_page == 0 || $per_page < 0) $per_page = 1;

      $dataInput = array(
        'start_date' => $start_date,
        'end_date' => $end_date,
        'org_id' => $isRightToken['org_id'],
        'phone' => $phone,
        'status' => $status,
        'page' => $page,
        'per_page' => $per_page,
        'token' => $token,
      );

      $result = $this->v2_model->get_data_report($dataInput);
      $outputData = $this->outData($result);

       $log = array(
        'time' => date('Y-m-d H:i:s'),
        'token' => '['.$token.']',
        'message' => "ATER REQUEST",
        'start_date' => '['.$start_date.']',
        'end_date' => '['.$end_date.']',
        'phone' => $phone,
        'status' => $status,
      );
      $this->lib->writeLog('report_sms.log',$log);

      if($outputData == NULL){
        $response['result'] = 1;
        $response['description'] = SUCCESS_MSG;
        $response['data'] = array();
        $this->lib->generateOutput(200, json_encode($response));
        exit();
      }

      $response['result'] = 1;
      $response['description'] = SUCCESS_MSG;
      $response['data'] = $outputData;
       // $response['total_count'] = $result['_metadata']['total_count'];
      // $response['per_page'] = $result['_metadata']['per_page'];
      // $response['total_page'] = $result['_metadata']['total_page'];
      // $response['page'] = $result['_metadata']['page'];
      $response['_metadata'] = $result['_metadata'];
      $this->lib->generateOutput(200, json_encode($response));

     
    } else {
      $response = json_encode(array('status' => '405', 'description'=> 'WRONG_METHOD_REQUEST'));
      $this->lib->generateOutput(405, $response);
    }
  }


  private function outData($data){
    if($data['data'] == NULL) return NULL;
    $result = NULL;
    foreach($data['data'] AS $index => $value){
      $result[] = array(
        'status' => intval($value['result']),
        'phone' => $value['phone'],
        'message' => $value['sms'],
        'date_time' => $value['date_time'],
        'msg_length' => intval($value['msg_length']),
        'utf' => ($value['type_msg'] == 1)?true:false,
      );
    }
    return $result;
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
    if($this->lib->isValidStr($brandname) == false) return array('status' => false, 'message' => $this->v2_model->response_description(-9), 'code' => -9);
    $phonenumber = $arrData['phonenumber'];
    if($this->lib->isValidStr($phonenumber) == false || $this->isPhoneNumber($phonenumber) == false) return array('status' => false, 'message' => $this->v2_model->response_description(-3), 'code' => -3);
    $message = $arrData['message'];
    if($this->lib->isValidStr($message) == false) return array('status' => false, 'message' => $this->v2_model->response_description(-4), 'code' => -4);
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

}