<?php
class Api_model extends CI_model {
  function __construct() {
    $this->load->database();
  }
  
  function fetch_all() {
    $this->db->order_by('id', 'DESC');
    return $this->db->get('tbl_sample') ;
  }

  function insert_api( $data ){
    $this->db->insert('tbl_sample', $data);
    if ($this->db->affected_rows() > 0) {
      return true;
    }
    return false;
  }

  function fetch_single_user( $user_id ) {
    $this->db->where("id", $user_id);
    $query = $this->db->get('tbl_sample');
    return $query->result_array();
  }

  function update_api( $user_id, $data) {
    $this->db->where("id", $user_id);
    $this->db->update("tbl_sample", $data);
  }

  function delete_single_user( $user_id ) {
    $this->db->where("id", $user_id);
    $this->db->delete("tbl_sample");
    if ($this->db->affected_rows() > 0) {
      return true;
    }
    return false;
  }

}