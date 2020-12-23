<?php

namespace App\Admin\Controllers;

use App\Models\ProductCategory;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Encore\Admin\Layout\Row;

class ProductCategoryController extends Controller
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

            $content->header('产品分类');
            $content->description('列表');
            $content->row(function(Row $row){
                $row->column(6,ProductCategory::tree(function($tree){
                    $tree->query(function($model){
                        $channelNameArr=Admin::user()->channelNameArr();
                        $model->whereIn('channel',array_keys($channelNameArr));
                        return $model->select(['id','name','parent_id','order_num']);
                    });
                    $tree->branch(function($branch){
                        return "{$branch['id']}--{$branch['name']} ";
                    });
                }));
            });
        });
    }

    /**
     * Show interface.
     *
     * @param $id
     * @return Content
     */
    public function show($id)
    {
        return Admin::content(function (Content $content) use ($id) {

            $content->header('产品分类');
            $content->description('查看');

            $content->body(Admin::show(ProductCategory::findOrFail($id), function (Show $show) {

                $show->id();

                $show->created_at();
                $show->updated_at();
            }));
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {

            $content->header('产品分类');
            $content->description('编辑');

            $content->body($this->form()->edit($id));
        });
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(function (Content $content) {

            $content->header('产品分类');
            $content->description('创建');

            $content->body($this->form());
        });
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(ProductCategory::class, function (Grid $grid) {

            $grid->id('ID')->sortable();

            $grid->created_at();
            $grid->updated_at();
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Admin::form(ProductCategory::class, function (Form $form) {
            $channelNameArr=Admin::user()->channelNameArr();
            $categoryListArr=ProductCategory::query()->where('channel',array_keys($channelNameArr))->get(['id','name','parent_id'])->toArray();
            $categoryTreeArr=[];
            $a=formatTreeList($categoryListArr);
            foreach($a as $k=>$v){
                $categoryTreeArr[$v['id']]=$v['name'];
            }
            $categoryTreeArr=['0'=>'--作为一级分类--']+$categoryTreeArr;
            $form->display('id', 'ID');
            $form->select('channel','渠道')->options($channelNameArr);
            $form->select('parent_id','上级分类')->options($categoryTreeArr)->rules('required');
            $form->text('name','分类名称')->rules('required');
            $form->image('thumb','封面图')->move('upload/product')->removable();
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '修改时间');
        });
    }
}
