<?php

use CP\common\Account;
use CP\book\BookShare;
use CP\book\BookBorrow;
use CP\common\AccountSessionKey;
use CP\common\OpenKey;

$app->post('/home/my/detail', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $model = new Account();
    $res = $model->getDetail($openid);

    return $response->withJson($res);
});

// 我的图书
/*$app->post('/home/my/share', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));

    $model = new BookShare();
    $res = $model->getMyBookShare($groupId, $openid);

    return $response->withJson($res);
});*/

// 我的借书记录
$app->post('/home/my/borrow', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));

    $model = new BookBorrow();
    $res = $model->getMyBookBorrow($openid);

    return $response->withJson($res);
});

// 我的藏书海报数据
$app->post('/home/my/poster/data', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $openKey = new OpenKey();
    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $openKey->getRealId($request->getParam('group_id'));
    $tags_cnt = $request->getParam('tags_cnt', 10);
    $books_cnt = $request->getParam('books_cnt', 20);
    $model = new Account();
    $res = $model->getPosterData($openid, $groupId, $tags_cnt, $books_cnt);
    return $response->withJson($res);
});

// 我的浏览记录
$app->post('/home/my/visit', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $type = $request->getParam('type', 1);

    $model = new BookBorrow();
    $res = $model->getMyVisit($openid, $type, $request->getParams());

    return $response->withJson($res);
});