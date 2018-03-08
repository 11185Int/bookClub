<?php

namespace CP\book;

use CP\common\AbstractModel;

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

    public function borrow($openid, $book_share_id, $remark)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        if (!$book_share_id) {
            return [
                'status' => 10000,
                'message' => '参数不全',
            ];
        }

        $bookShareModel = new BookShare();
        $bookShare = $bookShareModel->findBookShareById($book_share_id);
        if (empty($bookShare) || $bookShare['share_status'] != 1 || $bookShare['lend_status'] != 1) {
            return [
                'status' => 10001,
                'message' => '图书未分享或已被借出',
            ];
        }
        if ($bookShare['owner_openid'] == $openid) {
            return [
                'status' => 10003,
                'message' => '无法借阅自己分享的图书',
            ];
        }

        $kv = array(
            'book_share_id' => $book_share_id,
            'borrower_id' => $this->getUserIdByOpenid($openid),
            'borrower_openid' => $openid,
            'borrow_time' => time(),
            'return_status' => 0,
            'return_time' => 0,
            'remark' => $remark,
        );
        $this->capsule->getConnection()->beginTransaction();

        $r1 = $this->capsule->table('book_borrow')->insert($kv);
        $r2 = $this->capsule->table('book_share')->where('id', $book_share_id)->update(['lend_status' => 2]);
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

    public function returnBook($openid, $book_share_id, $remark)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        if (!$book_share_id) {
            return [
                'status' => 10000,
                'message' => '参数不全',
            ];
        }

        $book_borrow = $this->capsule->table('book_borrow')
            ->where('book_share_id', $book_share_id)
            ->where('return_status', 0)
            ->orderBy('id', 'desc')
            ->first();
        $book_borrow = $book_borrow ?: [];
        if (empty($book_borrow['id'])) {
            return [
                'status' => 99999,
                'message' => '找不到该借阅记录',
            ];
        }

        $kv = array(
            'return_status' => 1,
            'return_time' => time(),
            'remark' => $remark,
        );
        $this->capsule->getConnection()->beginTransaction();

        $r1 = $this->capsule->table('book_borrow')->where('id', $book_borrow['id'])->update($kv);
        $r2 = $this->capsule->table('book_share')->where('id', $book_share_id)->where('owner_openid', $openid)
            ->update(['lend_status' => 1]);

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

}
