<?php

use CP\common\Account;

$app->post('/home/account/login', function(\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $model = new Account();
    $res = $model->login($request->getParams());

    return $response->withJson($res);
});

$app->post('/home/account/rename', function(\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new \CP\common\AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $model = new Account();
    $realname = $request->getParam('realname');
    $res = $model->rename($openid, $realname);

    return $response->withJson($res);
});