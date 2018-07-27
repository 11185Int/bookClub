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
use CP\common\Isbn;
use Slim\Http\UploadedFile;

class Book extends AbstractModel
{

    public function getList($openid, $groupIds, $params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $name = isset($params['name']) ? $params['name'] : '';
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $pagesize = isset($params['pagesize']) ? intval($params['pagesize']) : 100;
        $offset = ($page - 1) * $pagesize;

        $builder = $this->capsule->table('book AS b')
            ->leftJoin('book_share AS s', 's.book_id', '=', 'b.id')
            ->leftJoin('book_share AS ss', 'ss.book_id', '=', 'b.id')
            ->leftJoin('book_borrow AS bb', function($join) {
                $join->on('bb.book_share_id', '=', 'ss.id');
                $join->where('bb.return_status', '=', 0);
            })
            ->select('b.id','b.isbn10','b.isbn13','b.title','b.image','s.share_status','s.lend_status')
            ->selectRaw('max('.$this->capsule->getConnection()->getTablePrefix().'s.id) AS sid')
            ->selectRaw('count(distinct '.$this->capsule->getConnection()->getTablePrefix().'s.id) AS book_share_sum')
            ->selectRaw('count(distinct '.$this->capsule->getConnection()->getTablePrefix().'bb.id) AS book_borrow_sum')
            ->where('s.share_status', 1)
            ->where(function ($q) use ($openid, $groupIds) {
                $q->whereIn('s.group_id', $groupIds)
                    ->orWhere(function ($q) use ($openid) {
                        $q->where('s.group_id', 0)->where('s.owner_openid', $openid);
                    });
            })
            ->where(function ($q) use ($openid, $groupIds) {
                $q->whereIn('ss.group_id', $groupIds)
                    ->orWhere(function ($q) use ($openid) {
                        $q->where('ss.group_id', 0)->where('ss.owner_openid', $openid);
                    });
            })
            ->groupBy('b.id');
        if ($name) {
            $builder->where(function ($q) use ($name) {
                $q->where('b.title', 'like', "%{$name}%")

                    ->orWhere('b.author', 'like', "%{$name}%")
                    ->orWhere('b.publisher', 'like', "%{$name}%")
                    ->orWhere('b.tags', 'like', "%{$name}%");
            });
        }
        $totalCount = count($builder->get());
        $builder->orderBy('sid', 'desc')
            ->limit($pagesize)->offset($offset);
        $data = $builder->get();
        if (!empty($data)) {
            $newData = [];
            foreach ($data as $item) {
                $item['canBorrow'] = $item['book_share_sum'] > $item['book_borrow_sum'] ? 1 : 0;
                $newData[] = $item;
            }
            $data = $newData;
        }
        $res['data'] = [
            'list' => $data,
            'total' => intval($totalCount),
            'pagesize' => $pagesize,
            'totalpage' => ceil($totalCount / $pagesize),
        ];
        return $res;
    }

    public function getListByGroup($openid, $groupId, $params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $user_group = $this->capsule->table('user_group')
            ->where('group_id', $groupId)
            ->where('openid', $openid)
            ->first();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '无权限操作，参数错误',
            ];
        }
        $name = isset($params['name']) ? $params['name'] : '';
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $pagesize = isset($params['pagesize']) ? intval($params['pagesize']) : 100;
        $offset = ($page - 1) * $pagesize;

        $builder = $this->capsule->table('book AS b')
            ->leftJoin('book_share AS s', 's.book_id', '=', 'b.id')
            ->leftJoin('book_share AS ss', 'ss.book_id', '=', 'b.id')
            ->leftJoin('book_borrow AS bb', function($join) {
                $join->on('bb.book_share_id', '=', 'ss.id');
                $join->where('bb.return_status', '=', 0);
            })
            ->select('b.id','b.isbn10','b.isbn13','b.title','b.image','s.share_status','s.lend_status')
            ->selectRaw('max('.$this->capsule->getConnection()->getTablePrefix().'s.id) AS sid')
            ->selectRaw('count(distinct '.$this->capsule->getConnection()->getTablePrefix().'s.id) AS book_share_sum')
            ->selectRaw('count(distinct '.$this->capsule->getConnection()->getTablePrefix().'bb.id) AS book_borrow_sum')
            ->where('s.share_status', 1)
            ->where('s.group_id', $groupId)
            ->where('ss.group_id', $groupId)
            ->groupBy('b.id');
        if ($name) {
            $builder->where(function ($q) use ($name) {
                $q->where('b.title', 'like', "%{$name}%")

                    ->orWhere('b.author', 'like', "%{$name}%")
                    ->orWhere('b.publisher', 'like', "%{$name}%")
                    ->orWhere('b.tags', 'like', "%{$name}%");
            });
        }
        $totalCount = count($builder->get());
        $builder->orderBy('sid', 'desc')
            ->limit($pagesize)->offset($offset);
        $data = $builder->get();
        if (!empty($data)) {
            $newData = [];
            foreach ($data as $item) {
                $item['canBorrow'] = $item['book_share_sum'] > $item['book_borrow_sum'] ? 1 : 0;
                $newData[] = $item;
            }
            $data = $newData;
        }
        $res['data'] = [
            'list' => $data,
            'total' => intval($totalCount),
            'pagesize' => $pagesize,
            'totalpage' => ceil($totalCount / $pagesize),
        ];
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

    public function getShareList($groupId, $isbn)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        if (!$isbn) {
            return [
                'status' => 1001,
                'message' => 'isbn不能为空',
            ];
        }
        $builder = $this->capsule->table('book_share AS share')
            ->select('share.id AS book_share_id','user.nickname','user.headimgurl','user.realname',
                'share.share_status','share.lend_status','share.share_time','share.remark')
            ->selectRaw('count('.$this->capsule->getConnection()->getTablePrefix().'share.id) AS amount')
            ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
            ->join('user AS user', 'user.openid', '=', 'share.owner_openid', 'inner')
            ->where('share.share_status', 1)
            ->where('share.lend_status', 1)
            ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
            ->where('share.group_id', $groupId)
            ->groupBy('user.id')
            ->orderBy('share.share_time', 'desc');

        $lent_builder = $this->capsule->table('book_share AS share')
            ->select('share.id AS book_share_id','user.nickname','user.headimgurl','user.realname',
                'share.share_status','share.lend_status','share.share_time','share.remark')
            ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
            ->join('user AS user', 'user.openid', '=', 'share.owner_openid', 'inner')
            ->where('share.share_status', 1)
            ->where('share.lend_status', 2)
            ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
            ->where('share.group_id', $groupId)
            ->groupBy('user.id')
            ->orderBy('share.share_time', 'desc');

        if ($builder) {
            $res['data']['list'] = $this->replaceRealName($builder->get());
            $res['data']['lent_list'] = $this->replaceRealName($lent_builder->get());
        } else {
            $res = array(
                'status' => 1001,
                'message' => '获取数据失败',
            );
        }

        return $res;
    }

    public function getReturnList($groupId, $openid, $isbn)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        if (!$isbn) {
            return [
                'status' => 1001,
                'message' => 'isbn不能为空',
            ];
        }
        $builder = $this->capsule->table('book_borrow AS borrow')
            ->select('share.id AS book_share_id','user.nickname','user.headimgurl','user.realname')
            ->join('book_share AS share', 'share.id', '=', 'borrow.book_share_id', 'inner')
            ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
            ->join('user AS user', 'user.openid', '=', 'borrow.borrower_openid', 'inner')
            ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
            ->where('share.owner_openid', $openid)
            ->where('borrow.return_status', 0)
            ->where('share.group_id', $groupId)
            ->groupBy('user.id')
            ->orderBy('share.share_time', 'desc');

        if ($builder) {
            $res['data']['list'] = $this->replaceRealName($builder->get());
        } else {
            $res = array(
                'status' => 1001,
                'message' => '获取数据失败',
            );
        }

        return $res;
    }

    public function getMyReturnList($groupId, $openid, $isbn)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        if (!$isbn) {
            return [
                'status' => 1001,
                'message' => 'isbn不能为空',
            ];
        }
        $builder = $this->capsule->table('book_borrow AS borrow')
            ->select('share.id AS book_share_id','user.nickname','user.headimgurl','user.realname')
            ->join('book_share AS share', 'share.id', '=', 'borrow.book_share_id', 'inner')
            ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
            ->join('user AS user', 'user.openid', '=', 'share.owner_openid', 'inner')
            ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
            ->where('borrow.borrower_openid', $openid)
            ->where('borrow.return_status', 0)
            ->where('share.group_id', $groupId)
            ->groupBy('user.id')
            ->orderBy('share.share_time', 'desc');

        if ($builder) {
            $res['data']['list'] = $this->replaceRealName($builder->get());
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
            ->where(strlen($isbn) == 10 ?'isbn10': 'isbn13', $isbn)
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
        if (empty($form['isbn']) || !Isbn::validate($form['isbn'])) {
            $message = 'isbn错误';
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

        $tagsArray = empty($form['tags']) ? [] : explode(',', $form['tags']);
        $author = empty($form['author']) ? [] : explode(',', $form['author']);
        $tags = [];
        if (!empty($tagsArray)) {
            foreach ($tagsArray as $item) {
                $tags[]['title'] = $item;
            }
        }

        $book = [];
        $book['isbn10'] = strlen($isbn) == 10? $isbn : Isbn::to10($isbn);
        $book['isbn13'] = strlen($isbn) == 13? $isbn : Isbn::to13($isbn);
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

    public function getBorrowHistory($groupId, $isbn)
    {
        $res = array(
            'status' => 0,
            'message' => '成功',
        );
        if (!$isbn) {
            return [
                'status' => 10000,
                'message' => '参数不全',
            ];
        }

        $data = $this->capsule->table('book')
            ->select('share.owner_openid','sharer.nickname AS sharer_nickname',
                'sharer.headimgurl AS sharer_headimgurl','share.group_id','borrow.book_share_id','borrow.borrower_openid',
                'borrower.nickname AS borrower_nickname','borrower.headimgurl AS borrower_headimgurl',
                'borrow.borrow_time', 'borrow.return_status', 'borrow.return_time')
            ->leftJoin('book_share AS share', 'share.book_id', '=', 'book.id')
            ->rightJoin('book_borrow AS borrow', 'borrow.book_share_id', '=', 'share.id')
            ->leftJoin('user AS sharer', 'sharer.openid', '=', 'share.owner_openid')
            ->leftJoin('user AS borrower', 'borrower.openid', '=', 'borrow.borrower_openid')
            ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
            ->where('share.group_id', $groupId)
            ->orderBy('borrow.borrow_time', 'desc')
            ->get();

        $list = [];
        foreach ($data as $datum) {
            $share_id = $datum['book_share_id'];
            $list[$share_id]['book_share_id'] = $share_id;
            $list[$share_id]['history'][] = [
                'sharer_nickname' => $datum['sharer_nickname'],
                'sharer_headimgurl' => $datum['sharer_headimgurl'],
                'borrower_nickname' => $datum['borrower_nickname'],
                'borrower_headimgurl' => $datum['borrower_headimgurl'],
                'borrow_time' => date('Y-m-d', $datum['borrow_time']),
                'return_time' => $datum['return_time'] ? date('Y-m-d', $datum['return_time']) : '',
                'return status' => $datum['return_status'],
                'borrow_during' => $datum['return_status'] ?
                    $this->calIntervalDays($datum['borrow_time'], $datum['return_time']).'天':'',
            ];
        }
        $res['data']['list'] = array_values($list);

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

    public function getSearchList($q, $page, $pagesize)
    {
        if (!$q) {
            return [
                'status' => 1001,
                'message' => '关键字不能为空',
            ];
        }
        $api = new Douban();
        $list = $api->searchBook($q, $page, $pagesize);
        return [
            'status' => 0,
            'message' => '成功',
            'data' => [
                'list' => $this->filterSearchBooks($list['books']),
                'total' => $list['total'],
                'pagesize' => $pagesize,
                'totalpage' => ceil($list['total'] / $pagesize),
            ]
        ];
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
            $tagArr[] = $tag['title'];
        }
        $tags = implode(',', $tagArr);
        $image = empty($book['images']['large']) ? $book['image'] : $book['images']['large'];
        $kv = [
            'isbn10' => isset($book['isbn10']) ? $book['isbn10'] : '',
            'isbn13' => isset($book['isbn13']) ? $book['isbn13'] : '',
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
            'ismanual' => empty($book['ismanual']) ? 0 : $book['ismanual'],
            'openid' => empty($book['openid']) ? '' : $book['openid'],
            'hd_image' => $this->getHdImage($image),
        ];
        $isbn = $book['isbn13'] ?: $book['isbn10'];
        if (!$isbn) {
            return false;
        }
        $this->capsule->table('book')->insert($kv);
         return true;
    }


    /**
     * @param $begin int
     * @param $end int
     * @return int
     */
    protected function calIntervalDays($begin, $end)
    {
        if (!$begin || !$end) {
            return 0;
        }
        $b = new \DateTime(date('Y-m-d',$begin));
        $e = new \DateTime(date('Y-m-d',$end));
        $interval = $b->diff($e);
        return $interval->days + 1;
    }

    protected function filterSearchBooks($books)
    {
        $retBooks = [];
        foreach ($books as $book) {
//            $tagArr = [];
//            foreach ($book['tags'] as $tag) {
//                $tagArr[] = $tag['title'];
//            }
//            $tags = implode(',', $tagArr);
            $image = empty($book['images']['large']) ? $book['image'] : $book['images']['large'];
            $b = [
                'isbn10' => isset($book['isbn10']) ? $book['isbn10'] : '',
                'isbn13' => isset($book['isbn13']) ? $book['isbn13'] : '',
                'category_id' => 1,
                'title' => mb_strimwidth($book['title'], 0, 200, '...'),
                'author' => mb_strimwidth(implode(',', $book['author']), 0, 200, '...'),
//                'rating' => $book['rating']['average'],
//                'publisher' => mb_strimwidth($book['publisher'], 0, 200, '...'),
//                'price' => $book['price'],
                'image' => $image,
//                'tags' => mb_strimwidth($tags, 0, 180, '...'),
//                'pubdate' => $book['pubdate'],
//                'summary' => mb_strimwidth($book['summary'], 0, 1000, '...'),
//                'ismanual' => 0,
            ];
            $retBooks[] = $b;
        }
        return $retBooks;
    }

    protected function getHdImage($url)
    {
        $url = str_replace('/lpic/', '/view/subject/l/public/', $url);
        $url = str_replace('/mpic/', '/view/subject/l/public/', $url);
        $url = str_replace('/spic/', '/view/subject/l/public/', $url);
        return $url;
    }

}