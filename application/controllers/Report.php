<?php
class Report extends CI_Controller {
  private $requestMethod;

  public function __construct() {
    parent::__construct();
    $this->load->model('report_model');
    $this->load->helper('url');
    $this->requestMethod = !empty($_SERVER['REQUEST_METHOD'])? $_SERVER['REQUEST_METHOD'] : 'GET';
  }

  function index() {
    if($this->requestMethod == 'GET'){
      $response = array(
        'status' => -2,
        'description' => $this->report_model->response_description(-2),
      );
      $token = isset($_GET['token']) ? $_GET['token'] : NULL;
      if($token == NULL || $token == ''){
        $response['status'] = -13;
        $response['description'] = $this->report_model->response_description(-13);
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }
      $isValidToken = $this->lib->isValidStr($token);
      if($isValidToken == false){
        $response['status'] = -13;
        $response['description'] = $this->report_model->response_description(-13);
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : NULL;
      $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : NULL;
      $isNotValidInputDate = ($start_date == NULL || $end_date == NULL);
      if($isNotValidInputDate){
        $response['status'] = -12;
        $response['description'] = $this->report_model->response_description(-12);
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      $isValidStartDate = $this->lib->isValidStr($start_date);
      $isValidEndDate = $this->lib->isValidStr($end_date);
      if($isValidStartDate == false || $isValidEndDate == false){
        $response['status'] = -12;
        $response['description'] = $this->report_model->response_description(-12);
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }
      //need to check format to add input date;
      $start_date = $this->lib->coverStringToDate($start_date, '00:00:00');
      $end_date =  $this->lib->coverStringToDate($end_date, '23:59:59'); 

      if($start_date == false || $end_date == false) {
        $response['status'] = -12;
        $response['description'] = $this->report_model->response_description(-12);
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      $isValidDateTime = $this->lib->isValidDateTime($start_date, $end_date);
      if($isValidDateTime == FALSE){
        $response['status'] = -12;
        $response['description'] = $this->report_model->response_description(-12);
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      //check phone and status 
      $phone = isset($_GET['phone']) ? $_GET['phone'] : '';
      $status = isset($_GET['status']) ? $_GET['status'] : '';

      $isValidPhone = $this->lib->isValidNumber($phone);
      if($isValidPhone == FALSE){
        $response['status'] = -3;
        $response['description'] = $this->report_model->response_description(-3);
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }
      
      $isValidStatus = $this->lib->isValidNumber($status);
      if($isValidStatus == FALSE){
        $response['status'] = -14;
        $response['description'] = $this->report_model->response_description(-14);
        $this->lib->generateOutput(404, json_encode($response));
        exit();
      }

      // continue checking token
      $isRightToken = $this->report_model->is_right_token($token);
      if($isRightToken['status'] == FALSE){
        $response['status'] = -13;
        $response['description'] = $this->report_model->response_description(-13);
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
        'description' => $this->report_model->response_description(-2),
      );
      $this->lib->writeLog('report_sms.log',$log);

      $dataInput = array(
        'start_date' => $start_date,
        'end_date' => $end_date,
        'org_id' => $isRightToken['org_id'],
        'phone' => $phone,
        'status' => $status,
      );

      $result = $this->report_model->get_data_report($dataInput);
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
        $response['status'] = 2;
        $response['description'] = $this->report_model->response_description(2);
        $this->lib->generateOutput(200, json_encode($response));
        exit();
      }

      $response['status'] = 1;
      $response['description'] = $this->report_model->response_description(1);
      $response['data'] = $outputData;
      $this->lib->generateOutput(200, json_encode($response));

      

     
    } else {
      $response = json_encode(array('status' => '405', 'description'=> 'WRONG_METHOD_REQUEST'));
      $this->lib->generateOutput(405, $response);
    }
  }

  private function outData($data){
    if($data == NULL) return NULL;
    $result = NULL;
    foreach($data AS $index => $value){
      $result[] = array(
        'status' => intval($value['result']),
        'phone' => $value['phone'],
        'message' => $value['sms'],
        'date_time' => $value['date_time'],
        'msg_length' => intval($value['msg_length']),
      );
    }
    return $result;
  }

}