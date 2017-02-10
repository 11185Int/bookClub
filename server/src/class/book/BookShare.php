<?php
/**
 * Created by PhpStorm.
 * User: Lau
 * Date: 2017/2/10
 * Time: 9:32
 */

namespace CP\book;

use CP\common\AbstractModel;
use CP\common\AccountSessionKey;

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

    public function findBookShareById($book_share_id)
    {
        $book_share = $this->fetch('book_share', "id = {$book_share_id}");
        return $book_share;
    }

    /**
     * @param $openid
     * @param $isbn
     * @param $remark
     * @return array
     */
    public function share($openid, $isbn, $remark)
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

        $kv = array(
            'book_id' => $book['id'],
            'owner_id' => $this->getUserIdByOpenid($openid),
            'owner_openid' => $openid,
            'share_status' => 1,
            'lend_status' => 1,
            'share_time' => time(),
            'remark' => $remark,
        );

        $this->insert('book_share', $kv);

        return $res;
    }

}
