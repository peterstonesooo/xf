<?php
namespace app\api\controller;

use app\model\LotteryRecord;
use app\model\LotterySetting;
use app\model\LotteryUserSetting;
use app\model\Order;
use app\model\User;
use think\facade\Cache;
use think\facade\Db;
use Exception;

class LotteryController extends AuthController
{
    // 奖品配置
//    private $prizes = [
//        ['id' => 1, 'name' => '10元红包', 'probability' => 0.30,'field'=>'team_bonus_balance','amount'=>10],  // 1%
//        ['id' => 2, 'name' => '20元红包', 'probability' => 0.20,'field'=>'team_bonus_balance','amount'=>20],  // 5%
//        ['id' => 3, 'name' => '40元红包', 'probability' => 0.20,'field'=>'team_bonus_balance','amount'=>40],  // 14%
//        ['id' => 4, 'name' => '100元红包', 'probability' => 0.15,'field'=>'team_bonus_balance','amount'=>100], // 80%
//        ['id' => 5, 'name' => '1000元红包', 'probability' => 0.15,'field'=>'team_bonus_balance','amount'=>1000], // 80%
//    ];

    /**
     * 抽奖接口
     * @return \think\response\Json
     */
    public function lottery()
    {
        $req = $this->validate(request(), [
            'type|类型参数' => 'number|require|in:1,2',
            'order_id|订单号' => 'number',
        ]);
        $orderId = array_key_exists('order_id', $req) ? $req['order_id'] : 0;


        $user = $this->user;
        $userId = $user['id'];
        $type = $req['type'];
        $lottery_user = Cache::get('lottery_user_'.$userId);
        if($lottery_user){
            return json(['code' => 0, 'msg' => '请勿频繁抽奖']);
        }
        Cache::set('lottery_user_'.$userId,time(),5);
        $user = User::find($userId);
        // 积分抽奖
        if ($type == 1) {
            if ($user['lottery_tickets'] < 1) {
                return json(['code' => 0, 'msg' => '请先兑换抽奖劵']);
            }
        }
        // 订单抽奖
        else {
            if($user['order_lottery_tickets'] < 1){
                return json(['code' => 0, 'msg' => '没有可抽奖的产品订单']);
            }
            $orderId = 1;
        }


        Db::startTrans();
        try {
            // 抽奖逻辑
            $prize = $this->getPrize($type);
            if($prize['cash_amount'] > 0){
                $receive = 1;
            }else{
                $receive = 0;
            }
            // 记录抽奖结果
            $record = new LotteryRecord;
            $record->user_id = $userId;
            $record->order_id = $type == 2 ? $orderId : 0;
            $record->lottery_time = date('Y-m-d H:i:s');
            $record->lottery_result = $prize['name'];
            $record->points_used = $type == 1 ? 1 : 0;
            $record->receive = $receive;   
            $record->lottery_id = $prize['id'];
            $record->save();
            $id = $record->id;  // 直接使用模型的id属性
            if($type == 1){
                // 扣除积分
                User::changeInc($user['id'], -1, 'lottery_tickets', 54 ,$id , 9,'积分抽奖');
            }else{
                User::where('id',$user['id'])->dec('order_lottery_tickets',1)->update();
            }
            if($prize['cash_amount'] > 0){
                $cash_to_wallet = $prize['cash_to_wallet'];
                switch($cash_to_wallet){
                    case 'team_bonus_balance':
                        $cash_to_wallet = 'team_bonus_balance';
                        $log_type_name = "荣誉金";
                        $log_type = 2;
                        break;
                    case 'butie':
                    case 'gongfu_wallet':
                        $cash_to_wallet = 'gongfu_wallet';
                        $log_type_name = "共富金";
                        $log_type = 3;
                        break;
                }
                // 领奖到钱包
                User::changeInc($user['id'], $prize['cash_amount'], $cash_to_wallet, 54 ,$id , $log_type,$log_type_name);
            }
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out([
            'id' => $id,
            'prize' => $prize['name'],
            'order_id' => $type == 2 ? $orderId : 0
        ]);
    }

    public function lotteryRecords(){
        $list = LotteryRecord::order('id','desc')->where('user_id',$this->user->id)->paginate(100);
        return out($list);
    }

    public function getLotterys(){
        $req = $this->validate(request(), [
            'type|类型' => 'in:1,2',
        ]);
        if(!empty($req['type'])){
            $type = $req['type'];
        }else{
            $type = 1;
        }

        $user = $this->user;
        $userId = $user['id'];
        $list = LotterySetting::order('cash_amount','asc')->where('type',$type)->select();

        $data['lottery_jifen'] = dbconfig('lottery_jifen');
        $data['signin_1_jifen'] = dbconfig('signin_1_jifen');
        $data['list'] = $list;
        $data['jifen_lottery_tickets'] = $user['lottery_tickets'];
        $data['order_lottery_tickets'] = $user['order_lottery_tickets'];
        return out($data);
    }
    /**
     * 领取奖品
     * @return \think\response\Json
     */
    public function receive()
    {
        $req = $this->validate(request(), [
            'id|参数' => 'number|require',
        ]);
        $id = $req['id'];
        $user = $this->user;
        $userId = $user['id'];

        $record = LotteryRecord::where([
            'id' => $id,
            'user_id' => $userId
        ])->find();

        if (!$record) {
            return json(['code' => 0, 'msg' => '记录不存在']);
        }

        if ($record->receive == 1) {
            return json(['code' => 0, 'msg' => '已领取']);
        }

        Db::startTrans();
        try {
            $record->receive = 1;
            $record->save();
            $lottery = LotterySetting::where('id', $record->lottery_id)->find()->toArray();

            if(!$lottery){
                return json(['code' => 0, 'msg' => '奖项不存在']);
            }
            // 领奖到钱包
            User::changeInc($user['id'], $lottery['cash_amount'], 'team_bonus_balance', 54 ,$id , 2,'积分抽奖');

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();

    }

    /**
     * 获取奖品
     * @return array
     */
    private function getPrize($type)
    {
        // 先检查是否有内定奖品
        $userSetting = LotteryUserSetting::where([
                'user_id' => $this->user['id'],
                'status' => 0
            ])
            ->find();

        if ($userSetting) {
            // 获取内定奖品信息
            $prize = LotterySetting::where('id', $userSetting['prize_id'])->where('type',$type)->find();
        
            if ($prize) {
                // 更新内定奖品状态为已使用
                LotteryUserSetting::where('id', $userSetting['id'])->update(['status' => 1]);
                return [
                    'id' => $prize['id'],
                    'name' => $prize['name'],
                    'cash_amount' => $prize['cash_amount'],
                    'type' => $type,
                    'cash_to_wallet' => $prize['cash_to_wallet']
                ];
            }
        }

        // 如果没有内定奖品，按概率正常抽奖
        $prizes = LotterySetting::where('type',$type)->select()->toArray();
        $totalProbability = array_sum(array_column($prizes, 'win_probability'));
        $random = mt_rand(1, $totalProbability * 100) / 100;
        
        $currentProbability = 0;
        foreach ($prizes as $prize) {
            $currentProbability += $prize['win_probability'];
            if ($random <= $currentProbability) {
                return [
                    'id' => $prize['id'],
                    'name' => $prize['name'],
                    'cash_amount' => $prize['cash_amount'],
                    'type' => $type,
                    'cash_to_wallet' => $prize['cash_to_wallet']
                ];
            }
        }
        
        // 如果出现意外情况，返回最后一个奖品
        $lastPrize = end($prizes);
        return [
            'id' => $lastPrize['id'],
            'name' => $lastPrize['name'],
            'cash_amount' => $lastPrize['cash_amount'],
            'type' => $type,
            'cash_to_wallet' => $lastPrize['cash_to_wallet']
        ];
    }
} 