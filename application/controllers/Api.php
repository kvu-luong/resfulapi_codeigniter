<?php
defined('BASEPATH') OR exist('No direct script access allowed');

class Api extends CI_Controller {
  public function __construct() {
    parent::__construct();
    $this->load->model('api_model');
    $this->load->library('form_validation');
  }

  function index() {
    $data = $this->api_model->fetch_all();
    echo json_encode($data->result_array());
  }

  function insert() {
    $this->form_validation->set_rules("first_name", "First Name", "required");
    $this->form_validation->set_rules("last_name", "Last Name", "required");
    $response = array();
    if($this->form_validation->run()){
      $data = array(
        'first_name' => trim($this->input->post('first_name')),
        'last_name' => trim($this->input->post('last_name')),
      );
      $this->api_model->insert_api($data);
      $response = array(
        'success' => true,
      );
    } else {
      $response = array(
        'error' => true,
        'first_name_error' => form_error('first_name'),
        'last_name_error' => form_error('last_name'),
      );
    }
    echo json_encode($response);
  }

  function fetch_single() {
    $id = $this->input->post('id');
    if ( isset($id) ) {
      $data = $this->api_model->fetch_single_user($id);
      foreach ($data AS $row) {
        $output['first_name'] = $row['first_name'];
        $output['last_name'] = $row['last_name'];
      }
      echo json_encode($output);
    }
  }

  function update(){
    $this->form_validation->set_rules("first_name", "First Name", "required");
    $this->form_validation->set_rules("last_name", "Last Name", "required");
    $response = array();
    if($this->form_validation->run()){
      $data = array(
        'first_name' => trim($this->input->post('first_name')),
        'last_name' => trim($this->input->post('last_name')),
      );
      $this->api_model->update_api($this->input->post('id'), $data);
      $response = array(
        'success' => true,
      );
    } else {
      $response = array(
        'error' => true,
        'first_name_error' => form_error('first_name'),
        'last_name_error' => form_error('last_name'),
      );
    }
    echo json_encode($response);
  }

  function delete() {
    $id = $this->input->post('id');
    if ( isset($id) ) {
      if($this->api_model->delete_single_user($id)){
        $response = array(
          'success' => true,
        );
      } else {
        $response = array(
          'success' => true,
        );
      }
      echo json_encode($response);
    }
  }

}