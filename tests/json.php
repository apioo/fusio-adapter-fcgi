<?php

header('Content-Type: application/json');

$body = file_get_contents('php://input');
$data = null;
if (!empty($body)) {
    $data = json_decode($body);
}

$data = [
    'foo' => 'bar',
    'body' => $data,
    'server' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'REQUEST_URI' => $_SERVER['REQUEST_URI'],
        'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'],
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'],
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'],
        'REMOTE_USER' => $_SERVER['REMOTE_USER'],
        'ROUTE_ID' => $_SERVER['ROUTE_ID'],
        'USER_ANONYMOUS' => $_SERVER['USER_ANONYMOUS'],
        'USER_ID' => $_SERVER['USER_ID'],
        'APP_ID' => $_SERVER['APP_ID'],
        'APP_KEY' => $_SERVER['APP_KEY'],
    ],
];

echo json_encode($data);
