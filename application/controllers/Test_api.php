<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test_api extends CI_Controller {
  public function __construct() {
    parent::__construct();
    $this->load->helper('url');
  }

  function index(){
  //$method_url = !empty($_SERVER['REQUEST_METHOD'])? $_SERVER['REQUEST_METHOD'] : 'GET';

    $this->load->view('api_view');
  }

  function action() {
    $action = $this->input->post('data_action');
    if (isset($action)) {
      if ($action == 'Delete') {
        $api_url = base_url()."api/delete";

        $form_data = array(
          'id' => $this->input->post('user_id'),
        );

        $response = $this->lib->curlPost($api_url, $form_data);
        echo $response;
      }

      if ($action == 'Edit') {
        $api_url = base_url()."api/update";

        $form_data = array(
          'first_name' => $this->input->post('first_name'),
          'last_name' => $this->input->post('last_name'),
          'id' => $this->input->post('user_id'),
        );

        $response = $this->lib->curlPost($api_url, $form_data);
        echo $response;
      }

      if ($action == 'fetch_single') {
        $api_url = base_url()."api/fetch_single";

        $form_data = array(
          'id' => $this->input->post('user_id'),
        );

        $response = $this->lib->curlPost($api_url, $form_data);
        echo $response;
      }

      if ($action == "Insert") {
        $api_url = base_url()."api/insert";

        $form_data = array(
          'first_name' => $this->input->post('first_name'),
          'last_name' => $this->input->post('last_name'),
        );

        $response = $this->lib->curlPost($api_url, $form_data);
        echo $response;
      }

      if($action == 'fetch_all') {
        $api_url = "https://dev-demo.cloudpbx.vn:8089/khanhvu/sms_brandname_api/api";
        $response = $this->lib->curlPost($api_url);
        $result = json_decode($response);

        $output = '';
        if (count($result) > 0) {
          foreach ($result AS $row) {
            $output .= '<tr>
                       <td>'.$row->first_name.'</td>
                       <td>'.$row->last_name.'</td>
                       <td><butto type="button" name="edit" class="btn btn-warning btn-xs edit" id="'.$row->id.'">Edit</button></td>
                       <td><button type="button" name="delete" class="btn btn-danger btn-xs delete" id="'.$row->id.'">Delete</button></td>
                      </tr>';
          }
        } else {
           $output .= '
                     <tr>
                      <td colspan="4" align="center">No Data Found</td>
                     </tr>
                     ';
        }

        echo $output;
      }
    }
  }

}