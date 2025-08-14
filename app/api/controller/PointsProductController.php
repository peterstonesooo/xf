<?php

namespace app\api\controller;

use app\model\PointsProduct;
use app\model\PointsOrder;
use app\model\User;
use app\model\UserDelivery;
use app\model\TeamGloryLog;
use think\facade\Cache;
use think\facade\Db;
use app\common\controller\BaseController;

class PointsProductController extends AuthController
{
    /**
     * 获取商品列表
     */
    public function getProductList()
    {
        $req = $this->validate(request(), [
            'page|页码' => 'number',
            'limit|每页数量' => 'number',
        ]);
         //計算折扣
        $discountArr = TeamGloryLog::where('user_id',$this->user['id'])->order('vip_level','desc')->find();
        if(isset($discountArr['get_discount'])){
            $discount = $discountArr['get_discount'];
        }else{
            $discount = 1;
        }
        $products  = Cache::get('PointsProductList');
        if(!$products){
            $products = PointsProduct::where('product_status', '1')
                ->field('id, product_name, product_description, points_price, stock_quantity, product_image_url')
                ->order('points_price', 'asc')
                ->paginate($req['limit']);

            Cache::set('PointsProductList', $products, 5);
        }
        foreach($products as $key=>$val){
            $products[$key]['discount_price'] = round($val['points_price'] * $discount);
        }
        return out($products);
    }
    
    /**
     * 积分购买商品
     */
    public function purchaseProduct()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,balance,integral')->find();
        
        $req = $this->validate(request(), [
            'product_id|商品ID' => 'require|number',
            'quantity|购买数量' => 'require|number|min:1',
            'delivery_id|地址' => 'require|number',
        ]);
        
        $productId = $req['product_id'];
        $quantity = $req['quantity'];

        // 获取商品信息
        $product = PointsProduct::where('id', $productId)
            ->where('product_status', '1')
            ->find();

        if (!$product) {
            return out([], 1, '商品不存在或已下架');
        }
        $userDelivery = UserDelivery::where('user_id', $user['id'])->where('id',$req['delivery_id'])->find();
        if(!$userDelivery){
            return out([], 1, '请选择正确收货地址');
        }

        Db::startTrans();
        try {
            // 检查库存
            if ($product['stock_quantity'] < $quantity) {
                return out([], 1, '商品库存不足');
            }
            

            $pointsNeeded = $product['points_price'] * $quantity ;

            // 检查用户积分是否足够
            if ($user['integral'] < $pointsNeeded) {
                return out([], 1, '积分不足');
            }

            // 更新商品库存
            PointsProduct::updateStock($productId, $quantity);
            
            // 创建订单
            $order = PointsOrder::createOrder($user['id'], $productId, $pointsNeeded, $quantity);

            // 扣除用户积分
            User::changeInc($user['id'],-$pointsNeeded,'integral',58,$order['id'],6,"积分商城购买商品",0,1);

            
            Db::commit();
            return out([], 0, '购买成功');
        } catch (\Exception $e) {
            Db::rollback();
            return out([], 1, '购买失败：' . $e->getMessage());
        }
    }
    
    /**
     * 获取兑换商品记录
     */
    public function getOrderList()
    {
        $req = $this->validate(request(), [
            'page|页码' => 'number',
            'limit|每页数量' => 'number',
            'type|状态' => 'number|in:1,2',  //1已兑换2已签收
        ]);
        
        $user = $this->user;
        if (!$user) {
            return out([], 1, '用户未登录');
        }

        $status = [1=>'待发货',2=>'已发货',3=>'已签收'];
        $obj = PointsOrder::alias("l")->join('mp_points_only_products p','l.product_id=p.id')->where('l.user_id', $user['id']);
        if (array_key_exists('type',$req) && $req['type'] ==1) {
            $obj = $obj->where('l.order_status', 'in', [1,2]);
        }
        if (array_key_exists('type',$req) && $req['type'] == 2) {
            $obj =  $obj->where('l.order_status', 3);
        }
        $orders = $obj->field('p.product_name,p.product_image_url,l.id,l.points_used,l.order_status,l.create_time')
            ->order('l.create_time', 'desc')
            ->paginate($req['limit'])
            ->each(function($item,$key)use($status) {
                $item['status'] = $status[$item['order_status']];
                return $item;
            });



        return out($orders);
    }

    public function getOrder()
    {
        $req = $this->validate(request(), [
            'id|订单ID' => 'number',
        ]);

        $user = $this->user;
        if (!$user) {
            return out([], 1, '用户未登录');
        }

        $obj = PointsOrder::alias("l")->join('mp_points_only_products p','l.product_id=p.id')->where('l.user_id', $user['id']);
        if (array_key_exists('id',$req) ){
            $obj = $obj->where('l.id', $req['id']);
        }
        $order =  $obj ->field('p.product_name,p.product_image_url,l.id,l.points_used,l.number,l.delivery_time,l.signing_time,l.order_status')
            ->order('l.create_time', 'desc')
            ->find();
        $status = [1=>'待发货',2=>'已发货',3=>'已签收'];
        if(isset($order['order_status'])){
            $order['status'] = $status[$order['order_status']];
        }


        return out($order);
    }

} 