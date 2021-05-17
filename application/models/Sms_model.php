<?php
class Sms_model extends CI_model {
  private $responseDescription;

  function __construct() {
    $this->load->database();
    $this->responseDescription = $this->get_response_description();
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

  function is_right_token($data) {
    $token = $data['token'];
    $this->db->where("token", $token);
    $res = $this->db->get('sms_token');
    $total = $res->num_rows();
    $singleData = $res->row();
    return array(
      'total'=> $total,
      'data'=> $singleData
    );
  }

  function is_right_brandname($data) {
    $org_id = $data['org_id'];
    $brand_name = $data['brandname'];
    $where = '`org_id`="'.$org_id.'" AND `brand_name`="'.$brand_name.'"';
    $this->db->where($where);
    $res = $this->db->get('sms_user_service');
    $result = $res->row();
    $total = $res->num_rows();
    return array(
      'total' => $total,
      'data' => $result
    );
  }

  function updateResponse($data, $id){
    $this->db->set($data);
    $this->db->where('id', $id);
    $res = $this->db->update('sms_send_receive');
    return $res;
  }

  function saveResponse($dataInsert){
    $this->db->set($dataInsert);
    $res = $this->db->insert('sms_send_receive');
    return $this->db->insert_id();
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

}