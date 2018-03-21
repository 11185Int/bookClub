<?php

namespace CP\group;

use CP\common\AbstractModel;

class Group extends AbstractModel
{

    public function create($openid, $name)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        if (!$name || mb_strlen($name,'utf8') > 10) {
            return [
                'status' => 99999,
                'message' => '图书馆名称长度错误',
            ];
        }

        $this->capsule->getConnection()->beginTransaction();
        $group = [
            'group_name' => $name,
            'group_amount' => 1,
            'creator_openid' => $openid,
            'create_time' => time(),
        ];
        $r1 = $this->capsule->table('group')->insertGetId($group);
        $this->capsule->table('user_group')->where('openid', $openid)->update(['is_current' => 0]);
        $user_group = [
            'group_id' => $r1,
            'openid' => $openid,
            'is_admin' => 1,
            'is_current' => 1,
            'realname' => '',
            'phone' => '',
            'create_time' => time(),
        ];
        $r2 = $this->capsule->table('user_group')->insert($user_group);

        if ($r1 && $r2) {
            $this->capsule->getConnection()->commit();
        } else {
            $this->capsule->getConnection()->rollBack();
            return [
                'status' => 99999,
                'message' => '创建图书馆失败',
            ];
        }

        return $res;
    }

    public function detail($openid, $groupId)
    {
        $group = $this->capsule->table('group')->find($groupId);
        $data = [
            'group_id' => $group['id'],
            'group_name' => $group['group_name'],
            'group_amount' => $group['group_amount'],
            'create_time' => date('Y/m/d', $group['create_time']),
            'is_admin' => $group['creator_openid'] == $openid ? 1 : 0,
        ];
        $res = array(
            'status' => 0,
            'message' => 'success',
            'data' => $data,
        );
        return $res;
    }

    public function getList($openId, $groupId)
    {
        if (!$groupId) {
            return [
                'status' => 99999,
                'message' => '缺少图书馆ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '图书馆不存在',
            ];
        }
        $user_group = $this->capsule->table('user_group')->where('openid', $openId)->where('group_id', $groupId)->get();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此图书馆',
            ];
        }
        $data = $this->capsule->table('user_group')
            ->leftJoin('user', 'user.openid', '=', 'user_group.openid')
            ->select('user.nickname','user.headimgurl','user.realname AS user_realname','user.openid',
                'user_group.id AS user_group_id',
                'user_group.is_current','user_group.is_admin','user_group.realname','user_group.phone')
            ->where('user_group.group_id', $groupId)
            ->orderBy('user_group.id', 'asc')
            ->get();

        $list = [];
        foreach ($data as $datum) {
            $list[] = [
                'user_group_id' => $datum['user_group_id'],
                'headimgurl' => $datum['headimgurl'],
                'realname' => $datum['realname'] ?: $datum['user_realname'] ?: $datum['nickname'],
                'is_current' => $datum['is_current'],
                'is_admin' => $datum['is_admin'],
                'is_me' => $datum['openid'] == $openId ? 1 : 0,
            ];
        }

        $res = array(
            'status' => 0,
            'message' => 'success',
            'data' => $list,
        );
        return $res;
    }

    public function deleteMember($groupId, $openid, $user_group_id)
    {

        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        return $res;
    }

    public function join($groupId, $openid, $realname, $phone)
    {
        if (!$groupId) {
            return [
                'status' => 99999,
                'message' => '缺少图书馆ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '图书馆不存在',
            ];
        }
        $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)->get();
        if (!empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '已加入该图书馆',
            ];
        }

        $this->capsule->getConnection()->beginTransaction();

        $this->capsule->table('user_group')->where('openid', $openid)->update(['is_current' => 0]);
        $user_group_insert = [
            'group_id' => intval($groupId),
            'openid' => $openid,
            'is_current' => 1,
            'is_admin' => 0,
            'realname' => $realname,
            'phone' => $phone,
            'create_time' => time(),
        ];
        $r1 = $this->capsule->table('user_group')->insert($user_group_insert);
        $r2 = $this->capsule->table('group')->where('id', $groupId)->increment('group_amount');

        if ($r1 && $r2) {
            $this->capsule->getConnection()->commit();
        } else {
            $this->capsule->getConnection()->rollBack();
            return [
                'status' => 99999,
                'message' => '加入图书馆失败',
            ];
        }

        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        return $res;
    }

    public function getGroupList($openid)
    {
        $data = $this->capsule->table('user_group')
            ->leftJoin('group', 'group.id', '=', 'user_group.group_id')
            ->select('group.id AS group_id','group.group_name','user_group.is_current')
            ->where('user_group.openid', $openid)
            ->orderBy('user_group.id', 'asc')
            ->get();
        $res = array(
            'status' => 0,
            'message' => 'success',
            'data' => $data,
        );
        return $res;
    }

    public function switchGroup($groupId, $openid)
    {
        if (!$groupId) {
            return [
                'status' => 99999,
                'message' => '缺少图书馆ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '图书馆不存在',
            ];
        }

        $this->capsule->getConnection()->beginTransaction();
        $this->capsule->table('user_group')->where('openid', $openid)->update(['is_current' => 0]);
        $r1 = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)
            ->update(['is_current' => 1]);

        if ($r1) {
            $this->capsule->getConnection()->commit();
        } else {
            $this->capsule->getConnection()->rollBack();
            return [
                'status' => 99999,
                'message' => '切换图书馆失败',
            ];
        }

        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        return $res;
    }

}