<?php

/**
 * Created by PhpStorm.
 * User: Du
 * Date: 2017/2/7
 * Time: 8:43
 */
namespace CP\book;

use CP\api\Douban;
use CP\common\AbstractModel;

class Book extends AbstractModel
{

    public function getList($params)
    {
        $res = array(
            'status' => 1,
            'message' => 'success',
        );
        $name = isset($params['name']) ? $params['name'] : '';
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $pagesize = isset($params['pagesize']) ? intval($params['pagesize']) : 100;

        $andWhere = '';
        if ($name) {
            $andWhere .= " and (b.title LIKE '%{$name}%' or b.author LIKE '%{$name}%' or b.publisher LIKE '%{$name}%' or b.tags LIKE '%{$name}%') ";
        }
        $offset = ($page - 1) * $pagesize;
        $this->db->sql("select 
            b.id,b.isbn10,b.isbn13,b.title,b.image, s.share_status, s.lend_status,
            count(s.id) AS book_share_sum
            from tb_book b
            left join tb_book_share s on s.book_id = b.id
            where s.share_status = 1 and s.lend_status = 1
            {$andWhere}
            group by b.id
            order by s.id desc 
            limit {$pagesize} offset {$offset}");
        $res['data']['list'] = $this->db->getResult();
        return $res;
    }

    public function getBookByISBN($isbn)
    {
        $book = $this->findBook($isbn);

        if (!(strlen($isbn) == 10 || strlen($isbn) == 13)) {
            return [
                'status' => 6000,
                'message' => 'isbn码错误',
            ];
        }

        if (empty($book)) {
            $api = new Douban();
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
            INNER JOIN tb_user AS `user` ON `user`.openid = borrow.borrower_openid
            WHERE (book.isbn10 = '{$isbn}' OR book.isbn13 = '{$isbn}')
                AND `share`.owner_openid = '{$openid}' AND borrow.return_status = 0
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

    public function findBook($isbn)
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
        $image = empty($book['images']['large']) ? $book['image'] : $book['images']['large'];
        $kv = [
            'isbn10' => $book['isbn10'],
            'isbn13' => $book['isbn13'],
            'category_id' => 1,
            'title' => mb_strimwidth($book['title'], 0, 200, '...'),
            'author' => mb_strimwidth(implode(',', $book['author']), 0, 200, '...'),
            'rating' => $book['rating']['average'],
            'publisher' => mb_strimwidth($book['publisher'], 0, 200, '...'),
            'price' => $book['price'],
            'image' => $image,
            'tags' => mb_strimwidth($tags, 0, 180, '...'),
            'pubdate' => $book['pubdate'],
            'summary' => mb_strimwidth($book['summary'], 0, 1000, '...'),
        ];
        $isbn = $book['isbn13'] ?: $book['isbn10'];
        if ($isbn) {
            $this->insert('book', $kv);
        }
        return true;
    }

}