<?php

use Slim\Http\Request;
use Slim\Http\Response;
use CP\common\AccountSessionKey;

$app->add(function (Request $request, Response $response, $next) {
    //BEFORE
    $key = $request->getParam('key');
    if ($key) {

        $accountKey = new AccountSessionKey();

        $openid = $accountKey->getOpenIdByKey($key);

        if ($openid) {

        } else {
            return $response->withJson(array(
                'status' => 20000,
                'message' => 'KEY错误或者已过期',
            ));
        }
    } else {

    }
    //NEXT
    $response = $next($request, $response);
    //AFTER
    return $response;
});