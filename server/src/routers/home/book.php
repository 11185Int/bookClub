<?php
/**
 * Created by PhpStorm.
 * User: Du
 * Date: 2017/2/7
 * Time: 8:42
 */

use CP\book\Book;
use CP\book\BookShare;
use CP\book\BookBorrow;
use CP\common\AccountSessionKey;
use CP\common\AccessList;
use CP\book\Search;
use CP\user\User;

// 个人藏书页
$app->get('/home/book/list', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $key = $request->getParam('key');
    $openid = $account->getOpenIdByKey($key);
    $groupIds = $account->getAllGroupIdByKey($key);
    $model = new Book();
    $res = $model->getList($openid, $groupIds, $request->getParams());

    return $response->withJson($res);
});

// 扫码 获取图书基本信息
$app->get('/home/book/isbn', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $isbn = $request->getParam('isbn');

    $model = new Book();
    $res = $model->getBookByISBN($isbn);

    return $response->withJson($res);
});

// 获取图书状态 1、能否借  2、能否归还 3、是否已添加 4、是否已共享 5、标记+书签
$app->get('/home/book/status', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $isbn = $request->getParam('isbn');
    $groupId = $request->getParam('group_id');
    $userId = $request->getParam('user_id');
    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));

    $model = new Book();
    if ($groupId) {
        $res = $model->getBookStatusByGroup($isbn, $openid, $groupId);
    } else if ($userId) {
        $user = new User();
        $owner_openid = $user->getOpenIdByUserId($userId);
        $res = $model->getBookStatusByUser($isbn, $openid, $owner_openid);
    } else {
        $res = array(
            'status' => 99999,
            'message' => '参数错误',
        );
    }
    return $response->withJson($res);
});

// 扫码 获取图书基本信息
$app->post('/home/book/saveBookmark', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $bookId = $request->getParam('book_id');
    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $params = $request->getParams();

    $model = new \CP\book\BookMark();
    $res = $model->saveBookmark($bookId, $openid, $params);

    return $response->withJson($res);
});



// 获取某图书可借阅列表
$app->get('/home/book/shareList', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $isbn = $request->getParam('isbn');
    $groupId = $request->getParam('group_id');
    $userId = $request->getParam('user_id');
    $account = new AccountSessionKey();
    $myOpenid = $account->getOpenIdByKey($request->getParam('key'));
    $model = new Book();
    $res = $model->getShareList($isbn, $groupId, $userId, $myOpenid);

    return $response->withJson($res);
});

// 获取某本图书我的可归还列表（拥有者）
$app->get('/home/book/returnList', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $isbn = $request->getParam('isbn');
    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));

    $model = new Book();
    $res = $model->getReturnList($groupId, $openid, $isbn);

    return $response->withJson($res);
});

// 获取某本图书我的阅读主动归还列表（借阅者）
$app->get('/home/book/myReturnList', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $isbn = $request->getParam('isbn');
    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));

    $model = new Book();
    $res = $model->getMyReturnList($groupId, $openid, $isbn);

    return $response->withJson($res);
});

// 确认共享
$app->post('/home/book/share', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));
    $isbn = $request->getParam('isbn');
    $remark = $request->getParam('remark');

    $model = new BookShare();
    $res = $model->share($groupId, $openid, $isbn, $remark);

    return $response->withJson($res);
});

// 取消共享
$app->post('/home/book/unshare', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));
    $book_share_id = $request->getParam('book_share_id');

    $model = new BookShare();
    $res = $model->unShare($groupId, $openid, $book_share_id);

    return $response->withJson($res);
});

// 恢复共享
$app->post('/home/book/reshare', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));
    $book_share_id = $request->getParam('book_share_id');

    $model = new BookShare();
    $res = $model->reShare($groupId, $openid, $book_share_id);

    return $response->withJson($res);
});

// 确认借阅
$app->post('/home/book/borrow', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));
    $book_share_id = $request->getParam('book_share_id');
    $remark = $request->getParam('remark');

    $model = new BookBorrow($this);
    $res = $model->borrow($groupId, $openid, $book_share_id, $remark);

    return $response->withJson($res);
});

// 确认归还
$app->post('/home/book/return', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));
    $book_share_id = $request->getParam('book_share_id');
    $remark = $request->getParam('remark');

    $model = new BookBorrow();
    $res = $model->returnBook($groupId, $openid, $book_share_id, $remark);

    return $response->withJson($res);
});

// 提交图书表单
$app->post('/home/book/submit', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $uploadFiles = $request->getUploadedFiles();
    $image = isset($uploadFiles['image']) ? $uploadFiles['image'] : null;
    $config = $this->get('settings')['config'];

    $params = $request->getParams();
    $params['image'] = $image;

    $model = new Book();
    $res = $model->submit($params, $openid, $config);

    return $response->withJson($res);
})->add(function (\Slim\Http\Request $request, \Slim\Http\Response $response, $next) {
    //BEFORE
    $key = $request->getParam('key');
    $checkFlag = true;
    if ($key) {
        $accountKey = new AccountSessionKey();
        $openid = $accountKey->getOpenIdByKey($key);
        if ($openid) {
            $model = new AccessList();
            if (!$model->checkAccess($openid)) {
                $checkFlag = false;
            }
        } else {
            $checkFlag = false;
        }
    } else {
        $checkFlag = false;
    }
    if (!$checkFlag) {
        return $response->withJson(array(
            'status' => 10005,
            'message' => '没有权限操作',
        ));
    }
    //NEXT
    $response = $next($request, $response);
    //AFTER
    return $response;
});

// 图书借阅历史
$app->post('/home/book/borrow/history', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $isbn = $request->getParam('isbn');
    $account = new AccountSessionKey();
    $groupId = $account->getCurrentGroupIdByKey($request->getParam('key'));

    $model = new Book();
    $res = $model->getBorrowHistory($groupId, $isbn);

    return $response->withJson($res);
});

// 通过豆瓣搜索图书
$app->get('/home/book/search', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $q = $request->getParam('name');
    $page = $request->getParam('page', 1);
    $pagesize = $request->getParam('pagesize', 20);

    $model = new Book();
    $res = $model->getSearchList($q, $page, $pagesize);

    return $response->withJson($res);
});

$app->get('/home/book/search/hot', function (\Slim\Http\Request $request, \Slim\Http\Response $response, $args) {

    $model = new Search();
    $account = new AccountSessionKey();
    $openid = $account->getOpenIdByKey($request->getParam('key'));
    $res = $model->getHotSearch($openid);
    return $response->withJson($res);
});