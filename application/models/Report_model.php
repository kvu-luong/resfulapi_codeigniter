<?php
class Report_model extends CI_model {
  private $responseDescription;

  function __construct() {
    $this->load->database();
    $this->responseDescription = $this->get_response_description();
  }
  
  // function fetch_all() {
  //   $this->db->order_by('id', 'DESC');
  //   return $this->db->get('tbl_sample') ;
  // }

  // function insert_api( $data ){
  //   $this->db->insert('tbl_sample', $data);
  //   if ($this->db->affected_rows() > 0) {
  //     return true;
  //   }
  //   return false;
  // }

  // function fetch_single_user( $user_id ) {
  //   $this->db->where("id", $user_id);
  //   $query = $this->db->get('tbl_sample');
  //   return $query->result_array();
  // }

  // function update_api( $user_id, $data) {
  //   $this->db->where("id", $user_id);
  //   $this->db->update("tbl_sample", $data);
  // }

  // function delete_single_user( $user_id ) {
  //   $this->db->where("id", $user_id);
  //   $this->db->delete("tbl_sample");
  //   if ($this->db->affected_rows() > 0) {
  //     return true;
  //   }
  //   return false;
  // }

   private function is_select_normal($start_date, $end_date){
    $finalStr = '';
    $dayOfSixMonth = date("Y:m:d H:i:s", strtotime("-6 months"));
    $dayOfSixMonth = new DateTime($dayOfSixMonth);
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);

    if($start > $dayOfSixMonth) return array('status' => true);
    if($start <= $dayOfSixMonth){
      if($end <= $dayOfSixMonth){
        return array('status' => false, 'table' => 1);
      }
      return array('status' => false, 'table' => 2);
    }
  }

  function get_data_report($data){
    $start_date = $data['start_date'];
    $end_date = $data['end_date'];
    $org_id = $data['org_id'];
    $phone = $data['phone'];
    $status = $data['status'];

    $where = " `is_get`=1 AND `is_done`=1 AND `org_id`='".$org_id."'";
    if ($phone != '') {
      $where .= ' AND `phone` IN ('.$phone.')';
    }
    if ($status != '') {
      $where .= ' AND `result` IN ('.$status.')';
    }

    $isSelectNormal = $this->is_select_normal($start_date, $end_date);

    if($isSelectNormal['status']){
      $where .= ' AND `date_time`>="'.$start_date.'" AND `date_time`<="'.$end_date.'"';
      $this->db->where($where);
      $res = $this->db->get('sms_send_receive');
      $rows = NULL;
      foreach($res->result_array() AS $result){
        $rows[] = $result;
      }
      return $rows;
    }

    if($isSelectNormal['table'] == 1){
      $where .= ' AND `date_time`>="'.$start_date.'" AND `date_time`<="'.$end_date.'"';
      $this->db->where($where);
      $res = $this->db->get('sms_send_receive_logical');
      $rows = NULL;
      foreach($res->result_array() AS $result){
        $rows[] = $result;
      }
      return $rows;
    }else{
      $dayOfSixMonth = date("Y:m:d H:i:s", strtotime("-6 months"));
      $whereTable1 = $where.' AND `date_time`>="'.$start_date.'" AND `date_time`<"'.$dayOfSixMonth.'"';
      $this->db->where($whereTable1);
      $tableOne = $this->db->get('sms_send_receive_logical');

      $whereTable2 = $where.' AND `date_time`>="'.$dayOfSixMonth.'" AND `date_time`<="'.$end_date.'"';
      $this->db->where($whereTable2);
      $tableTwo = $this->db->get('sms_send_receive');

      $rows = NULL;
      foreach($tableOne->result_array() AS $resultOne){
        $rows[] = $resultOne;
      };

      foreach($tableTwo->result_array() AS $resultTwo){
        $rows[] = $resultTwo;
      }
      return $rows;
    }
  }

  function is_right_token($token) {
    $this->db->where("token", $token);
    $res = $this->db->get('sms_token');
    $total = $res->num_rows();
    $singleData = $res->row();
    if($total == 1){
      return array('status' => TRUE, 'org_id' => $singleData->org_id);
    }
    return array('status' => FALSE, 'org_id' => NULL);
  }

  function get_response_description() {
    $res = $this->db->get('sms_event');
    $list_status = array();
    foreach($res->result_array() AS $status){
      $list_status[$status['id']] = $status['code_str'];
    }
    return $list_status;
  }

  function response_description($status) {
    // $description = array(
    //   '1'  => 'SUCCESS',
    //   '-2' => 'INVALID_ACCOUNT',
    //   '-3' => 'INVALID_PHONE',
    //   '-4' => 'INVALID_LENGTH',
    //   '-7' => 'INVALID_TELCO',
    //   '-8' => 'SPAM',
    //   '-9' => 'INVALID_BRANDNAME',
    //   '-10' => 'INVALID_ADS',
    //   '-11' => 'INVALID_FUNC',
    //   '-1' => 'FAIL' 
    // );
    $this->responseDescription['-12'] = "INVALID_DATETIME";
    $this->responseDescription['2'] = "NO_DATA_FOUND";
    $this->responseDescription['-13'] = "INVALID_TOKEN";
    $this->responseDescription['-14'] = "INVALID_STATUS";
    if($this->responseDescription[$status] == NULL){
      return 'WRONG STATUS - '.$status;
    }
    return $this->responseDescription[$status];
  }

}