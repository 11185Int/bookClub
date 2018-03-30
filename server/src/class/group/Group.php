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
        $res['data']['group_id'] = $r1;

        return $res;
    }

    public function detail($openid, $groupId)
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
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此图书馆',
            ];
        }
        $current_group = $this->capsule->table('user_group')->where('openid', $openid)->where('is_current', 1)->first();
        $data = [
            'group_id' => $group['id'],
            'group_name' => $group['group_name'],
            'group_amount' => $group['group_amount'],
            'create_time' => date('Y/m/d', $group['create_time']),
            'summary' => $group['summary'],
            'is_admin' => intval($user_group['is_admin']),
            'is_current' => $groupId == $current_group['group_id'] ? 1 : 0,
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

    /**
     * 此方法有内部调用，修改谨慎
     * @param $groupId
     * @param $openid int 操作人的openid
     * @param $user_group_id int 删除的成员id
     * @param $force int 是否强制删除 1是 0不是
     * @return array
     */
    public function deleteMember($groupId, $openid, $user_group_id, $force)
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
        $user_group = $this->capsule->table('user_group')->where('openid', $openid)
            ->where('group_id', $groupId)->first();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此图书馆',
            ];
        }
        //检查是否有操作权限
        if ($user_group['is_admin'] == 0) {
            return [
                'status' => 99999,
                'message' => '无操作权限',
            ];
        }
        //检查被删人是否在此组
        $target = $this->capsule->table('user_group')->find($user_group_id);
        if ($target['group_id'] != $groupId) {
            return [
                'status' => 99999,
                'message' => '无操作权限，此人不在该图书馆',
            ];
        }
        //检查组织里是否只剩一个人
        $user_group = $this->capsule->table('user_group')->where('group_id', $groupId)->get();
        if (count($user_group) > 1 && $target['openid'] == $openid) {
            return [
                'status' => 99999,
                'message' => '请先删除其他成员',
            ];
        }
        //检查是否有未归还的图书
        $unreturn = $this->capsule->table('book_borrow AS borrow')
            ->leftJoin('book_share AS share', 'share.id', '=', 'borrow.book_share_id')
            ->select('borrow.id', 'borrow.book_share_id')
            ->where('share.group_id', $groupId)
            ->where('borrow.borrower_openid', $target['openid'])
            ->where('borrow.return_status', 0)
            ->get();
        if (count($unreturn) > 0 && !$force) { //非强制移除
            return [
                'status' => 10007,
                'message' => '还有未归还的图书',
            ];
        }
        //获取目标其他组，默认一个作为当前
        $next_user_group = $this->capsule->table('user_group')->where('openid', $target['openid'])
            ->where('is_current', 0)->orderBy('id', 'desc')->first();
        $next_group_id = empty($next_user_group) ? 0 : $next_user_group['id'];

        $this->capsule->getConnection()->beginTransaction();
        try {
            if (count($unreturn) > 0 && $force) { //强制移除，自动归还所有未归还的书
                $kv = array(
                    'return_status' => 1,
                    'return_time' => time(),
                    'remark' => '移除成员，自动归还',
                );
                $borrowIds = array_column($unreturn, 'id');
                $this->capsule->table('book_borrow')->whereIn('id', $borrowIds)->update($kv);
                $shareIds = array_column($unreturn, 'book_share_id');
                $this->capsule->table('book_share')->whereIn('id', $shareIds)->update(['lend_status' => 1]);
            }

            //取消该成员在当前图书馆的所有分享
            $this->capsule->table('book_share')
                ->where('owner_openid', $target['openid'])
                ->where('group_id', $groupId)
                ->update(['share_status' => 0]);
            //删除成员
            $this->capsule->table('user_group')->delete($user_group_id);
            $this->capsule->table('group')->where('id', $groupId)->decrement('group_amount');

            //设置一个默认图书馆
            if ($next_group_id) {
                $this->capsule->table('user_group')->where('id', $next_group_id)->update(['is_current' => 1]);
            }

        } catch (\Exception $e) {
            $this->capsule->getConnection()->rollBack();
        }
        $this->capsule->getConnection()->commit();

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

    public function edit($groupId, $openid, $name, $summary, $realname)
    {
        $exist = $this->capsule->table('user_group')
            ->where('group_id', $groupId)
            ->where('openid', $openid)
            ->first();
        if (empty($exist)) {
            return [
                'status' => 99999,
                'message' => '无权限操作，参数错误',
            ];
        }
        if (mb_strlen($name,'utf8') > 10) {
            return [
                'status' => 99999,
                'message' => '图书馆名称长度错误',
            ];
        }
        if (mb_strlen($summary,'utf8') > 80) {
            return [
                'status' => 99999,
                'message' => '图书馆简介长度超过80',
            ];
        }
        if (mb_strlen($realname,'utf8') > 10) {
            return [
                'status' => 99999,
                'message' => '昵称长度错误',
            ];
        }
        if ($name && $exist['is_admin']) {
            $this->capsule->table('group')->where('id', $groupId)->update(['group_name' => $name]);
        }
        if ($summary && $exist['is_admin']) {
            $this->capsule->table('group')->where('id', $groupId)->update(['summary' => $summary]);
        }
        if ($realname) {
            $this->capsule->table('user_group')->where('group_id', $groupId)->where('openid', $openid)
                ->update(['realname' => $realname]);
        }

        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        return $res;
    }

    public function quit($groupId, $openid)
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
        $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)
            ->first();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此图书馆',
            ];
        }
        $creator_openid = $group['creator_openid'];
        $res = $this->deleteMember($groupId, $creator_openid, $user_group['id'], 0);

        return $res;
    }

    public function transfer($groupId, $openid, $to_user_group_id)
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
        $user_group = $this->capsule->table('user_group')->where('group_id', $groupId)->where('openid', $openid)
            ->where('is_admin', 1)->first();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此图书馆，或不是管理员',
            ];
        }
        $to_user_group = $this->capsule->table('user_group')->find($to_user_group_id);
        if (empty($to_user_group)) {
            return [
                'status' => 99999,
                'message' => '图书馆成员id错误',
            ];
        }
        $to_openid = $to_user_group['openid'];
        if ($openid == $to_openid) {
            return [
                'status' => 0,
                'message' => 'success',
            ];
        }

        $this->capsule->getConnection()->beginTransaction();
        try {
            $r1 = $this->capsule->table('user_group')->where('group_id', $groupId)->where('openid', $openid)
                ->update(['is_admin' => 0]);
            $r2 = $this->capsule->table('user_group')->where('group_id', $groupId)->where('openid', $to_openid)
                ->update(['is_admin' => 1]);
            if ($r1 && $r2) {
                $this->capsule->getConnection()->commit();
            } else {
                $this->capsule->getConnection()->rollBack();
                return [
                    'status' => 99999,
                    'message' => '转让失败',
                ];
            }
        } catch (\Exception $e) {
            $this->capsule->getConnection()->rollBack();
        }

        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        return $res;
    }

}