<?php

namespace CP\book;

use CP\common\AbstractModel;

class BookMark extends AbstractModel
{


    public function getBookmark($bookId, $openid)
    {
        if (!$bookId || !$openid) {
            return [];
        }
        $bookmark = $this->capsule->table('book_mark')->where('book_id', $bookId)->where('openid', $openid)->first();
        if (empty($bookmark)) {
            $insert = [
                'book_id' => $bookId,
                'openid' => $openid,
                'is_mark' => 0,
                'page_read' => 0,
                'page_all' => 0,
                'is_finish' => 0,
            ];
            $this->capsule->table('book_mark')->insert($insert);
            $bookmark = $insert;
        }
        unset($bookmark['id'], $bookmark['book_id'], $bookmark['openid']);
        return $bookmark;
    }



}