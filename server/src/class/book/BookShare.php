<?php
/**
 * Created by PhpStorm.
 * User: Lau
 * Date: 2017/2/10
 * Time: 9:32
 */

namespace CP\book;

use CP\common\AbstractModel;

class BookShare extends AbstractModel
{

    public function getMyBookShare($openid)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        $select = $this->db->sql(
            "SELECT 
            `share`.book_id, book.isbn10, book.isbn13, book.title, book.image,
            `share`.share_status, `share`.lend_status, `share`.share_time
            FROM tb_book_share AS `share`
            INNER JOIN tb_book AS book ON book.id = `share`.book_id
            WHERE `share`.owner_openid = '{$openid}'
            ORDER BY `share`.share_time DESC"
        );
        if ($select) {
            $res['data']['share'] = $this->db->getResult();
        } else {
            $res = array(
                'status' => 1001,
                'message' => '获取数据失败',
            );
        }

        return $res;
    }

}
