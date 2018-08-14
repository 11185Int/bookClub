<?php

namespace CP\book;

use CP\common\AbstractModel;
use CP\common\Account;
use CP\common\OpenKey;

class BookBorrow extends AbstractModel
{

    public function getMyBookBorrow($openid, $type, $params)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $pagesize = isset($params['pagesize']) ? intval($params['pagesize']) : 10;
        $offset = ($page - 1) * $pagesize;

        if ($type == 1) { //借阅记录
            $prefix = $this->capsule->getConnection()->getTablePrefix();
            $builder = $this->capsule->table('book_borrow AS bb')
                ->select('b.id AS book_id','b.isbn10','b.isbn13','b.title','b.image','b.author','u.nickname','u.realname')
                ->selectRaw('max('.$prefix.'bb.borrow_time) AS borrow_time')
                ->selectRaw('max('.$prefix.'bb.return_time) AS return_time')
                ->selectRaw('min('.$prefix.'bb.return_status) AS return_status')
                ->leftJoin('book_share AS bs', 'bs.id', '=', 'bb.book_share_id')
                ->leftJoin('book AS b', 'b.id', '=', 'bs.book_id')
                ->leftJoin('user AS u', 'u.id', '=', 'bs.owner_id')
                ->where(function ($q) use ($openid){
                    $q->where(function ($q) use ($openid){
                        $q->where('bs.group_id', 0)->where('bb.borrower_openid', $openid);
                    })->orWhere(function ($q) use ($openid){
                        $q->where('bs.group_id', '>', 0)->where('bb.borrower_openid', $openid);
                    });
                })
                ->groupBy('b.id')
                ->orderBy('return_status', 'asc');

        } else { //被借记录
            $prefix = $this->capsule->getConnection()->getTablePrefix();
            $builder = $this->capsule->table('book_borrow AS bb')
                ->select('b.id AS book_id','b.isbn10','b.isbn13','b.title','b.image','b.author','u.nickname','u.realname')
                ->selectRaw('max('.$prefix.'bb.borrow_time) AS borrow_time')
                ->selectRaw('max('.$prefix.'bb.return_time) AS return_time')
                ->selectRaw('min('.$prefix.'bb.return_status) AS return_status')
                ->leftJoin('book_share AS bs', 'bs.id', '=', 'bb.book_share_id')
                ->leftJoin('book AS b', 'b.id', '=', 'bs.book_id')
                ->leftJoin('user AS u', 'u.id', '=', 'bb.borrower_id')
                ->where('bs.group_id', 0)
                ->where('bs.owner_openid', $openid)
                ->groupBy('b.id')
                ->orderBy('return_status', 'asc');

        }

        $data = [];
        $totalCount = count($builder->get());
        $list = $builder->limit($pagesize)->offset($offset)->get();
        foreach ($list as $item) {
            $record = [
                'book_id' => $item['book_id'],
                'isbn10' => $item['isbn10'],
                'isbn13' => $item['isbn13'],
                'title' => $item['title'],
                'image' => $item['image'],
                'author' => $item['author'],
                'name' => $item['realname'] ?: $item['nickname'],
                'borrow_time' => date('Y年m月d日 H:i', $item['borrow_time']),
                'return_time' => $item['return_status'] > 0 ? date('Y年m月d日 H:i', $item['return_time']) : '',
                'return_status' => $item['return_status'],
            ];
            $data[] = $record;
        }


        if ($builder) {
            $res['data'] = [
                'list' => $data,
                'total' => intval($totalCount),
                'pagesize' => $pagesize,
                'totalpage' => ceil($totalCount / $pagesize),
            ];
        } else {
            $res = array(
                'status' => 1001,
                'message' => '获取数据失败',
            );
        }

        return $res;
    }

    public function getGroupBorrow($openid, $groupId, $type, $params)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $pagesize = isset($params['pagesize']) ? intval($params['pagesize']) : 10;
        $offset = ($page - 1) * $pagesize;

        $user_group = $this->capsule->table('user_group')->where('group_id', $groupId)
            ->where('openid', $openid)->first();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此小组',
            ];
        }
        if ($user_group['is_admin'] == 0) {
            return [
                'status' => 99999,
                'message' => '无权限操作',
            ];
        }

        $prefix = $this->capsule->getConnection()->getTablePrefix();
        $builder = $this->capsule->table('book_borrow AS bb')
            ->select('b.id AS book_id','b.isbn10','b.isbn13','b.title','b.image','b.author','u.nickname','u.realname')
            ->selectRaw('max('.$prefix.'bb.borrow_time) AS borrow_time')
            ->selectRaw('max('.$prefix.'bb.return_time) AS return_time')
            ->selectRaw('min('.$prefix.'bb.return_status) AS return_status')
            ->leftJoin('book_share AS bs', 'bs.id', '=', 'bb.book_share_id')
            ->leftJoin('book AS b', 'b.id', '=', 'bs.book_id')
            ->leftJoin('user AS u', 'u.id', '=', 'bb.borrower_id')
            ->where('bs.group_id', $groupId)
            ->groupBy('b.id')
            ->orderBy('bb.borrow_time', 'desc');

        if ($type == 1) { //正在借阅
            $builder->where('return_status', 0);
        } else { //已经归还
            $builder->where('return_status', 1);
        }

        $data = [];
        $totalCount = count($builder->get());
        $list = $builder->limit($pagesize)->offset($offset)->get();

        foreach ($list as $item) {
            $record = [
                'book_id' => $item['book_id'],
                'isbn10' => $item['isbn10'],
                'isbn13' => $item['isbn13'],
                'title' => $item['title'],
                'image' => $item['image'],
                'author' => $item['author'],
                'name' => $item['realname'] ?: $item['nickname'],
                'borrow_time' => date('Y年m月d日 H:i', $item['borrow_time']),
                'return_time' => $item['return_status'] > 0 ? date('Y年m月d日 H:i', $item['return_time']) : '',
                'return_status' => $item['return_status'],
            ];
            $data[] = $record;
        }

        if ($builder) {
            $res['data'] = [
                'list' => $data,
                'total' => intval($totalCount),
                'pagesize' => $pagesize,
                'totalpage' => ceil($totalCount / $pagesize),
            ];
        } else {
            $res = array(
                'status' => 1001,
                'message' => '获取数据失败',
            );
        }

        return $res;
    }

    public function borrow($groupId, $userId, $openid, $isbn, $remark)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        if (!$isbn) {
            return [
                'status' => 10000,
                'message' => '参数不全',
            ];
        }
        if (!$groupId && !$userId) {
            return [
                'status' => 10000,
                'message' => '参数不全',
            ];
        }
        $bookModel = new Book();
        $book = $bookModel->findBook($isbn);
        if (!$book) {
            return [
                'status' => 6000,
                'message' => '找不到图书',
            ];
        }
        $book_id = $book['id'];
        $groupId = $groupId ? intval($groupId) : 0;
        //是否设置了真实名字
        $accountModel = new Account();
        if ($accountModel->isRealNameEmpty($openid, $groupId)) {
            return [
                'status' => 10006,
                'message' => '还未设置真实名字',
            ];
        }

        $bookShare = null;
        if ($groupId) {
            $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)->first();
            if (empty($user_group)) {
                return [
                    'status' => 99999,
                    'message' => '还未加入此小组',
                ];
            }
            $bookShare = $this->capsule->table('book_share')->where('book_id', $book_id)->where('group_id', $groupId)
                ->where('share_status', 1)->where('lend_status', 1)->first();
        } else if ($userId) {
            $bookShare = $this->capsule->table('book_share')->where('book_id', $book_id)->where('group_id', 0)
                ->where('owner_id', $userId)->where('share_status', 1)->where('lend_status', 1)->first();
        }
        if (empty($bookShare)) {
            return [
                'status' => 10001,
                'message' => '图书未分享或已被借出',
            ];
        }
        $config = $this->app->get('settings')['config'];
        $borrowSelf = !isset($config['borrowSelf']) ? false : (bool)$config['borrowSelf'];
        if (!$borrowSelf && $bookShare['owner_openid'] == $openid) {
            return [
                'status' => 10003,
                'message' => '无法借阅自己分享的图书',
            ];
        }

        //能否同时借阅多本同一书籍
        $borrowMultiple = false;
        if (!$borrowMultiple) {
            $borrowing = $this->capsule->table('book_borrow AS bb')
                ->leftJoin('book_share AS s', 'bb.book_share_id', '=', 's.id')
                ->where('bb.borrower_openid', $openid)->where('bb.return_status', 0)
                ->where('s.book_id', $book_id)->count();
            if ($borrowing > 0) {
                return [
                    'status' => 99999,
                    'message' => '无法借阅多本同样的书',
                ];
            }
            $sharing = $this->capsule->table('book_share')
                ->where('owner_openid', $openid)->where('group_id', 0)
                ->where('book_id', $book_id)->where('share_status', 1)->count();
            if ($sharing > 0) {
                return [
                    'status' => 99999,
                    'message' => '你已分享此书',
                ];
            }
        }

        $kv = array(
            'book_share_id' => $bookShare['id'],
            'borrower_id' => $this->getUserIdByOpenid($openid),
            'borrower_openid' => $openid,
            'borrow_time' => time(),
            'return_status' => 0,
            'return_time' => 0,
            'remark' => $remark,
        );
        $this->capsule->getConnection()->beginTransaction();

        $r1 = $this->capsule->table('book_borrow')->insert($kv);
        $r2 = $this->capsule->table('book_share')->where('id', $bookShare['id'])->where('group_id', $groupId)
            ->update(['lend_status' => 2]);
        if ($r1 && $r2) {
            $this->capsule->getConnection()->commit();
        } else {
            $this->capsule->getConnection()->rollBack();
            return [
                'status' => 10002,
                'message' => '借阅失败',
            ];
        }

        return $res;
    }

    public function returnBook($openid, $isbn, $remark)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        if (!$isbn) {
            return [
                'status' => 10000,
                'message' => '参数不全',
            ];
        }
        $bookModel = new Book();
        $book = $bookModel->findBook($isbn);
        if (!$book) {
            return [
                'status' => 6000,
                'message' => '找不到图书',
            ];
        }
        $book_id = $book['id'];

        //默认还最后借的那本
        $book_borrow = $this->capsule->table('book_borrow AS bb')
            ->leftJoin('book_share AS bs', 'bs.id', '=', 'bb.book_share_id')
            ->select('bb.*')
            ->where('bb.borrower_openid', $openid)
            ->where('bs.book_id', $book_id)
            ->where('bb.return_status', 0)
            ->orderBy('bb.id', 'desc')
            ->first() ?: [];
        if (empty($book_borrow)) {
            return [
                'status' => 99999,
                'message' => '找不到该借阅记录',
            ];
        }
        $book_share = $this->capsule->table('book_share')->find($book_borrow['book_share_id']) ?: [];
        if (empty($book_share)) {
            return [
                'status' => 99999,
                'message' => '找不到该借阅记录',
            ];
        }
        //操作只允许借出者和借入者双方执行
        if ($book_share['owner_openid'] != $openid && $book_borrow['borrower_openid'] != $openid) {
            return [
                'status' => 20002,
                'message' => '越权操作',
            ];
        }

        $kv = array(
            'return_status' => 1,
            'return_time' => time(),
            'remark' => $remark,
        );
        $this->capsule->getConnection()->beginTransaction();

        $r1 = $this->capsule->table('book_borrow')->where('id', $book_borrow['id'])->update($kv);
        $r2 = $this->capsule->table('book_share')->where('id', $book_share['id'])->update(['lend_status' => 1]);

        if ($r1 && $r2) {
            $this->capsule->getConnection()->commit();
        } else {
            $this->capsule->getConnection()->rollBack();
            return [
                'status' => 10002,
                'message' => '借阅失败',
            ];
        }

        return $res;
    }

    public function getMyVisit($openid, $type, $params)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $pagesize = isset($params['pagesize']) ? intval($params['pagesize']) : 10;
        $offset = ($page - 1) * $pagesize;
        if ($type == 1) { //浏览
            $builder = $this->capsule->table('visit_history AS h')
                ->leftJoin('user AS u', 'u.openid', '=', 'h.dest_openid')
                ->leftJoin('book_share AS s', 's.owner_openid', '=', 'u.openid')
                ->select('u.id AS id', 'u.headimgurl', 'h.latest_time', 'u.realname', 'u.nickname')
                ->selectRaw('"user" AS type,count(distinct '.$this->capsule->getConnection()->getTablePrefix().'s.id) AS book_cnt')
                ->where('h.openid', $openid)
                ->where('h.dest_group_id', 0)
                ->where('h.dest_openid', '!=', $openid)
                ->where('s.group_id', 0)
                ->where('s.share_status', 1)
                ->groupBy('u.id')
                ->orderBy('h.latest_time', 'deac');
            $groupBuilder = $this->capsule->table('visit_history AS h')
                ->leftJoin('group AS g', 'g.id', '=', 'h.dest_group_id')
                ->leftJoin('book_share AS s', 's.group_id', '=', 'g.id')
                ->select('g.id AS id', 'g.headimgurl', 'h.latest_time', 'g.group_name AS realname', 'g.group_name AS nickname')
                ->selectRaw('"group" AS type,count(distinct '.$this->capsule->getConnection()->getTablePrefix().'s.id) AS book_cnt')
                ->where('h.openid', $openid)
                ->where('s.group_id', '>', 0)
                ->where('s.share_status', 1)
                ->groupBy('g.id')
                ->orderBy('h.latest_time', 'desc');
        } else {
            $builder = $this->capsule->table('visit_history AS h')
                ->leftJoin('user AS u', 'u.openid', '=', 'h.openid')
                ->leftJoin('book_share AS s', 's.owner_openid', '=', 'u.openid')
                ->select('u.id AS id', 'u.headimgurl', 'h.latest_time', 'u.realname', 'u.nickname')
                ->selectRaw('"user" AS type,count(distinct '.$this->capsule->getConnection()->getTablePrefix().'s.id) AS book_cnt')
                ->where('h.dest_openid', $openid)
                ->where('h.dest_group_id', 0)
                ->where('h.openid', '!=', $openid)
                ->where('s.group_id', 0)
                ->where('s.share_status', 1)
                ->groupBy('u.id')
                ->orderBy('h.latest_time', 'desc');
        }
        if (isset($groupBuilder)) {
            $builder = $builder->union($groupBuilder);
        }
        $totalCount = count($builder->get());
        $list = $builder->offset($offset)->limit($pagesize)->get();
        $data = [];
        $uid = [];
        $gid = [];
        $openKey = new OpenKey();
        foreach ($list as $item) {
            if ($item['type'] == 'user') {
                $uid[] = $item['id'];
            }
            if ($item['type'] == 'group') {
                $gid[] = $item['id'];
            }
            $data[] = [
                'type' => $item['type'],
                'id' => $item['type'] == 'user' ? $openKey->getOpenKey($item['id'], OpenKey::TYPE_USER_ID) :
                                                    $openKey->getOpenKey($item['id'], OpenKey::TYPE_GROUP_ID),
                'headimgurl' => $item['headimgurl'] ?: '',
                'realname' => $item['realname'] ?: $item['nickname'],
                'book_cnt' => $item['book_cnt'],
                'latest_time' => date('Y年m月d日 H:i', $item['latest_time']),
            ];
        }

        $booksArr = [];
        $isAdminArr = [];
        if (count($uid) > 0) {
            $prefix = $this->capsule->getConnection()->getTablePrefix();
            $books = $this->capsule->table('book_share AS s')
                ->leftJoin('book AS b', 'b.id', '=', 's.book_id')
                ->select('s.owner_id', 'b.isbn10', 'b.isbn13', 'b.image', 'b.hd_image', 'b.title', 'b.author')
                ->whereIn('s.owner_id', $uid)
                ->where('s.group_id', 0)
                ->whereRaw('3 > (select count(*) from '.$prefix.'book_share
                    where owner_id = '.$prefix.'s.owner_id and group_id = 0
                    and id > '.$prefix.'s.id)')
                ->groupBy(['s.owner_id','b.id'])
                ->orderBy('s.id', 'desc')
                ->get();

            foreach ($books as $book) {
                $owner_id = $openKey->getOpenKey($book['owner_id'], OpenKey::TYPE_USER_ID);
                unset($book['owner_id']);
                $booksArr[$owner_id][] = $book;
            }
        }
        if (count($gid) > 0) {
            $prefix = $this->capsule->getConnection()->getTablePrefix();
            $books = $this->capsule->table('book_share AS s')
                ->leftJoin('book AS b', 'b.id', '=', 's.book_id')
                ->select('s.group_id', 'b.isbn10', 'b.isbn13', 'b.image', 'b.hd_image', 'b.title', 'b.author')
                ->whereIn('s.group_id', $gid)
                ->whereRaw('3 > (select count(*) from '.$prefix.'book_share
                    where group_id = '.$prefix.'s.group_id
                    and id > '.$prefix.'s.id)')
                ->groupBy(['s.group_id','b.id'])
                ->orderBy('s.id', 'desc')
                ->get();

            foreach ($books as $book) {
                $group_id = $openKey->getOpenKey($book['group_id'], OpenKey::TYPE_GROUP_ID);
                unset($book['group_id']);
                $booksArr[$group_id][] = $book;
            }
            $user_group_list = $this->capsule->table('user_group')
                ->whereIn('group_id', $gid)->where('openid', $openid)->select('group_id', 'is_admin')->get();
            foreach ($user_group_list as $user_group) {
                $isAdminArr[$openKey->getOpenKey($user_group['group_id'], OpenKey::TYPE_GROUP_ID)] = $user_group['is_admin'];
            }
        }
        foreach ($data as $key => $datum) {
            $data[$key]['books'] = [];
            if (!empty($booksArr[$datum['id']])) {
                $data[$key]['books'] = $booksArr[$datum['id']];
            }
            $data[$key]['is_admin'] = empty($isAdminArr[$datum['id']]) ? 0 : intval($isAdminArr[$datum['id']]);
        }

        $res['data'] = [
            'list' => $data,
            'total' => intval($totalCount),
            'pagesize' => $pagesize,
            'totalpage' => ceil($totalCount / $pagesize),
        ];
        return $res;
    }

}
