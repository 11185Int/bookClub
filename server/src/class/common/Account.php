<?php

namespace CP\common;

use CP\book\Visit;

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
     * @param $openid
     * @return array
     */
    public function getDetail($openid)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
            'data' => array(),
        );


        $openKey = new OpenKey();
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        $user['id'] = $openKey->getOpenKey($user['id'], OpenKey::TYPE_USER_ID);
        $res['data'] = $user ?: [];

        unset($res['data']['openid']);

        $res['data']['realname'] = empty($user['realname']) ? '' : $user['realname'];

        $group_amount = $this->capsule->table('user_group')->where('openid', $openid)->count();
        $res['data']['group_amount'] = $group_amount;

        $visit = new Visit();
        $res['data']['ext'] = $visit->getVisitDataUser($openid);
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
        if ($groupId) {
            $group = $this->capsule->table('group')->where('id', $groupId)->first();
            $name = empty($group['group_name']) ? '' : $group['group_name'];
        } else {
            $name = $user['nickname'];
        }
        $data = [
            'name' => $name,
            'book_cnt' => 0,
            'book_rank' => 8,
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
        $rankBuilder = $this->capsule->table('book_share AS bs')
            ->where('bs.share_status', 1)
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
            $rankBuilder->where('bs.group_id', '>', 0)->groupBy('bs.group_id');
        } else {
            $builder->where('bs.group_id', 0)->where('bs.owner_openid', $openid);
            $rankBuilder->where('bs.group_id', 0)->groupBy(['bs.owner_openid', 'bs.group_id']);
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
        $rank = $rankBuilder->havingRaw('count('.$this->capsule->getConnection()->getTablePrefix().'bs.id) > '.$data['book_cnt'])->get();
        $data['book_rank'] = count($rank) + 1;
        $data['avg_rating'] = $data['book_cnt'] > 0 ? round($allRating/$data['book_cnt'], 1) : 0;
        $data['taste_percent'] = $data['avg_rating'] > 2 && $data['book_cnt'] > 0 ?
            round($allRating/$data['book_cnt'] * 12.38 - 23.75) : 0;
        arsort($allTags);
        $data['tags'] = array_slice(array_keys($allTags), 0, $tags_cnt);
        $data['title'] = $groupId? $this->getGroupTitleByNum($data['book_cnt']): $this->getTitleByNum($data['book_cnt']);
        $data['books'] = array_slice($data['books'], 0, $books_cnt);
        $res['data'] = $data;
        return $res;
    }

    public function getPosterDataShort($openid, $groupId)
    {
        //藏书 排名 评分 超越 借入 借出 浏览 被浏览
        //藏书 排名 评分 超越 成员 在借 归还人次 被浏览
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        if ($groupId) {
            $group = $this->capsule->table('group')->where('id', $groupId)->first();
            $name = empty($group['group_name']) ? '' : $group['group_name'];
        } else {
            $name = $user['nickname'];
        }
        $data = [
            'name' => $name,
            'title' => '',
            'book_cnt' => 0,    //藏书
            'book_rank' => 0,   //藏书排名
            'avg_rating' => 0,  //评分
            'taste_percent' => 0, //品味超越
            'borrow_cnt' => 0,  //借入
            'lend_cnt' => 0,    //借出
            'visit_cnt' => 0,    //浏览
            'be_visited_cnt' => 0, //被浏览
            'member_cnt' => 0,  //成员数
            'return_cnt' => 0,  //归还人次
        ];
        $builder = $this->capsule->table('book_share AS bs')
            ->leftJoin('book AS b', 'b.id', '=', 'bs.book_id')
            ->where('bs.share_status', 1)
            ->groupBy('bs.book_id')
            ->orderBy('b.rating', 'desc')
            ->limit(500)
            ->select('b.id','b.title','b.author','b.rating','b.image','b.tags')
            ->selectRaw('count('.$this->capsule->getConnection()->getTablePrefix().'bs.id) AS cnt');
        $rankBuilder = $this->capsule->table('book_share AS bs')
            ->where('bs.share_status', 1)
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
            $rankBuilder->where('bs.group_id', '>', 0)->groupBy('bs.group_id');
        } else {
            $builder->where('bs.group_id', 0)->where('bs.owner_openid', $openid);
            $rankBuilder->where('bs.group_id', 0)->groupBy(['bs.owner_openid', 'bs.group_id']);
        }
        $booksData = $builder->get();

        $allRating = 0;
        foreach ($booksData as $booksDatum) {
            $data['book_cnt'] += intval($booksDatum['cnt']);
            $allRating += $booksDatum['rating'] * $booksDatum['cnt'];
        }
        $rank = $rankBuilder->havingRaw('count('.$this->capsule->getConnection()->getTablePrefix().'bs.id) > '.$data['book_cnt'])->get();
        $data['book_rank'] = count($rank) + 1;
        $data['avg_rating'] = $data['book_cnt'] > 0 ? round($allRating/$data['book_cnt'], 1) : 0;
        $data['taste_percent'] = $data['avg_rating'] > 2 && $data['book_cnt'] > 0 ?
            round($allRating/$data['book_cnt'] * 12.38 - 23.75) : 0;
        $data['title'] = $groupId? $this->getGroupTitleByNum($data['book_cnt']): $this->getTitleByNum($data['book_cnt']);

        //记录
        $visit = new Visit();
        if ($groupId) {
            $visitData = $visit->getVisitDataGroup($groupId);
            //成员 在借 归还人次 被浏览
            $data['member_cnt'] = $visitData['member_cnt'];
            $data['lend_cnt'] = $visitData['lend_cnt'];
            $data['return_cnt'] = $visitData['return_cnt'];
            $data['be_visited_cnt'] = $visitData['be_visited_cnt'];
        } else {
            $visitData = $visit->getVisitDataUser($openid);
            //借入 借出 浏览 被浏览
            $data['borrow_cnt'] = $visitData['borrow_cnt'];
            $data['lend_cnt'] = $visitData['lend_cnt'];
            $data['visit_cnt'] = $visitData['visit_cnt'];
            $data['be_visited_cnt'] = $visitData['be_visited_cnt'];
        }

        $res['data'] = $data;
        return $res;
    }

    protected function getTitleByNum($num)
    {
        $titleArr = [
            1 => ['白丁','伴读书童','穷酸秀才','举人','进士','状元','学士','司徒','太傅','圣贤'],
            2 => ['平民','九品芝麻官','八品县丞','七品知县','六品通判','五品郎中','四品道员','三品御史','二品侍郎','一品大学士'],
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
        $class_type = array_rand($titleArr);
        return isset($titleArr[$class_type][$class_pos]) ? $titleArr[$class_type][$class_pos] : '未知';
    }

    protected function getGroupTitleByNum($num)
    {
        $titleArr = [
            '嘉兴烟雨楼','水榭听香','聚贤庄','桃花岛','百花谷','五指峰','冰火岛','思过崖','终南古墓','绝情谷',
            '黑木崖','光明顶','天龙寺','飘渺峰','灵鹫宫','罗汉堂','藏经阁','少室山','般若堂','菩提院',
            '琅擐福地','还施水阁','达摩院','侠客岛','笑傲江湖',
        ];
        $pos = [
            5 => 0,  //(0-5]
            10 => 1, //(5-10]
            15 => 2, //(10-15]
            20 => 3, //(15-20]
            25 => 4, //(20-25]
            30 => 5, //(25-30]
            35 => 6,//(30-35]
            40 => 7,//(35-40]
            45 => 8,//(40-45]
            50 => 9,//(45-50]
            55 => 10,//(50-55]
            60 => 11,//(55-60]
            65 => 12,//(60-65]
            70 => 13,//(65-70]
            75 => 14,//(70-75]
            80 => 15,//(75-80]
            85 => 16,//(80-85]
            90 => 17,//(85-90]
            95 => 18,//(90-95]
            100 => 19,//(95-100]
            150 => 20,//(100-150]
            200 => 21,//(150-200]
            250 => 22,//(200-250]
            300 => 23,//(250-300]
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
        return isset($titleArr[$class_pos]) ? $titleArr[$class_pos] : '未知';
    }

}