<?php

namespace app\admin\controller;

use Exception;
use app\model\User;
use think\facade\Cache;
use app\model\RelationshipRewardLog;
use app\model\UserRelation;
use think\facade\Db;
use app\model\Yuanmeng;
use app\model\YuanmengUser;

class YuanmengController extends AuthController
{
    //红包领取记录
    public function userLog()
    {
        $req = request()->param();
        $builder = YuanmengUser::alias('l')->field('l.*, u.phone');

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('l.user_id', $req['user_id']);
        }
        if (isset($req['yuanmeng_id']) && $req['yuanmeng_id'] !== '') {
            $builder->where('l.yuanmeng_id', $req['yuanmeng_id']);
        }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $user = User::where('phone', $req['phone'])->find();
            $builder->where('l.user_id', $user['id']);
        }
        if (isset($req['user_phone']) && $req['user_phone'] !== '') {
            $builder->where('l.user_phone', $req['user_phone']);
        }
        if (isset($req['user_name']) && $req['user_name'] !== '') {
            $builder->where('l.user_name', $req['user_name']);
        }

        if (isset($req['pay_status']) && $req['pay_status'] !== '') {
            $builder->where('l.pay_status', $req['pay_status']);
        }

        if (!empty($req['start_date'])) {
            $builder->where('l.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('l.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        if (!empty($req['auth1_start_date'])) {
            $start = strtotime($req['auth1_start_date'] . ' 00:00:00');
            $builder->where('auth1_start_time', '>=', $start);
        }
        if (!empty($req['auth1_end_date'])) {
            $end = strtotime($req['auth1_end_date'] . ' 23:59:59');
            $builder->where('auth1_start_time', '<=', $end);
        }

        
        $builder = $builder->leftJoin('mp_user u', 'l.user_id = u.id')->order('l.id', 'desc');
        $count = $builder->count();
        $list = $builder->paginate(['query' => $req])->each(function($item) {
            if (isset($item['user_idcard_photo_front'])) {
                $img = env('app.host') .'/storage'. explode('storage', $item['user_idcard_photo_front'])[1];
                $item['user_idcard_photo_front'] = $img;
            }
            if (isset($item['user_idcard_photo_back'])) {
                $img = env('app.host') .'/storage'. explode('storage', $item['user_idcard_photo_back'])[1];
                $item['user_idcard_photo_back'] = $img;
            }
            if (isset($item['user_bankcard_photo'])) {
                $img = env('app.host') .'/storage'. explode('storage', $item['user_bankcard_photo'])[1];
                $item['user_bankcard_photo'] = $img;
            }
            return $item;
        });
        $this->assign('data', $list);
        $this->assign('req', $req);
        $this->assign('count', $count);
        $this->assign('yuanmengList', Yuanmeng::order('sort', 'asc')->select()->toArray());
        return $this->fetch();
    }




    public function setting()
    {
        $this->assign('data', Yuanmeng::order('sort', 'asc')->select()->toArray());
        return $this->fetch();  
    }

    //项目设置提交
    public function editConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'number',
            'name|项目名' => 'require',
            'title|通道' => 'require',
            'sort|排序号' => 'number',
            'amount|返还数币金额' => 'require|number',
            'auth_price|实名认证金' => 'require|number',
            'description|描述' => 'require',
        ]);

        // if ($cover_img = upload_file('cover_img', false,false)) {
        //     $req['cover_img'] = $cover_img;
        // }

        if (!empty($req['id'])) {
            Yuanmeng::where('id', $req['id'])->update($req);
        } else {
            Yuanmeng::insert([
                'name' => $req['name'],
                'sort' => $req['sort'],
                'title' => $req['title'],
                'auth_price' => $req['auth_price'],
                'amount' => $req['amount'],
                'description' => $req['description'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return out();
    }

    public function yuanmengAdd()
    {
        $req = request();
        if (!empty($req['id'])) {
            $data = Yuanmeng::where('id', $req['id'])->find();
            $this->assign('data', $data);
        }
        return $this->fetch();
    }

    public function yuanmengUserChange()
    {
        $req = request();
        $data = YuanmengUser::where('id', $req['id'])->find();
        if (isset($data['user_idcard_photo_front'])) {
            $img = env('app.host') .'/storage'. explode('storage', $data['user_idcard_photo_front'])[1];
            $data['user_idcard_photo_front'] = $img;
        }
        if (isset($data['user_idcard_photo_back'])) {
            $img = env('app.host') .'/storage'. explode('storage', $data['user_idcard_photo_back'])[1];
            $data['user_idcard_photo_back'] = $img;
        }
        if (isset($data['user_bankcard_photo'])) {
            $img = env('app.host') .'/storage'. explode('storage', $data['user_bankcard_photo'])[1];
            $data['user_bankcard_photo'] = $img;
        }
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function yuanmengUserChangeSubmit()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'user_name|姓名' => 'require',
            'user_phone|登记手机号' => 'require',
            'user_idcard|身份证号' => 'require',
            'user_address|收货地址' => 'require',
            'user_idcard_photo_front|手持身份证人像面照片' => '',
            'user_idcard_photo_back|手持身份证国徽面照片' => '',
            'user_bankcard_photo|同名银行卡照片' => '',
        ]);

        if ($user_idcard_photo_front = upload_file('user_idcard_photo_front', false,false)) {
            $req['user_idcard_photo_front'] = $user_idcard_photo_front;
        }


        if ($user_idcard_photo_back = upload_file('user_idcard_photo_back', false,false)) {
            $req['user_idcard_photo_back'] = $user_idcard_photo_back;
        }

        if ($user_bankcard_photo = upload_file('user_bankcard_photo', false,false)) {
            $req['user_bankcard_photo'] = $user_bankcard_photo;
        }
        

        YuanmengUser::where('id', $req['id'])->update($req);

        return out();
    }
    
    public function del()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        Yuanmeng::destroy($req['id']);
        return out();
    }

    public function delOrder()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        $data = YuanmengUser::where('id', $req['id'])->find();
        if ($data['pay_status'] == 1) {
            return out(null, 10001, '已支付订单不能删除');
        }
        YuanmengUser::destroy($req['id']);
        return out();
    }

    public function changeAdminUser()
    {
        $req = request()->post();

        Yuanmeng::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function orderChangeTongdao()
    {
        if (request()->isGet()) {
            $req = request();
            $data = YuanmengUser::where('id', $req['id'])->find();
            $this->assign('data', $data);
            $this->assign('yuanmengList', Yuanmeng::order('id', 'asc')->select());
            return $this->fetch();
        } else {
            $req = $this->validate(request(), [
                'id' => 'require|number',
                'new_tongdao_id' => 'require|number',
            ]);

            $clickRepeatName = 'orderChangeTongdao-' . $req['id'];
            if (Cache::get($clickRepeatName)) {
                return out(null, 10001, '操作频繁，请稍后再试');
            }
            Cache::set($clickRepeatName, 1, 5);

            if ($req['id'] == $req['new_tongdao_id']) {
                return out(null, 10001, '通道相同');
            }

            $order = YuanmengUser::find($req['id']);
            $user = User::where('id', $order['user_id'])->find();

            $tongdao = Yuanmeng::find($order['yuanmeng_id']);
            $newTongdao = Yuanmeng::find($req['new_tongdao_id']);

            $payAmount = bcsub($newTongdao['auth_price'], $tongdao['auth_price']);
            if ( ($user['topup_balance'] + $user['team_bonus_balance'] + $user['balance'] + $user['release_balance']) < $payAmount && $order['pay_status'] == 1) {
                return out(null, 10001, '余额不足');
            }
            
            if ($newTongdao['auth_price'] < $tongdao['auth_price'] && $order['pay_status'] == 1) {
                //$returnAmount = bcsub($tongdao['auth_price'], $newTongdao['auth_price']);
                return out(null, 10001, '第四阶段已支付');
            }
            
            Db::startTrans();
            try {
                
                YuanmengUser::where('id', $order['id'])->update([
                    'title' => $newTongdao['title'],
                    'name' => $newTongdao['name'],
                    'yuanmeng_id' => $newTongdao['id'],
                    'auth_price' => $newTongdao['auth_price'],
                    'amount' => $newTongdao['amount'],
                ]);

                if ($order['pay_status'] == 1) {
                    Db::name('user')->where('id', $order['user_id'])->inc('invest_amount', $payAmount)->update();
                    //判断是否活动时间内记录活动累计消费 4.30-5.6
                    $time = time();
                    if($time > 1714406400 && $time < 1715011200) {
                        Db::name('user')->where('id', $order['user_id'])->inc('30_6_invest_amount', $payAmount)->update();
                    }
                    Db::name('user')->where('id', $order['user_id'])->inc('huodong', 1)->update();
                    User::upLevel($order['user_id']);

                    if($user['topup_balance'] >= $payAmount) {
                        User::changeInc($user['id'],-$payAmount,'topup_balance',42,$req['id'],1,'修改通道补差价','',1,'HL');
                    } else {
                        User::changeInc($user['id'],-$user['topup_balance'],'topup_balance',42,$req['id'],1,'修改通道补差价','',1,'HL');
                        $topup_amount = bcsub($payAmount, $user['topup_balance'],2);
                        if($user['team_bonus_balance'] >= $topup_amount) {
                            User::changeInc($user['id'],-$topup_amount,'team_bonus_balance',42,$req['id'],1,'修改通道补差价','',1,'HL');
                        } else {
                            User::changeInc($user['id'],-$user['team_bonus_balance'],'team_bonus_balance',42,$req['id'],1,'修改通道补差价','',1,'HL');
                            $signin_amount = bcsub($topup_amount, $user['team_bonus_balance'],2);
                            if($user['balance'] >= $signin_amount) {
                                User::changeInc($user['id'],-$signin_amount,'balance',42,$req['id'],1,'修改通道补差价','',1,'HL');
                            } else {
                                User::changeInc($user['id'],-$user['balance'],'balance',42,$req['id'],1,'修改通道补差价','',1,'HL');
                                $balance_amount = bcsub($signin_amount, $user['balance'],2);
                                User::changeInc($user['id'],-$balance_amount,'release_balance',42,$req['id'],1,'修改通道补差价','',1,'HL');
                            }
                        }
                    }
                    //User::changeInc($order['user_id'],-$payAmount,'topup_balance',42,$req['id'],1, '修改通道补差价','',1,'HL');
        
                    // 给上3级团队奖
                    $relation = UserRelation::where('sub_user_id', $order['user_id'])->select();
                    $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
                    foreach ($relation as $v) {
                        $reward = round(dbconfig($map[$v['level']])/100*$payAmount, 2);
                        if($reward > 0){
                            $orderuser = User::where('id', $order['user_id'])->find();
                            User::changeInc($v['user_id'],$reward,'balance',8,$req['id'],2,'团队奖励'.$v['level'].'级'.$orderuser['realname'],0,2,'TD');
                            RelationshipRewardLog::insert([
                                'uid' => $v['user_id'],
                                'reward' => $reward,
                                'son' => $order['user_id'],
                                'son_lay' => $v['level'],
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
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
    }
}
