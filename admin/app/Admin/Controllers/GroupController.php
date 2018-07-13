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

            $model = $grid->model();
            $model->select('group.id', 'group.group_name', 'group.group_amount', 'user.nickname',
                'group.create_time', 'group.summary')
                ->selectRaw('count(tb_bs.id) AS book_amount');
            $model->where('group_amount', '>', 0);
            $model->leftJoin('user', 'user.openid', '=', 'group.creator_openid');
            $model->leftJoin('book_share AS bs', function($join) {
                $join->on('bs.group_id', '=', 'group.id');
                $join->where('bs.share_status', '=', 1);
            })->groupBy('group.id');

            $grid->disableActions()
                ->disableRowSelector()
                ->disableCreation();

            $grid->id('ID')->sortable();
            $grid->column('group_name', '小组名称');
            $grid->column('group_amount', '人数')->sortable();
            $grid->column('book_amount', '藏书量')->sortable();
            $grid->column('0', '个人/小组')->display(function () {
                return $this->group_amount > 1? '小组' : '个人';
            });
            $grid->column('nickname', '创建人');
            $grid->column('create_time', '创建时间')->display(function ($create_time) {
                return date('Y-m-d H:i:s', $create_time);
            });
            $grid->column('summary', '小组简介')->setAttributes(['width'=>300]);

        });
    }


}
