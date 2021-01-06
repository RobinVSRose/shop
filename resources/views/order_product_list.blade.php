<table class="table table-hover">
    <thead>
        <tr>
            <td>产品ID</td>
            <td>产品名称</td>
            <td>属性</td>
            <td>数量</td>
            <td>价格</td>
            <td>实付价</td>
        </tr>
    </thead>
    <tbody>
    @foreach($products as $product)
        <tr>
            <td>{{$product->product_id }}</td>
            <td>{{$product->product_name}}</td>
            <td>{{$product->product_attr_name.":".$product->product_attr_value}}</td>
            <td>{{$product->product_num}}</td>
            <td>{{$product->product_price}}</td>
            <td>{{$product->deal_price}}</td>
        </tr>
    @endforeach
    </tbody>
</table>
