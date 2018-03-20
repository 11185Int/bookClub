<?php

use CP\common\AccountSessionKey;
use CP\group\Group;

// 创建图书馆
$app->post('/home/group/create', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $name = $request->getParam('name', '');

    $group = new Group();
    $res = $group->create($openid, $name);

    return $response->withJson($res);
});

// 获取当前图书馆信息
$app->post('/home/group/detail', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));

    $group = new Group();
    $res = $group->detail($groupId);

    return $response->withJson($res);
});

// 图书馆成员列表
$app->post('/home/group/member/list', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));

    $group = new Group();
    $res = $group->getList($openid, $groupId);

    return $response->withJson($res);
});

// 移除成员
$app->post('/home/group/member/delete', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));
    $user_group_id = $request->getParam('user_group_id');

    $group = new Group();
    $res = $group->deleteMember($groupId, $openid, $user_group_id);

    return $response->withJson($res);
});

// 加入图书馆
$app->post('/home/group/join', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $request->getParam('group_id');
    $realname = $request->getParam('realname');
    $phone = $request->getParam('phone');

    $group = new Group();
    $res = $group->join($groupId, $openid, $realname, $phone);

    return $response->withJson($res);
});

// 我的图书馆列表
$app->post('/home/group/list', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));

    $group = new Group();
    $res = $group->getGroupList($openid);

    return $response->withJson($res);
});

// 切换图书馆
$app->post('/home/group/switch', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $request->getParam('group_id');

    $group = new Group();
    $res = $group->switchGroup($groupId, $openid);

    return $response->withJson($res);
});
