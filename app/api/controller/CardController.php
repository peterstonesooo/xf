<?php

namespace app\api\controller;

use app\model\Apply;
use app\model\SyCard;
use app\model\User;
use app\model\UserAuth;
use app\model\UserCardRecord;
use app\model\UserDelivery;
use think\facade\Db;

class CardController extends AuthController
{
    //获取所有卡片按金额获取需要办理的卡 需要办理的卡排在第一位
    public function getAllCard()
    {
        $user = $this->user;
        $cards = SyCard::where('status', 1)
            ->order('id', 'asc')
            ->select()->toArray();
        $sumAmount = SyCard::userAllAmount($user);

        $needCard = [];
        foreach ($cards as $key => $card) {
            if ($sumAmount >= $card['min_amount'] && $sumAmount < $card['max_amount']) {
                $card['state'] = 1;//这个状态是确认需要办的卡
                $needCard = $card;
                unset($cards[$key]);
            }
        }
        $data['list'][] = $needCard;
        $data['sumAmount'] = $sumAmount;
        foreach ($cards as $key => $v) {
            $v['state'] = 0;
            $data['list'][] = $v;
        }
        $desc = [
            1 => '0-10万元',
            2 => '10万元-50万元',
            3 => '50万元-100万元',
            4 => '100万元-200万元',
            5 => '200万元及以上'
        ];
        foreach ($data['list'] as &$card) {
            $card['desc'] = $desc[$card['id']] ?? '';
        }
        $data['tips'] = '请办理该类生育卡';
        return out($data);
    }

    //用户办卡接口
    public function bindCard()
    {
        $req = $this->validate($this->request(), [
            'card_id|生育卡类型' => 'require|number|>:0',
        ]);
        return out(null, 10001, '本期办卡结束');
        $user = $this->user;
        $isHas = UserCardRecord::where('user_id', $user['id'])
            ->where('card_id', $req['card_id'])
            ->count();
        if ($isHas <= 0) {
            //return out(null, 10001, '您已办理过此类型的卡');
        }
        $cardInfo = SyCard::where('status', 1)
            ->where('id', $req['card_id'])
            ->where('status', 1)
            ->order('id', 'asc')
            ->find();
        if (empty($cardInfo)) {
            return out(null, 11111, '生育卡信息不存在');
        }
        if ($user['poverty_subsidy_amount'] < $cardInfo['fee']) {
            return out(null, 11111, '您得余额低于预存金额：' . $cardInfo['fee'] . '元');
        }

        Db::startTrans();
        try {
            // 判断余额
            $user = User::where('id', $user['id'])->lock(true)->find();
            //扣费
            $res = User::changeIncs($user['id'], -$cardInfo['fee'], 'poverty_subsidy_amount', 45, 0, 7, '生育卡预存金额');
            if ($res != 'success') {
                Db::rollback();
                return out(null, 10001, '网络异常，请稍后再试');
            }
            //添加领取记录
            $userCard = UserCardRecord::create([
                'user_id' => $user['id'],
                'fee' => $cardInfo['fee'],
                'status' => 1,//初始话办卡中
                'card_id' => $cardInfo['id'],
                'bank_card' => '62'.time().$user['id'],
            ]);
            //三级返佣
            $res = User::buyRewardV3($user['id'], $userCard['id']);
            if (!$res) {
                Db::rollback();
                return out(null, 200, '办卡异常');
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return out(null, 200, '领取成功');
    }

    //用户办卡接口
    public function getUserCard()
    {
        $user = $this->user;
        $cards = UserCardRecord::where('user_id', $user['id'])
            ->with(['syCard'])
            ->field('card_id, fee, status, bank_card, created_at')
            ->select()->each(function ($item, $key) {
                $item['title'] = $item->syCard['title'] ?? '';
                unset($item->syCard);
            });
        if ($cards->isEmpty()) {
            return out(null, 10001, '请您先领取生育卡');
        }
        //判断开户 todo
        $auth = Apply::where('user_id', $user['id'])
            ->field('user_id, status, id_card_front, id_card_opposite')
            ->find();
        if (empty($auth)) {
            return out(null, 10001, '请先进行开户认证');
        }
        if ($auth['status'] == 0) {
            return out(null, 10001, '请等待开户审核通过');
        }
        //获取地址信息
        $addr = UserDelivery::where('user_id', $user['id'])->find();
        if (empty($addr)) {
            return out(null, 10001, '请您先绑定收货地址');
        }
        foreach ($cards as $key => &$card) {
            if ($key == 0) {//第一张办得卡加上 提现得钱
                $card['fee'] = bcadd(SyCard::userWithdraw($user), $card['fee'], 2);
            }
            $card['addr'] = $addr['address'];
            $card['realname'] = $user['realname'];
            $card['ic_number'] = $user['ic_number'];
            $card['expired'] = '88/88';//'07/29';
            $card['bank_card'] = '8888888888888888';
        }
        $data['list'] = $cards;
//        $data['amount'] = SyCard::userWithdraw($user);
        return out($data);

    }

    public function authCheck()
    {
        $user = $this->user;
        $auth = UserAuth::where('user_id', $user['id'])
            ->field('user_id, status, realname, ic_number, img_url1, img_url2')
            ->find();
        if (empty($auth)) {
            $auth = [
                'user_id' => $user['id'],
                'status'  => 0,
                'realname' => $user['realname'],
                'ic_number' => $user['ic_number'],
                'img_url1' => '',
                'img_url2' => '',
            ];
        }
        return out($auth);
    }

    public function sumbitAuth()
    {
        $user = $this->user;
        $req = $this->validate($this->request(), [
            'realname|姓名' => 'require|max:50',
            'ic_number|身份证号码' => 'require|max:50',
            'img_url1|身份证正面' => 'require|max:255',
            'img_url2|身份证反面' => 'require|max:255',
        ]);
        $auth = UserAuth::where('user_id', $user['id'])->find();
        $req['realname'] = trim($req['realname']);
        $req['ic_number'] = trim($req['ic_number']);
        if ($req['realname'] != $user['realname'] || $req['ic_number'] != $user['ic_number']) {
            return out(null, 10001, '身份信息验证失败');
        }
        if (empty($auth)) {
            $data = [
                'user_id' => $user['id'],
                'realname' => $req['realname'],
                'ic_number' => $req['ic_number'],
                'img_url1' => $req['img_url1'],
                'img_url2' => $req['img_url2'],
                'status' => 1,//自动通过
            ];
            for ($i = 1; $i < 2; ++$i) {
                $field = 'img_url' . $i;
                if (isset($req[$field]) && !empty($req[$field])) {
                    $this->validate($req, [$field => 'url']);
                    $data[$field] = $req[$field];
                }
            }
            UserAuth::create($data);
        } else {
            if ($auth['status'] == 1) {
                return out(null, 10001, '您的认证已通过，请不要重复提交审核');
            }
            $req['status'] = 1;//自动通过
            UserAuth::where('user_id', $user['id'])->update($req);
        }
        return out(null, 200, "提交成功");
    }

}
