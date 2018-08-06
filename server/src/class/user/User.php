<?php

namespace CP\user;

use CP\common\AbstractModel;

class User extends AbstractModel
{

    public function getOpenIdByUserId($user_id)
    {
        if (!$user_id) {
            return '';
        }
        $user = $this->capsule->table('user')->where('id', $user_id)->first();
        if (!$user) {
            return '';
        }
        return $user['openid'];
    }

    public function getSharerInfo($openid, $group_id = 0)
    {
        $builder = $this->capsule->table('user AS u')
            ->select('u.id','u.nickname', 'u.realname', 'u.headimgurl');
        if ($group_id) {
            $builder->leftJoin('user_group AS ug', 'ug.openid', '=', 'u.openid')
                ->where('ug.group_id', $group_id)
                ->select('u.id','u.nickname', 'u.realname', 'u.headimgurl','ug.realname AS g_realname');
        }
        $info = $builder->where('u.openid', $openid)->first();
        if (empty($info)) {
            return [];
        }
        return [
            'user_id' => $info['id'],
            'realname' => !empty($info['g_realname']) ? $info['g_realname'] :
                !empty($info['realname']) ? $info['realname'] : $info['nickname'],
            'headimgurl' => $info['headimgurl'],
        ];
    }

    public function getSharerGroupInfo($group_id)
    {
        $info = $this->capsule->table('group')->where('id', $group_id)->first();
        if (empty($info)) {
            return [];
        }
        return [
            'user_id' => 0,
            'group_id' => $info['id'],
            'realname' => $info['group_name'],
            'headimgurl' => $info['headimgurl'] ?: '',
        ];
    }

}