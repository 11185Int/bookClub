<?php

use CP\common\AccountSessionKey;

$app->get('/home/list/list', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);

    $model = new \CP\book\BookList();
    $res = $model->getList($openid, $request->getParams());

    return $response->withJson($res);
});


$app->post('/home/list/create', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);

    $model = new \CP\book\BookList();
    $res = $model->create($openid, $request->getParams());

    return $response->withJson($res);
});

$app->post('/home/list/edit', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);
    $id = $request->getParam('id');

    $model = new \CP\book\BookList();
    $res = $model->edit($openid, $id, $request->getParams());

    return $response->withJson($res);

});

$app->post('/home/list/delete', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);
    $id = $request->getParam('id');

    $model = new \CP\book\BookList();
    $res = $model->delete($openid, $id);

    return $response->withJson($res);

});

$app->get('/home/list/detail', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);
    $id = $request->getParam('id');

    $model = new \CP\book\BookList();
    $res = $model->detail($openid, $id);

    return $response->withJson($res);

});

$app->get('/home/list/book/list', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $id = $request->getParam('id');

    $model = new \CP\book\BookList();
    $res = $model->bookList($id, $request->getParams());

    return $response->withJson($res);

});

$app->post('/home/list/book/add', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);
    $id = $request->getParam('id');
    $isbn = $request->getParam('isbn');

    $model = new \CP\book\BookList();
    $res = $model->add($openid, $id, $isbn);

    return $response->withJson($res);

});

$app->post('/home/list/book/remove', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);
    $id = $request->getParam('id');
    $isbn = $request->getParam('isbn');

    $model = new \CP\book\BookList();
    $res = $model->remove($openid, $id, $isbn);

    return $response->withJson($res);

});

$app->get('/home/list/my', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);
    $isbn = $request->getParam('isbn', '');

    $model = new \CP\book\BookList();
    $res = $model->getMyList($openid, $isbn);

    return $response->withJson($res);
});

$app->post('/home/list/subscribe', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);
    $booklist_id = $request->getParam('booklist_id', '');

    $model = new \CP\book\BookList();
    $res = $model->subscribe($openid, $booklist_id);

    return $response->withJson($res);
});

$app->post('/home/list/unSubscribe', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);
    $booklist_id = $request->getParam('booklist_id', '');

    $model = new \CP\book\BookList();
    $res = $model->unsubscribe($openid, $booklist_id);

    return $response->withJson($res);
});

