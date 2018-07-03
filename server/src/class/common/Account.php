<?php

namespace CP\common;

class Account extends AbstractModel
{
    protected $_accountKey = null;

    function __construct()
    {
        parent::__construct();

        $this->_accountKey = new AccountSessionKey();
    }

    public function login($params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );

        $code = isset($params['code']) ? $params['code'] : '';

        if (empty($code)) {
            $res['status'] = 10000;
            $res['message'] = '参数不全';
            return $res;
        }

        list($key, $openid, $session_key) = $this->_accountKey->generateKey($code);

        if (!$key) {
            $res['status'] = 99999;
            $res['message'] = '参数错误';
            return $res;
        }

        $data = [
            'openid' => $openid,
            'nickname' => isset($params['nickname']) ? $params['nickname'] : '',
            'sex' => isset($params['sex']) ? $params['sex'] : '',
            'city' => isset($params['city']) ? $params['city'] : '',
            'country' => isset($params['country']) ? $params['country'] : '',
            'province' => isset($params['province']) ? $params['province'] : '',
            'headimgurl' => isset($params['headimgurl']) ? $params['headimgurl'] : '',
        ];
        $this->_accountKey->updateUserInfo($openid, $data);

        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        $res['data']['realname'] = empty($user['realname']) ? '' : $user['realname'];

        $group_amount = $this->capsule->table('user_group')->where('openid', $openid)->count();
        $res['data']['group_amount'] = $group_amount;

        $res['data']['key'] = $key;
        return $res;
    }

    /**
     * 获取用户详情
     * @param $params
     * @return array
     */
    public function getDetail($params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
            'data' => array(),
        );

        $key = isset($params['key']) ? $params['key'] : '';
        $openid = $this->_accountKey->getOpenIdByKey($key);

        $detail = $this->capsule->table('user')->where('openid', $openid)->first();
        $res['data'] = $detail ?: [];

        unset($res['data']['openid']);

        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        $res['data']['realname'] = empty($user['realname']) ? '' : $user['realname'];

        $group_amount = $this->capsule->table('user_group')->where('openid', $openid)->count();
        $res['data']['group_amount'] = $group_amount;

        $res['data']['group_id'] = $this->_accountKey->getCurrentGroupIdByKey($key);
        $res['data']['group_name'] = $this->_accountKey->getCurrentGroupNameByKey($key);

        return $res;
    }

    public function rename($openid, $realname)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );

        if (!$realname) {
            $res['status'] = 99999;
            $res['message'] = '参数错误';
            return $res;
        }
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        if ($user['realname']) {
            $res['status'] = 99999;
            $res['message'] = '只能设置一次真实姓名';
            return $res;
        }
        $this->capsule->table('user')->where('openid', $openid)->update(['realname' => $realname]);
        return $res;
    }

    public function isRealNameEmpty($openid)
    {
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        return empty($user['realname']);
    }

    public function getPosterData($openid)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        $data = [
            'name' => $user['nickname'],
            'book_cnt' => 0,
            'best_book' => '',
            'best_rating' => 0,
            'tags' => [],
            'books' => [],
        ];
        $booksData = $this->capsule->table('book_share AS bs')
            ->leftJoin('book AS b', 'b.id', '=', 'bs.book_id')
            ->where('bs.owner_openid', $openid)
            ->where('bs.share_status', 1)
            ->groupBy('bs.book_id')
            ->orderBy('b.rating', 'desc')
            ->limit(500)
            ->select('b.id','b.title','b.author','b.rating','b.image','b.tags')->selectRaw('count(tb_bs.id) AS cnt')
            ->get();

        $allTags = [];
        foreach ($booksData as $booksDatum) {
            $data['book_cnt'] += intval($booksDatum['cnt']);
            if ($booksDatum['rating'] > $data['best_rating']) {
                $data['best_rating'] = $booksDatum['rating'];
                $data['best_book'] = $booksDatum['title'];
            }
            $tags = explode(',', $booksDatum['tags']);
            foreach ($tags as $tag) {
                if (isset($allTags[$tag])) {
                    $allTags[$tag] += floatval($booksDatum['rating']);
                } else {
                    $allTags[$tag] = floatval($booksDatum['rating']);
                }
            }
            $data['books'][] = [
                'title' => $booksDatum['title'],
                'author' => $booksDatum['author'],
                'image' => $booksDatum['image'],
            ];
        }
        arsort($allTags);
        $data['tags'] = array_slice(array_keys($allTags), 0, 10);
        $data['books'] = array_slice($data['books'], 0, 20);
        $res['data'] = $data;
        return $res;
    }

}