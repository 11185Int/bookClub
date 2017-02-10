<?php

use CP\common\Account;
use CP\book\Book;

$app->get('/home/my/detail', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $model = new Account();
    $res = $model->getDetail($request->getParams());

    return $response->withJson($res);
});

// 我的图书 扫码
$app->get('/home/my/getBook', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $model = new Book();


    return $response->withJson([]);
});