<?php

use CP\book\Book;
use CP\user\User;

// 小组共享图书
$app->get('/home/user/book/list', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $user = new User();
    $user_id = $request->getParam('user_id');
    $openid = $user->getOpenIdByUserId($user_id);
    $model = new Book();
    $res = $model->getListByUser($openid, $request->getParams());

    return $response->withJson($res);
});
