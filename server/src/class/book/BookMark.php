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

    public function saveBookmark($bookId, $openid, $params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );
        $book = $this->capsule->table('book_mark')->where('book_id', $bookId)->where('openid', $openid)->first();
        if (!$book) {
            return [
                'status' => 6000,
                'message' => '找不到标签',
            ];
        }
        $is_mark = isset($params['is_mark']) && intval($params['is_mark']) > 0 ? 1 : 0;
        $page_read = isset($params['page_read']) ? intval($params['page_read']) : 0;
        $page_all = isset($params['page_all']) ? intval($params['page_all']) : 0;
        $is_finish = isset($params['is_finish']) && intval($params['is_finish']) > 0 ? 1 : 0;

        if ($page_read > $page_all) {
            $page_read = $page_all;
        }

        $update = [
            'is_mark' => $is_mark,
            'page_read' => $page_read,
            'page_all' => $page_all,
            'is_finish' => $is_finish,
        ];
        $this->capsule->table('book_mark')->where('book_id', $bookId)->where('openid', $openid)->update($update);
        return $res;
    }


}