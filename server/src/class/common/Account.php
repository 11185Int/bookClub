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

    public function isRealNameEmpty($openid, $group_id)
    {
        if (!$group_id) {
            return false;
        }
        $user_group = $this->capsule->table('user_group')
            ->where('group_id', $group_id)->where('openid', $openid)->first();
        if ($user_group && !empty($user_group['realname'])) {
            return false;
        }
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        return empty($user['realname']);
    }

    public function getPosterData($openid, $groupId, $tags_cnt = 10, $books_cnt = 20)
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
            'avg_rating' => 0,
            'taste_percent' => 0,
            'tags' => [],
            'books' => [],
        ];
        $builder = $this->capsule->table('book_share AS bs')
            ->leftJoin('book AS b', 'b.id', '=', 'bs.book_id')
            ->where('bs.share_status', 1)
            ->groupBy('bs.book_id')
            ->orderBy('b.rating', 'desc')
            ->limit(500)
            ->select('b.id','b.title','b.author','b.rating','b.image','b.tags')
            ->selectRaw('count('.$this->capsule->getConnection()->getTablePrefix().'bs.id) AS cnt');

        if ($groupId) {
            $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)->first();
            if (empty($user_group)) {
                return [
                    'status' => 99999,
                    'message' => '还未加入此小组',
                ];
            }
            $builder->where('bs.group_id', $groupId);
        } else {
            $builder->where('bs.group_id', 0)->where('bs.owner_openid', $openid);
        }
        $booksData = $builder->get();

        $allRating = 0;
        $allTags = [];
        foreach ($booksData as $booksDatum) {
            $data['book_cnt'] += intval($booksDatum['cnt']);
            if ($booksDatum['rating'] > $data['best_rating']) {
                $data['best_rating'] = $booksDatum['rating'];
                $data['best_book'] = $booksDatum['title'];
            }
            $allRating += $booksDatum['rating'] * $booksDatum['cnt'];
            $tags = explode(',', $booksDatum['tags']);
            foreach ($tags as $tag) {
                if (mb_strlen($tag) > 6) {
                    $allTags[$tag] = 0;
                    continue;
                }
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
                'cnt' => intval($booksDatum['cnt']),
            ];
        }
        $data['avg_rating'] = $data['book_cnt'] > 0 ? round($allRating/$data['book_cnt'], 1) : 0;
        $data['taste_percent'] = $data['avg_rating'] > 2 && $data['book_cnt'] > 0 ?
            round($allRating/$data['book_cnt'] * 12.38 - 23.75) : 0;
        arsort($allTags);
        $data['tags'] = array_slice(array_keys($allTags), 0, $tags_cnt);
        $data['title'] = $this->getTitleByNum($data['book_cnt']);
        $data['books'] = array_slice($data['books'], 0, $books_cnt);
        $res['data'] = $data;
        return $res;
    }

    protected function getTitleByNum($num)
    {
        $titleArr = [
            1 => ['白丁','伴读书童','穷酸秀才','举人','进士','状元','学士','司徒','太傅','圣贤'],
            2 => ['白丁','伴读书童','穷酸秀才','举人','进士','状元','学士','司徒','太傅','圣贤'],
        ];
        $pos = [
            1 => 0,  //(0-1]
            5 => 1,  //(1-5]
            10 => 2, //(5-10]
            20 => 3, //(10-20]
            35 => 4, //(20-35]
            50 => 5, //(35-50]
            75 => 6, //(50-75]
            100 => 7,//(75-100]
            200 => 8,//(100-200]
        ];
        $class_pos = 0;
        foreach ($pos as $max => $pos_value) {
            if ($num > $max) {
                $class_pos = $pos_value + 1;
            } else { //$num <= $max
                $class_pos = $pos_value;
                break;
            }
        }

        return $titleArr[1][$class_pos];
    }

}