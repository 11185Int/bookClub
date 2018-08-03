<?php

namespace CP\book;

use CP\common\AbstractModel;

class Visit extends AbstractModel
{

    public function getVisitDataUser($openid)
    {
        $summary = $this->getVisitSummaryUser($openid);
        $visit_cnt = $this->capsule->table('visit_history')->where('openid', $openid)->sum('view_cnt');

        $data = [

            'visit_cnt' => $visit_cnt,
            'be_visited_cnt' => $summary['view_cnt'],
        ];
        return $data;
    }

    public function getVisitDataGroup($groupId)
    {

        return [];
    }

    public function getVisitSummaryGroup($groupId)
    {
        if (!$groupId) {
            return [];
        }
        $summary = $this->capsule->table('visit_summary')->where('group_id', $groupId)->first();
        if (empty($summary)) {
            $insert = [
                'openid' => '',
                'group_id' => $groupId,
                'view_cnt' => 0,
            ];
            $id = $this->capsule->table('visit_summary')->insertGetId($insert);
            $insert['id'] = $id;
            $summary = $insert;
        }
        return $summary;
    }

    public function getVisitSummaryUser($openid)
    {
        if (!$openid) {
            return [];
        }
        $summary = $this->capsule->table('visit_summary')->where('group_id', 0)->where('openid', $openid)->first();
        if (empty($summary)) {
            $insert = [
                'openid' => $openid,
                'group_id' => 0,
                'view_cnt' => 0,
            ];
            $id = $this->capsule->table('visit_summary')->insertGetId($insert);
            $insert['id'] = $id;
            $summary = $insert;
        }
        return $summary;
    }

    public function increVisitedUser($dest_openid)
    {
        $dest_summary = $this->getVisitSummaryUser($dest_openid);
        if (empty($dest_summary['id'])) {
            return false;
        }
        $id = $dest_summary['id'];
        $this->capsule->table('visit_summary')->where('id', $id)->increment('view_cnt');
        return true;
    }

    public function addVisitHistoryUser($openid, $dest_openid)
    {
        $where = [
            'openid' => $openid,
            'dest_group_id' => 0,
            'dest_openid' => $dest_openid,
        ];
        $history = $this->capsule->table('visit_history')->where($where)->first();
        if (empty($history)) {
            $insert = [
                'openid' => $openid,
                'dest_group_id' => 0,
                'dest_openid' => $dest_openid,
                'latest_time' => time(),
                'view_cnt' => 1,
            ];
            $this->capsule->table('visit_history')->insert($insert);
        } else {
            $id = $history['id'];
            $this->capsule->table('visit_history')->where('id', $id)->increment('view_cnt');
        }
    }

    public function increVisitedGroup($dest_group_id)
    {
        $dest_summary = $this->getVisitSummaryGroup($dest_group_id);
        if (empty($dest_summary['id'])) {
            return false;
        }
        $id = $dest_summary['id'];
        $this->capsule->table('visit_summary')->where('id', $id)->increment('view_cnt');
        return true;
    }

    public function addVisitHistoryGroup($openid, $dest_group_id)
    {
        $where = [
            'openid' => $openid,
            'dest_group_id' => $dest_group_id,
        ];
        $history = $this->capsule->table('visit_history')->where($where)->first();
        if (empty($history)) {
            $insert = [
                'openid' => $openid,
                'dest_group_id' => $dest_group_id,
                'dest_openid' => '',
                'latest_time' => time(),
                'view_cnt' => 1,
            ];
            $this->capsule->table('visit_history')->insert($insert);
        } else {
            $id = $history['id'];
            $this->capsule->table('visit_history')->where('id', $id)->increment('view_cnt');
        }
    }


    public function visitGroup($openid, $dest_group_id)
    {
        $this->increVisitedGroup($dest_group_id);
        $this->addVisitHistoryGroup($openid, $dest_group_id);
    }

    public function visitUser($openid, $dest_openid)
    {
        $this->increVisitedUser($dest_openid);
        $this->addVisitHistoryUser($openid, $dest_openid);
    }



}