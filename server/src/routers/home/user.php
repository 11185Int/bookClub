<?php

use CP\book\Book;
use CP\user\User;
use CP\book\Visit;
use CP\common\AccountSessionKey;

// 小组共享图书
$app->get('/home/user/book/list', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $user = new User();
    $user_id = $request->getParam('user_id');
    $openid = $user->getOpenIdByUserId($user_id);
    $account = new AccountSessionKey();
    $myOpenid = $account->getOpenIdByKey($request->getParam('key'));
    $model = new Book();
    $res = $model->getListByUser($openid, $request->getParams());

    if ($res['status'] == 0) {
        $visit = new Visit();
        $visit->visitUser($myOpenid, $openid);
    }

    return $response->withJson($res);
});
