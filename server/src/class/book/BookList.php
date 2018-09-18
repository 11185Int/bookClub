<?php

namespace CP\book;

use CP\common\AbstractModel;
use CP\user\User;

class BookList extends AbstractModel
{

    public function getList($openid, $params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $prefix = $this->capsule->getConnection()->getTablePrefix();
        $myBuilder = $this->capsule->table('book_list AS l')
            ->select('l.id','l.name','l.description','l.can_subscribe','l.list_type','l.book_amount','l.subscribe_amount','l.update_time')
            ->where('l.creator_openid', $openid)
            ->where('l.enable', 1)
            ->orderByRaw($prefix.'l.list_type = \'favourite\' desc, '.$prefix.'l.id desc');
        $myList = $myBuilder->get();

        $subBuilder = $this->capsule->table('book_list AS l')
            ->leftJoin('book_list_subscribe AS sub', 'sub.booklist_id', '=', 'l.id')
            ->select('l.id','l.name','l.description','l.can_subscribe','l.list_type','l.book_amount','l.subscribe_amount','l.update_time')
            ->where('sub.openid', $openid)
            ->where('l.can_subscribe', 1)
            ->where('l.enable', 1)
            ->orderBy('sub.id', 'desc');
        $subList = $subBuilder->get();

        $listIds = array_merge(array_column($myList, 'id'), array_column($subList, 'id'));

        $bookBuilder = $this->capsule->table('book AS b')
            ->leftJoin('book_list_rel AS rel', 'rel.book_id', '=', 'b.id')
            ->leftJoin('book_list AS l', 'l.id', '=', 'rel.book_list_id')
            ->select('b.title', 'b.image', 'rel.book_list_id')
            ->whereRaw('3 > (select count(*) from '.$prefix.'book
                    left join '.$prefix.'book_list_rel on '.$prefix.'book_list_rel.book_id = '.$prefix.'book.id
                    left join '.$prefix.'book_list on '.$prefix.'book_list.id = '.$prefix.'book_list_rel.book_list_id
                    where '.$prefix.'book_list.id = '.$prefix.'l.id
                    and '.$prefix.'book.id > '.$prefix.'b.id )')
            ->whereIn('l.id', $listIds)
            ->groupBy(['l.id', 'b.id']);
        $books = $bookBuilder->get();

        $booksInList = [];
        foreach ($books as $book) {
            $booksInList[$book['book_list_id']][] = $book;
        }
        foreach ($myList as $key => $item) {
            $myList[$key]['update_time'] = date('Y年m月d日', $item['update_time']);
            if (isset($booksInList[$item['id']])) {
                $myList[$key]['books'] = $booksInList[$item['id']];
            } else {
                $myList[$key]['books'] = [];
            }
        }
        foreach ($subList as $key => $item) {
            $subList[$key]['update_time'] = date('Y年m月d日', $item['update_time']);
            if (isset($booksInList[$item['id']])) {
                $subList[$key]['books'] = $booksInList[$item['id']];
            } else {
                $subList[$key]['books'] = [];
            }
        }

        $data = [
            'my_cnt' => count($myList),
            'subscribe_cnt' => count($subList),
            'my_list' => $myList,
            'subscribe_list' => $subList,
        ];
        $res['data'] = $data;
        return $res;
    }

    public function getMyList($openid, $isbn = '')
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $book_list = $this->capsule->table('book_list')->where('creator_openid', $openid)
            ->where('enable', 1)
            ->select('id','name','list_type')
            ->selectRaw('0 AS in_list')
            ->orderByRaw('list_type = \'favourite\' desc, id desc')
            ->get();
        if ($isbn) {
            $bookModel = new Book();
            $book = $bookModel->findBook($isbn);
            if (isset($book['id'])) {
                $book_id = $book['id'];
                $in_list = $this->capsule->table('book_list_rel AS rel')
                    ->leftJoin('book_list AS l', 'l.id', '=', 'rel.book_list_id')
                    ->select('l.id')
                    ->where('l.creator_openid', $openid)
                    ->where('rel.book_id', $book_id)
                    ->get();
                if (!empty($in_list)) {
                    $in_list_id_arr = array_column($in_list, 'id');
                    foreach ($book_list as $key => $item) {
                        if (in_array($item['id'], $in_list_id_arr)) {
                            $book_list[$key]['in_list'] = 1;
                        }
                    }
                }
            }
        }
        $prefix = $this->capsule->getConnection()->getTablePrefix();
        $bookBuilder = $this->capsule->table('book AS b')
            ->leftJoin('book_list_rel AS rel', 'rel.book_id', '=', 'b.id')
            ->leftJoin('book_list AS l', 'l.id', '=', 'rel.book_list_id')
            ->select('b.title', 'b.image', 'rel.book_list_id')
            ->whereRaw('1 > (select count(*) from '.$prefix.'book
                    left join '.$prefix.'book_list_rel on '.$prefix.'book_list_rel.book_id = '.$prefix.'book.id
                    left join '.$prefix.'book_list on '.$prefix.'book_list.id = '.$prefix.'book_list_rel.book_list_id
                    where '.$prefix.'book_list.id = '.$prefix.'l.id
                    and '.$prefix.'book.id > '.$prefix.'b.id )')
            ->where('l.creator_openid', $openid)
            ->where('l.enable', 1)
            ->groupBy(['l.id', 'b.id']);
        $books = $bookBuilder->get();

        $firstBookInList = [];
        foreach ($books as $book) {
            $firstBookInList[$book['book_list_id']] = $book;
        }
        foreach ($book_list as $key => $item) {
            if (isset($firstBookInList[$item['id']])) {
                $book_list[$key]['first_book'] = $firstBookInList[$item['id']];
            } else {
                $book_list[$key]['first_book'] = null;
            }
        }

        $res['data'] = [
            'my_list' => $book_list,
        ];
        return $res;
    }

    public function create($openid, $params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $name = isset($params['name']) ? $params['name'] : null;
        $description = isset($params['description']) ? $params['description'] : null;
        $can_subscribe = isset($params['can_subscribe']) ? $params['can_subscribe'] : null;

        if (!$name || mb_strlen($name,'utf8') > 30) {
            return [
                'status' => 99999,
                'message' => '名字不超过30个汉字',
            ];
        }

        if (mb_strlen($description,'utf8') > 500) {
            return [
                'status' => 99999,
                'message' => '简介不超过500个汉字',
            ];
        }
        $can_subscribe = intval($can_subscribe) ? 1: 0;
        $time = time();
        $data = [
            'name' => $name,
            'description' => $description,
            'creator_openid' => $openid,
            'creator_userid' => $this->getUserIdByOpenid($openid),
            'can_subscribe' => $can_subscribe,
            'list_type' => 'normal',
            'is_public' => 1,
            'book_amount' => 0,
            'subscribe_amount' => 0,
            'create_time' => $time,
            'update_time' => $time,
            'enable' => 1,
        ];
        $id = $this->capsule->table('book_list')->insertGetId($data);

        $res['data'] = [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'can_subscribe' => $can_subscribe,
            'book_amount' => 0,
            'subscribe_amount' => 0,
            'create_time' => $time,
            'update_time' => $time,
            'enable' => 1,
        ];
        return $res;
    }

    public function edit($openid, $id, $params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        if (!$id) {
            return [
                'status' => 99999,
                'message' => '缺少id',
            ];
        }
        $book_list = $this->capsule->table('book_list')->where('creator_openid', $openid)->where('id', $id)->first();
        if (empty($book_list)) {
            return [
                'status' => 99999,
                'message' => '没有权限操作',
            ];
        }
        $name = isset($params['name']) ? $params['name'] : null;
        $description = isset($params['description']) ? $params['description'] : null;
        $can_subscribe = isset($params['can_subscribe']) ? $params['can_subscribe'] : null;

        if ($name && mb_strlen($name,'utf8') > 30) {
            return [
                'status' => 99999,
                'message' => '名字不超过30个汉字',
            ];
        }
        if ($description && mb_strlen($description,'utf8') > 500) {
            return [
                'status' => 99999,
                'message' => '简介不超过500个汉字',
            ];
        }

        if ($name) {
            $this->capsule->table('book_list')->where('id', $id)->update(['name' => $name]);
        }
        if ($description) {
            $this->capsule->table('book_list')->where('id', $id)->update(['description' => $description]);
        }
        if ($can_subscribe) {
            $can_subscribe = intval($can_subscribe) ? 1: 0;
            $this->capsule->table('book_list')->where('id', $id)->update(['can_subscribe' => $can_subscribe]);
        }
        $time = time();
        $data = [
            'update_time' => $time,
        ];
        $this->capsule->table('book_list')->where('id', $id)->update($data);

        return $res;
    }

    public function delete($openid, $id)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        if (!$id) {
            return [
                'status' => 99999,
                'message' => '缺少id',
            ];
        }
        $book_list = $this->capsule->table('book_list')->where('creator_openid', $openid)->where('id', $id)->first();
        if (empty($book_list)) {
            return [
                'status' => 99999,
                'message' => '没有权限操作',
            ];
        }
        $this->capsule->table('book_list')->where('id', $id)->update(['enable' => 0]);
        return $res;
    }

    public function detail($openid, $id)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        if (!$id) {
            return [
                'status' => 99999,
                'message' => '缺少id',
            ];
        }

        $detail = $this->capsule->table('book_list')->where('id', $id)->first();
        if (empty($detail)) {
            return [
                'status' => 99999,
                'message' => '书单不存在',
            ];
        }
        $subscribe = $this->capsule->table('book_list_subscribe')->where('booklist_id', $id)->where('openid', $openid)->first();
        $is_subscribe = $subscribe ? 1: 0;

        $userModel = new User();
        $creator = $userModel->getSharerInfo($detail['creator_openid']);

        $data = [
            'detail' => [
                'name' => $detail['name'],
                'description' => $detail['description'],
                'can_subscribe' => $detail['can_subscribe'],
                'book_amount' => $detail['book_amount'],
                'subscribe_amount' => $detail['subscribe_amount'],
                'list_type' => $detail['list_type'],
                'update_time' => date('Y年m月d日', $detail['update_time']),
            ],
            'is_subscribe' => $is_subscribe,
            'is_creator' => $detail['creator_openid'] == $openid? 1: 0,
            'creator' => $creator,
        ];
        $res['data'] = $data;
        return $res;
    }

    public function add($openid, $id, $isbn)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        if (!$id) {
            return [
                'status' => 99999,
                'message' => '缺少id',
            ];
        }
        $book_list = $this->capsule->table('book_list')->where('creator_openid', $openid)->where('id', $id)->first();
        if (empty($book_list)) {
            return [
                'status' => 99999,
                'message' => '没有权限操作',
            ];
        }
        $bookModel = new Book();
        $book = $bookModel->findBook($isbn);
        if (empty($book)) {
            return [
                'status' => 99999,
                'message' => 'isbn错误',
            ];
        }
        $exist = $this->capsule->table('book_list_rel')->where('book_list_id', $id)->where('book_id', $book['id'])->first();
        if (!$exist) {
            $data = [
                'book_list_id' => $id,
                'book_id' => $book['id'],
                'create_time' => date('Y-m-d H:i:s', time()),
            ];
            $rsl = $this->capsule->table('book_list_rel')->insert($data);
            if ($rsl) {
                $this->capsule->table('book_list')->where('id', $id)->increment('book_amount');
            }
        }
        return $res;
    }

    public function remove($openid, $id, $isbn)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        if (!$id) {
            return [
                'status' => 99999,
                'message' => '缺少id',
            ];
        }
        $book_list = $this->capsule->table('book_list')->where('creator_openid', $openid)->where('id', $id)->first();
        if (empty($book_list)) {
            return [
                'status' => 99999,
                'message' => '没有权限操作',
            ];
        }
        $bookModel = new Book();
        $book = $bookModel->findBook($isbn);
        if (empty($book)) {
            return [
                'status' => 99999,
                'message' => 'isbn错误',
            ];
        }
        $exist = $this->capsule->table('book_list_rel')->where('book_list_id', $id)->where('book_id', $book['id'])->first();
        if ($exist) {
            $rsl = $this->capsule->table('book_list_rel')
                ->where('book_list_id', $id)->where('book_id', $book['id'])->delete();
            if ($rsl) {
                $this->capsule->table('book_list')->where('id', $id)->decrement('book_amount');
            }
        }
        return $res;
    }

    public function bookList($id, $params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $page = isset($params['page']) ? intval($params['page']) : 1;
        $pagesize = isset($params['pagesize']) ? intval($params['pagesize']) : 100;
        $offset = ($page - 1) * $pagesize;

        $bookBuilder = $this->capsule->table('book AS b')
            ->leftJoin('book_list_rel AS rel', 'rel.book_id', '=', 'b.id')
            ->leftJoin('book_list AS l', 'l.id', '=', 'rel.book_list_id')
            ->select('b.id','b.isbn10','b.isbn13','b.title','b.author','b.image','b.rating','b.price')
            ->where('l.id', $id);
        $totalCount = $bookBuilder->count();
        $books = $bookBuilder->offset($offset)->limit($pagesize)->get();

        $res['data'] = [
            'list' => $books,
            'total' => intval($totalCount),
            'pagesize' => $pagesize,
            'totalpage' => ceil($totalCount / $pagesize),
        ];
        return $res;
    }

    public function createFavouriteList($openid)
    {
        $exist = $this->capsule->table('book_list')->where('list_type', 'favourite')
            ->where('creator_openid', $openid)->first();
        if ($exist) {
            return;
        }
        $time = time();
        $data = [
            'name' => '我喜欢的书',
            'description' => '我喜欢的书',
            'creator_openid' => $openid,
            'creator_userid' => $this->getUserIdByOpenid($openid),
            'can_subscribe' => 0,
            'list_type' => 'favourite',
            'is_public' => 0,
            'book_amount' => 0,
            'subscribe_amount' => 0,
            'create_time' => $time,
            'update_time' => $time,
            'enable' => 1,
        ];
        $this->capsule->table('book_list')->insert($data);
    }

    public function subscribe($openid, $booklist_id)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $booklist = $this->capsule->table('book_list')->where('id', $booklist_id)->first();
        if (!$booklist) {
            return [
                'status' => 99999,
                'message' => '书单不存在',
            ];
        }
        if ($openid == $booklist['creator_openid'] || 0 == $booklist['can_subscribe']) {
            return [
                'status' => 99999,
                'message' => '无法关注此书单',
            ];
        }
        $subscribe = $this->capsule->table('book_list_subscribe')->where('booklist_id', $booklist_id)
            ->where('openid', $openid)->first();
        if ($subscribe) {
            return [
                'status' => 99999,
                'message' => '已关注此书单',
            ];
        }
        $data = [
            'booklist_id' => $booklist_id,
            'user_id' => $this->getUserIdByOpenid($openid),
            'openid' => $openid,
            'subscribe_time' => time(),
        ];
        $rsl = $this->capsule->table('book_list_subscribe')->insert($data);
        if ($rsl) {
            $this->capsule->table('book_list')->where('id', $booklist_id)->increment('subscribe_amount');
        }
        return $res;
    }

    public function unSubscribe($openid, $booklist_id)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $booklist = $this->capsule->table('book_list')->where('id', $booklist_id)->first();
        if (!$booklist) {
            return [
                'status' => 99999,
                'message' => '书单不存在',
            ];
        }
        $subscribe = $this->capsule->table('book_list_subscribe')->where('booklist_id', $booklist_id)
            ->where('openid', $openid)->first();
        if (!$subscribe) {
            return [
                'status' => 99999,
                'message' => '还未关注此书单',
            ];
        }
        $rsl = $this->capsule->table('book_list_subscribe')->where('booklist_id', $booklist_id)
            ->where('openid', $openid)->delete();
        if ($rsl) {
            $this->capsule->table('book_list')->where('id', $booklist_id)->decrement('subscribe_amount');
        }
        return $res;
    }

}