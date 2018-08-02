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

    public function getMyBookShare($groupId, $openid)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        $builder = $this->capsule->table('book_share AS share')
            ->join('book AS book', 'book.id', '=', 'share.book_id', 'inner')
            ->select('share.id AS book_share_id','share.book_id','book.isbn10','book.isbn13','book.title','book.image',                 'share.share_status','share.lend_status','share.share_time')
            ->where('share.owner_openid', $openid)
            ->where('share.share_status', 1)
            ->where('share.group_id', $groupId)
            ->orderBy('share.lend_status', 'desc')
            ->orderBy('share.share_time', 'desc');

        if ($builder) {
            $res['data']['share'] = $builder->get();
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
        $book_share = $this->capsule->table('book_share')->find($book_share_id);
        return $book_share ?: [];
    }

    /**
     * @param $groupId
     * @param $openid
     * @param $isbn
     * @param $remark
     * @return array
     */
    public function share($groupId, $openid, $isbn, $remark)
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
            'remark' => $remark ?: '',
            'group_id' => $groupId ? intval($groupId) : 0,
        );

        if ($groupId) { //添加到小组

            $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)->first();
            if (empty($user_group)) {
                return [
                    'status' => 99999,
                    'message' => '还未加入此小组',
                ];
            }
            if ($user_group['is_admin'] == 0) {
                return [
                    'status' => 99999,
                    'message' => '无权限操作',
                ];
            }
            $this->capsule->table('book_share')->insert($kv);

        } else { //添加到个人藏书

            $kv['group_id'] = 0;
            $this->capsule->table('book_share')->insert($kv);

        }


        return $res;
    }

    public function unShare($groupId, $openid, $isbn)
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
        $book_id = $book['id'];
        $groupId = $groupId ? intval($groupId) : 0;

        if ($groupId) { //小组的检查权限
            $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)->first();
            if (empty($user_group)) {
                return [
                    'status' => 99999,
                    'message' => '还未加入此小组',
                ];
            }
            if ($user_group['is_admin'] == 0) {
                return [
                    'status' => 99999,
                    'message' => '无权限操作',
                ];
            }
            $bookShares = $this->capsule->table('book_share')->where('book_id', $book_id)->where('group_id', $groupId)->get();
        } else { //个人的
            $bookShares = $this->capsule->table('book_share')->where('book_id', $book_id)->where('group_id', 0)
                ->where('owner_openid', $openid)->get();
        }



        if (empty($bookShares)) {
            return [
                'status' => 6000,
                'message' => '找不到此分享图书',
            ];
        }

        $this->forceReturn($bookShares);
        $kv = array(
            'share_status' => 0,
            'lend_status' => 0,
        );

        if ($groupId) {
            $this->capsule->table('book_share')->where('book_id', $book_id)->where('group_id', $groupId)->update($kv);
        } else {
            $this->capsule->table('book_share')->where('book_id', $book_id)->where('owner_openid', $openid)
                ->where('group_id', 0)->update($kv);
        }

        return $res;
    }

    public function reShare($groupId, $openid, $isbn)
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
        $book_id = $book['id'];
        $groupId = $groupId ? intval($groupId) : 0;

        if ($groupId) { //小组 检查权限
            $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)->first();
            if (empty($user_group)) {
                return [
                    'status' => 99999,
                    'message' => '还未加入此小组',
                ];
            }
            if ($user_group['is_admin'] == 0) {
                return [
                    'status' => 99999,
                    'message' => '无权限操作',
                ];
            }
            $bookShares = $this->capsule->table('book_share')->where('book_id', $book_id)
                ->where('group_id', $groupId)->get();
        } else { //个人
            $bookShares = $this->capsule->table('book_share')->where('book_id', $book_id)
                ->where('owner_openid', $openid)->where('group_id', 0)->get();
        }

        if (empty($bookShares)) {
            return [
                'status' => 6000,
                'message' => '找不到此图书',
            ];
        }

        $kv = array(
            'share_status' => 1,
            'lend_status' => 1,
        );

        if ($groupId) {
            $this->capsule->table('book_share')->where('book_id', $book_id)->where('group_id', $groupId)->update($kv);
        } else {
            $this->capsule->table('book_share')->where('book_id', $book_id)->where('group_id', 0)
                ->where('owner_openid', $openid)->update($kv);
        }

        return $res;
    }

    protected function forceReturn($bookShares)
    {
        if (empty($bookShares)) {
            return false;
        }
        foreach ($bookShares as $bookShare) {
            if ($bookShare['lend_status'] == 2) {
                $book_borrow = $this->capsule->table('book_borrow')
                    ->where('book_share_id', $bookShare['id'])
                    ->where('return_status', 0)
                    ->first() ?: [];
                if (!empty($book_borrow)) {
                    $kv = array(
                        'return_status' => 1,
                        'return_time' => time(),
                        'remark' => '系统自动归还',
                    );
                    $this->capsule->table('book_borrow')->where('id', $book_borrow['id'])->update($kv);
                }
            }
        }
        return true;
    }

}
