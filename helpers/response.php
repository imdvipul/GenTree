<?php
function response($s,$m,$d=null){echo json_encode(["status"=>$s,"message"=>$m,"data"=>$d]);exit;}
