<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'book';

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'isbn10',
        'isbn13',
        'category_id',
        'title',
        'author',
        'rating',
        'publisher',
        'price',
        'image',
        'tags',
        'pubdate',
        'summary',
        'ismanual',
        'openid',
        'hd_image',
    ];
}
