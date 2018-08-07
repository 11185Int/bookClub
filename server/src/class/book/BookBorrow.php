<?php

namespace CP\book;

use CP\common\AbstractModel;
use CP\common\Account;
use CP\common\OpenKey;

class BookBorrow extends AbstractModel
{

    public function getMyBookBorrow($openid)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        $builder = $this->capsule->table('book_borrow AS borrow')
            ->select('share.id AS book_share_id', 'share.book_id', 'book.isbn10', 'book.isbn13', 'book.title',
                'book.image', 'share.owner_openid', 'borrow.borrow_time')
            ->selectRaw('min('.$this->capsule->getConnection()->getTablePrefix().'borrow.return_status) as return_status')
            ->join('book_share AS share', 'share.id', '=', 'borrow.book_share_id', 'inner')
            ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
            ->where('borrow.borrower_openid', $openid)
            ->groupBy('book.id')
            ->orderBy('return_status', 'asc')
            ->orderBy('borrow.borrow_time', 'desc');
        
        if ($builder) {
            $res['data']['borrow'] = $builder->get();
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
        if ($type == 1) {
            $builder = $this->capsule->table('visit_history AS h')
                ->leftJoin('user AS u', 'u.openid', '=', 'h.dest_openid')
                ->leftJoin('book_share AS s', 's.owner_openid', '=', 'u.openid')
                ->leftJoin('book AS b', 'b.id', '=', 's.book_id')
                ->select('u.id AS user_id', 'u.headimgurl', 'u.nickname', 'u.realname', 'h.latest_time')
                ->selectRaw('count(distinct '.$this->capsule->getConnection()->getTablePrefix().'b.id) AS book_cnt')
                ->where('h.openid', $openid)
                ->where('h.dest_group_id', 0)
                ->where('h.dest_openid', '!=', $openid)
                ->where('s.group_id', 0)
                ->groupBy('u.id')
                ->orderBy('h.latest_time', 'deac');
        } else {
            $builder = $this->capsule->table('visit_history AS h')
                ->leftJoin('user AS u', 'u.openid', '=', 'h.openid')
                ->leftJoin('book_share AS s', 's.owner_openid', '=', 'u.openid')
                ->leftJoin('book AS b', 'b.id', '=', 's.book_id')
                ->select('u.id AS user_id', 'u.headimgurl', 'u.nickname', 'u.realname', 'h.latest_time')
                ->selectRaw('count(distinct '.$this->capsule->getConnection()->getTablePrefix().'b.id) AS book_cnt')
                ->where('h.dest_openid', $openid)
                ->where('h.dest_group_id', 0)
                ->where('h.openid', '!=', $openid)
                ->where('s.group_id', 0)
                ->groupBy('u.id')
                ->orderBy('h.latest_time', 'deac');
        }
        $totalCount = count($builder->get());
        $list = $builder->offset($offset)->limit($pagesize)->get();
        $data = [];
        $uid = [];
        $openKey = new OpenKey();
        foreach ($list as $item) {
            $uid[] = $item['user_id'];
            $data[] = [
                'user_id' => $openKey->getOpenKey($item['user_id'], OpenKey::TYPE_USER_ID),
                'headimgurl' => $item['headimgurl'],
                'realname' => $item['realname'] ?: $item['nickname'],
                'book_cnt' => $item['book_cnt'],
                'latest_time' => date('Y年m月d日 H:i', $item['latest_time']),
            ];
        }

        if (count($uid) > 0) {
            $prefix = $this->capsule->getConnection()->getTablePrefix();
            $books = $this->capsule->table('book_share AS s')
                ->leftJoin('book AS b', 'b.id', '=', 's.book_id')
                ->select('s.owner_id', 'b.isbn10', 'b.isbn13', 'b.image', 'b.hd_image', 'b.title', 'b.author')
                ->whereIn('s.owner_id', $uid)
                ->where('s.group_id', 0)
                ->whereRaw('4 > (select count(*) from '.$prefix.'book_share
                    where owner_id = '.$prefix.'s.owner_id and group_id = 0
                    and id > '.$prefix.'s.id)')
                ->groupBy(['s.owner_id','b.id'])
                ->orderBy('s.id', 'desc')
                ->get();
            $booksArr = [];
            foreach ($books as $book) {
                $owner_id = $book['owner_id'];
                unset($book['owner_id']);
                $booksArr[$owner_id][] = $book;
            }
            foreach ($data as $key => $datum) {
                $data[$key]['books'] = [];
                if (isset($booksArr[$datum['user_id']])) {
                    $data[$key]['books'] = $booksArr[$datum['user_id']];
                }
            }
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
