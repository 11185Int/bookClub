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

}
