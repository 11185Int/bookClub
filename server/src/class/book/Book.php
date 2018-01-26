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
        $offset = ($page - 1) * $pagesize;

        $builder = $this->capsule->table('book AS b')
            ->leftJoin('book_share AS s', 's.book_id', '=', 'b.id')
            ->select('b.id','b.isbn10','b.isbn13','b.title','b.image','s.share_status','s.lend_status')
            ->selectRaw('count('.$this->capsule->getConnection()->getTablePrefix().'s.id) AS book_share_sum')
            ->where('s.share_status', 1)
            ->where('s.lend_status', 1);
        if ($name) {
            $builder->where(function ($q) use ($name) {
                $q->where('b.title', 'like', "%{$name}%")
                    ->orWhere('b.author', 'like', "%{$name}%")
                    ->orWhere('b.publisher', 'like', "%{$name}%")
                    ->orWhere('b.tags', 'like', "%{$name}%");
            });
        }
        $builder->groupBy('b.id')
            ->orderBy('s.id', 'desc')
            ->limit($pagesize)->offset($offset);
        $res['data']['list'] = $builder->get();
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
        $builder = $this->capsule->table('book_share AS share')
            ->select('share.id AS book_share_id','user.nickname','user.headimgurl',
                'share.share_status','share.lend_status','share.share_time')
            ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
            ->join('user AS user', 'user.openid', '=', 'share.owner_openid', 'inner')
            ->where('share.share_status', 1)
            ->where('share.lend_status', 1)
            ->where(function ($q) use ($isbn) {
                $q->where('book.isbn10', $isbn)->orWhere('isbn13', $isbn);
            })
            ->groupBy('user.id')
            ->orderBy('share.share_time', 'desc');

        if ($builder) {
            $res['data']['list'] = $builder->get();
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

        $builder = $this->capsule->table('book_borrow AS borrow')
            ->select('share.id AS book_share_id','user.nickname','user.headimgurl')
            ->join('book_share AS share', 'share.id', '=', 'borrow.book_share_id', 'inner')
            ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
            ->join('user AS user', 'user.openid', '=', 'borrow.borrower_openid', 'inner')
            ->where(function ($q) use ($isbn) {
                $q->where('book.isbn10', $isbn)->orWhere('isbn13', $isbn);
            })->where('share.owner_openid', $openid)
            ->where('borrow.return_status', 0)
            ->groupBy('user.id')
            ->orderBy('share.share_time', 'desc');

        if ($builder) {
            $res['data']['list'] = $builder->get();
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
        $book = $this->capsule->table('book')
            ->where('isbn10', $isbn)->orWhere('isbn13', $isbn)
            ->first();
        return $book ? $book->toArray() : [];
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
            'title' => $book['title'],
            'author' => implode(',', $book['author']),
            'rating' => $book['rating']['average'],
            'publisher' => $book['publisher'],
            'price' => $book['price'],
            'image' => $image,
            'tags' => $tags,
            'pubdate' => $book['pubdate'],
            'summary' => $book['summary'],
        ];
        $isbn = $book['isbn13'] ?: $book['isbn10'];
        if ($isbn) {
            $this->capsule->table('book')->insert($kv);
        }
        return true;
    }

}