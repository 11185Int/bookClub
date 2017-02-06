<?php

use CP\common\Account;

$app->get('/home/my/detail', function(\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $model = new Account();
    $res = $model->getDetail($request->getParams());

    return $response->withJson($res);
});

