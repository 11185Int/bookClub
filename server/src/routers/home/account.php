<?php

use CP\common\Account;

$app->post('/home/account/login', function(\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $model = new Account();
    $res = $model->login($request->getParams());

    return $response->withJson($res);
});


