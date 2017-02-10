<?php
/**
 * Created by PhpStorm.
 * User: Du
 * Date: 2017/2/7
 * Time: 8:42
 */

use CP\book\Book;

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