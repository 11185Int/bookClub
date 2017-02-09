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
