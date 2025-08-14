<?php

namespace app\api\controller;

use think\facade\Cache;
use app\model\Coin;
use app\model\User;
use app\model\CoinOrder;
use app\model\UserRelation;
use app\model\UserCoinBalance;
use app\model\UserCoinTransferLog;
use Exception;
use think\facade\Db;

class CoinController extends AuthController
{

    /**
     * 1.推荐奖2级返利
     */

    /**
     * 当前币种价格
     */
    public function price()
    {
        $req = $this->validate(request(), [
            'code|币种' => 'require',
        ]);
        $code = $req['code'];
        $nowPrice = coin::nowPrice($code);
        return out($nowPrice);
    }

    /**
     * 购买龙头币
     */
    public function placeOrder()
    {
        $user = $this->user;
        $clickRepeatName = 'cion-buy-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);
        
        $req = $this->validate(request(), [
            'code|币种' => 'require',
            'buyNumber|购买数量' => 'require|float',
        ]);
        $code = $req['code'];
        $codeInfo = Coin::where('code', $code)->find();

        //购买限制
        $buyLimit = Coin::buyLimit;
        if ($user['level'] == 0) {
            return out(null, 10001, '购买额度不足');
        }
        $limit = $buyLimit[$user['level']];
        $coinBuySum = CoinOrder::where('user_id', $user['id'])->sum('buy_number');
        if ($coinBuySum + $req['buyNumber'] > $limit) {
            return out(null, 10001, '购买额度不足');
        }

        //当前价
        $nowPrice = Coin::nowPrice($code);
        $totalPirce = bcmul($nowPrice, $req['buyNumber']);

        if ($user->balance < $totalPirce) {
            return out(null, 10001, '可用余额不足');
        }

        Db::startTrans();
        try {
            $orderId = CoinOrder::insertGetId([
                'user_id' => $user['id'],
                'code_id' => $codeInfo['id'],
                'buy_number' => $req['buyNumber'],
                'buy_price' => $nowPrice,
                'total_price' => $totalPirce,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            User::changeBalance($user['id'], $totalPirce * -1, 3, $orderId);

            UserCoinBalance::changeBalance($user['id'], $codeInfo['id'], $req['buyNumber']);

            //购买产品和恢复资产用户激活
            if ($user['is_active'] == 0 ) {
                User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                // 下级用户激活
                UserRelation::where('sub_user_id', $user['user_id'])->update(['is_active' => 1]);
            }

            //返佣
            if ($user['up_user_id']) {
                $father = User::find($user['up_user_id']);
                if ($father) {
                    User::changeBalance($father['id'], bcmul($totalPirce, $codeInfo['layer1'], 4), 31, $orderId);
                    if ($father['up_user_id']) {
                        $grandFather = User::find($father['up_user_id']);
                        if ($grandFather) {
                            User::changeBalance($grandFather['id'], bcmul($totalPirce, $codeInfo['layer2'], 4), 31, $orderId);
                        }
                    }
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    //持有列表
    public function coinHold()
    {
        $user = $this->user;
        $data = CoinOrder::alias('c')->field('c.*, n.code')->leftJoin('coin n', 'n.id = c.code_id')->where('c.user_id', $user->id)->order('c.id', 'desc')->paginate();
        return out($data);
    }

    /**
     * 龙头币转账
     */
    public function transfer()
    {
        $user = $this->user;
        $clickRepeatName = 'cion-transfer-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);
        
        $req = $this->validate(request(), [
            'code|币种' => 'require',
            'number|转账数量' => 'require|float',
            'toPhone|接收人手机号' => 'require|float',
            'payPassword|支付密码' => 'require',
        ]);
        $code = $req['code'];
        $codeInfo = Coin::where('code', $code)->find();
        
        $toUser = User::where('phone', $req['toPhone'])->find();
        if (empty($toUser)) {
            return out(null, 10001, '接收人不存在');
        }

        if (sha1(md5($req['payPassword'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }

        Db::startTrans();
        try {

            UserCoinBalance::changeBalance($user['id'], $codeInfo['id'], $req['number'] * -1);
            UserCoinBalance::changeBalance($toUser['id'], $codeInfo['id'], $req['number']);

            UserCoinTransferLog::insert([
                'user_id' => $user['id'],
                'to_user_id' => $toUser['id'],
                'amount' => $req['number'],
                'created_at' => date('Y-m-d H:i:s'),
                'code_id' => $codeInfo['id'],
            ]);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }
}
