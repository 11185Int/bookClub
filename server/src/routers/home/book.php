<?php
/**
 * Created by PhpStorm.
 * User: Du
 * Date: 2017/2/7
 * Time: 8:42
 */

use CP\book\Book;
use CP\common\AccountSessionKey;

$app->get('/home/book/list', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $model = new Book();
    $res = $model->getList($request->getParams());

    return $response->withJson($res);
});

// 扫码 获取图书基本信息
$app->get('/home/book/isbn', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $isbn = $request->getParam('isbn');

    $model = new Book();
    $res = $model->getBookByISBN($isbn);

    return $response->withJson($res);
});

// 获取某图书可借阅列表
$app->get('/home/book/shareList', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $isbn = $request->getParam('isbn');

    $model = new Book();
    $res = $model->getShareList($isbn);

    return $response->withJson($res);
});

// 获取某本图书我的可归还列表
$app->get('/home/book/returnList', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $isbn = $request->getParam('isbn');
    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));

    $model = new Book();
    $res = $model->getReturnList($openid, $isbn);

    return $response->withJson($res);
});