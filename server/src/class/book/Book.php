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
use CP\user\User;
use Slim\Http\UploadedFile;

class Book extends AbstractModel
{

    const BOOK_VERSION = 2;

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
            ->leftJoin('book_borrow AS bb', function($join) {
                $join->on('bb.book_share_id', '=', 's.id');
                $join->where('bb.return_status', '=', 0);
            })
            ->select('b.id','b.isbn10','b.isbn13','b.title','b.image','s.share_status','s.lend_status','bb.borrower_openid')
            ->selectRaw('max('.$this->capsule->getConnection()->getTablePrefix().'s.id) AS sid')
            ->selectRaw('count(distinct '.$this->capsule->getConnection()->getTablePrefix().'s.id) AS book_share_sum')
            ->selectRaw('count(distinct '.$this->capsule->getConnection()->getTablePrefix().'bb.id) AS book_borrow_sum')
            ->where('s.share_status', 1)
            ->where(function ($q) use ($openid) {
                $q->where(function ($q) use ($openid) {
                    $q->where('s.group_id', 0)->where('s.owner_openid', $openid);
                })->orWhere('bb.borrower_openid', $openid);
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
        $builder->orderBy('bb.return_status', 'is not null')
            ->orderBy('bb.id', 'desc')
            ->orderBy('sid', 'desc')
            ->limit($pagesize)->offset($offset);
        $data = $builder->get();
        if (!empty($data)) {
            $newData = [];
            foreach ($data as $item) {
                $item['canBorrow'] = $item['book_share_sum'] > $item['book_borrow_sum'] ? 1 : 0;
                $item['shouldReturn'] = $item['borrower_openid'] == $openid ? 1: 0;
                unset($item['borrower_openid']);
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
        $prefix = $this->capsule->getConnection()->getTablePrefix();
        $builder->orderByRaw('count(distinct '.$prefix.'s.id) = count(distinct '.$prefix.'bb.id)')
            ->orderBy('sid', 'desc')
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

    public function getListByUser($openid, $params)
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
            ->where('s.group_id', 0)->where('s.owner_openid', $openid)
            ->where('ss.group_id', 0)->where('ss.owner_openid', $openid)
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
        $prefix = $this->capsule->getConnection()->getTablePrefix();
        $builder->orderByRaw('count(distinct '.$prefix.'s.id) = count(distinct '.$prefix.'bb.id)')
            ->orderBy('sid', 'desc')
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

        if (strlen($isbn) < 8) {
            return [
                'status' => 6000,
                'message' => 'isbn码错误',
            ];
        }

        if (empty($book) || $this->isBookOutdated($book)) {
            $id = empty($book['id']) ? 0 : $book['id'];
            $api = new Douban();
            $bookDetail = $api->getBook($isbn);
            $this->saveBook($bookDetail, $id);
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

    public function getBookStatusByGroup($isbn, $openid, $groupId)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        $book = $this->findBook($isbn);
        if (!$book) {
            $book['id'] = 0;
//            return [
//                'status' => 6000,
//                'message' => '找不到图书',
//            ];
        }
        $user_group = $this->capsule->table('user_group')->where('group_id', $groupId)
            ->where('openid', $openid)->first();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此小组',
            ];
        }
        $shouldReturn = 0;
        $canBorrow = 0;
        $canEdit = 0;
        $position_type = 1;//1组员 2分享者 3管理者(包含发布者)

        $book_shares = $this->capsule->table('book_share')->where('book_id', $book['id'])
            ->where('group_id', $groupId)->where('share_status', 1)->get(); //查看分享的所有book_share

        //是否需要还
        foreach ($book_shares as $book_share) {

            $book_borrow = $this->capsule->table('book_borrow')->where('book_share_id', $book_share['id'])
                ->where('borrower_openid', $openid)->orderBy('id', 'desc')->first();
            if (!empty($book_borrow) && $book_borrow['return_status'] == 0) {
                $shouldReturn = 1;
                break;
            }
        }

        $share_sum = count($book_shares);
        $sharer_openid_arr = [];
        $lend_sum = 0;
        $sharer_openid = '';
        foreach ($book_shares as $book_share) {
            if ($book_share['lend_status'] == 2) { //在架,未借出
                $lend_sum ++;
            }
            $sharer_openid_arr[] = $book_share['owner_openid'];
            $sharer_openid = $book_share['owner_openid'];
        }

        //是否能编辑(管理员)
        if ($share_sum > 0 && $user_group['is_admin'] == 1) {
            $canEdit = 1;
            $position_type = 3;
        }
        //是否能编辑(分享者)
        else if ($share_sum > 0 && in_array($openid, $sharer_openid_arr)) {
            $canEdit = 1;
            $position_type = 2;
        }

        //是否能借
        if ($shouldReturn == 0) {
            foreach ($book_shares as $book_share) {
                if ($book_share['lend_status'] == 1) { //在架,未借出
                    $canBorrow = 1;
                    break;
                }
            }
        }

        $isAdd = 0;
        if ($user_group['is_admin'] == 1) {
            //是否已经添加
            $add_cnt = $this->capsule->table('book_share')->where('book_id', $book['id'])
                ->where('group_id', $groupId)->count();
            $isAdd = $add_cnt > 0? 1: 0;
        }

        $bmModel = new BookMark();
        $bookmark = $bmModel->getBookmark($book['id'], $openid);

        $userModel = new User();
        $sharer = $userModel->getSharerInfo($sharer_openid, $groupId);

        $group = $this->capsule->table('group')->find($groupId);

        $res['data'] = [
            'sharer' => $sharer,
            'shouldReturn' => $shouldReturn,
            'share_sum' => $share_sum,
            'lend_sum' => $lend_sum,
            'canBorrow' => $canBorrow,
            'isAdd' => $isAdd,
            'canEdit' => $canEdit,
            'position_type' => $position_type,
            'can_member_share' => $group['can_member_share'],
            'bookmark' => $bookmark,
        ];

        return $res;
    }

    public function getBookStatusByUser($isbn, $openid, $owner_openid)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        $book = $this->findBook($isbn);
        if (!$book) {
            $book['id'] = 0;
//            return [
//                'status' => 6000,
//                'message' => '找不到图书',
//            ];
        }
        $shouldReturn = 0;
        $canBorrow = 0;
        $canEdit = 0;

        $book_shares = $this->capsule->table('book_share')->where('book_id', $book['id'])
            ->where('group_id', 0)->where('owner_openid', $owner_openid)->where('share_status', 1)->get();//查看分享的所有book_share

        //是否需要还
        foreach ($book_shares as $book_share) {
            $book_borrow = $this->capsule->table('book_borrow')->where('book_share_id', $book_share['id'])
                ->where('borrower_openid', $openid)->orderBy('id', 'desc')->first();
            if (!empty($book_borrow) && $book_borrow['return_status'] == 0) {
                $shouldReturn = 1;
                break;
            }
        }
        $share_sum = count($book_shares);
        $lend_sum = 0;
        foreach ($book_shares as $book_share) {
            if ($book_share['lend_status'] == 2) { //在架,未借出
                $lend_sum ++;
            }
        }

        //是否能借
        if ($shouldReturn == 0) {
            foreach ($book_shares as $book_share) {
                if ($book_share['lend_status'] == 1) { //在架,未借出
                    $canBorrow = 1;
                    break;
                }
            }
        }
        //是否已经添加
        $add_cnt = $this->capsule->table('book_share')->where('book_id', $book['id'])
            ->where('group_id', 0)->where('owner_openid', $owner_openid)->count();
        $isAdd = $add_cnt > 0? 1: 0;

        $bmModel = new BookMark();
        $bookmark = $bmModel->getBookmark($book['id'], $openid);

        $userModel = new User();
        $sharer = $userModel->getSharerInfo($owner_openid);

        $res['data'] = [
            'sharer' => $sharer,
            'shouldReturn' => $shouldReturn,
            'share_sum' => $share_sum,
            'lend_sum' => $lend_sum,
            'canBorrow' => $canBorrow,
            'isAdd' => $isAdd,
            'canEdit' => $canEdit,
            'bookmark' => $bookmark,
        ];

        return $res;
    }

    public function getBookStatusBySelf($isbn, $openid)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );
        $book = $this->findBook($isbn);
        if (!$book) {
            $book['id'] = 0;
//            return [
//                'status' => 6000,
//                'message' => '找不到图书',
//            ];
        }
        $shouldReturn = 0;
        $canBorrow = 0;
        $canEdit = 0;

        $return_book_shares = $this->capsule->table('book_share AS s')
            ->leftJoin('book_borrow AS b', 'b.book_share_id', '=', 's.id')
            ->select('s.id', 's.share_status', 's.lend_status', 's.owner_openid', 's.group_id')
            ->where('s.book_id', $book['id'])
            ->where('b.borrower_openid', $openid)
            ->where('s.share_status', 1)
            ->where('b.return_status', 0)
            ->groupBy('s.id')
            ->get();//查看分享的所有book_share

        //是否需要还
        if (count($return_book_shares) > 0) {
            $shouldReturn = 1;
        }

        $book_shares = $this->capsule->table('book_share')->where('book_id', $book['id'])
            ->where('group_id', 0)->where('owner_openid', $openid)->where('share_status', 1)->get();
        //是否能借
        if ($shouldReturn == 0) {
            //查看【本人】分享的所有book_share

            foreach ($book_shares as $book_share) {
                if ($book_share['lend_status'] == 1) { //在架,未借出
                    $canBorrow = 1;
                    break;
                }
            }
        }

        $share_sum = count($book_shares);
        $lend_sum = 0;
        foreach ($book_shares as $book_share) {
            if ($book_share['lend_status'] == 2) { //在架,未借出
                $lend_sum ++;
            }
        }

        //是否能编辑
        if ($share_sum > 0) {
            $canEdit = 1;
        }

        //是否已经添加
        $add_cnt = $this->capsule->table('book_share')->where('book_id', $book['id'])
            ->where('group_id', 0)->where('owner_openid', $openid)->count();
        $isAdd = $add_cnt > 0? 1: 0;

        $bmModel = new BookMark();
        $bookmark = $bmModel->getBookmark($book['id'], $openid);

        $sharer = [];
        if ($shouldReturn == 1) { //要还，显示拥有者信息
            $book_share = reset($return_book_shares);
            if (!empty($book_share)) {
                $userModel = new User();
                if ($book_share['group_id']) {
                    $sharer = $userModel->getSharerGroupInfo($book_share['group_id']);
                } else {
                    $sharer = $userModel->getSharerInfo($book_share['owner_openid']);
                }
            }
        } else { //不用还，显示自己的

            $book_share = $this->capsule->table('book_share')->where('book_id', $book['id'])->where('group_id', 0)
                ->where('owner_openid', $openid)->where('share_status', 1)->first();
            if (!empty($book_share)) {
                $userModel = new User();
                $sharer = $userModel->getSharerInfo($book_share['owner_openid']);
            }
        }

        $res['data'] = [
            'sharer' => $sharer,
            'shouldReturn' => $shouldReturn,
            'share_sum' => $share_sum,
            'lend_sum' => $lend_sum,
            'canBorrow' => $canBorrow,
            'isAdd' => $isAdd,
            'canEdit' => $canEdit,
            'bookmark' => $bookmark,
        ];

        return $res;
    }

    public function getShareList($isbn, $groupId, $userId, $myOpenid)
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
        $builder = null;
        $lent_builder = null;

        if ($groupId) { //小组
            $user_group = $this->capsule->table('user_group')->where('group_id', $groupId)
                ->where('openid', $myOpenid)->first();
            if (empty($user_group)) {
                return [
                    'status' => 99999,
                    'message' => '还未加入此小组',
                ];
            }
            $builder = $this->capsule->table('book_share AS share')
                ->select('book.id AS book_id','share.id AS book_share_id','user.nickname','user.headimgurl','user.realname',
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
                ->select('book.id AS book_id','share.id AS book_share_id','user.nickname','user.headimgurl','user.realname',
                    'share.share_status','share.lend_status','share.share_time','share.remark')
                ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
                ->join('user AS user', 'user.openid', '=', 'share.owner_openid', 'inner')
                ->where('share.share_status', 1)
                ->where('share.lend_status', 2)
                ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
                ->where('share.group_id', $groupId)
                ->groupBy('user.id')
                ->orderBy('share.share_time', 'desc');

        } else if ($userId) { //好友书库

            $builder = $this->capsule->table('book_share AS share')
                ->select('book.id AS book_id','share.id AS book_share_id','user.nickname','user.headimgurl','user.realname',
                    'share.share_status','share.lend_status','share.share_time','share.remark')
                ->selectRaw('count('.$this->capsule->getConnection()->getTablePrefix().'share.id) AS amount')
                ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
                ->join('user AS user', 'user.openid', '=', 'share.owner_openid', 'inner')
                ->where('share.share_status', 1)
                ->where('share.lend_status', 1)
                ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
                ->where('share.group_id', 0)
                ->where('share.owner_id', $userId)
                ->groupBy('user.id')
                ->orderBy('share.share_time', 'desc');

            $lent_builder = $this->capsule->table('book_share AS share')
                ->select('book.id AS book_id','share.id AS book_share_id','user.nickname','user.headimgurl','user.realname',
                    'share.share_status','share.lend_status','share.share_time','share.remark')
                ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
                ->join('user AS user', 'user.openid', '=', 'share.owner_openid', 'inner')
                ->where('share.share_status', 1)
                ->where('share.lend_status', 2)
                ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
                ->where('share.group_id', 0)
                ->where('share.owner_id', $userId)
                ->groupBy('user.id')
                ->orderBy('share.share_time', 'desc');

        } else {
            $res = array(
                'status' => 99999,
                'message' => '参数错误',
            );
        }

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

    public function getMyReturnList($openid, $isbn)
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
            //->where('share.group_id', $groupId)
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

    public function getBorrowHistory($openid, $isbn, $groupId)
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

        $data = [];
        $share_status = 0;
        if ($groupId) {
            $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)->first();
            if (empty($user_group)) {
                return [
                    'status' => 99999,
                    'message' => '还未加入此小组',
                ];
            }
            $book_shares = $this->capsule->table('book_share')->where('group_id', $groupId)
                ->where('share_status', '>', 0)->select('id')->get();
            $share_status = count($book_shares) > 0 ? 1: 0;
            $data = $this->capsule->table('book')
                ->select('share.owner_openid','sharer.nickname AS sharer_nickname',
                    'sharer.headimgurl AS sharer_headimgurl','share.group_id','borrow.book_share_id','borrow.borrower_openid',
                    'borrower.nickname AS borrower_nickname','borrower.headimgurl AS borrower_headimgurl',
                    'ug.realname AS borrower_realname',
                    'borrow.borrow_time', 'borrow.return_status', 'borrow.return_time')
                ->leftJoin('book_share AS share', 'share.book_id', '=', 'book.id')
                ->rightJoin('book_borrow AS borrow', 'borrow.book_share_id', '=', 'share.id')
                ->leftJoin('user AS sharer', 'sharer.openid', '=', 'share.owner_openid')
                ->leftJoin('user AS borrower', 'borrower.openid', '=', 'borrow.borrower_openid')
                ->leftJoin('user_group AS ug', function ($join) use ($groupId) {
                    $join->on('borrower.openid', '=', 'ug.openid');
                    $join->where('ug.group_id', '=', $groupId);
                })
                ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
                ->where('share.group_id', $groupId)
                ->orderBy('borrow.borrow_time', 'desc')
                ->get();
        } else {
            $book_shares = $this->capsule->table('book_share')->where('group_id', 0)->where('owner_openid', $openid)
                ->where('share_status', '>', 0)->select('id')->get();
            $share_status = count($book_shares) > 0 ? 1: 0;
            $data = $this->capsule->table('book')
                ->select('share.book_id','share.owner_openid','sharer.nickname AS sharer_nickname',
                    'sharer.headimgurl AS sharer_headimgurl','share.group_id','borrow.book_share_id','borrow.borrower_openid',
                    'borrower.nickname AS borrower_nickname','borrower.headimgurl AS borrower_headimgurl',
                    'borrower.realname AS borrower_realname',
                    'borrow.borrow_time', 'borrow.return_status', 'borrow.return_time')
                ->leftJoin('book_share AS share', 'share.book_id', '=', 'book.id')
                ->rightJoin('book_borrow AS borrow', 'borrow.book_share_id', '=', 'share.id')
                ->leftJoin('user AS sharer', 'sharer.openid', '=', 'share.owner_openid')
                ->leftJoin('user AS borrower', 'borrower.openid', '=', 'borrow.borrower_openid')
                ->where(strlen($isbn) == 10 ?'book.isbn10': 'book.isbn13', $isbn)
                ->where('share.group_id', 0)
                ->where('share.owner_openid', $openid)
                ->orderBy('borrow.borrow_time', 'desc')
                ->get();
        }

        $list = [];
        foreach ($data as $datum) {
            $list[] = [
                'sharer_nickname' => $datum['sharer_nickname'],
                'sharer_headimgurl' => $datum['sharer_headimgurl'],
                'borrower_nickname' => $datum['borrower_realname'] ?: $datum['borrower_nickname'],
                'borrower_headimgurl' => $datum['borrower_headimgurl'],
                'borrow_time' => date('Y-m-d', $datum['borrow_time']),
                'return_time' => $datum['return_time'] ? date('Y-m-d', $datum['return_time']) : '',
                'return_status' => $datum['return_status'],
                'borrow_during' => $this->calIntervalDays($datum['borrow_time'], $datum['return_time']).'天',
            ];
        }
        $res['data']['share_status'] = $share_status;
        $res['data']['history'] = $list;

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
                'pagesize' => intval($pagesize),
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

    protected function saveBook($book, $id = 0)
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
            'pages' => empty($book['pages']) ? 0 : intval($book['pages']),
            'catalog' => empty($book['catalog']) ? '': mb_strimwidth($book['catalog'], 0, 9999, '...'),
            'ismanual' => empty($book['ismanual']) ? 0 : $book['ismanual'],
            'openid' => empty($book['openid']) ? '' : $book['openid'],
            'hd_image' => $this->getHdImage($image),
            'version' => self::BOOK_VERSION,
        ];
        $isbn = $book['isbn13'] ?: $book['isbn10'];
        if (!$isbn) {
            return false;
        }
        if ($id > 0) {
            $this->capsule->table('book')->where('id', $id)->update($kv);
        } else {
            $this->capsule->table('book')->insert($kv);
        }
         return true;
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

    protected function isBookOutdated($book)
    {
        if (empty($book)) {
            return false;
        }
        if ($book['ismanual'] == 0 && $book['version'] < self::BOOK_VERSION) {
            return true;
        }
        return false;
    }
}