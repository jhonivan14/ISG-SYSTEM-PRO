<?php

$url = "http://192.168.1.3:8080/sms/send"; // imuha IP

$data = [
    "phone" => "09304237713",
    "message" => "Test SMS: Working na boss!",
    "key" => "ABCD1234"
];

$options = [
    "http" => [
        "header"  => "Content-Type: application/json\r\n",
        "method"  => "POST",
        "content" => json_encode($data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo $result;

?>
