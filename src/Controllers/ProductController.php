<?php

namespace Ejoy\Shop\Controllers;

use App\Admin\Extensions\Actions\CustomActions;
use App\Models\Product;

use Ejoy\Shop\Models\ProductCategory;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
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

            $content->header('产品');
            $content->description('列表');

            $content->body($this->grid());
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

            $content->header('产品');
            $content->description('编辑');

            $content->body($this->form($id)->edit($id));
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

            $content->header('产品');
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
        return Admin::grid(Product::class, function (Grid $grid) {
            $channelNameArr=Admin::user()->channelNameArr();
            $grid->model()->whereIn('channel',array_keys($channelNameArr));
            $grid->disableExport()->disableRowSelector()->disableActions();
            $grid->filter(function($filter) use($channelNameArr){
                $filter->disableIdFilter();
                $categoryTreeArr=ProductCategory::formatTree(array_keys($channelNameArr));
                $filter->in('channel','渠道')->multipleSelect($channelNameArr);
                $filter->in('category_id','产品分类')->select($categoryTreeArr);
                $filter->like('name', '产品名称');
            });
            $grid->model()->orderByDesc('id');
            $grid->id('ID')->sortable();
            $grid->column('channel','渠道标识')->using($channelNameArr);
            $grid->name('名称');
            $grid->column('category.name','分类名称');
            $grid->updated_at('更新时间');
            $grid->column('is_sale','是否上架')->switch(config('state.common.switch_flag'));
            $grid->column('actions','操作')->displayUsing(CustomActions::class);
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id=null)
    {
        return Admin::form(Product::class, function (Form $form) use ($id) {
            $channelNameArr=Admin::user()->channelNameArr();
            $categoryTreeArr=ProductCategory::formatTree(array_keys($channelNameArr));
            //产品分类只取 二三级做group
            $form->display('id', 'ID');
            $form->select('channel','渠道')->options($channelNameArr);
            $form->select('category_id','产品分类')->options($categoryTreeArr)->rules("required|integer");
            $form->text('name','产品名称')->rules("required");

            $form->multipleFile('thumb','封面图')->move('upload/product')->sortable()->removable();
            $form->custom_table('attr','属性/规格',function(Form\NestedForm $table) use($id){
                $table->hidden('id');
                $table->hidden('product_id')->default($id);
                $table->hidden('name',trans('nrxl.product_attr_name'))->default('规格');
                $table->text('value',trans('nrxl.product_attr_value'))->default('');
                $table->decimal('price',trans('nrxl.product_attr_price'));
                $table->display('saled_num',trans('nrxl.product_saled_num'));
                $table->number('num',trans('nrxl.product_attr_num'))->default(0)->min(0);
            });
            $form->display('total_sale','已售数量')->setWidth(2);
            $form->editor('description','描述')->options(['height'=>'768px']);
            $form->decimal('express_cost','快递费')->default(0.00);
            $form->switch('is_sale','是否上架')->options(config('state.product.switch_is_sale'));
//            $productIdArr=Product::query()->whereIn('channel',$channerIdArr)->select(DB::raw("CONCAT_WS('-',`id`,`name`) as name"),'id')->orderByDesc('id')->get()->pluck('name','id');;
//            $form->multipleSelect('recommend_ids','推荐产品')->options($productIdArr);
            $form->display('created_at', '创建时间');
            $form->display('updated_at', '更新时间');
        });
    }
}
