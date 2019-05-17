<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\UserAddress;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\ProductSku;
use App\Http\Requests\OrderRequest;
use App\Exceptions\InvalidRequestException;

class OrdersController extends Controller
{
    // 保存订单数据
    public function store(OrderRequest $request)
    {
        // 获取当前用户实例
        $user = $request->user();
        // 开始事务
        $order = DB::transaction(function () use ($user, $request) {
            // 获取当前收货地址信息的对象
            $address = UserAddress::query()->find($request->input('address_id'));
            // 更新此地址的最后使用时间
            // $address->update(['last_used_at'=>date('Y-m-d H:i:s')]);
            $address->update(['last_used_at' => Carbon::now()]);
            // 创建一个订单对象
            $order = new order([
                // 地址信息 将会自动转换为json格式
                'address' => [
                    'address' => $address->full_address,
                    'zip'   => $address->zip,
                    'contact_name' => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                // 备注信息
                'remark' => $request->input('remark'),
                // 总价格先设定为0
                'total_amount' => 0,
            ]);
            // 订单关联到当前用户
            // 更新 belongsto 从属关的关联时使用 associate()方法
            // 取消 belongsto 从属关的关联时使用 dissociate()方法
            // 其实这里的意思和 $order->user_id = $user->id是一样的
            // 也是将user_id更新到orde表中,但这是Laravel推荐的写法
            // 好处是在免于之后用到时多查询一次user表的id数据
            $order->user()->associate($user);
            // 订单保存
            $order->save();

            // 开始计算总价格
            $totalAmount = 0;
            $items = $request->input('items');
            // 遍历用户提交的 SKU
            foreach ($items as $data) {
                $sku = ProductSku::query()->find($data['sku_id']);
                // 创建 OrderItem 对象并与当前订单关联
                $item = $order->items()->make([
                    'amount' => $data['amount'],
                    'price' => $sku->price,
                ]);
                // 从属关联
                $item->product()->associate($sku->product_id);
                // 从属关联
                $item->ProductSku()->associate($sku);
                // 保存 orderItem
                $item->save();
                // 累计计算当前orderitem价格
                $totalAmount += $sku->price * $data['amount'];
                // 减库存并当库存不足时抛出异常
                if($sku->decreaseStock($data['amount']) <= 0){
                    throw new InvalidRequestException('库存不足');
                }
            }
            // 更新订单总金额
            $order->update(['total_amount'=>$totalAmount]);

            // 将下单的商品从购物车中移除
            $skuIds = collect($request->input('items'))->pluck('sku_id');
            $user->cartItems()->whereIn('product_sku_id',$skuIds)->delete();
            // 将 DB::transaction() 的返回值从闭包中传递出去
            return $order;
        });

        return $order;
    }
}