<?php

namespace App\Admin\Controllers;

use App\Models\Group;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

class GroupController extends Controller
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

            $content->header('图书小组');
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
        return Admin::grid(Group::class, function (Grid $grid) {

            $grid->model()->where('group_amount', '>', 0);

            $grid->disableActions()
                ->disableRowSelector()
                ->disableCreation();

            $grid->id('ID')->sortable();
            $grid->column('group_name', '小组名称');
            $grid->column('group_amount', '人数')->sortable();
            $grid->column('0', '个人/小组')->display(function () {
                return $this->group_amount > 1? '小组' : '个人';
            });
            $grid->column('create_time', '创建时间')->display(function ($create_time) {
                return date('Y-m-d H:i:s', $create_time);
            });
            $grid->column('summary', '小组简介');

        });
    }


}
