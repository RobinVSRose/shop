<?php
/**
 * Created by PhpStorm.
 * User: robin
 * Date: 2018/10/11
 * Time: 13:14
 */

namespace App\Admin\Extensions;


use Encore\Admin\Grid\Filter\Between;

class CustomBetween extends Between
{
    protected $table='';
    public function __construct($tableName,$column, string $label = '')
    {
        $this->table=$tableName;
        parent::__construct($column, $label);
    }

    public function condition($inputs)
    {
        if (!array_has($inputs, $this->column)) {
            return;
        }

        $this->value = array_get($inputs, $this->column);

        $value = array_filter($this->value, function ($val) {
            return $val !== '';
        });

        if (empty($value)) {
            return;
        }
//        if(isset($inputs['_export_']) && !empty($inputs['created_at'])){
//            $inputs['order.created_at']=$inputs['created_at'];
//            unset($inputs['created_at']);
//        }

        if (!isset($value['start'])) {
            return $this->buildCondition($this->table.'.created_at', '<=', $value['end']);
        }

        if (!isset($value['end'])) {
            return $this->buildCondition($this->table.'.created_at', '>=', $value['start']);
        }

        $this->query = 'whereBetween';

        return $this->buildCondition($this->table.'.created_at', $this->value);
    }
}
