<?php

namespace app\admin\controller;

use app\common\Request;
use app\model\Order5;
use app\model\RelationshipRewardLog;
use app\model\UserCardRecord;
use app\model\OrderTiyan;
use think\facade\Cache;
use app\model\Apply;
use app\model\Capital;
use app\model\EquityYuanRecord;
use app\model\FamilyChild;
use app\model\Order;
use app\model\PayAccount;
use app\model\Project;
use app\model\User;
use app\model\Promote;
use app\model\Message;
use app\model\NoticeMessage;
use app\model\NoticeMessageUser;
use app\model\Authentication;
use app\model\UserBalanceLog;
use app\model\UserBank;
use app\model\UserProduct;
use app\model\UserRelation;
use app\model\GiftRecord;
use app\model\UserProjectGroup;
use app\model\HappinessEquityActivation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;
use think\db\Fetch;
use think\facade\Db;
use think\paginator\driver\Bootstrap;

class UserController extends AuthController
{
    public function promote()
    {
        $req = request()->param();

        $builder = Promote::order('id', 'desc');
        if (isset($req['type']) && $req['type'] !== '') {
            $builder->where('type', 'like', '%' . $req['type'] . '%');
        }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('phone', 'like', '%' . $req['phone'] . '%');
        }
        if (isset($req['name']) && $req['name'] !== '') {
            $builder->where('name', 'like', '%' . $req['name'] . '%');
        }
        if (isset($req['id_card']) && $req['id_card'] !== '') {
            $builder->where('id_card', 'like', '%' . $req['id_card'] . '%');
        }
        if (!empty($req['start_date'])) {
            $builder->where('date_time', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('date_time', '<=', $req['end_date'] . ' 23:59:59');
        }

        if (!empty($req['export'])) {
            $list = $builder->select();
            create_excel($list, [
                'id' => '序号',
                'name'=>'姓名',
                'phone' => '手机号',
                'id_card' => '身份证',
                'type' => '通道',
                'date_time' => '时间',
            ], '' . date('YmdHis'));
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function userList()
    {
        $req = request()->param();

        $builder = User::order('id', 'desc');
        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('id', $req['user_id']);
        }
        if (isset($req['up_user']) && $req['up_user'] !== '') {
            $user_ids[] = $req['up_user'];
            $builder->whereIn('up_user_id', $user_ids);
        }
        if (isset($req['tm_up_user']) && $req['tm_up_user'] !== '') {
            $user_ids = UserRelation::where('user_id', $req['tm_up_user'])->column('sub_user_id');
            $builder->whereIn('id', $user_ids);
        }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('phone', $req['phone']);
        }
        if (isset($req['invite_code']) && $req['invite_code'] !== '') {
            $builder->where('invite_code', $req['invite_code']);
        }
        if (isset($req['realname']) && $req['realname'] !== '') {
            $builder->where('realname', $req['realname']);
        }
        if(isset($req['shiming_status']) && $req['shiming_status'] !== ''){
            $builder->where('shiming_status', $req['shiming_status']);
        }
        if (isset($req['level']) && $req['level'] !== '') {
            $builder->where('level', $req['level']);
        }
        if (isset($req['is_active']) && $req['is_active'] !== '') {
            if ($req['is_active'] == 0) {
                $builder->where('is_active', 0);
            }
            else {
                $builder->where('is_active', 1);
            }
        }

        if (!empty($req['start_date'])) {
            $builder->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        if (!empty($req['active_date'])) {
            $start = strtotime($req['active_date'] . ' 00:00:00');
            $end = strtotime($req['active_date'] . ' 23:59:59');
            $builder->where('active_time', '>=', $start);
            $builder->where('active_time', '<=', $end);
        }

        if (isset($req['ic_number']) && $req['ic_number'] !== '') {
            $builder->where('ic_number', 'like', '%' . $req['ic_number'] . '%');
        }

        $count = $builder->count();
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $count);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showUser()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = User::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function message()
    {
        $req = request()->param();
        $this->assign('req', $req);
        return $this->fetch();
    }

    public function addMessage()
    {
        $req = $this->validate(request(), [
            'user_id' => 'require|number',
            'title|标题' => 'require',
            'content|内容' => 'require',
        ]);
        Db::startTrans();
        try {
            $msg = NoticeMessage::insertGetId([
                'type' => 4,
                'title' => $req['title'],
                'content' => $req['content'],
            ]);
            NoticeMessageUser::insert([
                'message_id' => $msg,
                'user_id' => $req['user_id'],
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '发送失败'.$e->getMessage());
        }

        return out();
    }

    public function editUser()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'password|登录密码' => 'max:50',
            'pay_password|支付密码' => 'max:50',
            'realname|实名认证姓名' => 'max:50',
            'ic_number|身份证号' => 'max:50',
        ]);

        if(strlen(trim($req['ic_number'])) != 18 && strlen(trim($req['ic_number'])) != 15){
            return out(null,10001,'身份证号格式错误');
        }

        if (empty($req['password'])) {
            unset($req['password']);
        }
        else {
            $req['password_bak'] = $req['password'];
            $req['password'] = sha1(md5($req['password']));
        }

        if (empty($req['pay_password'])) {
            unset($req['pay_password']);
        }
        else {
            $req['pay_password_bak'] = $req['pay_password'];
            $req['pay_password'] = sha1(md5($req['pay_password']));
        }
        if(empty($req['realname'])) {
            unset($req['realname']);
        }
        if(empty($req['ic_number'])) {
            unset($req['ic_number']);
        }
        // if (empty($req['realname']) && !empty($req['ic_number'])) {
        //     return out(null, 10001, '实名和身份证号必须同时为空或同时不为空');
        // }
        // if (!empty($req['realname']) && empty($req['ic_number'])) {
        //     return out(null, 10001, '实名和身份证号必须同时为空或同时不为空');
        // }

        // 判断给直属上级额外奖励
        if (!empty($req['ic_number'])) {
            if (User::where('ic_number', $req['ic_number'])->where('id', '<>', $req['id'])->where('status', 1)->count()) {
                return out(null, 10001, '该身份证号已经实名过了');
            }

            // $user = User::where('id', $req['id'])->find();
            // if (!empty($user['up_user_id']) && empty($user['ic_number'])) {
            //     User::changeBalance($user['up_user_id'], dbconfig('direct_recommend_reward_amount'), 7, $user['id']);
            // }
        }

        User::where('id', $req['id'])->update($req);

        // 把注册赠送的股权给用户
        EquityYuanRecord::where('user_id', $req['id'])->where('type', 1)->where('status', 1)->where('relation_type', 2)->update(['status' => 2, 'give_time' => time()]);

        return out();
    }

    public function changeUser()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);
        $user = User::where('id', $req['id'])->find();
        if($req['field'] == 'status' && $req['value'] == 1){
            $existingUser = User::where('ic_number', $user['ic_number'])->where('id', '<>', $req['id'])->where('status', 1)->find();
            if ($existingUser) {
                return out(null, 10001, '该身份证号已被其他用户使用');
            }
        }

        User::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    /**
     * 一键封禁/解封用户及其下属团队
     */
    public function banUserAndTeam()
    {
        $req = $this->validate(request(), [
            'user_id' => 'require|number',
            'action' => 'require|in:ban,unban',
        ]);
        
        $user = User::where('id', $req['user_id'])->find();
        if (!$user) {
            return out(null, 10001, '用户不存在');
        }
        
        // 获取用户的下三级用户ID
        $subUserIds = UserRelation::where('user_id', $req['user_id'])->whereIn('level', [1, 2, 3])->column('sub_user_id');
        
        // 添加用户自己
        $allUserIds = array_merge([$req['user_id']], $subUserIds);
        
        // 根据操作类型设置状态
        $status = $req['action'] == 'ban' ? 0 : 1;
        
        Db::startTrans();
        try {
            // 批量更新用户状态
            User::whereIn('id', $allUserIds)->update(['status' => $status]);
            
            Db::commit();
            
            $teamCount = count($subUserIds);
            $actionText = $req['action'] == 'ban' ? '封禁' : '解封';
            
            // 返回更新后的状态信息，包括个人状态和团队封禁状态
            return out([
                'message' => "成功{$actionText}用户及其{$teamCount}个团队成员",
                'status' => $status, // 个人状态
                'team_ban_status' => $status // 团队封禁状态
            ]);
            // return out(, 200, "成功{$actionText}用户及其{$teamCount}个团队成员");
            
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '操作失败：' . $e->getMessage());
        }
    }

    public function editPhone(){
        if(request()->isPost()){
            $req = $this->validate(request(), [
                'user_id'=>'require',
                'phone|手机号' => 'require|mobile',
            ]);
            $new = User::where('phone',$req['phone'])->find();
            if($new){
                return out(null,10001,'已有的手机号');
            }
            $user = User::where('id',$req['user_id'])->find();
            $ret = User::where('id',$req['user_id'])->update(['phone'=>$req['phone'],'prev_phone'=>$user['phone']]);
            return out();
        }else{
            $req = $this->validate(request(), [
                'user_id'=>'require',
            ]);
            $user = User::where('id',$req['user_id'])->find();
            $this->assign('data', $user);

            return $this->fetch();
        }
    }

    public function editRelease(){
        if(request()->isPost()){
            $req = $this->validate(request(), [
                'user_id'=>'require',
                'private_release|释放提现额度' => 'require',
            ]);
            //$user = User::where('id',$req['user_id'])->find();
            $ret = User::where('id',$req['user_id'])->update(['private_release'=>$req['private_release']]);
            return out();
        }else{
            $req = $this->validate(request(), [
                'user_id'=>'require',
            ]);
            $user = User::where('id',$req['user_id'])->find();
            $this->assign('data', $user);

            return $this->fetch();
        }
    }

    public function editBank(){
        if(request()->isPost()){
            $req = $this->validate(request(), [
                'user_id'=>'require',
                'bank_name|姓名' => 'require',
                'bank_number|银行卡号' => 'require',
            ]);
            $user = User::where('id',$req['user_id'])->find();
            $ret = User::where('id',$req['user_id'])->update(['bank_name'=>$req['bank_name'],'bank_number'=>$req['bank_number']]);
            return out();
        }else{
            $req = $this->validate(request(), [
                'user_id'=>'require',
            ]);
            $user = User::where('id',$req['user_id'])->find();
            $this->assign('data', $user);

            return $this->fetch();
        }
    }

    public function showChangeBalance()
    {
        $req = request()->get();
        $this->validate($req, [
            'user_id' => 'require|number',
            'type' => 'require|in:15,16',
        ]);

        $this->assign('req', $req);

        return $this->fetch();
    }

    public function showChangeOrder()
    {
        $req = request()->get();
        $this->validate($req, [
            'user_id' => 'require|number',
            'type' => 'require|in:15,16',
        ]);

        // 检查并更新用户的产品组完成状态
        UserProjectGroup::checkAndUpdateUserGroups($req['user_id']);

        $this->assign('req', $req);
        $this->assign('project1', Project::where('id', 155)->order('id', 'desc')->select()->toArray());
        
        return $this->fetch();
    }

    /**
     * 赠送产品
     */
    public function giftProduct()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'project_id' => 'require|number',
        ]);

        // 限制重复操作
        $clickRepeatName = 'giftProduct-' . $req['user_id'] . '-' . $req['project_id'];
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $adminUser = $this->adminUser;
        
        // 检查用户是否存在
        $user = User::where('id', $req['user_id'])->find();
        if (!$user) {
            return out(null, 10001, '用户不存在');
        }
        
        // 检查用户状态
        if ($user['status'] == 0) {
            return out(null, 10001, '用户已被封禁');
        }

        // 检查项目是否存在
        $project = Project::where('id', $req['project_id'])->where('status', 1)->find();
        if (!$project) {
            return out(null, 10001, '项目不存在或已下架');
        }

        // 检查用户赠送次数限制
        if (!GiftRecord::canGift($req['user_id'])) {
            $completedGroups = UserProjectGroup::getUserCompletedGroups($req['user_id']);
            $giftCount = GiftRecord::getUserGiftCount($req['user_id']);
            $maxAllowed = min($completedGroups, 5);
            return out(null, 10001, "该用户已完成{$completedGroups}个产品组，已赠送{$giftCount}次，最多可赠送{$maxAllowed}次，无法继续赠送");
        }

        Db::startTrans();
        try {
            // 生成订单号
            $order_sn = 'GIFT'.build_order_sn($req['user_id']);
            
            // 准备订单数据
            $orderData = [
                'user_id' => $req['user_id'],
                'up_user_id' => $user['up_user_id'],
                'order_sn' => $order_sn,
                'project_id' => $req['project_id'],
                'project_name' => $project['name'],
                'project_group_id' => $project['project_group_id'],
                'class' => $project['class'],
                'cover_img' => $project['cover_img'],
                'single_amount' => $project['single_amount'],
                'single_integral' => $project['single_integral'],
                'total_num' => $project['total_num'],
                'daily_bonus_ratio' => $project['daily_bonus_ratio'],
                'sum_amount' => $project['sum_amount'],
                'dividend_cycle' => $project['dividend_cycle'],
                'period' => $project['period'],
                'single_gift_equity' => $project['single_gift_equity'],
                'single_gift_digital_yuan' => $project['single_gift_digital_yuan'],
                'sham_buy_num' => $project['sham_buy_num'],
                'progress_switch' => $project['progress_switch'],
                'bonus_multiple' => $project['bonus_multiple'],
                'settlement_method' => $project['settlement_method'],
                'min_amount' => $project['min_amount'],
                'max_amount' => $project['max_amount'],
                'year_income' => $project['year_income'],
                'total_quota' => $project['total_quota'],
                'remaining_quota' => $project['remaining_quota'],
                'gongfu_amount' => $project['gongfu_amount'],
                'minsheng_amount' => $project['minsheng_amount'],
                'huimin_days_return' => $project['huimin_days_return'],
                'buy_num' => 1,
                'pay_method' => 1, // 赠送方式
                'price' => $project['single_amount'],
                'buy_amount' => $project['single_amount'],
                'status' => 2, // 直接设置为已支付状态
                'pay_time' => time(),
                'is_admin_confirm' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            // 创建订单
            $order = Order::create($orderData);

            // 记录赠送记录
            GiftRecord::create([
                'user_id' => $req['user_id'],
                'project_id' => $req['project_id'],
                'project_name' => $project['name'],
                'order_sn' => $order_sn,
                'gift_amount' => $project['single_amount'],
                'admin_user_id' => $adminUser['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 完成订单支付流程（参考placeOrder方法，但不扣钱）
            Order::orderPayComplete($order['id'], $project, $req['user_id'], $project['single_amount']);

            // 给上3级团队奖
            // $relation = UserRelation::where('sub_user_id', $req['user_id'])->select();
            // $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
            // foreach ($relation as $v) {
            //     $reward = round(dbconfig($map[$v['level']])/100*$project['single_amount'], 2);
            //     if($reward > 0){
            //         User::changeInc($v['user_id'],$reward,'team_bonus_balance',8,$order['id'],2,'团队奖励',0,2,'TD');
            //     }
            // }

            // 如果项目有共富专项金，直接发放
            if($project['gongfu_amount'] > 0){
                User::changeInc($req['user_id'], $project['gongfu_amount'], 'butie',52,$order['id'],3,'共富专项金',0,1);
            }
            
            // 如果项目有民生保障金，直接发放
            if($project['minsheng_amount'] > 0){
                User::changeInc($req['user_id'], $project['minsheng_amount'], 'balance',52,$order['id'],4,'民生保障金',0,1);
            }

            Db::commit();
            return out(['order_id' => $order['id']], 0, '产品赠送成功');
        } catch (Exception $e) {
            Db::rollback();
            return out(null, 10001, '赠送失败：' . $e->getMessage());
        }
    }

    /**
     * 获取用户赠送次数
     */
    public function getGiftCount()
    {
        $req = request()->get();
        $this->validate($req, [
            'user_id' => 'require|number',
        ]);

        $giftCount = GiftRecord::getUserGiftCount($req['user_id']);
        $completedGroups = UserProjectGroup::getUserCompletedGroups($req['user_id']);
        $availableCount = GiftRecord::getAvailableGiftCount($req['user_id']);
        
        return out([
            'count' => $giftCount,
            'completed_groups' => $completedGroups,
            'available_count' => $availableCount
        ]);
    }

    /**
     * 赠送记录列表
     */
    public function giftRecordList()
    {
        $req = request()->param();

        $builder = GiftRecord::alias('g')
            ->leftJoin('user u', 'u.id = g.user_id')
            ->leftJoin('project p', 'p.id = g.project_id')
            ->leftJoin('admin_user au', 'au.id = g.admin_user_id')
            ->field('g.*, u.phone, u.realname, p.name as project_name, au.username as admin_name')
            ->order('g.id', 'desc');

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('g.user_id', $req['user_id']);
        }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', 'like', '%' . $req['phone'] . '%');
        }
        if (isset($req['project_id']) && $req['project_id'] !== '') {
            $builder->where('g.project_id', $req['project_id']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('g.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('g.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        $data = $builder->paginate(['query' => $req]);

        // 获取项目列表用于筛选
        $projects = Project::where('status', 1)->field('id,name')->select()->toArray();
        
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('projects', $projects);

        return $this->fetch();
    }

    public function batchShowBalance()
    {
        $req = request()->get();

        return $this->fetch();
    }

    public function addBalance()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'money' => 'require|float',
            'type'=>'require|number',
            'remark' => 'max:50',
        ]);
        
        // 限制重复操作
        $clickRepeatName = 'addBalance-' . $req['user_id'] . '-' . $req['type'];
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);
        
        $adminUser = $this->adminUser;
        switch($req['type']){
            case 1:
                $filed = 'topup_balance';
                $log_type = 1;
                $balance_type = 15;
                $text = '余额';
                break;
            case 2:
                $filed = 'balance';
                $log_type = 4;
                $balance_type = 15;
                $text = '民生钱包';
                break;
            case 3:
                $filed = 'team_bonus_balance';
                $log_type = 2;
                $balance_type = 15;
                $text = '荣誉钱包';
                break;
            case 4:
                $filed = 'digit_balance';
                $log_type = 5;
                $balance_type = 15;
                $text = '惠民钱包';
                break;
            case 5:
                $filed = 'butie';
                $log_type = 3;
                $balance_type = 15;
                $text = '稳盈钱包';
                break;
            case 12:
                $filed = 'topup_balance';
                $log_type = 1;
                $balance_type = 15;
                $text = '专属充值';
                break;
            case 15:
                $filed = 'puhui';
                $log_type = 13;
                $balance_type = 15;
                $text = '普惠钱包';
                break;
            case 16:
                $filed = 'zhenxing_wallet';
                $log_type = 14;
                $balance_type = 15;
                $text = '振兴钱包';
                break;
            case 17:
                $filed = 'gongfu_wallet';
                $log_type = 16;
                $balance_type = 15;
                $text = '共富钱包';
                break;
            default:
                return out(null, 10001, '类型错误');
        }

        if (0>$req['money']){
            $str = '手动出金到'.$text;
        } else {
            $str = '手动入金到'.$text;
        }

        Db::startTrans();
        try{
            // 保存充值记录
            if($req['type'] == 12) {
                $userInfo = User::where('id',$req['user_id'])->find();
                $capital_sn = build_order_sn($req['user_id']);
                $capital = Capital::create([
                    'user_id' => $req['user_id'],
                    'capital_sn' => $capital_sn,
                    'type' => 1,
                    'pay_channel' => 1,
                    'amount' => $req['money'],
                    // 'withdraw_amount' => $req['money'],
                    // 'withdraw_fee' => 0,
                    'realname' => $userInfo['realname'],
                    'phone' => $userInfo['phone'],
                    'account' => $req['money'],
                    'log_type' => $log_type,
                    'audit_remark' => $str,
                    'status' => 2
                ]);
                $capital_id = $capital['id'];
                $text = !isset($req['remark']) || $req['remark'] == '' ? '专属充值' : $req['remark'];
            }else{
                $capital_id = 0;
                $text = !isset($req['remark']) || $req['remark'] == '' ? $str : $req['remark'];
            }

            User::changeInc($req['user_id'],$req['money'],$filed,$balance_type,$capital_id,$log_type,$text,$adminUser['id']);
            if($filed == 'topup_balance') {
                if($req['type'] == 12){
                        // 增加积分
                        $text = !isset($req['remark']) || $req['remark'] == '' ? '积分' : $req['remark'];
                        User::changeInc($req['user_id'],$req['money'],'integral',15,$capital_id,6,$text,$adminUser['id'],1,'CZ');
                }
            }
            Db::commit();
            return out();
        }catch(\Exception $e){
            Db::rollback();
            return out(null, 10001, $e->getMessage());
        }
        

    }

    public function addOrder()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'project_id'=>'require|number',
        ]);

        $clickRepeatName = 'addOrder-' . $req['user_id'];
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);

        $user = User::find($req['user_id']);
        $order_sn = 'JH'.build_order_sn($user['id']);
        $project = Project::find($req['project_id']);
        $pay_amount = $project['price'];
        Order::create([
            'user_id' => $req['user_id'],
            'up_user_id' => $user['up_user_id'],
            'order_sn' => $order_sn,
            'status' => 1,
            'buy_num' => 1,
            'pay_method' => $req['pay_method'] ?? 1,
            'price' => $pay_amount,
            'buy_amount' => $pay_amount,
            'shouyi' => $project['shouyi'],
            'project_id' => $project['id'],
            'project_name' => $project['name'],
            'shengyu' => $project['shengyu'],
            'cover_img' => $project['cover_img'],
            'shengyu_butie' => $project['shengyu_butie'],
            'zhaiwujj' => $project['zhaiwujj'],
            'buzhujin' => $project['buzhujin'],
            'shouyijin' => $project['shouyijin'],
            'project_group_id' => $project['project_group_id'],
            
            'is_gift' => 1,
        ]);


        if ($user['is_active'] == 0) {
            User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
            UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
        }

        if ($project['name'] == "科技与社会发展"){
            UserCardRecord::create([
                'user_id' => $req['user_id'],
                'fee' => $pay_amount,
                'status' => 2,//初始话办卡中
                'card_id' => 5,
                'bank_card' => '62'.time().$user['id'],
            ]);
        }

        return out();
    }

    public function batchBalance()
    {
        $req = request()->post();
        $this->validate($req, [
            'users' => 'require',
            'money' => 'require|float',
            'type'=>'require|number',
            'remark' => 'max:50',
        ]);
        $phoneList = explode(PHP_EOL, $req['users']);
        if(count($phoneList)<=0){
            return out(null, 10001, '用户不能为空');
        }
        $adminUser = $this->adminUser;
        $filed = 'balance';
        $log_type = 0;
        $balance_type = 1;
        $text = '余额';
        switch($req['type']){
            case 1:
                $filed = 'topup_balance';
                $log_type = 1;
                $balance_type = 15;
                $text = '余额';
                break;
            case 2:
                $filed = 'income_balance';
                $log_type = 2;
                $balance_type = 15;
                $text = '收益';
                break;
            case 3:
                $filed = 'shengyu_balance';
                $log_type = 3;
                $balance_type = 15;
                $text = '生育津贴';
                break;
            case 4:
                $filed = 'xuanchuan_balance';
                $log_type = 4;
                $balance_type = 15;
                $text = '宣传奖励';
                break;
            case 5:
                $filed = 'shengyu_butie_balance';
                $log_type = 5;
                $balance_type = 15;
                $text = '生育补贴';
                break;
            default:
                return out(null, 10001, '类型错误');
        }
        $str = '客服专员入金';
        if (0>$req['money']){
            $str = '金额转出';
        } else {
            $str = '金额转入';
        }
        //User::changeBalance($req['user_id'], $req['money'], 15, 0, 1, $req['remark']??'', $adminUser['id']);
        $text = !isset($req['remark']) || $req['remark'] == '' ? $str : $req['remark'];
        foreach($phoneList as $key=>$phone){
            $phoneList[$key] = trim($phone);
        }
        $ids = User::whereIn('phone',$phoneList)->column('id');
        Db::startTrans();
        try{
            foreach($ids as $id){
                User::changeInc($id,$req['money'],$filed,$balance_type,0,$log_type,$text,$adminUser['id']);
            }
        }catch(\Exception $e){
            Db::rollback();
            return out(null, 10001, $e->getMessage());
        }
        Db::commit();

        return out();
    }

    public function deductBalance()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'money' => 'require|float',
            'remark' => 'max:50',
        ]);
        $adminUser = $this->adminUser;

        $user = User::where('id', $req['user_id'])->find();
        if ($user['balance'] < $req['money']) {
            return out(null, 10001, '用户余额不足');
        }

        if (Capital::where('user_id', $user['id'])->where('type', 2)->where('pay_channel', 1)->where('status', 1)->count()) {
            return out(null, 10001, '该用户有待审核的手动出金，请先去完成审核');
        }

        // 保存到资金记录表
        Capital::create([
            'user_id' => $user['id'],
            'capital_sn' => build_order_sn($user['id']),
            'type' => 2,
            'pay_channel' => 1,
            'amount' => 0 - $req['money'],
            'withdraw_amount' => $req['money'],
            'audit_remark' => $req['remark'] ?? '',
            'admin_user_id' => $adminUser['id'],
        ]);

        return out();
    }

    public function userTeamList()
    {
        $req = request()->get();

        $user = User::where('id', $req['user_id'])->find();

        $data = ['user_id' => $user['id'], 'phone' => $user['phone']];

        $total_num = UserRelation::where('user_id', $req['user_id']);
        $active_num = UserRelation::where('user_id', $req['user_id'])->where('is_active', 1);
        if (!empty($req['start_date'])) {
            $total_num->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $active_num->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $data['total_num'] = $total_num->count();
        $data['active_num'] = $active_num->count();


        $total_num1 = UserRelation::where('user_id', $req['user_id'])->where('level', 1);
        $active_num1 = UserRelation::where('user_id', $req['user_id'])->where('level', 1)->where('is_active', 1);
        if (!empty($req['start_date'])) {
            $total_num1->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $active_num1->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $data['total_num1'] = $total_num1->count();
        $data['active_num1'] = $active_num1->count();

        $total_num2 = UserRelation::where('user_id', $req['user_id'])->where('level', 2);
        $active_num2 = UserRelation::where('user_id', $req['user_id'])->where('level', 2)->where('is_active', 1);
        if (!empty($req['start_date'])) {
            $total_num2->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $active_num2->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $data['total_num2'] = $total_num2->count();
        $data['active_num2'] = $active_num2->count();

        $total_num3 = UserRelation::where('user_id', $req['user_id'])->where('level', 3);
        $active_num3 = UserRelation::where('user_id', $req['user_id'])->where('level', 3)->where('is_active', 1);
        if (!empty($req['start_date'])) {
            $total_num3->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $active_num3->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $data['total_num3'] = $total_num3->count();
        $data['active_num3'] = $active_num3->count();

        $this->assign('data', $data);
        $this->assign('req', $req);
        return $this->fetch();
    }
    public function KKK(){
        $a = User::field('id,up_user_id,is_active')->limit(0,150000)->select()->toArray();
        // $a = User::field('id,up_user_id,is_active')->limit(150000,150000)->select()->toArray();
        // $a = User::field('id,up_user_id,is_active')->limit(300000,150000)->select()->toArray();
        // echo '<pre>';print_r($a);die;
        $re = $this->tree($a,4);
        echo count($re);
    }

    public function tree($data,$pid){
        static $arr = [];
        foreach($data as $k=>$v){
            if($v['up_user_id']==$pid && $v['is_active'] == 1){
                $arr[] = $v;
                unset($data[$k]);
                $this->tree($data,$v['id']);
            }
        }
        return $arr;
    }

    /**
     * 实名认证
     */
    public function authentication()
    {
        $req = request()->param();

        $builder = Authentication::order('id', 'desc');
        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('user_id', $req['user_id']);
        }
        
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('phone', $req['phone']);
        }
        if (isset($req['realname']) && $req['realname'] !== '') {
            $builder->where('realname', $req['realname']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }

        // 设置每页显示数量
        $limit = isset($req['limit']) ? intval($req['limit']) : 10;
        $data = $builder->paginate($limit);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    /**
     * 实名认证通过
     */
    public function pass()
    {
        $req = request()->param();
        //5秒内限制重复提交
        if(cache::get('update_user_auth_pass_'.$req['id'])){
            return out(null, 10001, '操作过于频繁，请稍后再试');
        }
        cache::set('update_user_auth_pass_'.$req['id'],1,5);
        $authentication = Authentication::find($req['id']);
        if (!$authentication) {
            return out(null, 10001, '认证记录不存在');
        }

        // 检查是否已审核
        if ($authentication['status'] != 0) {
            return out(null, 10001, '该记录已审核，不能重复操作');
        }

        // 检查用户是否存在
        $user = User::where('id', $authentication['user_id'])->find();
        if (!$user) {
            return out(null, 10001, '用户不存在');
        }

        // 验证手机号是否一致
        if ($authentication['phone'] != $user['phone']) {
            return out(null, 10001, '认证手机号与用户手机号不一致');
        }

        Db::startTrans();
        try {
            // 更新用户实名信息
            User::where('id', $authentication['user_id'])->update([
                'realname' => $authentication['realname'],
                'ic_number' => $authentication['card_number'],
                'shiming_status' => 1,
            ]);

            // 更新认证状态
            Authentication::where('id', $req['id'])->update([
                'status' => 1,
                'checked_at' => date('Y-m-d H:i:s')
            ]);

            //上级奖励5元
            $direct_recommend_reward_team_bonus_balance = dbconfig('direct_recommend_reward_team_bonus_balance');
            if($direct_recommend_reward_team_bonus_balance > 0){
                User::changeInc($user['up_user_id'],$direct_recommend_reward_team_bonus_balance,'team_bonus_balance',66,$req['id'],2,'直推实名认证奖励'.'-'.$user['realname'],0,2,'TD');
            }
            
            //上级获得一张幸福助力卷
            if(dbconfig('direct_recommend_reward_zhulijuan') > 0){
                User::changeInc($user['up_user_id'], dbconfig('direct_recommend_reward_zhulijuan'), 'xingfu_tickets', 66, $user['id'], 12, '实名审核通过奖励-' . $user['realname'], 0, 2, 'TD');
            }
            //振兴钱包
            if(dbconfig('direct_recommend_reward_zhenxing') > 0){
                User::changeInc($user['up_user_id'], dbconfig('direct_recommend_reward_zhenxing'), 'zhenxing_wallet', 66, $user['id'], 14, '直推实名认证奖励-' . $user['realname'], 0, 2, 'TD');
            }
            

            if(dbconfig('direct_recommend_reward_jifen') > 0){
                User::changeInc($user['up_user_id'], dbconfig('direct_recommend_reward_jifen'), 'integral', 66, $user['id'], 6, '直推实名认证奖励-' . $user['realname'], 0, 2, 'TD');
            }
            
            
            Db::commit();
            return out();
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '操作失败：' . $e->getMessage());
        }
    }

    /**
     * 实名认证拒绝
     */
    public function reject()
    {
        $req = request()->param();
        if (empty($req['reason'])) {
            return out(null, 10001, '请填写拒绝原因');
        }

        $authentication = Authentication::find($req['id']);
        if (!$authentication) {
            return out(null, 10001, '认证记录不存在');
        }

        // 检查是否已审核
        if ($authentication['status'] != 0) {
            return out(null, 10001, '该记录已审核，不能重复操作');
        }

        Authentication::where('id', $req['id'])->update([
            'status' => 2,
            'checked_at' => date('Y-m-d H:i:s'),
            'remark' => $req['reason']
        ]);
        return out();
    }

    /**
     * 导入
     */
    public function import()
    {
        return $this->fetch();
    }

    public function importSubmit()
    {
        if(Cache::get('user-importSubmit')){
            return out(null,10002,'请勿重复提交');
        }
        Cache::set('user-importSubmit',1,5);
        $file = upload_file3('file');
        $walletType = request()->param('wallet_type');
        $amount = request()->param('amount');
        $remark = request()->param('remark', ''); // 获取备注字段，默认为空字符串
        
        $spreadsheet = IOFactory::load($file);
        $sheetData = $spreadsheet->getActiveSheet()->toArray();

        
        $newArr = [];
        $errorArr = [];
        if(Cache::store('redis')->lLen('批量入金任务队列') > 0){
           return out(null,10001,'任务队列已存在');
        }
        $batchId = date('YmdHi').'-'.uniqid();
        foreach ($sheetData as $key => $value) {
            $v = "用户phone=".$value[0]."｜金额=".$amount."｜钱包=".$walletType."｜备注=".$remark."｜批次id=".$batchId;
            Cache::store('redis')->lpush('批量入金任务队列', $v);
            // $res = Db::name('user')->field('realname, phone, id')->where('phone', trim($value[0]))->select()->toArray();
            // // return out(trim($value[0]));
            // if (count($res) == 1) {
            //     $singleArr = $res[0];
            //     $singleArr['amount'] = $value[1];
            //     $singleArr['remark'] = $value[2];
            //     $singleArr['head'] = 0;
            //     $singleArr['wallet_type'] = $walletType;
            //     array_push($newArr, $singleArr);
            // } else {
            //     $errorArr[] = $value[0];
            // }
        }
        // $data = [
        //     'success' => $newArr,
        //     'error' => $errorArr
        // ];
        return out($sheetData);
    }

    public function importExec()
    {
        $req = request()->param();
        $ids = $req['ids'];
        Db::startTrans();
        try {
            foreach ($ids as $key => $value) {
                $field = 'topup_balance';
                switch($value['wallet_type']) {
                    case 'topup_balance':
                        $field = 'topup_balance';
                        $type = 99;
                        $log_type = 1;
                        break;
                    case 'team_bonus_balance':
                        $field = 'team_bonus_balance';
                        $log_type = 2;
                        break;
                    case 'butie':
                        $field = 'butie';
                        $log_type = 3;
                        break;
                    case 'balance':
                        $field = 'balance';
                        $log_type = 4;
                        break;
                    case 'digit_balance':
                        $field = 'digit_balance';
                        $log_type = 5;
                        break;
                    default:
                        throw new \Exception('无效的钱包类型');
                }
                User::changeInc($value['id'], $value['amount'], $field, 102, $value['id'], $log_type, $value['remark'], '', 1, 'IM');
            }
            Db::commit();
        } catch(\Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    //银行卡列表
    public function bankList(Request $request)
    {
        $req = $request->param();
        $builder = UserBank::where('user_id', $req['user_id']);
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        $this->assign('status', ['未激活','激活']);
        return view();
    }

    //银行卡修改状态
    public function bankedit(Request $request)
    {
        $req = $request->param();
        $res = UserBank::where('id', $req['id'])->data(['status' => $req['status']])->update();
        return out();
    }

    //银行卡删除
    public function bankdel(Request $request)
    {
        $req = $request->param();
        $res = UserBank::where('id', $req['id'])->delete();
        return out();
    }

    //银行卡修改
    public function updateBank(Request $request)
    {
        $req = $this->validate(request(), [
            'id|银行卡ID' => 'require|number',
            'name|姓名' => 'require',
            'bank_name|银行名称' => 'require',
            'bank_address|开户行' => 'require',
            'bank_sn|卡号' => 'require',
        ]);

        $updateData = [
            'name' => $req['name'],
            'bank_name' => $req['bank_name'],
            'bank_address' => $req['bank_address'],
            'bank_sn' => $req['bank_sn'],
        ];

        try {
            UserBank::where('id', $req['id'])->update($updateData);
            return out();
        } catch (\Exception $e) {
            return out(null, 10001, '修改失败：' . $e->getMessage());
        }
    }

    //开户认证
    public function openAuth(Request $request)
    {
        $req = $request->param();
        $builder = Apply::where('user_id', $req['user_id']);
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        return view();
    }

    //户口认证列表
    public function homelandList(Request $request)
    {
        $req = $request->param();
        $req['type'] = 6;
        $builder = Db::table('mp_family_child')->alias('a')
                        ->field('a.id, a.user_id,a.my, a.created_at, a.child1, a.child2, a.child3, a.family_members, a.family_address, b.realname')
                        ->leftJoin('mp_user b','a.user_id = b.id')
                        ->where('a.user_id', $req['user_id']);

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        return view();
    }


    //看房
    public function reserveHouse(Request $request)
    {
        $req = $request->param();
        $builder = Db::table('mp_reserve')->where('user_id', $req['user_id'])->where('type',1);
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        $this->assign('status', ['未激活','激活']);
        return view();
    }

    //看房
    public function reserveCar(Request $request)
    {
        $req = $request->param();
        $builder = Db::table('mp_reserve')->where('user_id', $req['user_id'])->where('type',2);
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('count', $builder->count());
        $this->assign('data', $data);
        $this->assign('status', ['未激活','激活']);
        return view();
    }

    //修改状态
    public function reserveEditStatus(Request $request)
    {
        $req = $request->param();
        $res = Db::table('mp_reserve')->where('id', $req['id'])->data(['status' => $req['status']])->update();
        return out();
    }

    //获取我的地址
    public function address(Request $request)
    {
        $req = $request->param();
        $data = Db::table('mp_user_delivery')->where('user_id', $req['user_id'])->select();

        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('count', $data->count());
        return view();
    }

    //银行卡页面展示
    public function showBank(Request $request)
    {
        $this->assign('user_id', $request['user_id']);
        return $this->fetch();
    }

    //添加银行卡
    public function addBank(Request $request)
    {
        $req = $this->validate(request(), [
            'user_id|用户id' => 'require',
            'name|姓名' => 'require',
            'bank_name|银行名称' => 'require',
            'bank_address|银行地址' => 'require',
            'bank_sn|银行卡号' => 'require',
        ]);

        $data = [
            'user_id'   => $req['user_id'],
            'name'      => $req['name'],
            'bank_name' => $req['bank_name'],
            'bank_address' => $req['bank_address'],
            'bank_sn'   => $req['bank_sn'],
            'reg_date'  => date('Y-m-d H:i:s'),
            'status'    => 0,
        ];
        UserBank::create($data);
        return out(null,200,'成功');
    }

    //展示身份证
    public function showUserNum(Request $request)
    {
        $this->assign('user_id', $request['user_id']);
        $data = User::where('id', $request['user_id'])->find();
        $this->assign('data', $data);
        return $this->fetch();
    }

    //添加身份证
    public function addUserNum(Request $request)
    {
        $req = $this->validate(request(), [
            'user_id|用户id' => 'require',
            'realname|真实姓名' => 'require',
            'card_number|身份证号' => 'require',
            'bank_card|银行卡号' => 'require',
            'phone|手机号' => 'require',
            'gender|性别' => 'require',
        ]);

        $req['card_front'] = upload_file('cover_img1');
        $req['card_back'] = upload_file('cover_img2');

        $data = [
            'user_id'       => $req['user_id'],
            'card_front' => $req['card_front'],
            'card_back' => $req['card_back'],
            'realname' => $req['realname'],
            'phone' => $req['phone'],
            'gender' => $req['gender'],
            'card_number' => $req['card_number'],
            'bank_card' => $req['bank_card'],
            'created_at'    => date('Y-m-d H:i:s'),
            'status'        => 0,
        ];
        Authentication::create($data);
        return out(null,200,'成功');
    }

    public function delUserNum()
    {
        $req = $this->validate(request(), [
            'id|用户id' => 'require',
        ]);

        Apply::where('id',$req['id'])->delete();
        return out([null,200,'成功']);
    }

    //家庭，展示
    public function showFamily(Request $request)
    {
        $this->assign('user_id', $request['user_id']);
        return $this->fetch();
    }

    //添加家庭成员
    public function addUserFamily()
    {
        $req = request()->param();

        if ($my = upload_file('cover_img1', false)) {
            $req['my'] = $my;
        }

        $req['child1'] = upload_file('cover_img2');

        if ($cover_img3 = upload_file('cover_img3', false)) {
            $req['child2'] = $cover_img3;
        }

        if ($cover_img4 = upload_file('cover_img4', false)) {
            $req['child3'] = $cover_img4;
        }

        $data = [
            'user_id'=> $req['user_id'],
            'my'     => $req['my'] ?? "",
            'child1' => $req['child1'] ?? "",
            'child2' => $req['child2'] ?? "",
            'child3' => $req['child3'] ?? "",
            'family_members' => $req['family_members'],
            'family_address' => $req['family_address'],
            'created_at'   => date('Y-m-d H:i:s'),
        ];
        FamilyChild::create($data);
        return out(null,200,'成功');
    }

    //删除家庭成员
    public function delUserFimly()
    {
        $req = $this->validate(request(), [
            'id|id' => 'require',
        ]);

        FamilyChild::where('id',$req['id'])->delete();
        return out([null,200,'成功']);
    }

    //产品-用户人数
    public function userNums(Request $request)
    {
        $req = request()->param();

        // 一级团队人数
        $data['level1_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 1)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 二级团队人数
        $data['level2_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 2)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 三级团队人数
        $data['level3_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 3)->where('created_at', '>=', '2024-02-24 00:00:00')->count();

        // 一级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 1)->column('sub_user_id');
        //分组id
        $a = Order::whereIn('user_id', $ids);
        if (isset($req['project_group_id']) && $req['project_group_id'] !== ''){
            $a->where('project_group_id', $req['project_group_id']);
        }
        if (isset($req['project_id']) && $req['project_id'] !== ''){
            $a->where('project_id', $req['project_id']);
        }
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $a->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $a->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $level1 = $a->count();

        // 二级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 2)->column('sub_user_id');
        $b = Order::whereIn('user_id', $ids);
        if (isset($req['project_group_id']) && $req['project_group_id'] !== ''){
            $b->where('project_group_id', $req['project_group_id']);
        }
        if (isset($req['project_id']) && $req['project_id'] !== ''){
            $b->where('project_id', $req['project_id']);
        }
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $b->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $b->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $level2 = $b->count();

        // 三级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 3)->column('sub_user_id');
        $c = Order::whereIn('user_id', $ids);
        if (isset($req['project_group_id']) && $req['project_group_id'] !== ''){
            $c->where('project_group_id', $req['project_group_id']);
        }
        if (isset($req['project_id']) && $req['project_id'] !== ''){
            $c->where('project_id', $req['project_id']);
        }
        if (!empty($req['start_date']) && $req['start_date'] !== '') {
            $c->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] !== '') {
            $c->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        $level3 = $c->count();

        if ((isset($req['project_group_id']) && $req['project_group_id'] !== '')  && (isset($req['project_id']) && $req['project_id'] !== '')){
            $data['level1_total'] = $level1;
            $data['level2_total'] = $level2;
            $data['level3_total'] = $level3;
        }

        $data['count'] = $data['level1_total'] + $data['level2_total'] + $data['level3_total'];

        $groupList = [
            "5" => "最后阶段"
        ];

        $projectList = [
            "117" => "全免费入学发展",
            "118" => "全免费医药发展",
            "119" => "科教与人才发展",
            "120" => "农村与农业发展",
            "121" => "科技与社会发展"
        ];
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('projectList', $projectList);
        $this->assign('groupList', $groupList);
        return view();
    }

    public function projectList(Request $request)
    {
        $req = request()->param();

        $projectList = [];
        if (isset($req['project_group_id']) && $req['project_group_id'] !== ''){
            $projectList = Project::where('project_group_id',$req['project_group_id'])->select();
        }
        return out($projectList);
    }

    public function applyedit(Request $request)
    {
        $req = $request->param();
        Apply::where('id', $req['id'])->data(['status' => $req['status']])->update();
        return out();
    }

    public function showChangeJiaoNa()
    {
        $req = request()->get();
        $this->validate($req, [
            'user_id' => 'require|number',
        ]);

        $user = User::where('id',$req['user_id'])->find();
        //总数
        $data['total'] = $user['yixiaoduizijin'] + $user['zhaiwujj'] + $user['yu_e_bao'] + $user['buzhujin'] + $user['shouyijin'];
        //需缴纳
        $data['price'] = round($data['total'] * 0.02,2);
        //余额
        $data['topup_balance'] = $user['topup_balance'];
        //垫付
        $data['gift_prize'] = 0;
        if ($data['price'] > $data['topup_balance']) {
            $data['gift_prize'] = abs($data['topup_balance'] - $data['price']);
        }

        $this->assign('req', $req);
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function addJiaoNa()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
            'gift_prize' => 'require|number',
        ]);

        $clickRepeatName = 'product5-pay-' . $req['user_id'];
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }

        Cache::set($clickRepeatName, 1, 5);

        $order5count = Order5::where('user_id',$req['user_id'])->count();
        if ($order5count >= 1){
            return out(null, 10001, '已赠送');
        }

        Db::startTrans();
        try {
            $user = User::where('id',$req['user_id'])->find();
            //资金来源 已校对资金 债务基金 余额宝 补助金 收益金
            $total = $user['yixiaoduizijin'] + $user['zhaiwujj'] + $user['yu_e_bao'] + $user['buzhujin'] + $user['shouyijin'];
            $jiaofei = round($total * 0.02,2);
            $time = date('Y-m-d H:i:s');

            //钱不够
            if ($jiaofei > $user['topup_balance']) {
                User::changeInc1($user['id'],$req['gift_prize'],'topup_balance',103,$user['id'],1,'预付金',0,1);

                $order = Order5::create([
                    'user_id' => $req['user_id'],
                    'price' => $jiaofei,
                    'total' => $total,
                    'created_at' => $time,
                    'updated_at' => $time,
                    'is_gift' => 1,
                    'gift_prize' => $req['gift_prize'],
                ]);
                User::changeInc1($user['id'],-$jiaofei,'topup_balance',3,$order['id'],1,'资金来源证明',0,1);

                // 给上3级团队奖（迁移至申领）
                $relation = UserRelation::where('sub_user_id', $user['id'])->select();
                $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
                foreach ($relation as $v) {
                    $reward = round(dbconfig($map[$v['level']])/100*$user['topup_balance'], 2);
                    if($reward > 0){
                        User::changeInc1($v['user_id'],$reward,'xuanchuan_balance',8,$order['id'],4,'宣传奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                        RelationshipRewardLog::insert([
                            'uid' => $v['user_id'],
                            'reward' => $reward,
                            'son' => $user['id'],
                            'son_lay' => $v['level'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }


                if ($user['is_active'] == 0) {
                    User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                    UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
                }
            } else {
                $price = $jiaofei;
                //钱够
                $order = Order5::create([
                    'user_id' => $req['user_id'],
                    'price' => $price,
                    'total' => $total,
                    'created_at' => $time,
                    'updated_at' => $time,
                    'is_gift' => 1,
                    'gift_prize' => $req['gift_prize'],
                ]);
                User::changeInc($user['id'],-$price,'topup_balance',3,$order['id'],1,'资金来源证明',0,1);

                // 给上3级团队奖（迁移至申领）
                $relation = UserRelation::where('sub_user_id', $user['id'])->select();
                $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
                foreach ($relation as $v) {
                    $reward = round(dbconfig($map[$v['level']])/100*$price, 2);
                    if($reward > 0){
                        User::changeInc($v['user_id'],$reward,'xuanchuan_balance',8,$order['id'],4,'宣传奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                        RelationshipRewardLog::insert([
                            'uid' => $v['user_id'],
                            'reward' => $reward,
                            'son' => $user['id'],
                            'son_lay' => $v['level'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                //增加预付金
                if ($req['gift_prize'] > 0){
                    //增加 预付金
                    User::changeInc($user['id'],$req['gift_prize'],'topup_balance',103,$user['id'],1,'预付金',0,1);
                }

                if ($user['is_active'] == 0) {
                    User::where('id', $user['id'])->update(['is_active' => 1, 'active_time' => time()]);
                    UserRelation::where('sub_user_id', $user['id'])->update(['is_active' => 1]);
                }
            }

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    //缴纳人数
    public function jiaoNaNums()
    {
        $req = request()->param();
        $data['today'] = date('Y-m-d');

        // 一级团队人数
        //$data['level1_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 1)->where('created_at', '>=', $data['today'].' 00:00:00')->count();
        // 二级团队人数
        //$data['level2_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 2)->where('created_at', '>=', $data['today'].' 00:00:00')->count();
        // 三级团队人数
        //$data['level3_total'] = UserRelation::where('user_id', $req['user_id'])->where('level', 3)->where('created_at', '>=', $data['today'].' 00:00:00')->count();

        // 一级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 1)->column('sub_user_id');
        $a = Order5::whereIn('user_id', $ids);
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $a->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $a->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        if (empty($req['start_date']) && empty($req['end_date']) ){
            $a->where('created_at', '>=', $data['today'] . ' 00:00:00');
        }
        $level1 = $a->count();

        // 二级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 2)->column('sub_user_id');
        $b = Order5::whereIn('user_id', $ids);
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $b->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $b->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        if (empty($req['start_date']) && empty($req['end_date']) ){
            $b->where('created_at', '>=', $data['today'] . ' 00:00:00');
        }
        $level2 = $b->count();

        // 三级申领人数
        $ids =  UserRelation::where('user_id', $req['user_id'])->where('level', 3)->column('sub_user_id');
        $c = Order5::whereIn('user_id', $ids);
        if (!empty($req['start_date']) && $req['start_date'] != '') {
            $c->where('created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date']) && $req['end_date'] != '') {
            $c->where('created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        if (empty($req['start_date']) && empty($req['end_date']) ){
            $c->where('created_at', '>=', $data['today'] . ' 00:00:00');
        }
        $level3 = $c->count();

//        $data['totalLevel'] = $data['level1_total'] + $data['level2_total'] + $data['level3_total'];
        $data['count'] = $level1 + $level2 + $level3;
        $data['level1'] = $level1;
        $data['level2'] = $level2;
        $data['level3'] = $level3;

        $this->assign('req', $req);
        $this->assign('data', $data);
        return view();
    }

    /**
     * 批量实名认证通过
     */
    public function batchPass()
    {
        $req = request()->param();
        if (empty($req['ids']) || !is_array($req['ids'])) {
            return out(null, 10001, '请选择要审核的记录');
        }

        Db::startTrans();
        try {
            foreach ($req['ids'] as $id) {
                $authentication = Authentication::find($id);
                if (!$authentication) {
                    continue;
                }

                // 检查是否已审核
                if ($authentication['status'] != 0) {
                    continue;
                }

                // 检查用户是否存在
                $user = User::where('id', $authentication['user_id'])->find();
                if (!$user) {
                    continue;
                }

                // 验证手机号是否一致
                if ($authentication['phone'] != $user['phone']) {
                    continue;
                }

                // 更新用户实名信息
                User::where('id', $authentication['user_id'])->update([
                    'realname' => $authentication['realname'],
                    'ic_number' => $authentication['card_number'],
                    'shiming_status' => 1,
                ]);


                // 更新认证状态
                Authentication::where('id', $id)->update([
                    'status' => 1,
                    'checked_at' => date('Y-m-d H:i:s')
                ]);
                if($user['up_user_id']){
                    //上级奖励5元
                    $direct_recommend_reward_team_bonus_balance = dbconfig('direct_recommend_reward_team_bonus_balance');
                    if($direct_recommend_reward_team_bonus_balance > 0){
                        User::changeInc($user['up_user_id'],$direct_recommend_reward_team_bonus_balance,'team_bonus_balance',66,$req['id'],2,'直推实名认证奖励'.'-'.$user['realname'],0,2,'TD');
                    }
                    //上级获得一张幸福助力卷
                    if(dbconfig('direct_recommend_reward_zhulijuan') > 0){
                        User::changeInc($user['up_user_id'], dbconfig('direct_recommend_reward_zhulijuan'), 'xingfu_tickets', 66, $user['id'], 12, '实名审核通过奖励-' . $user['realname'], 0, 2, 'TD');
                    }
                    //振兴钱包
                    if(dbconfig('direct_recommend_reward_zhenxing') > 0){
                        User::changeInc($user['up_user_id'], dbconfig('direct_recommend_reward_zhenxing'), 'zhenxing_wallet', 66, $user['id'], 14, '直推实名认证奖励-' . $user['realname'], 0, 2, 'TD');
                    }
                    

                    if(dbconfig('direct_recommend_reward_jifen') > 0){
                        User::changeInc($user['up_user_id'], dbconfig('direct_recommend_reward_jifen'), 'integral', 66, $user['id'], 6, '直推实名认证奖励-' . $user['realname'], 0, 2, 'TD');
                    }
                    
                }
            
            }

            Db::commit();
            return out();
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '操作失败：' . $e->getMessage());
        }
    }

    /**
     * 批量实名认证拒绝
     */
    public function batchReject()
    {
        $req = request()->param();
        if (empty($req['ids']) || !is_array($req['ids'])) {
            return out(null, 10001, '请选择要审核的记录');
        }
        if (empty($req['reason'])) {
            return out(null, 10001, '请填写拒绝原因');
        }

        try {
            // 只更新未审核的记录
            Authentication::whereIn('id', $req['ids'])
                ->where('status', 0)
                ->update([
                    'status' => 2,
                    'checked_at' => date('Y-m-d H:i:s'),
                    'remark' => $req['reason']
                ]);
            return out();
        } catch (\Exception $e) {
            return out(null, 10001, '操作失败：' . $e->getMessage());
        }
    }

    /**
     * 删除实名认证记录
     */
    public function deleteAuth()
    {
        $req = request()->param();
        if (empty($req['id'])) {
            return out(null, 10001, '参数错误');
        }

        try {
            Authentication::where('id', $req['id'])->delete();
            return out();
        } catch (\Exception $e) {
            return out(null, 10001, '删除失败：' . $e->getMessage());
        }
    }

    /**
     * 批量删除实名认证记录
     */
    public function batchDeleteAuth()
    {
        $req = request()->param();
        if (empty($req['ids']) || !is_array($req['ids'])) {
            return out(null, 10001, '请选择要删除的记录');
        }

        try {
            Authentication::whereIn('id', $req['ids'])->delete();
            return out();
        } catch (\Exception $e) {
            return out(null, 10001, '删除失败：' . $e->getMessage());
        }
    }

    /**
     * 查询队列进度
     */
    public function checkQueueProgress()
    {
        try {
            // 获取Redis队列长度
            $queueLength = Cache::store('redis')->lLen('批量入金任务队列');
            
            $data = [
                'queue_length' => $queueLength,
                'last_check_time' => date('Y-m-d H:i:s'),
                'queue_name' => '批量入金任务队列'
            ];
            
            return out($data);
        } catch (\Exception $e) {
            return out(null, 10001, '查询队列进度失败：' . $e->getMessage());
        }
    }

    /**
     * 获取入金记录
     */
    public function getRujinRecords()
    {
        try {
            $redis = Cache::store('redis')->handler();
            $records = [];
            
            // 获取所有以"入金成功"开头的键
            $successKeys = $redis->keys('入金成功-*');
            foreach ($successKeys as $key) {
                $batchId = str_replace('入金成功-', '', $key);
                $phones = $redis->get($key);
                
                // 调试信息
                \think\facade\Log::debug('Redis键: ' . $key . ', 值: ' . $phones);
                
                // 确保phones不为null
                if ($phones === null || $phones === false) {
                    $phones = '';
                }
                
                $phoneArray = explode(',', $phones);
                $phoneArray = array_filter($phoneArray, function($phone) {
                    return trim($phone) !== '';
                });
                
                $records[] = [
                    'batch_id' => $batchId,
                    'type' => 'success',
                    'phones' => $phones,
                    'count' => count($phoneArray),
                    'created_time' => $this->getBatchTime($batchId)
                ];
            }
            
            // 获取所有以"入金失败"开头的键
            $failureKeys = $redis->keys('入金失败-*');
            foreach ($failureKeys as $key) {
                $batchId = str_replace('入金失败-', '', $key);
                $phones = $redis->get($key);
                
                // 调试信息
                \think\facade\Log::debug('Redis键: ' . $key . ', 值: ' . $phones);
                
                // 确保phones不为null
                if ($phones === null || $phones === false) {
                    $phones = '';
                }
                
                $phoneArray = explode(',', $phones);
                $phoneArray = array_filter($phoneArray, function($phone) {
                    return trim($phone) !== '';
                });
                
                $records[] = [
                    'batch_id' => $batchId,
                    'type' => 'failure',
                    'phones' => $phones,
                    'count' => count($phoneArray),
                    'created_time' => $this->getBatchTime($batchId)
                ];
            }
            
            // 按创建时间倒序排列
            usort($records, function($a, $b) {
                return strtotime($b['created_time']) - strtotime($a['created_time']);
            });
            
            return out($records);
        } catch (\Exception $e) {
            return out(null, 10001, '获取入金记录失败：' . $e->getMessage());
        }
    }
    
    /**
     * 根据批次ID获取创建时间
     */
    private function getBatchTime($batchId)
    {
        // 批次ID格式: YmdHi-uniqid
        $parts = explode('-', $batchId);
        if (count($parts) >= 2) {
            $datePart = $parts[0];
            // 解析日期时间: YmdHi -> Y-m-d H:i
            $year = substr($datePart, 0, 4);
            $month = substr($datePart, 4, 2);
            $day = substr($datePart, 6, 2);
            $hour = substr($datePart, 8, 2);
            $minute = substr($datePart, 10, 2);
            
            return $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute . ':00';
        }
        return date('Y-m-d H:i:s');
    }

    public function testRedis()
    {
        try {
            // 获取Redis配置信息
            $redisConfig = config('cache.stores.redis');
            
            // 测试写入Redis
            $testKey = 'test_redis_key_' . time();
            $testValue = 'test_value_' . time();
            
            // 尝试连接Redis
            try {
                $redis = Cache::store('redis')->handler();
                $connectionTest = $redis->ping();
            } catch (\Exception $e) {
                return out(null, 10001, 'Redis连接失败：' . $e->getMessage());
            }
            
            // 明确使用Redis存储
            $writeResult = Cache::store('redis')->set($testKey, $testValue, 3600);
            
            // 从Redis读取
            $readValue = Cache::store('redis')->get($testKey);
            
            // 获取Redis连接信息
            try {
                $info = $redis->info();
            } catch (\Exception $e) {
                $info = ['error' => $e->getMessage()];
            }
            
            // 安全获取配置信息
            $config = [
                'host' => $redisConfig['host'] ?? 'unknown',
                'port' => $redisConfig['port'] ?? 'unknown',
                'password' => isset($redisConfig['password']) && $redisConfig['password'] ? '已设置' : '未设置',
                'database' => $redisConfig['select'] ?? 0
            ];
            
            return out([
                'redis_config' => $config,
                'connection_test' => $connectionTest,
                'write_result' => $writeResult,
                'write_key' => $testKey,
                'write_value' => $testValue,
                'read_value' => $readValue,
                'redis_info' => [
                    'version' => $info['redis_version'] ?? 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 'unknown',
                    'used_memory' => $info['used_memory_human'] ?? 'unknown',
                    'status' => 'success'
                ]
            ]);
        } catch (\Exception $e) {
            // 获取更详细的错误信息
            $errorInfo = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            
            // 尝试获取Redis配置信息
            try {
                $redisConfig = config('cache.stores.redis');
                $errorInfo['redis_config'] = [
                    'host' => $redisConfig['host'] ?? 'unknown',
                    'port' => $redisConfig['port'] ?? 'unknown',
                    'password' => isset($redisConfig['password']) && $redisConfig['password'] ? '已设置' : '未设置',
                    'database' => $redisConfig['select'] ?? 0
                ];
            } catch (\Exception $configError) {
                $errorInfo['config_error'] = $configError->getMessage();
            }
            
            return out(null, 10001, 'Redis测试异常：' . json_encode($errorInfo, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 获取用户实名认证信息
     */
    public function getUserAuthInfo()
    {
        $req = $this->validate(request(), [
            'user_id' => 'require|number',
        ]);

        $user = User::where('id', $req['user_id'])->find();
        if (!$user) {
            return out(null, 10001, '用户不存在');
        }

        // 获取认证信息
        $auth = Authentication::where('user_id', $req['user_id'])->find();
        
        $data = [
            'realname' => $user['realname'] ?: ($auth['realname'] ?? ''),
            'phone' => $user['phone'],
            'ic_number' => $user['ic_number'] ?: ($auth['card_number'] ?? ''),
            'bank_card' => $auth['bank_card'] ?? '',
            'card_front' => $auth['card_front'] ?? '',
            'card_back' => $auth['card_back'] ?? '',
        ];

        return out($data);
    }

    /**
     * 更新用户实名认证信息
     */
    public function updateUserAuth()
    {
        $req = $this->validate(request(), [
            'user_id' => 'require|number',
            'ic_number' => 'require',
            'bank_card' => 'require',
        ]);

        //5秒内限制重复提交
        if(cache::get('update_user_auth_'.$req['user_id'])){
            return out(null, 10001, '操作过于频繁，请稍后再试');
        }
        cache::set('update_user_auth_'.$req['user_id'],1,5);

        $user = User::where('id', $req['user_id'])->find();
        if (!$user) {
            return out(null, 10001, '用户不存在');
        }

        // 检查身份证号是否已被其他用户使用
        $existingUser = User::where('ic_number', $req['ic_number'])->where('id', '<>', $req['user_id'])->where('status', 1)->find();
        if ($existingUser) {
            return out(null, 10001, '该身份证号已被其他用户使用');
        }

        Db::startTrans();
        try {
            // 处理身份证正面照上传
            $cardFront = '';
            if ($cardFrontimg = upload_file('card_front_file', false,false)) {
                $cardFront = $cardFrontimg;
            }
            $cardBack = '';
            if($cardBackimg = upload_file('card_back_file', false,false)){
                $cardBack = $cardBackimg;
            }

            if($user['shiming_status'] == 0 && $user['up_user_id']){
                //上级奖励5元
                $direct_recommend_reward_team_bonus_balance = dbconfig('direct_recommend_reward_team_bonus_balance');
                if($direct_recommend_reward_team_bonus_balance > 0){
                    User::changeInc($user['up_user_id'],$direct_recommend_reward_team_bonus_balance,'team_bonus_balance',66,$user['id'],2,'直推实名认证奖励'.'-'.$user['realname'],0,2,'TD');
                }
                //上级获得一张幸福助力卷
                if(dbconfig('direct_recommend_reward_zhulijuan') > 0){
                    User::changeInc($user['up_user_id'], dbconfig('direct_recommend_reward_zhulijuan'), 'xingfu_tickets', 66, $user['id'], 12, '实名审核通过奖励-' . $user['realname'], 0, 2, 'TD');
                }
                //振兴钱包
                if(dbconfig('direct_recommend_reward_zhenxing') > 0){
                    User::changeInc($user['up_user_id'], dbconfig('direct_recommend_reward_zhenxing'), 'zhenxing_wallet', 66, $user['id'], 14, '直推实名认证奖励-' . $user['realname'], 0, 2, 'TD');
                }
            
                if(dbconfig('direct_recommend_reward_jifen') > 0){
                    User::changeInc($user['up_user_id'], dbconfig('direct_recommend_reward_jifen'), 'integral', 66, $user['id'], 6, '直推实名认证奖励-' . $user['realname'], 0, 2, 'TD');
                }

            }

            // 更新用户表
            $userData = [
                'ic_number' => $req['ic_number'],
                'shiming_status' => 1, // 自动审核通过
            ];
            $user->save($userData);

            // 更新或创建认证记录
            $auth = Authentication::where('user_id', $req['user_id'])->find();
            if (!$auth) {
                $auth = new Authentication();
                $auth->user_id = $req['user_id'];
                $auth->realname = $user['realname'];
                $auth->phone = $user['phone'];
                $auth->card_number = $req['ic_number'];
                $auth->created_at = date('Y-m-d H:i:s');
            }

            $authData = [
                'bank_card' => $req['bank_card'],
                'status' => 1, // 自动审核通过
                'checked_at' => date('Y-m-d H:i:s'),
            ];

            // 只有上传了新图片才更新
            if ($cardFront) {
                $authData['card_front'] = $cardFront;
            }
            if ($cardBack) {
                $authData['card_back'] = $cardBack;
            }

            $auth->save($authData);


            Db::commit();
            return out(null, 200, '更新成功，实名认证已自动通过');
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '更新失败：' . $e->getMessage());
        }
    }

    public function suspiciousUserList()
    {
        $req = request()->param();
        
        // 构建基础SQL查询
        $base_sql = "SELECT DISTINCT
            u1.id as inviter_id,
            u1.phone as inviter_phone,
            u1.invite_code,
            u1.realname as inviter_realname,
            u1.created_at as inviter_created_at
        FROM mp_user u1
        WHERE EXISTS (
            SELECT 1 
            FROM mp_user u2, mp_user u3
            WHERE u2.up_user_id = u1.id 
                AND u3.up_user_id = u1.id
                AND u2.id < u3.id
                AND TIMESTAMPDIFF(SECOND, u2.created_at, u3.created_at) < 5
        )";
        
        // 添加搜索条件
        $where_conditions = [];
        $params = [];
        
        if (isset($req['inviter_phone']) && $req['inviter_phone'] !== '') {
            $where_conditions[] = "u1.phone = ?";
            $params[] = $req['inviter_phone'];
        }
        
        if (isset($req['invite_code']) && $req['invite_code'] !== '') {
            $where_conditions[] = "u1.invite_code = ?";
            $params[] = $req['invite_code'];
        }
        
        if (isset($req['inviter_realname']) && $req['inviter_realname'] !== '') {
            $where_conditions[] = "u1.realname = ?";
            $params[] = $req['inviter_realname'];
        }
        
        if (!empty($req['start_date'])) {
            $where_conditions[] = "u1.created_at >= ?";
            $params[] = $req['start_date'] . ' 00:00:00';
        }
        
        if (!empty($req['end_date'])) {
            $where_conditions[] = "u1.created_at <= ?";
            $params[] = $req['end_date'] . ' 23:59:59';
        }
        
        // 如果有搜索条件，添加到SQL中
        if (!empty($where_conditions)) {
            $base_sql .= " AND " . implode(" AND ", $where_conditions);
        }
        
        $base_sql .= " ORDER BY u1.created_at DESC";
        
        // 获取总数
        $count_sql = "SELECT COUNT(*) as total FROM ($base_sql) as temp";
        $total_result = Db::query($count_sql, $params);
        $total = $total_result[0]['total'];
        
        // 分页处理
        $page = isset($req['page']) ? max(1, intval($req['page'])) : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        // 添加分页限制
        $sql = $base_sql . " LIMIT $offset, $limit";
        
        // 执行查询
        $data = Db::query($sql, $params);
        
        // 为每个可疑用户获取详细信息
        foreach ($data as &$item) {
            // 获取下级用户数量
            $item['sub_users_num'] = User::where('up_user_id', $item['inviter_id'])->count();
            $item['sub_users_shiming'] = User::where('up_user_id', $item['inviter_id'])->where('shiming_status', 1)->count();
            
            // 获取可疑用户详情（5秒内注册的用户）
            $detail_sql = "SELECT 
                u2.id, u2.phone, u2.realname, u2.created_at,
                u3.id as suspicious_id, u3.phone as suspicious_phone, 
                u3.realname as suspicious_realname, u3.created_at as suspicious_created_at,
                TIMESTAMPDIFF(SECOND, u2.created_at, u3.created_at) as time_diff
            FROM mp_user u2 
            INNER JOIN mp_user u3 ON u3.up_user_id = u2.up_user_id
            WHERE u2.up_user_id = ? 
                AND u2.id < u3.id
                AND TIMESTAMPDIFF(SECOND, u2.created_at, u3.created_at) < 5
            ORDER BY u2.created_at DESC
            LIMIT 10";
            
            // $details = Db::query($detail_sql, [$item['inviter_id']]);
            // $item['suspicious_detail'] = $details;
            // $item['suspicious_count'] = count($details);
        }
        
        // 创建分页对象
        $paginator = Bootstrap::make($data, $limit, $page, $total, false, [
            'path'  => request()->url(),
            'query' => $req,
        ]);
        $this->assign('req', $req);
        $this->assign('count', $total);
        $this->assign('data', $paginator);
        
        return $this->fetch();
    }

    public function deleteUserAndSub(){
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'phone' => 'require',
            'realname' => 'require',
        ]);
        $user = User::where('id', $req['id'])
        ->where('phone',$req['phone'])
        ->where('realname',$req['realname'])->find();
        if(!$user){
            return out(null, 10001, '用户信息不匹配，请检查ID、手机号、姓名是否正确');
        }
        Db::startTrans();
        try{
            //删除用户以及下级订单信息
            $user_ids = User::where('up_user_id', $req['id'])->column('id');
            $user_ids[] = $req['id'];
            //删除用户以及下级订单信息
            Order::whereIn('user_id', $user_ids)->delete();
            //删除用户以及下级资金信息
            Capital::whereIn('user_id', $user_ids)->delete();  
            //删除用户以及下级认证信息
            Authentication::whereIn('user_id', $user_ids)->delete();
            //删除用户以及下级消息信息
            Message::whereIn('user_id', $user_ids)->delete();
            OrderTiyan::whereIn('user_id', $user_ids)->delete();
            UserBalanceLog::whereIn('user_id', $user_ids)->delete();

            //删除用户以及下级用户信息
            User::whereIn('up_user_id', $user_ids)->delete();
            $user->delete();



            Db::commit();
            return out(null, 200, '删除成功');
        }catch(\Exception $e){
            Db::rollback();
            return out(null, 10001, '删除失败：' . $e->getMessage());
        }
    }

    /**
     * 用户产品组完成记录列表
     */
    public function userProjectGroupList()
    {
        $req = request()->param();

        $builder = UserProjectGroup::alias('upg')
            ->leftJoin('user u', 'u.id = upg.user_id')
            ->field('upg.*, u.phone, u.realname')
            ->order('upg.id', 'desc');

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('upg.user_id', $req['user_id']);
        }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', 'like', '%' . $req['phone'] . '%');
        }
        if (isset($req['group_id']) && $req['group_id'] !== '') {
            $builder->where('upg.group_id', $req['group_id']);
        }

        if (!empty($req['start_date'])) {
            $builder->where('upg.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('upg.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }

        $data = $builder->paginate(['query' => $req]);

        // 获取产品组列表用于筛选
        $groups = [
            ['id' => 7, 'name' => '五福临门板块1'],
            ['id' => 8, 'name' => '五福临门板块2'],
            ['id' => 9, 'name' => '五福临门板块3'],
            ['id' => 10, 'name' => '五福临门板块4'],
            ['id' => 11, 'name' => '五福临门板块5'],
        ];
        
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('groups', $groups);

        return $this->fetch();
    }

    /**
     * 手动更新用户产品组完成状态
     */
    public function updateUserProjectGroup()
    {
        $req = request()->post();
        $this->validate($req, [
            'user_id' => 'require|number',
        ]);

        try {
            $result = UserProjectGroup::checkAndUpdateUserGroups($req['user_id']);
            if ($result) {
                return out(null, 0, '更新成功');
            } else {
                return out(null, 10001, '用户不存在');
            }
        } catch (Exception $e) {
            return out(null, 10001, '更新失败：' . $e->getMessage());
        }
    }

    /**
     * 获取用户团队激活数据
     */
    public function getTeamActivationData()
    {
        $req = request()->param();
        $this->validate($req, [
            'user_id' => 'require|number',
        ]);

        try {
            $userId = $req['user_id'];
            
            // 获取下三级团队总人数
            $teamTotalCount = UserRelation::where('user_id', $userId)
                ->whereIn('level', [1, 2, 3])
                ->count();
            
            // 获取下三级团队实名人数
            $teamRealNameCount = UserRelation::alias('ur')
                ->join('user u', 'u.id = ur.sub_user_id')
                ->where('ur.user_id', $userId)
                ->whereIn('ur.level', [1, 2, 3])
                ->where('u.shiming_status', 1)
                ->count();
            
            // 获取下三级团队激活人数（从happiness_equity_activation表计算）
            $teamActiveCount = UserRelation::alias('ur')
                ->join('happiness_equity_activation hea', 'hea.user_id = ur.sub_user_id')
                ->where('ur.user_id', $userId)
                ->whereIn('ur.level', [1, 2, 3])
                ->where('hea.status', 1) // 只统计已激活状态
                ->count();
            
            $data = [
                'team_total_count' => $teamTotalCount,
                'team_real_name_count' => $teamRealNameCount,
                'team_active_count' => $teamActiveCount
            ];
            
            return out($data, 200, '获取成功');
        } catch (Exception $e) {
            return out(null, 10001, '获取失败：' . $e->getMessage());
        }
    }
}
