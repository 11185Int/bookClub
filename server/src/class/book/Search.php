<?php

namespace CP\book;

use CP\api\Douban;
use CP\common\AbstractModel;
use CP\common\Isbn;
use Slim\Http\UploadedFile;

class Search extends AbstractModel
{

    public function getHotSearch($openid)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $hotSearch = $this->capsule->table('hot_search')->where('type', 'hot')->get();
        $hotRank = $this->capsule->table('hot_search AS h')
            ->leftJoin('book AS b', 'b.isbn13', '=', 'h.key')
            ->select('b.id','b.isbn10','b.isbn13','b.title','b.image')
            ->where('h.type', 'rank')
            ->get();
        $hot = [];
        $rank = [];
        foreach ($hotSearch as $item) {
            $hot[] = $item['key'];
        }
        foreach ($hotRank as $item) {
            $item['is_add'] = 0;
            $rank[] = $item;
        }

        $res['data'] = [
            'hot' => $hot,
            'rank' => $rank,
        ];
        return $res;
    }

}