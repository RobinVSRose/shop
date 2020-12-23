<?php

namespace App\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Product;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;

class PageController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {

            $content->header('列表');
            $content->description('页面');

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

            $content->header('编辑');
            $content->description('页面');

            $content->body($this->form($id));
        });
    }
//    public function destroy($id)
//    {
//        Order::where('id',$id)->update(['status'=>0]);
//        return response()->json([
//            'status'  => true,
//            'message' => trans('admin.delete_succeeded'),
//        ]);
//    }
    public function store($id=null)
    {
        return $this->form($id)->store();
    }

    public function update($id)
    {
        return $this->form($id)->update($id);
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(function (Content $content) {

            $content->header('创建');
            $content->description('页面');

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
        return Admin::grid(Page::class, function (Grid $grid) {
            $channelNameArr=Admin::user()->channelNameArr();
            $grid->model()->whereIn('channel',array_keys($channelNameArr));
            $grid->disableExport()->disableFilter()->disableRowSelector()->disableColumnSelector();
            $grid->column('id','ID')->sortable();
            $grid->column('channel','渠道')->using($channelNameArr);
            $grid->column('title','页面标题');
//            $grid->column('status','状态')->switch(config('state.common.switch_state_flag'));
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id=null)
    {

        $model=Page::query()->findOrNew($id);
        $arr=json_decode($model->json,true)??[];
        return Admin::form($model, function (Form $form) use ($arr,$id) {
            $mode=$id?"edit":"create";
            $form->builder()->setMode($mode);
            $form->builder()->setResourceId($id);
            $productArr=Product::query()->pluck('name','id');
//            $form->select('channel','渠道')->options();
            $channelNameArr=Admin::user()->channelNameArr();
            $form->select('channel','渠道')->options($channelNameArr)->fill($arr);
            $form->text('title','首页标题')->fill($arr);
            $form->custom_table('category','商品分类',function(Form\NestedForm $table){
                $table->image('image','图片')->downloadable();
                $table->text('name','标题');
                $table->text('description','描述');
                $table->text('path','跳转路径');
            })->fill($arr);
            $form->custom_table('recommend_category','推荐分类',function(Form\NestedForm $table) use($productArr){
                $table->image('image','图片')->downloadable();
                $table->text('name','标题');
                $table->text('description','描述');
                $table->text('path','跳转路径');
                $table->select('type','呈现方式')->options(['slide'=>'轮播','view'=>'平铺']);
                $table->text('order','排序');
                $table->multipleSelect('product_id','商品')->options($productArr);
            })->fill($arr);
            $form->saving(function(Form $form) use ($arr){
                $post=request()->all();
                $inserts = $form->prepareInsert($post);//获取表单预处理数据结果
                foreach($inserts as $key=>&$value){
                    if(is_array($value)){
                        foreach($value as $k=>&$v){
//                            if(!empty($v['image']) && strpos($v['image'],"http")===false)
//                                $v['image']=url($v['image']);
                            if(isset($v['_remove_']) && $v['_remove_']==1){
                                unset($value[$k]);
                            }else{
                                $originalArr=!empty($arr[$key][$k])?$arr[$key][$k]:[];
                                $v=array_merge($originalArr,$v);
                                unset($v['_remove_']);
                            }
                            if(!empty($v['product_id'])){
                                $productList=Product::query()->whereIn('id',$v['product_id'])->get();
                                $v['product']=[];
                                foreach($productList as $product){
                                    $v['product'][]=$product->formatSimpleProductDetail();
                                }
                            }
                        }
                        $value=array_values($value);
                    }

                }
                $pageData=json_encode($inserts,JSON_UNESCAPED_UNICODE);
                $form->model()->fill(['title'=>$inserts['title'],'channel'=>$inserts['channel'],'json'=>$pageData])->save();
                redis_set($inserts['channel']."_page_index",$pageData);
                admin_toastr(trans('admin.save_succeeded'));
                return redirect('/admin/page');
                exit;
            });

        });
    }

}

