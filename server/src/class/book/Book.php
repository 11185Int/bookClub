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
use Slim\Http\UploadedFile;

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
                'status' => 10004,
                'message' => '找不到图书(Douban)',
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
        return $book ?: [];
    }

    public function submit($form, $openid, $config)
    {
        $res = array(
            'status' => 0,
            'message' => '成功',
        );
        $image = $form['image'];

        $message = '';
        if (empty($form['isbn'])) {
            $message = '缺少isbn';
        }
        if (empty($form['title']) || empty($form['author'])) {
            $message = '缺少参数';
        }
        if ($message) {
            return [
                'status' => 99999,
                'message' => $message,
            ];
        }
        if (!$image || !in_array($image->getClientMediaType(),
                ['image/png','image/jpeg','image/jpg','image/gif','image/bmp','image/tiff','image/svg+xml'])) {
            return [
                'status' => 99999,
                'message' => '图片不存在或格式错误',
            ];
        }

        if ($image->getSize() > 2 * 1024 * 1024) {
            return [
                'status' => 99999,
                'message' => '图片超过2M',
            ];
        }

        $isbn = $form['isbn'];
        $book = $this->findBook($isbn);
        if (!empty($book)) {
            return [
                'status' => 99999,
                'message' => '此书已存在',
            ];
        }
        $imageUrl = '';
        if ($image->getError() === UPLOAD_ERR_OK) {
            $directory = __DIR__. '/../../../public/resources/book/image/';
            $filename = $this->moveUploadedFile($directory, $image);
            $domain = $config['domain'];
            $imageUrl = $domain . 'resources/book/image/'. $filename;
        }

        $tags = empty($form['tags']) ? [] : explode(',', $form['tags']);
        $author = empty($form['author']) ? [] : explode(',', $form['author']);

        $book = [];
        $book['isbn10'] = strlen($isbn) == 10? $isbn : '';
        $book['isbn13'] = strlen($isbn) == 13? $isbn : '';
        $book['category_id'] = 1;
        $book['title'] = $form['title'];
        $book['author'] = $author;
        $book['rating'] = '';
        $book['publisher'] = empty($form['publisher']) ? '' : $form['publisher'];
        $book['price'] = empty($form['price']) ? '' : $form['price'];
        $book['image'] = $imageUrl;
        $book['tags'] = $tags;
        $book['pubdate'] = empty($form['pubdate']) ? '' : $form['pubdate'];
        $book['summary'] = empty($form['summary']) ? '' : $form['summary'];
        $book['rating']['average'] = 4.0;
        $book['ismanual'] = 1;
        $book['openid'] = $openid;

        $flag = $this->saveBook($book);
        if (!$flag) {
            return [
                'status' => 99999,
                'message' => '添加失败',
            ];
        }
        $res['book'] = $book;
        return $res;
    }

    /**
     * @param $image UploadedFile
     * @return array
     */
    public function saveImage($image, $config)
    {
        $res = array(
            'status' => 0,
            'message' => '',
            'image' => '',
        );

        if (!$image || !in_array($image->getClientMediaType(),
            ['image/png','image/jpeg','image/gif','image/bmp','image/tiff','image/svg+xml'])) {
            return [
                'status' => 99999,
                'message' => '图片不存在或格式错误',
            ];
        }

        if ($image->getSize() > 2 * 1024 * 1024) {
            return [
                'status' => 99999,
                'message' => '图片超过2M',
            ];
        }

        if ($image->getError() === UPLOAD_ERR_OK) {
            $directory = __DIR__. '/../../../public/resources/book/image/';
            $filename = $this->moveUploadedFile($directory, $image);
            $domain = $config['domain'];
            $res['image'] = $domain . 'resources/book/image/'. $filename;
        }

        return $res;
    }

    protected function moveUploadedFile($directory, UploadedFile $uploadedFile)
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }

    protected function saveBook($book)
    {
        if (empty($book)) {
            return false;
        }
        $tagArr = [];
        foreach ($book['tags'] as $tag) {
            $tagArr[] = $tag;
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
            'ismanual' => empty($book['ismanual']) ? '' : $book['ismanual'],
            'openid' => empty($book['openid']) ? '' : $book['openid'],
        ];
        $isbn = $book['isbn13'] ?: $book['isbn10'];
        if (!$isbn) {
            return false;
        }
        $this->capsule->table('book')->insert($kv);
        return true;
    }

    public function clear()
    {
        $res = array(
            'status' => 0,
            'message' => '成功',
        );
        $bookids = $this->capsule->table('book')->where('ismanual', 1)->select('id')->get();
        $bookids = array_column($bookids, 'id');
        $this->capsule->table('book')->whereIn('id', $bookids)->delete();
        $this->capsule->table('book_share')->whereIn('book_id', $bookids)->delete();
        return $res;
    }

}