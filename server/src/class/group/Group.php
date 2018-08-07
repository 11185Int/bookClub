<?php

namespace CP\group;

use CP\api\Wechat;
use CP\common\AbstractModel;
use CP\common\OpenKey;
use Slim\Http\UploadedFile;

class Group extends AbstractModel
{

    public function create($openid, $name, $image, $config)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        if (!$name || mb_strlen($name,'utf8') > 10) {
            return [
                'status' => 99999,
                'message' => '名字不超过10个汉字',
            ];
        }
        $imageUrl = '';
        if ($image) {
            if (!in_array($image->getClientMediaType(),
                    ['image/png','image/jpeg','image/jpg','image/gif','image/bmp','image/tiff','image/svg+xml'])) {
                return [
                    'status' => 99999,
                    'message' => '图片格式错误',
                ];
            }
            if ($image->getSize() > 2 * 1024 * 1024) {
                return [
                    'status' => 99999,
                    'message' => '图片超过2M',
                ];
            }
            if ($image->getError() === UPLOAD_ERR_OK) {
                $directory = __DIR__. '/../../../public/resources/group/image/';
                $filename = $this->moveUploadedFile($directory, $image);
                $domain = $config['domain'];
                $imageUrl = $domain . 'resources/group/image/'. $filename;
            }
        }

        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        $this->capsule->getConnection()->beginTransaction();
        $group = [
            'group_name' => $name,
            'group_amount' => 1,
            'creator_openid' => $openid,
            'create_time' => time(),
            'headimgurl' => $imageUrl,
        ];
        $r1 = $this->capsule->table('group')->insertGetId($group);
        $this->capsule->table('user_group')->where('openid', $openid)->update(['is_current' => 0]);
        $user_group = [
            'group_id' => $r1,
            'openid' => $openid,
            'is_admin' => 1,
            'is_current' => 1,
            'realname' => $user['realname'] ?: '',
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
                'message' => '创建小组失败',
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
                'message' => '缺少小组ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '小组不存在',
            ];
        }
        $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)->first();

        $current_group = $this->capsule->table('user_group')->where('openid', $openid)->where('is_current', 1)->first();
        $openKey = new OpenKey();
        $data = [
            'group_id' => $openKey->getOpenKey($group['id'], OpenKey::TYPE_GROUP_ID),
            'group_name' => $group['group_name'],
            'headimgurl' => $group['headimgurl'] ?: '',
            'group_amount' => $group['group_amount'],
            'create_time' => date('Y/m/d', $group['create_time']),
            'summary' => $group['summary'],
            'is_admin' => isset($user_group['is_admin'])? intval($user_group['is_admin']) : 0,
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
                'message' => '缺少小组ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '小组不存在',
            ];
        }
        $user_group = $this->capsule->table('user_group')->where('openid', $openId)->where('group_id', $groupId)->get();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此小组',
            ];
        }
        $data = $this->capsule->table('user_group')
            ->leftJoin('user', 'user.openid', '=', 'user_group.openid')
            ->select('user.id AS user_id','user.nickname','user.headimgurl','user.realname AS user_realname','user.openid',
                'user_group.id AS user_group_id',
                'user_group.is_current','user_group.is_admin','user_group.realname','user_group.phone')
            ->where('user_group.group_id', $groupId)
            ->orderBy('user_group.id', 'asc')
            ->get();

        $openKey = new OpenKey();
        $list = [];
        foreach ($data as $datum) {
            $list[] = [
                'user_group_id' => $datum['user_group_id'],
                'user_id' => $openKey->getOpenKey($datum['user_id'], OpenKey::TYPE_USER_ID),
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
                'message' => '缺少小组ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '小组不存在',
            ];
        }
        $user_group = $this->capsule->table('user_group')->where('openid', $openid)
            ->where('group_id', $groupId)->first();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此小组',
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
                'message' => '无操作权限，此人不在该小组',
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

            //取消该成员在当前小组的所有分享
            $this->capsule->table('book_share')
                ->where('owner_openid', $target['openid'])
                ->where('group_id', $groupId)
                ->update(['share_status' => 0]);
            //删除成员
            $r1 = $this->capsule->table('user_group')->delete($user_group_id);
            if ($r1) {
                $this->capsule->table('group')->where('id', $groupId)->decrement('group_amount');
            }

            //设置一个默认小组
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
                'message' => '缺少小组ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '小组不存在',
            ];
        }
        $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)->get();
        if (!empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '已加入该小组',
            ];
        }
        $user_group_exist = $this->capsule->table('user_group')->where('group_id', $groupId)->count();

        $this->capsule->getConnection()->beginTransaction();

        $this->capsule->table('user_group')->where('openid', $openid)->update(['is_current' => 0]);
        $user_group_insert = [
            'group_id' => intval($groupId),
            'openid' => $openid,
            'is_current' => 1,
            'is_admin' => $user_group_exist > 0 ? 0 : 1,
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
                'message' => '加入小组失败',
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
            ->select('group.id AS group_id','group.group_name','group.headimgurl','user_group.is_current','user_group.is_admin')
            ->where('user_group.openid', $openid)
            ->orderBy('user_group.id', 'asc')
            ->get();
        $openKey = new OpenKey();
        foreach ($data as $key => $item) {
            $data[$key]['group_id'] = $openKey->getOpenKey($item['group_id'], OpenKey::TYPE_GROUP_ID);
        }
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
                'message' => '缺少小组ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '小组不存在',
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
                'message' => '切换小组失败',
            ];
        }

        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        return $res;
    }

    public function edit($groupId, $openid, $name, $image, $summary, $realname, $config)
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
                'message' => '名称不超过10个汉字',
            ];
        }
        if (mb_strlen($summary,'utf8') > 80) {
            return [
                'status' => 99999,
                'message' => '小组简介不超过80个汉字',
            ];
        }
        if (mb_strlen($realname,'utf8') > 10) {
            return [
                'status' => 99999,
                'message' => '昵称不超过10个汉字',
            ];
        }
        if ($name && $exist['is_admin']) {
            $this->capsule->table('group')->where('id', $groupId)->update(['group_name' => $name]);
        }
        if ($summary && $exist['is_admin']) {
            $this->capsule->table('group')->where('id', $groupId)->update(['summary' => $summary]);
        }
        if ($image && $exist['is_admin']) {
            if (!in_array($image->getClientMediaType(),
                ['image/png','image/jpeg','image/jpg','image/gif','image/bmp','image/tiff','image/svg+xml'])) {
                return [
                    'status' => 99999,
                    'message' => '图片格式错误',
                ];
            }
            if ($image->getSize() > 2 * 1024 * 1024) {
                return [
                    'status' => 99999,
                    'message' => '图片超过2M',
                ];
            }
            if ($image->getError() === UPLOAD_ERR_OK) {
                $directory = __DIR__. '/../../../public/resources/group/image/';
                $filename = $this->moveUploadedFile($directory, $image);
                $domain = $config['domain'];
                $imageUrl = $domain . 'resources/group/image/'. $filename;
                $this->capsule->table('group')->where('id', $groupId)->update(['headimgurl' => $imageUrl]);
            }
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
                'message' => '缺少小组ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '小组不存在',
            ];
        }
        $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('group_id', $groupId)
            ->first();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此小组',
            ];
        }
        $admin_user_group = $this->capsule->table('user_group')->where('group_id', $groupId)->where('is_admin', 1)
            ->first();
        $admin_openid = $admin_user_group['openid'];
        $res = $this->deleteMember($groupId, $admin_openid, $user_group['id'], 0);

        return $res;
    }

    public function transfer($groupId, $openid, $to_user_group_id)
    {
        if (!$groupId) {
            return [
                'status' => 99999,
                'message' => '缺少小组ID',
            ];
        }
        $group = $this->capsule->table('group')->find($groupId);
        if (empty($group)) {
            return [
                'status' => 99999,
                'message' => '小组不存在',
            ];
        }
        $user_group = $this->capsule->table('user_group')->where('group_id', $groupId)->where('openid', $openid)
            ->where('is_admin', 1)->first();
        if (empty($user_group)) {
            return [
                'status' => 99999,
                'message' => '还未加入此小组，或不是管理员',
            ];
        }
        $to_user_group = $this->capsule->table('user_group')->find($to_user_group_id);
        if (empty($to_user_group)) {
            return [
                'status' => 99999,
                'message' => '小组成员id错误',
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

    public function getWxCode($openid, $params)
    {
        $scene = $params['scene'];
        $page = $params['page'];
        $width = $params['width'];
        $auto_color = $params['auto_color'];
        $line_color = $params['line_color'];
        $wechat = new Wechat();
        $config = $this->app->get('settings')['config'];
        $qrcode = $wechat->getWxCode($config, $scene, $page, $width, $auto_color, $line_color);

        $res = [
            'status' => 0,
            'message' => 'success',
            'data' => [
                'qrcode' => $qrcode,
            ]
        ];
        return $res;
    }

    protected function moveUploadedFile($directory, UploadedFile $uploadedFile)
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }
}