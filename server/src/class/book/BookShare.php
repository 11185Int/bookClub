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
            'remark' => $remark,
            'group_id' => $groupId
        );

        $this->capsule->table('book_share')->insert($kv);

        return $res;
    }

    public function unShare($groupId, $openid, $book_share_id)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        if (!$book_share_id) {
            return [
                'status' => 10000,
                'message' => '参数不全',
            ];
        }

        $bookShare = $this->findBookShareById($book_share_id);
        if ($bookShare['owner_openid'] != $openid) {
            return [
                'status' => 6000,
                'message' => '找不到此分享图书',
            ];
        }
        if (empty($bookShare)) {
            return [
                'status' => 6000,
                'message' => '找不到此分享图书',
            ];
        }
        if ($bookShare['lend_status'] == 2) {
            return [
                'status' => 6000,
                'message' => '此图书借出中，无法取消共享',
            ];
        }

        $kv = array(
            'share_status' => 0,
            'lend_status' => 0,
        );

        $this->capsule->table('book_share')->where('id', $book_share_id)->where('group_id', $groupId)->update($kv);
        return $res;
    }

    public function reShare($groupId, $openid, $book_share_id)
    {
        $res = array(
            'status' => 0,
            'message' => '',
        );

        if (!$book_share_id) {
            return [
                'status' => 10000,
                'message' => '参数不全',
            ];
        }

        $bookShare = $this->findBookShareById($book_share_id);
        if ($bookShare['owner_openid'] != $openid) {
            return [
                'status' => 6000,
                'message' => '找不到此分享图书',
            ];
        }
        if (empty($bookShare)) {
            return [
                'status' => 6000,
                'message' => '找不到此分享图书',
            ];
        }
        if ($bookShare['share_status'] || $bookShare['lend_status']) {
            return [
                'status' => 6000,
                'message' => '此图书无法恢复共享',
            ];
        }

        $kv = array(
            'share_status' => 1,
            'lend_status' => 1,
        );

        $this->capsule->table('book_share')->where('id', $book_share_id)->where('group_id', $groupId)->update($kv);

        return $res;
    }

}
