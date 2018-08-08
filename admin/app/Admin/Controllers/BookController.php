<?php

namespace App\Admin\Controllers;

use App\Models\Book;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

class BookController extends Controller
{
    use ModelForm;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {

            $content->header('图书信息');
            $content->description('');

            $content->body($this->grid());

        });
    }


    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(Book::class, function (Grid $grid) {

            $model = $grid->model();
            $model->select('book.id','isbn10','isbn13','title','author','image','ismanual','user.nickname');
            $model->leftJoin('user', 'user.openid', '=', 'book.openid');

            $grid->disableActions()
                ->disableRowSelector()
                ->disableCreation();
            $grid->filter(function ($filter) {

                $filter->where(function ($query) {
                    $query->where('isbn10', 'like', "%{$this->input}%")
                        ->orWhere('isbn13', 'like', "%{$this->input}%");
                }, 'isbn');
                $filter->equal('ismanual')->select(['1' => '手动添加', '0'=>'豆瓣抓取']);
            });

            $grid->id('ID')->sortable();
            $grid->column('isbn10', 'isbn10');
            $grid->column('isbn13', 'isbn13');
            $grid->column('title', '书名');
            $grid->column('author', '作者');
            $grid->column('image', '图片')->display(function () {
                return "<img width='100px' src='{$this->image}'/>";
            });
            $grid->column('ismanual', '手动添加')->display(function () {
                return $this->ismanual > 0 ? '手动添加':'豆瓣抓取';
            })->sortable();
            $grid->column('nickname', '添加人');


        });
    }


}
