<?php

namespace App\Admin\Controllers;

use App\Dateline;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Str;

class DatelineController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '大事记';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Dateline);

        $grid->quickSearch('content');

        $grid->filter(function($filter) {
            // 去掉默认的id过滤器
            $filter->disableIdFilter();
            $filter->column(1/2, function ($filter) {
                $filter->where(function ($query) {
                    $query->whereHas('blog', function ($query) {
                        $query->where('name', 'like', "%{$this->input}%");
                    });
                }, '博客名称');
            });
            $filter->column(1/2, function ($filter) {
                $filter->like('id', __('Id'));
            });
        });

        $grid->column('id', __('ID'));
        $grid->column('blog.name', __('博客名称'))->display(function ($value) {
            return '<a target="_blank" href="'.$this->blog->detail_url.'">'.Str::limit(strip_tags($value), 6).'</a>';
        });
        $grid->column('date', __('日期'))->sortable()->editable('datetime')->filter('range', 'datetime');
        $grid->column('content', __('内容'))->display(function ($content) {
            return Str::limit(strip_tags($content), 150);
        });
        $grid->column('status', '状态')->editable('select', Dateline::STATUS)->filter(Dateline::STATUS);
        $grid->column('created_at', __('创建时间'))->sortable()->filter('range', 'datetime');

        $grid->disableCreateButton();

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Dateline::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('date', __('日期'));
        $show->field('content', __('内容'));
        $show->field('created_at', __('创建时间'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Dateline);

        // $form->number('blog_id', __('Blog id'));
        $form->datetime('date', __('日期'))->default(date('Y-m-d H:i:s'));
        $form->summernote('content', __('内容'));
        $form->hidden('status');

        return $form;
    }
}
