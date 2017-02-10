<?php

/**
 * Created by PhpStorm.
 * User: Du
 * Date: 2017/2/7
 * Time: 8:43
 */
namespace CP\book;

use CP\Api\Douban;
use CP\common\AbstractModel;

class Book extends AbstractModel
{

    public function getList()
    {
        $res = array(
            'status' => 1,
            'message' => 'success',
        );
        $this->db->sql('SELECT b.id,b.isbn10,b.isbn13,b.title,b.image,IF(sum(IF(s.share_status = 1,1,0)) > 0,1,0) as share_status,IF(SUM(IF(s.lend_status = 1,1,0)) > 0,1,0) as lend_status
                        FROM tb_book b LEFT JOIN tb_book_share s ON b.id = s.book_id GROUP BY b.isbn10');
        $res['data ']['list'] = $this->db->getResult();
        return $res;
    }

    public function getBookByISBN($isbn)
    {
        $api = new Douban();

        $book = $this->findBook($isbn);
        if (empty($book)) {
            $bookDetail = $api->getBook($isbn);
            $this->saveBook($bookDetail);
            $book = $this->findBook($isbn);
        }

        if (empty($book)) {
            return [
                'status' => 6000,
                'message' => '找不到图书',
            ];
        }

        return [
            'status' => 0,
            'message' => '成功',
            'data' => [
                'book' => $book
            ]
        ];
    }

    public function getShareList($isbn)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        $select = $this->db->sql(
            "SELECT 
            `share`.id AS book_share_id, `user`.nickname, `user`.headimgurl,
            `share`.share_status, `share`.lend_status, `share`.share_time
            FROM tb_book_share AS `share`
            INNER JOIN tb_book AS book ON book.id = `share`.book_id
            INNER JOIN tb_user AS user ON user.openid = `share`.owner_openid
            WHERE (book.isbn10 = '{$isbn}' OR book.isbn13 = '{$isbn}')
                AND `share`.share_status = 1 AND `share`.lend_status = 1
            GROUP BY user.id
            ORDER BY `share`.share_time DESC"
        );
        if ($select) {
            $res['data']['list'] = $this->db->getResult();
        } else {
            $res = array(
                'status' => 1001,
                'message' => '获取数据失败',
            );
        }

        return $res;
    }

    public function getReturnList($openid, $isbn)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        $select = $this->db->sql(
            "SELECT
            `share`.id AS book_share_id, `user`.nickname, `user`.headimgurl
            FROM tb_book_borrow AS borrow
            INNER JOIN tb_book_share AS `share` ON `share`.id = borrow.book_share_id
            INNER JOIN tb_book AS book ON book.id = `share`.book_id 
            INNER JOIN tb_user AS `user` ON `user`.openid = `share`.owner_openid
            WHERE (book.isbn10 = '{$isbn}' OR book.isbn13 = '{$isbn}')
                AND borrow.borrower_openid = '{$openid}' AND borrow.return_status = 0
            GROUP BY `user`.id
            ORDER BY `share`.share_time DESC"
        );
        if ($select) {
            $res['data']['list'] = $this->db->getResult();
        } else {
            $res = array(
                'status' => 1001,
                'message' => '获取数据失败',
            );
        }

        return $res;
    }

    protected function findBook($isbn)
    {
        $where = " isbn10 = '{$isbn}' OR isbn13 = '{$isbn}' ";
        return $this->fetch('book', $where);
    }

    protected function saveBook($book)
    {
        if (empty($book)) {
            return false;
        }
        $tagArr = [];
        foreach ($book['tags'] as $tag) {
            $tagArr[] = $tag['title'];
        }
        $tags = implode(',', $tagArr);
        $kv = [
            'isbn10' => $book['isbn10'],
            'isbn13' => $book['isbn13'],
            'category_id' => 1,
            'title' => $book['title'],
            'author' => implode(',', $book['author']),
            'rating' => $book['rating']['average'],
            'publisher' => $book['publisher'],
            'price' => $book['price'],
            'image' => $book['image'],
            'tags' => $tags,
            'pubdate' => $book['pubdate'],
            'summary' => $book['summary'],
        ];
        $this->insert('book', $kv);
        return true;
    }

}