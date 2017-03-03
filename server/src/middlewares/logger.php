<?php

use Slim\Http\Request;
use Slim\Http\Response;
use CP\common\AccountSessionKey;

$app->add(function (Request $request, Response $response, $next) {
    //BEFORE

    //NEXT
    $response = $next($request, $response);
    //AFTER

    $logger = new \CP\common\Logger();
    $client_ip = $logger->getClientIp();
    $uri = $request->getUri();
    $account = new AccountSessionKey();
    $openid = '';
    if ($request->getParam('key')) {
        $openid = $account->getOpenIdByKey($request->getParam('key'));
    }
    $body = $response->getBody();
    $logData = [
        'action' => $uri->getPath(),
        'data' => $uri->getQuery(),// json_encode($request->getQueryParams()),
        'posttime' => date('Y-m-d H:i:s', time()),
        'openid' => $openid ?: '',
        'returndata' => addslashes((string)$body),
        'userip' => $client_ip,
    ];
    $logger->save($logData);

    return $response;
});