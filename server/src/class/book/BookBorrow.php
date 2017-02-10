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

        $select = $this->db->sql(
            "SELECT 
            `share`.book_id, book.isbn10, book.isbn13, book.title, book.image,
            `share`.owner_openid, borrow.borrow_time
            FROM tb_book_borrow AS borrow
            INNER JOIN tb_book_share AS `share` ON `share`.id = borrow.book_share_id
            INNER JOIN tb_book AS book ON book.id = `share`.book_id
            WHERE borrow.borrower_openid = '{$openid}' AND borrow.return_status = 0
            ORDER BY borrow.borrow_time DESC"
        );
        if ($select) {
            $res['data']['borrow'] = $this->db->getResult();
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

        $kv = array(
            'book_share_id' => $book_share_id,
            'borrower_id' => $this->getUserIdByOpenid($openid),
            'borrower_openid' => $openid,
            'borrow_time' => time(),
            'return_status' => 0,
            'return_time' => 0,
            'remark' => $remark,
        );
        $this->db->beginTransaction();

        $r1 = $this->insert('book_borrow', $kv);
        $r2 = $this->update('book_share', ['lend_status' => 2], "id = {$book_share_id}");
        if ($r1 && $r2) {
            $this->db->commit();
        } else {
            $this->db->rollback();
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

        $kv = array(
            'return_status' => 1,
            'return_time' => time(),
            'remark' => $remark,
        );
        $this->db->beginTransaction();

        $r1 = $this->update('book_borrow', $kv, "book_share_id = {$book_share_id} AND borrower_openid = '{$openid}'");
        $r2 = $this->update('book_share', ['lend_status' => 1], "id = {$book_share_id}");

        if ($r1 && $r2) {
            $this->db->commit();
        } else {
            $this->db->rollback();
            return [
                'status' => 10002,
                'message' => '借阅失败',
            ];
        }

        return $res;
    }

}
