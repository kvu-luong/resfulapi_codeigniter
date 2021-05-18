<?php
class V2_model extends CI_model {
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
    $page = $data['page'];
    $per_page = $data['per_page'];
    $token = $data['token'];

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
      //checking page
      $total_count = $this->total_count('sms_send_receive', $where);
      $total_page = $this->page_count($total_count, $per_page);

      if($total_page < $page){
        $this->save_request($token, 0, $org_id);
        return  array(
          'status' => -1,
          'description' => 'EXCEED_PAGE',
          'data' => NULL,
          '_metadata' => $this->metadata_links(0, $total_count, $page, $per_page, $total_page),
        );
      }
      $limit_number = $this->limit_number($page, $per_page);
      $where .= ' LIMIT '.$limit_number.','.$per_page;

      $this->db->where($where);
      $res = $this->db->get('sms_send_receive');
      $rows = NULL;
      foreach($res->result_array() AS $result){
        $rows[] = $result;
      }
      $this->save_request($token, count($rows), $org_id);
      return  array(
          'status' => 1,
          'description' => 'SUCCESS_DEFAULT',
          'data' => $rows,
          '_metadata' => $this->metadata_links(count($rows), $total_count, $page, $per_page, $total_page),
        );
    }

    if($isSelectNormal['table'] == 1){
      $where .= ' AND `date_time`>="'.$start_date.'" AND `date_time`<="'.$end_date.'"';

      //checking page
      $total_count = $this->total_count('sms_send_receive_logical', $where);
      $total_page = $this->page_count($total_count, $per_page);

      if($total_page < $page){
        $this->save_request($token, 0, $org_id);
        return array(
          'status' => -1,
          'description' => 'EXCEED_PAGE',
          'data' => NULL,
          '_metadata' => $this->metadata_links(0, $total_count, $page, $per_page, $total_page),
        );
      }
      $limit_number = $this->limit_number($page, $per_page);
      $where .= ' LIMIT '.$limit_number.','.$per_page;

      $this->db->where($where);
      $res = $this->db->get('sms_send_receive_logical');
      $rows = NULL;
      foreach($res->result_array() AS $result){
        $rows[] = $result;
      }
      $this->save_request($token, count($rows), $org_id);
      return array(
        'status' => 1,
        'description' => 'SUCCESS_LOGICAL',
        'data' => $rows,
        '_metadata' => $this->metadata_links(count($rows), $total_count, $page, $per_page, $total_page),
      );
    }else{
      $dayOfSixMonth = date("Y:m:d H:i:s", strtotime("-6 months"));
      $whereTable1 = $where.' AND `date_time`>="'.$start_date.'" AND `date_time`<"'.$dayOfSixMonth.'"';
      $this->db->where($whereTable1);
      $tableOne = $this->db->get('sms_send_receive_logical');
      $totalDataOne = $tableOne->num_rows();

      $whereTable2 = $where.' AND `date_time`>="'.$dayOfSixMonth.'" AND `date_time`<="'.$end_date.'"';
      $this->db->where($whereTable2);
      $tableTwo = $this->db->get('sms_send_receive');
      $totalDataTwo = $tableTwo->num_rows();

      $total_count = $totalDataOne + $totalDataTwo;
      $total_page = $this->page_count($total_count, $per_page);
      if($total_page < $page){
        $this->save_request($token, 0 , $org_id);
        return array(
          'status' => -1,
          'description' => 'EXCEED_PAGE',
          'data' => NULL,
          '_metadata' => $this->metadata_links(0, $total_count, $page, $per_page, $total_page),
        );
      }
      $limit_number = $this->limit_number($page, $per_page);

      $rows = array();
      foreach($tableOne->result_array() AS $resultOne){
        $rows[] = $resultOne;
      };

      foreach($tableTwo->result_array() AS $resultTwo){
        $rows[] = $resultTwo;
      }
      $rows = array_slice($limit_number, $per_page);
      $this->save_request($token, count($rows), $org_id);
      return array(
        'status' => 1,
        'description' => 'SUCCESS_BOTH',
        'data' => $rows,
        '_metadata' => $this->metadata_links(count($rows), $total_count, $page, $per_page, $total_page),
      );
    }
  }

  function metadata_links($total_data, $total_count, $page, $per_page, $total_page) {
    return array(
      'per_page' => $per_page,
      'total_count' => $total_count,
      'actual_count' => $total_data,
      'total_page' => $total_page,
      'page' => $page,
      'links' => array(
        'self' => "/report?page=".$page."&per_page=".$per_page,
        'first' => "/report?page=1&per_page=".$per_page,
        'previous' => "/report?page=".$this->previous_page($page)."&per_page=".$per_page,
        'next' => "/report?page=".$this->next_page($page, $total_page)."&per_page=".$per_page,
        'last' => "/report?page=".$total_page."&per_page=".$per_page,
      ),
    );
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

  function total_count($table, $where){
    $this->db->where($where);
    $result = $this->db->get($table);
    return $result->num_rows();
  }

  function page_count($total, $per_page){
    return ceil($total / $per_page);
  }

  function is_exceed_page_count($page, $page_count){
    if($page > $page_count){
      return true;
    }
    return false;
  }

  function limit_number($page, $per_page){
    if($page == 1 || $page == 0 || $page < 0) return 0;
    $page = $page - 1; //because limit start from 0;
    return $page * $per_page;
  }

  function previous_page($page){
    if($page == 1) return 1;
    return $page - 1;
  }

  function next_page($page, $total_page){
    if($page == $total_page) return $page;
    return $page + 1;
  }

  function is_can_request($token){
    // $this->band_idaddpress();
    $setting_request = $this->get_setting_request($token);
    if($setting_request == NULL) return array('status'=> FALSE, 'code'=> 429, 'description' => 'Too Many Requests');
    $current_request = $this->get_request_current_day($token);
    if($current_request == NULL) return TRUE;

    $isExceedRequest = $current_request['total_request'] > $setting_request['total_request'];
    if($isExceedRequest) return array('status' => FALSE, 'code' => 429, 'description' => 'Too Many Requests');
    $isExceedData = $current_request['total_data'] > $setting_request['total_data'];
    if($isExceedData) return array('status' => FALSE, 'code' => 503, 'description' => '503 Service Unavailable');
    return array('status' => TRUE);
  }

  function get_setting_request($token){
    $this->db->where('`token`="'.$token.'"');
    $result = $this->db->get('sms_token');
    $data = $result->row();
    if($data == NULL) return NULL;
    return array(
      'total_request' => intval($data->total_request),
      'total_data' => intval($data->total_data),
    );
  }
  function get_request_current_day($token){
    $current_day = date('Y-m-d');
    $this->db->select('`token`, count(`request_time`) AS `total_request`, sum(`request_data`) AS `total_data`');
    $this->db->where('`token` ="'.$token.'" AND `request_time`>= "'.$current_day.' 00:00:00" AND `request_time` <= "'.$current_day.' 23:59:59"');
    $this->db->group_by('`token`');
    $result = $this->db->get('checking_request');
    // print_r($this->db->last_query());    
    $data = $result->row();
    if($data == NULL){
      return NULL;
    }
    return array(
      'total_request' => intval($data->total_request),
      'total_data' => intval($data->total_data),
    );
  }

  function save_request($token, $total_data, $org_id){
    $insertData = array(
        'token' => $token,
        'request_data' => $total_data,
        'request_time' => date('Y-m-d H:i:s'),
        'org_id' => $org_id
    );
    $isOk = $this->db->insert('checking_request', $insertData);
    return $isOk;
  }

  function get_remain_request_data($token){

  }
  function is_right_token_sms($data) {
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

  function getUserInformation($data){
    $response = array(
      'status' => false,
      'message' => '',
      'data' => null, 
      'code' => 1,
    );
    $isRightUser = $this->is_right_token_sms($data);
    if($isRightUser['total'] == 0){
      $response['message'] = $this->response_description(-2);
      $response['code'] = -2;
      return $response;
    }

    $data['org_id'] = $isRightUser['data']->org_id;
    $isRightBrandName = $this->is_right_brandname($data);

    if($isRightBrandName['total'] == 0){
      $response['message'] = $this->response_description(-9);
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
}