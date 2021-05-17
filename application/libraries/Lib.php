<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Lib {

  function curlPost($url, $data = NULL, $headers = NULL){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    if ( $data != NULL ) {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    
    if ($headers != NULL) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
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
      $this->writeLog('send_sms.log',$log);
      curl_close($ch);
      return -1;
    }else{
      $log['status'] = "CURL OK";
      $log['rs'] = "\n\t\t\t".$output;
      $this->writeLog('send_sms.log',$log);
      curl_close($ch);
      return $output;//object
    }
  }

  function coverStringToDate($string, $tail = NULL) {
    $isTrueDateType = $this->validateDate($string);
    if($isTrueDateType == 0) return false;
    if($isTrueDateType == 2){
      $string .= ' '.$tail;
    }

    $time = strtotime($string);
    $newformat = date('Y-m-d H:i:s',$time);
    return $newformat;
  }

  function validateDate($date) {
    //check exactly format like this
    $fullFormat = DateTime::createFromFormat('Y-m-d H:i:s', $date);
    if($fullFormat &&  $fullFormat->format('Y-m-d H:i:s') === $date) return 1;
    $partFormat = DateTime::createFromFormat('Y-m-d', $date);
    if($partFormat &&  $partFormat->format('Y-m-d') === $date) return 2;
    return 0;
  }

  function isValidDateTime($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);

    $interval = $start->diff($end);
    $space = intval($interval->format('%a'));

    $isEndLessThanStart =  $end < $start ;
    if($isEndLessThanStart) return FALSE;

    $isLargerThan31Day =  $space > 31;
    if($isLargerThan31Day) return FALSE;

    return TRUE;
  }

  function isValidNumber($msg) {
    if (preg_match('/[\'"`<>|a-zA-Z!@#%~^*()_+=.\/\{}.]/', $msg))
    {
      return false;
    }
    return true;
  }

  function isNoSpecialChar($msg) {
    if (preg_match('/[\'"`<>|]/', $msg))
    {
      return false;
    }
    return true;
  }

  function isValidStr($str) {
    if($this->isNoSpecialChar($str) && $str != '' && $str != NULL){
      return true;
    }
    return false;
  }

  function writeLog($filename, $data) {
    $folder = getcwd()."/application/logs/".date("Y-m")."/".date("d")."/sms"; 
    if(!file_exists($folder)){
      mkdir($folder,0777,true);
      chmod($folder,0777);
    }
    $file = fopen($folder."/".$filename, 'a');
    foreach ($data as $key => $value) {
      fwrite($file,$value."\t");
    }
    fwrite($file, "\n");
    fclose($file);
  }

  function generateOutput($code, $data) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    switch($code) {
      case 200:
        echo $data;
        break;
      case 404:
        header("HTTP/1.0 404 Not Found");
        echo $data;
        break;
      case 405:
        header("HTTP/1.0 405 Method Not Allowed");
        echo $data;
        break;
      case 406:
        header("HTTP/1.0 406 Not Acceptable");
        echo $data; 
        break;
      default:
    }
  }
}
?>