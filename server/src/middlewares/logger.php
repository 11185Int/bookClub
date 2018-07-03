<?php

use Slim\Http\Request;
use Slim\Http\Response;
use CP\common\AccountSessionKey;

$app->add(function (Request $request, Response $response, $next) {
    //BEFORE

    //NEXT
    $response = $next($request, $response);
    //AFTER

    $logger = new \CP\common\Logger($this);
    $logger->log($request, $response);

    return $response;
});