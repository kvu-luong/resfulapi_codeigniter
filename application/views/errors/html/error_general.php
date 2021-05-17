<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

header("HTTP/1.0 406 Not Acceptable");
echo json_encode(array('status' => '406', 'description' => 'SPECIAL_CHARACTER_NOT_ALLOW')); 

?>
