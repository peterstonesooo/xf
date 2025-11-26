<?php

namespace app\api\controller;

use app\model\Apply;
use app\model\Capital;
use app\model\FamilyChild;
use app\model\Order5;
use app\model\PayAccount;
use app\model\Timing;
use app\model\UserBank;
use app\model\UserProduct;
use app\model\YuanmengUser;
use app\model\Message;
use app\model\Coin;
use app\model\Order;
use app\model\PaymentConfig;
use app\model\Project;
use app\model\TransferConfig;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserRelation;
use app\model\KlineChartNew;
use app\model\HongliOrder;
use app\model\Hongli;
use app\model\Authentication;
use app\model\ButieOrder;
use app\model\Certificate;
use app\model\DengJi;
use app\model\Payment;
use app\model\RelationshipRewardLog;
use app\model\Taxoff;
use app\model\UserCoinBalance;
use app\model\Order4;
use think\facade\Cache;
use app\model\UserDelivery;
use app\model\UserPrivateBank;
use app\model\WalletAddress;
use app\model\ZhufangOrder;
use app\model\UserPointsSwap;
use app\model\OrderTiyan;
use app\model\OrderTongxing;
use app\model\VipLog;
use think\facade\Db;
use Exception;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use think\facade\App;

use think\Request;
use function PHPSTORM_META\map;

class UserController extends AuthController
{
    public function userInfo()
    {
        $user = $this->user;

        //topup_balance 充值余额 team_bonus_balance 团队奖励 butie 补贴钱包 balance 签到红包钱包 digit_balance 项目惠民钱包
        //$user = User::where('id', $user['id'])->append(['equity', 'digital_yuan', 'my_bonus', 'total_bonus', 'profiting_bonus', 'exchange_equity', 'exchange_digital_yuan', 'passive_total_income', 'passive_receive_income', 'passive_wait_income', 'subsidy_total_income', 'team_user_num', 'team_performance', 'can_withdraw_balance'])->find()->toArray();
        $user = User::where('id', $user['id'])
                    ->field('id,phone,realname,pay_password,up_user_id,is_active,invite_code,ic_number,level,balance,topup_balance,team_bonus_balance,appreciating_wallet,butie_lock,created_at,qq,avatar,digit_balance,butie,integral,tiyan_wallet,tiyan_wallet_lock,xingfu_tickets,puhui,zhenxing_wallet,vote_tickets,shouyi_wallet,gongfu_wallet,vip_status')
                    ->find()
                    ->toArray();
    
        $user['is_set_pay_password'] = !empty($user['pay_password']) ? 1 : 0;
        $user['wallet_address'] = '';
        unset($user['password'], $user['pay_password']);
        $delivery=UserDelivery::where('user_id', $user['id'])->find();
        $userTiyan = OrderTiyan::where('user_id', $user['id'])->find();
        $user['tiyan_wallet_show'] = 1;
        $user['tiyan_status'] = 1;//1未参与，2已参与，3已结束
        if($userTiyan){
            $user['tiyan_status'] = $userTiyan['status'] == 4 ? 3 : 2;
            if($userTiyan['status'] == 4 && $user['tiyan_wallet'] == 0){
                $user['tiyan_wallet_show'] = 0;
            }
        }
        $user['heart_tag'] = OrderTongxing::where('user_id', $user['id'])->where('status', 2)->count();
//        if($delivery){
//            $user['address']=$delivery['address'];
//        }
        $wallet_address = WalletAddress::where('user_id', $user['id'])->find();
        if($wallet_address){
            $user['wallet_address']=$wallet_address['address'];
        }
        $userAuthen = Authentication::where('user_id', $user['id'])->find();
        //实名状态
        $user['shiming_status'] = -1;
        if($userAuthen){
            $user['shiming_status'] = $userAuthen['status'];
        }
        //总注册人数
        $now_register = dbconfig('now_register');
        $user['has_register'] = $now_register + User::count() + (date('m')*30*24*60+date('d')*24*60+date('H')*60+date('i'));
        $user['total_register'] = dbconfig('total_register');
        $user['register_rate'] = round($user['has_register'] / $user['total_register'] * 100, 2);
        $user['total_assets'] = bcadd(($user['topup_balance'] + $user['team_bonus_balance'] + $user['butie'] + $user['balance']), $user['digit_balance'], 2);
    
        //计算可提现额度
        $user['can_withdraw_balance'] = Project::getUserCanWithdrawBalance($user['id']);
        $user['phone'] = substr_replace($user['phone'],'****', 3, 4);
        $auth = Apply::where('user_id', $user['id'])->where('status', 1)->find();
        $user['auth'] = ($auth?1:0);

        return out($user);
    }

    public function releaseConfig()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        $shenbao = $user['jijin_shenbao_amount'] + $user['yuan_shenbao_amount'];

        if($shenbao <= 0) {
            return out(null, 10001, '请先进行申购纳税');
        }

        if($shenbao < 1000000) {
           
            return out(['release' => 3000]);
            
        } elseif ($shenbao >= 1000000 && $shenbao < 5000000) {
            return out(['release' => 4000]);
        } elseif ($shenbao >= 5000000) {
            return out(['release' => 5000]);
        }
        
    }

    public function dengji()
    {
        $req = $this->validate(request(), [
            'province_name|省' => 'require',
            'city_name|市' => 'require',
            'area|区(县)' => 'require',
        ]);
        $user = $this->user;
        $req['user_id'] = $user['id'];
        $repeat = DengJi::where('user_id', $user['id'])->find();
        if($repeat) {
            return out(null, 10001, '您已经登记过');
        }
        $provinces = [
            '新疆维吾尔自治区',
            '重庆市',
            '四川省',
            '云南省',
            '贵州省',
            '广西壮族自治区',
            '西藏自治区',
            '陕西省',
            '甘肃省',
            '宁夏回族自治区',
            '青海省',
            '内蒙古自治区',
        ];
        Db::startTrans();
        try {
            if (in_array($req['province_name'], $provinces)) {
                $butie = 100000;
            } else {
                $butie = 50000;
            }
            User::changeInc($user['id'], $butie, 'butie',50,$user['id'],3);

            DengJi::create($req);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out();
    }

    public function duihuan()
    {
        $req = $this->validate(request(), [
            'type|类型' => 'require|number|in:1,2',
        ]);
        $user = $this->user;
        $user = User::where('id', $user['id'])->field('id,integral')->find();
        $lottery_jifen = dbconfig('lottery_jifen');
        $jifen_to_cash = dbconfig('jifen_to_cash');
        Db::startTrans();
        try {
            if($req['type'] == 1){
                // 检查用户积分是否足够
                if ($user['integral'] < $lottery_jifen) {
                    return out([], 1, '积分不足'.$lottery_jifen);
                }
                $data['use_points'] = $lottery_jifen;
                $data['to_wallet'] = 'lottery_tickets';
                $data['money'] = 1;
                $log_type = 9;
            }else{
                if($user['integral'] < $jifen_to_cash){
                    return out([], 1, '积分不足'.$jifen_to_cash);
                }
                $data['use_points'] = $jifen_to_cash;
                $data['to_wallet'] = 'team_bonus_balance';
                $data['money'] = 100;
                $log_type = 2;
            }

            $data['user_id'] = $user['id'];
            $data['from_wallet'] = 'integral';
            $data['creat_time'] = date('Y-m-d H:i:s',time());
            $duihuan = UserPointsSwap::create($data);
            // 扣除用户积分
            User::changeInc($user['id'],-abs($data['use_points']),'integral',51,$duihuan['id'],6,'兑换');
            //添加余额
            User::changeInc($user['id'],$data['money'],$data['to_wallet'],51,$duihuan['id'],$log_type,'兑换');

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            return out([], 1, '购买失败：' . $e->getMessage());
        }
        return out();

    }

    public function xingfuDuihuan(){
        $req = $this->validate(request(), [
            'type|类型' => 'number',  //1兑换抽奖券，2兑换投票权
        ]);
        if(!isset($req['type'])){
            $req['type'] = 1;
        }
        if(!in_array($req['type'], [1,2])){
            return out(null, 10001, '类型错误');
        }
        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        if($req['type'] == 1){
            // 检查用户积分是否足够
            if($user['xingfu_tickets'] < 5) {
                return out(null, 10001, '幸福助力劵不足5张');
            }
            $use_points = 5;
            $to_wallet = 'lottery_tickets';
            $money = 1;
            $log_type = 9;
        }else{
            if($user['xingfu_tickets'] < 1) {
                return out(null, 10001, '幸福助力劵不足');
            }
            $use_points = 1;
            $to_wallet = 'vote_tickets';
            $money = dbconfig('xingfu_to_vote_tickets');
            $log_type = 15;
        }

        
        
        Db::startTrans();
        try {
            $data['money'] = $money;
            $data['use_points'] = $use_points;
            $data['to_wallet'] = $to_wallet;
            $data['from_wallet'] = 'xingfu_tickets';
            $data['user_id'] = $user['id'];
            $data['creat_time'] = date('Y-m-d H:i:s',time());
            $duihuan = UserPointsSwap::create($data);

            User::changeInc($user['id'],-$use_points,'xingfu_tickets',106,$user['id'],12,'幸福助力卷兑换',0,2,'TD');
            User::changeInc($user['id'],$money,$to_wallet,106,$user['id'],$log_type,'幸福助力卷兑换',0,2,'TD');
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            return out(null, 10001, '兑换失败：' . $e->getMessage());
        }
        return out();
    }



    public function duihuanList(){
       $data = UserPointsSwap::where('user_id',$this->user['id'])->order('creat_time desc')->paginate();
       foreach($data as $k => $v){
            if($v['to_wallet'] == 'lottery_tickets' ){
                $v['type'] ='抽奖卷';
            }elseif($v['to_wallet'] == 'team_bonus_balance'){
                $v['type'] = '荣誉金';
            }elseif($v['to_wallet'] == 'vote_tickets'){
                $v['type'] = '投票权';
            }
            //判断来源
            if($v['from_wallet']=='xingfu_tickets'){
                $v['from_type'] = '助力劵';
            }elseif($v['from_wallet']=='integral'){
                $v['from_type'] = '积分';
            }elseif($v['from_wallet']=='vote_tickets'){
                $v['from_type'] = '投票权';
            }
       }
       return out($data);
    }

    public function userSnno()
    {
        $user = $this->user;
        $user = User::where('id', $user['id'])->find();
        $shenbao = $user['jijin_shenbao_amount'] + $user['yuan_shenbao_amount'];
        if($shenbao <= 0) {
            return out(null, 10001, '请先进行申购纳税');
        }

        if($user['sn_no']) {
            return out(['sn_no' => $user['sn_no']]);
        } else {
            $rand = 'SN'.str_pad(mt_rand(0, 99999999), 8, '1', STR_PAD_LEFT).'BTK';
            try {
                User::where('id', $user['id'])->update(['sn_no' => $rand]);
            } catch (Exception $e) {
                return out(null, 10001, '网络问题，请重试');
            }
            return out(['sn_no' => $rand]);
        }
    }

    public function userBank()
    {
        $user = $this->user;
        $data = UserPrivateBank::where('user_id', $user['id'])->select();
        return out($data);
    }

    public function userFund()
    {
        $user = $this->user;

        $user = User::where('id', $user['id'])->field('id,digital_yuan_amount,monthly_subsidy,used_digital_yuan_amount,used_monthly_subsidy,used_checkingAmount,used_digital_gift')->find()->toArray();
        //审核中金额
        $user['checkingAmount'] = YuanmengUser::where('user_id', $user['id'])->where('order_status', 1)->sum('amount');
        
        $gift = Order::where('user_id', $user['id'])->where('project_group_id', 3)->find();
        if($gift) {
            $user['digital_gift'] = $gift['sum_amount'];
        } else {
            $user['digital_gift'] = 0;
        }
        $user['digital_gift'] = bcsub($user['digital_gift'], $user['used_digital_gift'], 2);
        $user['checkingAmount'] = bcsub($user['checkingAmount'], $user['used_checkingAmount'], 2);
        if($user['checkingAmount'] < 0) {
            $user['checkingAmount'] = 0;
        }
        $user['digital_yuan_amount'] = bcsub($user['digital_yuan_amount'], $user['used_digital_yuan_amount'], 2);
        $user['monthly_subsidy'] = bcsub($user['monthly_subsidy'], $user['used_monthly_subsidy'], 2);
        
        $user['all'] = $user['digital_gift'] + $user['checkingAmount']+ $user['digital_yuan_amount']+ $user['monthly_subsidy'];

        $user['all'] = round($user['all'], 0);

        $user['total_fund'] = Order::where('user_id', $user['id'])->where('project_group_id', 2)->sum('all_bonus');
        return out($user);
    }

    public function getName()
    {
        $req = $this->validate(request(), [
            'phone|手机号' => 'require',
        ]);
        $user = User::where('phone', $req['phone'])->field('realname')->find();

        if(!$user) {
            return out(null, 10001, '用户不存在');
        } else {
            return out($user);
        }
    }

    public function applyMedal(){
        $req = $this->validate(request(), [
            'address|详细地址' => 'require',
        ]);
        $user = $this->user;


        UserDelivery::updateAddress($user,$req);
        $subCount = UserRelation::where('user_id',$user['id'])->where('is_active',1)->count();
        if($subCount<500){
            return out(null,10002,'激活人数不足500人');
        }
        $msg = Apply::add($user['id'],1);
        if($msg==""){
            return out();
        }else{
            return out(null,10003,$msg);
        }

    }
    public function applyHouse(){
        $user = $this->user;
        $is_three_stage = User::isThreeStage($user['id']);
        if(!$is_three_stage){
            return out(null,10001,'暂未满足条件');
        }
        $msg = Apply::add($user['id'],2);
        if($msg==""){
            return out();
        }else{
            return out(null,10002,"预约看房申请已提交，请耐心等待，留意好您的手机。");
        }
    }
    public function applyCar(){
        $user = $this->user;
        $count = UserRelation::where('user_id',$user['id'])->where('is_active',1)->count();
        if($count<1000){
            $projectIds = [53,54,55,56,57];
            foreach($projectIds as $v){
                $order = Order::where('user_id',$user['id'])->where('project_group_id',4)->where('project_id',$v)->where('status','>=',2)->find();
                if(!$order){
                    return out(null,10001,'暂未满足条件');
                }
            }
       }
        $msg = Apply::add($user['id'],3);
        if($msg==""){
            return out();
        }else{
            return out(null,10002,"预约提车申请已提交，请耐心等待，留意好您的手机。");
        }
    }

    public function myHouse(){
        $user = $this->user;
        $data = User::myHouse($user['id']);
        if($data['msg']!=''){
            return out(null,10001,$data['msg']);
        }
        $house = $data['house'];
        $coverImg = Project::where('id',$house['project_id'])->value('cover_img');
        $houseFee = \app\model\HouseFee::where('user_id',$user['id'])->find();
        $data = [
            'name'=>$house['project_name'],
            'cover_img'=>$coverImg,
            'is_house_fee'=>$houseFee?1:0,
        ];
        
        return out($data);
    }

    public function cardAuth(){
        $user = $this->user;
        $order = Order::where('user_id',$user['id'])->where('project_group_id',5)->where('status','>=',2)->find();
        if(!$order){
            return out(null,10001,'请先购买办卡项目');
        }
        $req= $this->validate(request(), [
            'realname|真实姓名' => 'require',
            'ic_number|身份证号' => 'require',
        ]);
        if($user['realname']=='' || $user['ic_number']==''){
            return out(null,10002,'请先完成实名认证');
        }
        if($user['realname']!=$req['realname'] || $user['ic_number']!=$req['ic_number']){
            return out(null,10003,'与实名认证信息不一致');
        }
        $msg = Apply::add($user['id'],5);
        if($msg==""){
            return out();
        }else if($msg=="已经申请过了"){
            return out();
        }else{
            return out(null,10004,$msg);
        }
    }

    public function cardProgress(){
        $user = $this->user;
        $apply = Apply::where('user_id',$user['id'])->where('type',5)->find();
        if(!$apply){
            return out(null,10001,'请先开户认证');
        }
        $order = Order::where('user_id',$user['id'])->where('project_group_id',5)->where('status','>=',2)->select();
        $data = [];
        //$ids = [];
        foreach($order as $v){
            // if(isset($ids[$v['project_id']])){
            //     continue;
            // }
            $data[] = [
                'name'=>$v['project_name'],
                'cover_img'=>get_img_api($v['cover_img']),
            ];
            //$ids[$v['project_id']] = 1;
        }
        
        return out($data);
    }

    //邀请
    public function invite(){
        $user = $this->user;
        $host = env('app.host', '');
        $frontHost = env('app.front_host', 'https://h5.zdrxm.com');
       
        $url = "$frontHost/#/pages/system-page/gf_register?invite_code={$user['invite_code']}";
        $img = $user['invite_img'];
//        if($img==''){
//            $qrCode = QrCode::create($url)
//            // 内容编码
//            ->setEncoding(new Encoding('UTF-8'))
//            // 内容区域大小
//            ->setSize(200)
//            // 内容区域外边距
//            ->setMargin(10);
//            // 生成二维码数据对象
//            $result = (new PngWriter)->write($qrCode);
//            // 直接输出在浏览器中
//            // ob_end_clean(); //处理在TP框架中显示乱码问题
//            // header('Content-Type: ' . $result->getMimeType());
//            // echo $result->getString();
//            // 将二维码图片保存到本地服务器
//            $today = date("Y-m-d");
//            $basePath = App::getRootPath()."public/";
//            $path =  "storage/qrcode/$today";
//            if(!is_dir($basePath.$path)){
//                mkdir($basePath.$path, 0777, true);
//            }
//            $name = "{$user['id']}.png";
//            $filePath = $basePath.$path.'/'.$name;
//            $result->saveToFile($filePath);
//            $img = $path.'/'.$name;
//            User::where('id',$user['id'])->update(['invite_img'=>$img]);
//        }else{
//        }
        $img = $host.'/'.$img;
        // 返回 base64 格式的图片
        //$dataUri = $result->getDataUri();
        //echo "<img src='{$dataUri}'>";
        $data=[
            'invite_code' => $user['invite_code'],
            'url'=>dbconfig('ios_download_url'),
            'apk_url' => dbconfig('apk_download_url'),
            'download_url' => dbconfig('download_url'),
            'download_chat_url' => dbconfig('download_chat_url'),
            'chat_group_id' => dbconfig('chat_group_id'),
//            'img'=>$img,
        ];
        return out($data);
    }
    
/*     public function hongbao(){
        $user = $this->user;
        $zg = UserRelation::where('user_id',$user['id'])->where('level',1)->where('is_active',1)->select();
        $data = [];
        if(count($zg) >= 10){
            $data['zg'] = 1;
        }else{
            $data['zg'] = 0;
        }
        $sql = 'select user_id from mp_user_relation where is_active=1 and level=1 GROUP BY user_id having count(user_id)>=10';
        $u = Db::query($sql);
        // $data['amount'] = round(100000000 / count($u),2);
        $d = [
            '20221205'=>'225891.28',
            '20221206'=>'236156.19',
            '20221207'=>'278912.01',
            '20221208'=>'300007.22',
            '20221209'=>'326517.59',
            '20221210'=>'353027.95',
            '20221211'=>'379538.32',
            '20221212'=>'406048.68',
            '20221213'=>'432559.05',
            '20221214'=>'459069.41',
            '20221215'=>'485579.78',
            '20221216'=>'512090.14',
            '20221217'=>'538600.51',
            '20221218'=>'565110.87',
            '20221219'=>'591621.24',
            '20221220'=>'618131.60',
            '20221221'=>'644641.97',
            '20221222'=>'671152.33',
            '20221223'=>'697662.70',
            '20221224'=>'724173.06',
            '20221225'=>'750683.43',
            '20221226'=>'777193.79',
            '20221227'=>'803704.16',
            '20221228'=>'830214.52',
            '20221229'=>'856724.89',
            '20221230'=>'883235.25',
            '20221231'=>'909745.62',
            '20220101'=>'936255.98',
            '20220102'=>'962766.35',
            '20220103'=>'989276.71',
            '20220104'=>'1015787.08',
            '20220105'=>'1042297.44',
            '20220106'=>'1068807.81',
            '20220107'=>'1095318.17',
            '20220108'=>'1121828.54',
            '20220109'=>'1148338.90',
            '20220110'=>'1174849.27',
            '20220111'=>'1201359.63',
            '20220112'=>'1227870.00',
            '20220113'=>'1254380.36',
            '20220114'=>'1280890.73',
            '20220115'=>'1307401.09',
            '20220116'=>'1333911.46',
            '20220117'=>'1360421.82',
            '20220118'=>'1386932.19',
            '20220119'=>'1413442.55',
            '20220120'=>'1439952.92',
            '20220121'=>'1466463.28',
            '20220122'=>'1492973.65',
            '20220123'=>'1519484.01'];
        $data['amount'] = $d[date('Ymd')];
        if(!empty($u)){
            $uid = [];
            foreach($u as $v){
                $uid[] = $v['user_id'];
            }
           $phone = User::whereIn('id',$uid)->field('phone,realname')->select();
           foreach($phone as $v){
                $q = substr($v['phone'],0,3);
                $h = substr($v['phone'],7,10);
                $qq = mb_substr($v['realname'],0,1);
                $hh = mb_substr($v['realname'],2);
                $data['list'][] = $q .'****' . $h .'  '.$qq.'*'.$hh;
           }
        }
        return out($data);

    } */

    public function wallet(){
        $user = $this->user;
        $umodel = new User();
        //$user['invite_bonus'] = $umodel->getInviteBonus(0,$user);
        $user['total_balance'] = bcadd($user['topup_balance'],$user['balance'],2);
        $map = config('map.user_balance_log')['type_map'];
        $list = UserBalanceLog::where('user_id',$user['id'])
        ->where('log_type',1)->whereIn('type',[1,2,18,19,30,31,32,302])
        ->order('created_at','desc')
        ->paginate(10)
        ->each(function($item,$key) use ($map){
            $typeText = $map[$item['type']];
            $item['type_text'] = $typeText;
            if($item['type']==3){
                $projectName = Order::where('id',$item['relation_id'])->value('project_name');
                $item['type_text']=$typeText.$projectName;
            }
            return $item;
        });
        $u=[
            'topup_balance'=>$user['topup_balance'],
            'total_balance'=>$user['total_balance'],
            'balance'=>$user['balance'],
        ];
        $data['wallet']=$u;
        $data['list'] = $list;
        return out($data);
    }

    //数字人民币转账
    public function transferAccounts(){
        $req = $this->validate(request(), [
            'type' => 'require|in:1,2,3,4,5',//1-惠民钱包 2-荣誉钱包 3-余额钱包 4-普惠钱包 5-收益钱包
            'realname|对方姓名' => 'require|max:20',
            'account|对方账号' => 'require',
            'money|转账金额' => 'require|number',
            'pay_password|支付密码' => 'require',
        ]);//type 1 数字人民币，，realname 对方姓名，account 对方账号，money 转账金额，pay_password 支付密码
        $user = $this->user;

        // 检查用户是否已激活幸福权益
        // $activation = \app\model\HappinessEquityActivation::getUserActivation($user['id']);
        // if (!$activation) {
        //     return out(null, 10001, '请先完成幸福权益激活');
        // }

        // 检查转账配置
        $transferCheck = TransferConfig::checkTransferAllowed($req['type'], $req['money']);
        if (!$transferCheck['allowed']) {
            return out(null, 10001, $transferCheck['message']);
        }
        if ($user['shiming_status'] == 0) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        if (!in_array($req['type'], [1,2,3,4,5])) {
            return out(null, 10001, '不支持该支付方式');
        }
        if ($user['phone'] == $req['account'] && $req['type']==3) {
            return out(null, 10001, '余额不能转账给自己');
        }

        Db::startTrans();
        try {
            //topup_balance充值余额 
            $user = User::where('id', $user['id'])->lock(true)->find();//转账人
            $take =User::where('phone',$req['account'])->find();
            if(!$take){
                return out(null, 10002, '转账手机号不存在');
            }
            if ($take['shiming_status'] == 0) {
                return out(null, 10002, '您尝试转账的用户不存在或尚未完成实名注册');
            }
            //判断收款人名字是否正确
            if($take['realname'] != $req['realname']){
                return out(null, 10002, '收款人名字不正确');
            }
            
            switch($req['type']){
                case 1:
                    $field = 'digit_balance';
                    $fieldText = '惠民钱包';
                    $logType=5;
                    break;
                case 2:
                    $field = 'team_bonus_balance';
                    $fieldText = '荣誉钱包';
                    $logType = 2;
                    break;
                case 3:
                    $field = 'topup_balance';
                    $fieldText = '余额钱包';
                    $logType = 1;
                    break;
                case 4:
                    $field = 'puhui';
                    $fieldText = '普惠钱包';
                    $logType = 13;
                    break;
                case 5:
                    $field = 'shouyi_wallet';
                    $fieldText = '收益钱包';
                    $logType = 17;
                    break;
            }


            // 获取转账配置
            $transferConfig = TransferConfig::getConfigByWalletType($req['type']);
            
            // 计算手续费
            $feeAmount = 0;
            if ($transferConfig && $transferConfig['fee_rate'] > 0) {
                $feeAmount = round($req['money'] * ($transferConfig['fee_rate'] / 100), 2);
            }
            
            // 计算实际扣除金额（转账金额 + 手续费）
            $totalDeductAmount = $req['money'] + $feeAmount;
            
            if ($totalDeductAmount > $user[$field]) {
                return out(null, 10002, $fieldText.'余额不足（无法完成转账）');
            }
            
            // 转出金额（包含手续费）
            $change_balance = 0 - $totalDeductAmount;
            
            // 扣除转账人余额（包含手续费）
            User::changeInc($user['id'], $change_balance, $field, 18, 0, $logType, '转账['.$fieldText.']给-'.$take['realname'].($feeAmount > 0 ? '（手续费：'.$feeAmount.'元）' : ''), 0, 1);
                
            // 收款人只收到转账金额（不含手续费）
            User::changeInc($take['id'], $req['money'], 'topup_balance', 19, 0, 1, '接收转账来自-'.$user['realname'], 0, 1);
            
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    
    //转账2
    public function transferAccounts2(){
        $req = $this->validate(request(), [
            'type' => 'require|in:1,2,3,4',//1推荐给奖励,2 转账余额（充值金额）3 可提现余额 4普惠钱包
            //'realname|对方姓名' => 'require|max:20',
            'account|对方账号' => 'require',//虚拟币钱包地址
            'money|转账金额' => 'require|number|between:100,100000',
            'pay_password|支付密码' => 'require',
        ]);//type 1 数字人民币，，realname 对方姓名，account 对方账号，money 转账金额，pay_password 支付密码
        $user = $this->user;

        // 检查用户是否已激活幸福权益
        // $activation = \app\model\HappinessEquityActivation::getUserActivation($user['id']);
        // if (!$activation) {
        //     return out(null, 10001, '请先完成幸福权益激活');
        // }

        if (empty($user['ic_number'])) {
            return out(null, 10001, '请先完成实名认证');
        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        if (!in_array($req['type'], [1,2,3])) {
            return out(null, 10001, '不支持该支付方式');
        }
        if ($user['phone'] == $req['account'] && $req['type']==2) {
            return out(null, 10001, '不能转帐给自己');
        }

        Db::startTrans();
        try {
            //1可用余额（可提现金额） 2 转账余额（充值金额加他人转账的金额）
            //topup_balance充值余额 can_withdraw_balance可提现余额  balance总余额
            $user = User::where('id', $user['id'])->lock(true)->find();//转账人
            $wallet =WalletAddress::where('address',$req['account'])->where('user_id','>',0)->find();
            if(!$wallet){
                exit_out(null, 10002, '目标地址不存在');
            }
            $take = User::where('id', $wallet['user_id'])->lock(true)->find();//收款人
            if (!$take) {
                exit_out(null, 10002, '用户不存在');
            }
            if ($take['shiming_status'] == 0) {
                exit_out(null, 10002, '收款人未完成实名认证');
            }
            
            switch($req['type']){
                case 1:
                    $field = 'digital_yuan_amount';
                    $fieldText = '数字人民币';
                    $logType=2;
                    break;
                case 4:
                    $field = 'puhui';
                    $fieldText = '普惠钱包';
                    $logType=13;
                    break;
                default:
                    exit_out(null, 10001, '不支持该支付方式');
            }


            if ($req['money'] > $user[$field]) {
                exit_out(null, 10002, '转账余额不足');
            }
            //转出金额  扣金额 可用金额 转账金额
            $change_balance = 0 - $req['money'];
            

            //2 转账余额（充值金额加他人转账的金额）
            //User::where('id', $user['id'])->inc('balance', $change_balance)->inc($field, $change_balance)->update();
            User::where('id', $user['id'])->inc($field, $change_balance)->update();
            //User::changeBalance($user['id'], $change_balance, 18, 0, 1,'转账余额转账给'.$take['realname']);
            //增加资金明细
            UserBalanceLog::create([
                'user_id' => $user['id'],
                'type' => 18,
                'log_type' => $logType,
                'relation_id' => $take['id'],
                'before_balance' => $user[$field],
                'change_balance' => $change_balance,
                'after_balance' =>  $user[$field]-$req['money'],
                'remark' => '转账'.$fieldText.'转账给'.$take['realname'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => ''
            ]);

            //收到金额  加金额 转账金额
            //User::where('id', $take['id'])->inc('balance', $req['money'])->inc('topup_balance', $req['money'])->update();
            User::where('id', $take['id'])->inc('balance', $req['money'])->update();
            //User::changeBalance($take['id'], $req['money'], 18, 0, 1,'接收转账来自'.$user['realname']);
            UserBalanceLog::create([
                'user_id' => $take['id'],
                'type' => 19,
                'log_type' => 1,
                'relation_id' => $user['id'],
                'before_balance' => $take[$field],
                'change_balance' => $req['money'],
                'after_balance' =>  $take[$field]+$req['money'],
                'remark' => '接收'.$fieldText.'来自'.$user['realname'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => ''
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    //余额转账  余额转余额
    public function balanceTransfer(){
        $req = $this->validate(request(), [
//            'type'             => 'require',  //2 转账余额（充值金额）
            'realname|对方姓名' => 'require|max:20', //姓名
            'account|对方账号'  => 'require|max:11', //手机号
            'money|转账金额'    => 'require|number|between:100,100000', //转账金额
            'pay_password|支付密码' => 'require', //转账密码
        ]);

        $user = $this->user;

//        $count = FamilyChild::where('user_id',$user['id'])->count();
//        if (!$count) {
//            return out(null, 10001, '请先完成实名认证');
//        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
//        if ($req['type'] != 2) {
//            return out(null, 10001, '不支持该支付方式');
//        }
        if ($user['phone'] == $req['account'] ) {
            return out(null, 10001, '不能转帐给自己');
        }

        Db::startTrans();
        try {
            //1可用余额（可提现金额）
            $transferUser =  User::where('id', $user['id'])->lock(true)->find();//转账人

            $wallet = User::where('phone',$req['account'])->where('realname',$req['realname'])->find();
            if(!$wallet){
                exit_out(null, 10002, '没找到转账人');
            }

            $take = User::where('id', $wallet['id'])->lock(true)->find();//收款人

            if (!$take) {
                exit_out(null, 10002, '用户不存在');
            }

//            if (empty($take['ic_number'])) {
//                exit_out(null, 10002, '请收款用户先完成实名认证');
//            }

            if ($req['money'] > $transferUser['topup_balance']) {
                exit_out(null, 10002, '余额不足');
            }

            //转出金额  扣金额 可用金额 转账金额
            $change_balance = 0 - $req['money'];

            //2 转账余额
            User::where('id', $transferUser['id'])->inc('topup_balance', $change_balance)->update();

            //增加资金明细（当前用户余额 - 转账钱数）
            UserBalanceLog::create([
                'user_id' => $transferUser['id'],
                'type' => 18,
                'log_type' => 1,
                'relation_id' => $take['id'],
                'before_balance' => $transferUser['topup_balance'],
                'change_balance' => $change_balance,
                'after_balance' =>  $transferUser['topup_balance']-$req['money'],
                'remark' => '转账给'.$take['realname'].$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => 999999999
            ]);

            //收到金额  加金额 转账金额
            $data = [
                'topup_balance' => $wallet['topup_balance'] + $req['money']
            ];
            User::where('id', $wallet['id'])->update($data);
            UserBalanceLog::create([
                'user_id' => $take['id'],
                'type' => 19,
                'log_type' => 1,
                'relation_id' => $user['id'],
                'before_balance' => $wallet['topup_balance'],
                'change_balance' => $req['money'],
                'after_balance' => $wallet['topup_balance']+$req['money'],
                'remark' => '来自'.$user['realname'].'的转账给'.$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => 999999999
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    //宣传转账（宣传转余额）
    public function promotionTransfer(){
        $req = $this->validate(request(), [
//            'type'             => 'require',  //4 转账余额（充值金额）
            'realname|对方姓名' => 'require|max:20', //姓名
            'account|对方账号'  => 'require|max:11', //手机号
            'money|转账金额'    => 'require|number|between:100,100000', //转账金额
//            'pay_password|支付密码' => 'require', //转账密码
        ]);

        $user = $this->user;

//        $count = FamilyChild::where('user_id',$user['id'])->count();
//        if (!$count) {
//            return out(null, 10001, '请先完成实名认证');
//        }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
//        if ($req['type'] != 4) {
//            return out(null, 10001, '不支持该支付方式');
//        }
//        if ($user['phone'] == $req['account']) {
//            return out(null, 10001, '不能转帐给自己'); 
//        }

        Db::startTrans();
        try {
            //1可用余额（可提现金额）
            $user = User::where('id', $user['id'])->lock(true)->find();//转账人
            $wallet = User::where('phone',$req['account'])->where('realname',$req['realname'])->find();

            if(!$wallet){
                exit_out(null, 100023, '目标用户不存在');
            }

            $take = User::where('id', $wallet['id'])->lock(true)->find();//收款人
            if (!$take) {
                exit_out(null, 100025, '用户不存在');
            }

//            if (empty($take['ic_number'])) {
//                exit_out(null, 10002, '请收款用户先完成实名认证');
//            }
            

            if ($req['money'] > $user['xuanchuan_balance']) {
                exit_out(null, 10002, '余额不足');
            }
            //转出金额  扣金额 可用金额 转账金额
            $change_balance = 0 - $req['money'];

            //2 宣传
            User::where('id', $user['id'])->inc('xuanchuan_balance', $change_balance)->update();

            //扣除
            UserBalanceLog::create([
                'user_id' => $user['id'],
                'type' => 18,
                'log_type' => 4,
                'relation_id' => $take['id'],
                'before_balance' => $user['xuanchuan_balance'],
                'change_balance' => $change_balance,
                'after_balance' =>  $user['xuanchuan_balance']-$req['money'],
                'remark' => '转账给'.$wallet['realname'].$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => 999999999
            ]);

            //增加
            User::where('id', $take['id'])->inc('topup_balance', $req['money'])->update();
            UserBalanceLog::create([
                'user_id' => $wallet['id'],
                'type' => 19,
                'log_type' => 1,
                'relation_id' => $user['id'],
                'before_balance' => $take['topup_balance'],
                'change_balance' => $req['money'],
                'after_balance' =>  $take['topup_balance']+$req['money'],
                'remark' => '来自'.$user['realname'].'的转账'.$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => 999999999
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    public function transferList()
    {
        $req = $this->validate(request(), [
            //'status' => 'number',
            //'search_type' => 'number',
        ]);
        $user = $this->user;

        $builder = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [18,19])->order('created_at','desc')
                    ->paginate(10,false,['query'=>request()->param()]);
        if($builder){
            foreach($builder as $k => $v){
                $builder[$k]['phone'] = User::where('id', $v['relation_id'])->value('phone');
            } 
        }    
        
        return out($builder);
    }

    public function submitProfile()
    {
        $req = $this->validate(request(), [
            'realname|真实姓名' => 'require',
            'ic_number|身份证号' => 'require|idCard',
        ]);
        $userToken = $this->user;
        $redis = new \Predis\Client(config('cache.stores.redis'));
        $ret = $redis->set('profile_'.$userToken['id'],1,'EX',10,'NX');
        if(!$ret){
            return out("服务繁忙，请稍后再试");
        }
        //\think\facade\Log::debug('submitProfile method start.');
        \think\facade\Log::debug('Request validated'.json_encode(['request' => $req,'user_id'=>$userToken['id']],JSON_UNESCAPED_SLASHES));
        Db::startTrans();
        try{
            $user = User::where('id',$userToken['id'])->find();
            
            if ($user['ic_number']!='') {
                return out(null, 10001, '您已经实名认证了');
            }
            if($user['realname']!=''){
                return out(null, 10001, '您已经实名认证了');
            }

            if (User::where('ic_number', $req['ic_number'])->count()) {
                return out(null, 10001, '该身份证号已经实名过了');
            }
            \think\facade\Log::debug('User not verified.'.json_encode(['user_id' => $user['id'],'realname'=>$user['realname'],'ic_number'=>$user['ic_number']],JSON_UNESCAPED_SLASHES));

            User::where('id', $user['id'])->update($req);

            //注册赠送100万数字人民币
            if($user['is_realname']==0){
                User::changeInc($user['id'], 1000000,'digital_yuan_amount',24,0,3,'注册赠送数字人民币',0,1,'SM');
            }
            Db::commit();

        }catch(\Exception $e){
            \think\facade\Log::debug('Error in submitProfile method.'. $e->getMessage());
            Db::rollback();
            return out(null,10012,$e->getMessage());
        }
        //\think\facade\Log::debug('submitProfile method completed.');
        
        
        // 给直属上级额外奖励
/*         if (!empty($user['up_user_id'])) {
            User::changeBalance($user['up_user_id'], dbconfig('direct_recommend_reward_amount'), 7, $user['id']);
        } */

        // // 把注册赠送的股权给用户
        // EquityYuanRecord::where('user_id', $user['id'])->where('type', 1)->where('status', 1)->where('relation_type', 2)->update(['status' => 2, 'give_time' => time()]);
        
        //         // 把注册赠送的数字人民币给用户
        // EquityYuanRecord::where('user_id', $user['id'])->where('type', 2)->where('status', 1)->where('relation_type', 2)->update(['status' => 2, 'give_time' => time()]);

        // // 把注册赠送的贫困补助金给用户
        // EquityYuanRecord::where('user_id', $user['id'])->where('type', 3)->where('status', 1)->where('relation_type', 2)->update(['status' => 2, 'give_time' => time()]);

        return out();
    }

    public function changePassword()
    {
        $req = $this->validate(request(), [
            'type' => 'require|in:1,2',
            'new_password|新密码' => 'require|alphaNum|length:6,12',
            'old_password|原密码' => 'requireIf:type,1',
        ]);
        $user = $this->user;

        if ($req['type'] == 2 && !empty($user['pay_password']) && empty($req['old_password'])) {
            return out(null, 10001, '原密码不能为空');
        }

        // if ($req['type'] == 2 && empty($user['ic_number'])) {
        //     return out(null, 10002, '请先进行实名认证');
        // }

        $field = $req['type'] == 1 ? 'password' : 'pay_password';
        // if (!empty($user['pay_password']) && !empty($req['old_password']) && $user[$field] !== sha1(md5($req['old_password']))) {
        //     return out(null, 10003, '原密码错误');
        // }
        if (!empty($user[$field]) && $user[$field] !== sha1(md5($req['old_password']))) {
            return out(null, 10003, '原密码错误');
        }

        User::where('id', $user['id'])->update([$field => sha1(md5($req['new_password']))]);

        return out();
    }

/*     public function userBalanceLog()
    {
        $req = $this->validate(request(), [
            'log_type' => 'require|in:1,2,3,4',
            'type' => 'number',
        ]);
        $user = $this->user;

        $builder = UserBalanceLog::where('user_id', $user['id'])->where('log_type', $req['log_type']);
        if (!empty($req['type'])) {
            $builder->where('type', $req['type']);
        }
        $data = $builder->order('id', 'desc')->paginate();

        return out($data);
    }
 */

    public function team(Request $request)
    {
        $user = $this->user;
        $active_cn = ['未激活','已激活'];
        $level = $request->param('level');
        $where[] = ['a.user_id', '=',$user['id']];
        $where[] = ['a.level', '=', $level];

        $where1[] = ['user_id', '=',$user['id']];
        $where1[] = ['level', '=', $level];
        $pageSize = $request->param('pageSize', 10, 'intval');
        $pageNum = $request->param('pageNum', 1);
//        $data['list'] = UserRelation::with('subUser')->where($where)->page($pageNum, $pageSize)->select()->each(function ($item) use ($active_cn) {
//            $item['is_active_cn'] = $active_cn[$item['is_active']];
//        });
//        $data['list'] = [];
        $today = date("Y-m-d 00:00:00", time());
        $list = Db::table('mp_user_relation')->alias('a')->leftJoin('mp_user b','a.sub_user_id = b.id')->where($where)->page($pageNum, $pageSize)->select();
        if($list){
            foreach ($list as $k =>$v){
                $users = User::field('id,avatar,phone,realname,invite_bonus,invest_amount,equity_amount,level,is_active,created_at')->where('id', $v['sub_user_id'])->find();
                $users['topup_today'] = round(Capital::where('user_id', $v['sub_user_id'])->where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
                $users['topup_all'] = round(Capital::where('user_id', $v['sub_user_id'])->where('status', 2)->where('type', 1)->sum('amount'), 2);
                $list[$k] = $users;
            }  
        }
        $data['list'] = $list;
        $data['total_num'] = UserRelation::where($where1)->count();
        $data['total_receive_num'] = UserRelation::where($where1)->count();
        $data['zong_total_num'] = UserRelation::where('user_id', $user['id'])->count();
        $data['year_new'] = UserRelation::where('user_id', $user['id'])->where('created_at', '>=', date('Y-m-01 00:00:00', time()))->count();
        $data['month_new'] = UserRelation::where('user_id', $user['id'])->where('created_at', '>=', date('Y-01-01 00:00:00', time()))->count();
        $level1 = UserRelation::where('user_id', $user['id'])->where('level', 1)->column('sub_user_id');
        $level2 = UserRelation::where('user_id', $user['id'])->where('level', 2)->column('sub_user_id');
        $level3 = UserRelation::where('user_id', $user['id'])->where('level', 3)->column('sub_user_id');
        $data['topup_today_1'] = round(Capital::whereIn('user_id', $level1)->where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
        $data['topup_all_1'] = round(Capital::whereIn('user_id', $level1)->where('status', 2)->where('type', 1)->sum('amount'), 2);
        $data['topup_today_2'] = round(Capital::whereIn('user_id', $level2)->where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
        $data['topup_all_2'] = round(Capital::whereIn('user_id', $level2)->where('status', 2)->where('type', 1)->sum('amount'), 2);
        $data['topup_today_3'] = round(Capital::whereIn('user_id', $level3)->where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
        $data['topup_all_3'] = round(Capital::whereIn('user_id', $level3)->where('status', 2)->where('type', 1)->sum('amount'), 2);

        //上一级名
        $data['up_user_name'] = "没有上级";
        if ($user['up_user_id']){
            $up_info = User::where('id',$user['up_user_id'])->find();
            $data['up_user_name'] = $up_info['realname'];
        }

        $arr = [1,2,3];
        foreach ($arr as $v){
            $level = 'level'.$v;
            $count = UserRelation::where('user_id', $user['id'])->where('level', $v)->count();
            $data[$level] = $count;
        }

        return out($data);
    }

    public function inviteBonus(){
        $user = $this->user;
        $invite_bonus = UserBalanceLog::alias('l')->join('mp_order o','l.relation_id=o.id')
                                                ->field('l.created_at,l.type,l.remark,change_balance,single_amount,buy_num,project_name,o.user_id')
                                                ->where('l.type',9)
                                                ->where('l.user_id',$user['id'])
                                                ->order('l.created_at','desc')
                                                ->limit(10)
                                                //->fetchSql(true)
                                                ->paginate();
        foreach($invite_bonus as $key=>$item){
        
            $orderPrice = bcmul((string)$item['single_amount'],(string)$item['buy_num'],2);
            $realname = User::where($item['user_id'])->value('realname');
            $invite_bonus[$key]['realname'] = $realname;
            $level = UserRelation::where('user_id',$user['id'])->where('sub_user_id',$item['user_id'])->value('level');
            $levelText = [
                '1'=>"一级",
                '2'=>'二级',
                '3'=>'三级',
            ];
            if($item['type'] == 8){
                $remark = $item['remark'];
            }elseif($item['type'] == 9){
                $remark = $item['remark'];
            }else{
                $remark = '奖励';
            }
            $invite_bonus[$key]['text'] = "推荐{$levelText[$level]}用户 $realname 投资 $orderPrice ,{$remark} {$item['change_balance']} ";

        }                                     
        $data['list'] = $invite_bonus;
        return out($data);
    }

    public function teamRankList()
    {
       $req = $this->validate(request(), [
           'level' => 'in:1,2,3',
       ]);
        $user = $this->user;
        $today = date("Y-m-d 00:00:00", time());
        $zong_total = UserRelation::where('user_id', $user['id'])->column('sub_user_id'); //总团三级s
        // $all_team_bonus_balance = round(User::whereIn('id',array_merge($zong_total,[$user['id']]))->sum('team_bonus_balance'),2);
        $all_team_bonus_balance = round(UserBalanceLog::whereIn('type',[8,66])->where('log_type',2)->where('user_id',$user['id'])->sum('change_balance'),2);
        
        $todya_total_num = UserRelation::where('user_id', $user['id'])->where('created_at', '>=', $today)->count();
        $realname_num = Authentication::whereIn('user_id', $zong_total)->where('status', 1)->count();
        $tiyan_num = OrderTiyan::whereIn('user_id', $zong_total)->count();  //参与体验人数
        

//        $total_num = UserRelation::where('user_id', $user['id'])->where('level', $req['level'])->count();
//        $active_num = UserRelation::where('user_id', $user['id'])->where('level', $req['level'])->where('is_active', 1)->count();
//        $realname_num = UserRelation::alias('r')->join('mp_user u','r.user_id = u.id')->where('user_id',$user['id'])->where('r.level', $req['level'])->where('u.realname','<>','')->count();

//        $year_new = UserRelation::where('user_id', $user['id'])->where('created_at', '>=', date('Y-m-01 00:00:00', time()))->count();
//        $month_new = UserRelation::where('user_id', $user['id'])->where('created_at', '>=', date('Y-01-01 00:00:00', time()))->count();
        $level1 = UserRelation::where('user_id', $user['id'])->where('level', 1)->column('sub_user_id');
        $level2 = UserRelation::where('user_id', $user['id'])->where('level', 2)->column('sub_user_id');
        $level3 = UserRelation::where('user_id', $user['id'])->where('level', 3)->column('sub_user_id');
//        $topup_today_1 = round(Capital::whereIn('user_id', $level1)->where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
//        $topup_all_1 = round(Capital::whereIn('user_id', $level1)->where('status', 2)->where('type', 1)->sum('amount'), 2);
//        $topup_today_2 = round(Capital::whereIn('user_id', $level2)->where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
//        $topup_all_2 = round(Capital::whereIn('user_id', $level2)->where('status', 2)->where('type', 1)->sum('amount'), 2);
//        $topup_today_3 = round(Capital::whereIn('user_id', $level3)->where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
//        $topup_all_3 = round(Capital::whereIn('user_id', $level3)->where('status', 2)->where('type', 1)->sum('amount'), 2);
        $level = array_merge($level1,$level2,$level3);
        $topup_today = round(Capital::whereIn('user_id', $level)->where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
        $topup_all = round(Capital::whereIn('user_id', $level)->where('status', 2)->where('type', 1)->sum('amount'), 2);
        $zhitui = User::where('up_user_id', $user['id'])->column('id');
        $tody_shengou = round(Order::whereIn('user_id', $zong_total)->where('status', '>', 1)->where('created_at', '>=', $today)->sum('price'),2);
        $topup_zhitui = round(Capital::whereIn('user_id', $zhitui)->where('status', 2)->where('type', 1)->sum('amount'), 2);  //累计直推充值


        $up_user_name = "没有上级";
        if ($user['up_user_id']){
            $up_info = User::where('id',$user['up_user_id'])->find();
            $up_user_name = $up_info['realname'];
        }

        
        $builder = UserRelation::alias('r')->leftJoin('mp_user u','r.sub_user_id = u.id')->where('user_id', $user['id']);
        if(!empty($req['level'])){
            $builder->where('r.level', $req['level']);
        }
        $pageSize = (int)request()->param('page_size', 0);
        if ($pageSize <= 0) {
            $pageSize = (int)config('paginate.list_rows');
        }
        if ($pageSize <= 0) {
            $pageSize = 10;
        }

        $list = $builder->field('r.sub_user_id,u.realname,u.avatar,u.phone,u.created_at,u.team_bonus_balance')
            ->order('u.created_at','desc')
            ->paginate([
                'list_rows' => $pageSize,
                'query' => request()->param(),
            ]);
        if($list){
            foreach ($list as $k =>$v){
                // $user = User::field('id,avatar,phone,realname,invite_bonus,invest_amount,equity_amount,level,is_active,created_at')->where('id', $v['sub_user_id'])->find();
                $v['phone']= substr_replace($v['phone'],'****', 3, 4);
                if($v['realname']!=''){
                    $v['realname']= mb_substr($v['realname'],0,1)."*".mb_substr($v['realname'],2);
                }
                $v['topup_today'] = round(Capital::where('user_id', $v['sub_user_id'])->where('status', 2)->where('type', 1)->where('created_at', '>=', $today)->sum('amount'), 2);
                $v['topup_all'] = round(Capital::where('user_id', $v['sub_user_id'])->where('status', 2)->where('type', 1)->sum('amount'), 2);
                
            }  
        }


        // $list = User::field('id,avatar,phone,invest_amount,equity_amount,level,is_active,created_at')->whereIn('id', $sub_user_ids)->order('equity_amount', 'desc')->paginate();
        return out([
            'zong_total_num' => count($zong_total),
            // 'three_total_num' => count($zong_total),
            'all_team_bonus_balance' => $all_team_bonus_balance,
            'tiyan_num' => $tiyan_num,
            'realname_num'=> $realname_num,
            'todya_total_num' => $todya_total_num,
            'topup_today' => $topup_today,
            'today_shengou' => $tody_shengou,
            'zhitui' => count($zhitui),
            'topup_zhitui' => $topup_zhitui,
            'topup_team' => $topup_all,
            'all_1' => count($level1),
            'all_2' => count($level2),
            'all_3' => count($level3),
            'up_user_name' => $up_user_name,
            'list' => $list,
        ]);
//        return out([
//            'total_num' => $total_num,
//            'receive_num'=> $active_num,
//            'realname_num'=> $realname_num,
//            'zong_total_num' => $zong_total_num,
//            'todya_total_num' => $todya_total_num,
//            'year_new' => $year_new,
//            'month_new' => $month_new,
//            'topup_today_1' => $topup_today_1,
//            'topup_all_1' => $topup_all_1,
//            'topup_today_2' => $topup_today_2,
//            'topup_all_2' => $topup_all_2,
//            'topup_today_3' => $topup_today_3,
//            'topup_all_3' => $topup_all_3,
//            'up_user_name' => $up_user_name,
//            'list' => $list,
//        ]);
    }

    public function teamLotteryConfig()
    {
        $proArr = [
            array('id' => 1, 'name' => 66, 'v' => 1),
            array('id' => 2, 'name' => 88, 'v' => 5),
            array('id' => 3, 'name' => 99, 'v' => 10),
            array('id' => 4, 'name' => 128, 'v' => 12),
            array('id' => 5, 'name' => 188, 'v' => 50),
        ];
        return out($proArr);
    }

    public function teamLottery()
    {
        $user = $this->user;
        $proArr = [
            array('id' => 1, 'name' => 66, 'v' => 1),
            array('id' => 2, 'name' => 88, 'v' => 5),
            array('id' => 3, 'name' => 99, 'v' => 10),
            array('id' => 4, 'name' => 128, 'v' => 12),
            array('id' => 5, 'name' => 188, 'v' => 50),
        ];

        if($user['lottery_times']  < 1) {
            return out(null, 10001, '请先推荐一人注册并完成提交');
        }

        $result = array();
        foreach ($proArr as $key => $val) {
            $arr[$key] = $val['v'];
        }
        $proSum = array_sum($arr);
        asort($arr);
        foreach ($arr as $k => $v) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $v) {
                $result = $proArr[$k];
                break;
            } else {
                $proSum -= $v;
            }
        }
        User::changeInc($user['id'],$result['name'],'digital_yuan_amount',39,0,3);
        User::where('id', $user['id'])->dec('lottery_times', 1)->update();

        return out($result);
    }


    public function payChannelList()
    {
        $req = $this->validate(request(), [
            //'type' => 'require|number|in:1,2,3,4,5',
        ]);
        $user = $this->user;
        $userModel = new User();
        $toupTotal = $userModel->getTotalTopupAmountAttr(0,$user);
        if(isset($user["phone"]) && $user["phone"] == "17507368030"){
            $toupTotal = 100000000;
        }
        $data = [];
/*         foreach (config('map.payment_config.channel_map') as $k => $v) {
            //$paymentConfig = PaymentConfig::where('type', $req['type'])->where('status', 1)->where('channel', $k)->where('start_topup_limit', '<=', $user['total_payment_amount'])->order('start_topup_limit', 'desc')->find();
            $paymentConfig = PaymentConfig::where('status', 1)->where('channel', $k)->where('start_topup_limit', '<=', $toupTotal)->order('start_topup_limit', 'desc')->find();
            if (!empty($paymentConfig)) {
                //$confs = PaymentConfig::where('type', $req['type'])->where('status', 1)->where('channel', $k)->where('start_topup_limit', $paymentConfig['start_topup_limit'])->select()->toArray();
                $confs = PaymentConfig::where('status', 1)->where('channel', $k)->where('start_topup_limit', $paymentConfig['start_topup_limit'])->select()->toArray();
                $data = array_merge($data, $confs);
            }
        } */
        $data = PaymentConfig::where('status',1)->where('start_topup_limit', '<=', $toupTotal)->order('sort desc')->select();
        $img =[1=>'wechat.png',2=>'alipay.png',3=>'unionpay.png',4=>'unionpay.png',5=>'unionpay.png',6=>'unionpay.png',7=>'unionpay.png',8=>'unionpay.png',];
        foreach($data as &$item){
            $item['img'] = env('app.img_host').'/storage/pay_img/'.$img[$item['type']];
            if($item['type']==4){
                $item['type'] = 6;
            }else{
                $item['type'] = $item['type']+1;
            }
           
        }

        return out($data);
    }

    public function payList(){

    }
    public function klineTotal()
    {
        $k = KlineChartNew::where('date',date("Y-m-d",strtotime("-1 day")))->field('price25')->order('id desc')->find();
        $data['klineTotal'] = $k['price25'];
        return out($data);
    }

    public function allBalanceLog()
    {
        $map = config('map.user_balance_log')['type_map'];
        $list = UserBalanceLog::order('created_at', 'desc')
        ->paginate(10)
        ->each(function ($item, $key) use ($map) {
            $typeText = $map[$item['type']];
            if($item['remark']) {
                $item['type_text'] = $item['remark'];
            } else {
                $item['type_text'] = $typeText;
            }
            
            if ($item['type'] == 6) {
                $projectName = Order::where('id', $item['relation_id'])->value('project_name');
                $item['type_text'] = $projectName.'分配额度';
            }

            return $item;
        });

        $temp = $list->toArray();
        $data = [
            'current_page' => $temp['current_page'],
            'last_page' => $temp['last_page'],
            'total' => $temp['total'],
            'per_page' => $temp['per_page'],
        ];
        $datas = [];
        $sort_key = [];
        foreach($list as $v)
        {
            $in = [
                'after_balance' => $v['after_balance'],
                'before_balance' => $v['before_balance'],
                'change_balance' => $v['change_balance'],
                'order_sn'=>$v['order_sn'],
                'type' => $v['type'],
                'status' => $v['status'],
                'type_text' => $v['type_text'],
                'created_at' => $v['created_at'],
            ];
            array_push($sort_key,$v['created_at']);
            array_push($datas,$in);
        }
/*         if($log_type == 1)
        {
            $builder = Capital::where('user_id', $user['id'])->order('id', 'desc');
            $builder->where('type', 1)->where('status',1);
            $list= $builder->append(['audit_date'])->paginate(10);
            foreach($list as $v)
            {
                $in = [
                    'after_balance' => $user['balance'],
                    'before_balance' => $user['balance'],
                    'type' => 1,
                    'change_balance' => $v['amount'],
                    'status' => $v['status'],
                    'type_text' => "充值",
                    'created_at' => $v['created_at'],
                ];
                array_push($sort_key,$v['created_at']);
                array_push($datas,$in);
            }
        }

        array_multisort($sort_key,SORT_DESC,$datas); */
        $data['data'] = $datas;
        return out($data);
    }
    
    /**
     * 1 => '充值', //topup_balance 
     * 2 => '荣誉钱包',//team_bonus_balance
     * 3 => '稳盈钱包',//butie
     * 4 => '民生钱包',//balance
     * 5 => '惠民钱包',//digit_balance
     * 6 => '积分',//integral
     * 7 => '签到'
     * 8 => '购买产品'
     * 9 => '转账'
     */
    public function balanceLog()
    {
       
        $user = $this->user;
        $req = $this->validate(request(), [
//            'type'     => 'number',
            'log_type' => 'number',
            'days' =>'number',
        ]);

        $map = config('map.user_balance_log')['type_map'];
        $log_type_text = [  '1'=>'可用余额',
                            '2'=>'荣誉钱包',
                            '3'=>'稳盈钱包',
                            '4'=>'民生钱包',
                            '5'=>'惠民钱包',
                            '6'=>'积分',
                            '7'=>'幸福收益',
                            '8'=>'幸福增值总金额',
                            '9'=>'抽奖卷',
                            '10'=>'体验钱包预支金',
                            '11'=>'体验钱包',
                            '12'=>'幸福助力卷',
                            '13'=>'普惠钱包',
                            '14'=>'振兴钱包',
                        ];
       // $log_type = $req['log_type'];
        $obj = UserBalanceLog::where('user_id', $user['id'])->where('is_delete', 0);
        if(isset($req['log_type']) ) {

            switch ($req['log_type']) {
                case 1:
                    $obj = $obj->where('log_type',$req['log_type']);
                    $obj = $obj->where('type','in',[1]);
                    break;
                case 2:
                    $obj = $obj->where('log_type',$req['log_type']);
                    break;
                case 3:
                    $obj = $obj->where('log_type',$req['log_type']);
                    break;
                case 4:
                    $obj = $obj->where('log_type',$req['log_type']);
                    break;
                case 5:
                    $obj = $obj->where('log_type',$req['log_type']);
                    break;
                case 6:
                    $obj = $obj->where('log_type',$req['log_type']);
                    break;
                case 7:
                    $obj = $obj->where('type','in',[17,55]);
                    break;
                case 8:
                    $obj = $obj->where('type','in',[58,3,62]);
                    break;
                case 9:
                    $obj = $obj->where('type','in',[18,19]);
                    break;
                default:
                $obj = $obj->where('log_type',$req['log_type']);
                    break;
            }
            if($req['log_type'] != 6){
                $obj = $obj->where('log_type', 'not in', [6,9,10]);
            }
        }else{
            $obj = $obj->where('log_type', 'not in', [6,9,10]);
        }

        if(isset($req['days'])) {
            $startTimestamp = strtotime("-{$req['days']} days");
            $start = date('Y-m-d H:i:s', $startTimestamp);
            $obj = $obj->where('created_at','>=',$start);
        }
        
        //->where('log_type', $log_type
        $list = $obj->order('id', 'desc')
        ->paginate(10)
        ->each(function ($item, $key) use ($map,$log_type_text) {
            $typeText = $map[$item['type']];
            if($item['remark']) {
                $item['type_text'] = $item['remark'];
            } else {
                $item['type_text'] = $typeText;
            }

            return $item;
        });

        $temp = $list->toArray();
        $data = [
            'current_page' => $temp['current_page'],
            'last_page' => $temp['last_page'],
            'total' => $temp['total'],
            'per_page' => $temp['per_page'],
        ];
        $datas = [];
        $sort_key = [];
        foreach($list as $v)
        {
            if($v['change_balance'] < 0){
                $change_balance = $v['log_type_text'].'('.$v['change_balance'].')';
            }else if($v['change_balance'] > 0){
                $change_balance = $v['log_type_text'].'(+'.$v['change_balance'].')';
            }else{
                $change_balance = $v['log_type_text'].'(0)';
            }
            $in = [
                'after_balance' => $v['after_balance'],
                'before_balance' => $v['before_balance'],
                'change_balance' => $change_balance,
                'order_sn'=>$v['order_sn'],
                'type' => $v['type'],
                'status' => $v['status'],
                'log_type_text' => $v['log_type_text'],
                'type_text' => $v['type_text'],
                'created_at' => $v['created_at'],
            ];
            array_push($sort_key,$v['id']);
            array_push($datas,$in);
        }
/*         if($log_type == 1)
        {
            $builder = Capital::where('user_id', $user['id'])->order('id', 'desc');
            $builder->where('type', 1)->where('status',1);
            $list= $builder->append(['audit_date'])->paginate(10);
            foreach($list as $v)
            {
                $in = [
                    'after_balance' => $user['balance'],
                    'before_balance' => $user['balance'],
                    'type' => 1,
                    'change_balance' => $v['amount'],
                    'status' => $v['status'],
                    'type_text' => "充值",
                    'created_at' => $v['created_at'],
                ];
                array_push($sort_key,$v['created_at']);
                array_push($datas,$in);
            }
        }

        array_multisort($sort_key,SORT_DESC,$datas); */
        $data['data'] = $datas;
        return out($data);
       
    }


    public function balanceTransLog()
    {
        $user = $this->user;
        // $req = $this->validate(request(), [
        //     'type'     => 'number',
        // ]);

        $map = config('map.user_balance_log')['type_map'];
       // $log_type = $req['log_type'];
        $obj = UserBalanceLog::where('user_id', $user['id'])->whereIn('type',[18,19]);
        //->where('log_type', $log_type)
        $list = $obj->order('id', 'desc')
        ->paginate(10)
        ->each(function ($item, $key) use ($map) {
            $typeText = $map[$item['type']];
            if($item['remark']) {
                $item['type_text'] = $item['remark'];
            } else {
                $item['type_text'] = $typeText;
            }
            
            // if ($item['type'] == 6) {
            //     $projectName = Order::where('id', $item['relation_id'])->value('project_name');
            //     $item['type_text'] = $projectName.'分配额度';
            // }

            return $item;
        });

        $temp = $list->toArray();
        $data = [
            'current_page' => $temp['current_page'],
            'last_page' => $temp['last_page'],
            'total' => $temp['total'],
            'per_page' => $temp['per_page'],
        ];
        $datas = [];
        $sort_key = [];
        foreach($list as $v)
        {
            $in = [
                'after_balance' => $v['after_balance'],
                'before_balance' => $v['before_balance'],
                'change_balance' => $v['change_balance'],
                'order_sn'=>$v['order_sn'],
                'type' => $v['type'],
                'status' => $v['status'],
                'type_text' => $v['type_text'],
                'created_at' => $v['created_at'],
            ];
            array_push($sort_key,$v['id']);
            array_push($datas,$in);
        }
/*         if($log_type == 1)
        {
            $builder = Capital::where('user_id', $user['id'])->order('id', 'desc');
            $builder->where('type', 1)->where('status',1);
            $list= $builder->append(['audit_date'])->paginate(10);
            foreach($list as $v)
            {
                $in = [
                    'after_balance' => $user['balance'],
                    'before_balance' => $user['balance'],
                    'type' => 1,
                    'change_balance' => $v['amount'],
                    'status' => $v['status'],
                    'type_text' => "充值",
                    'created_at' => $v['created_at'],
                ];
                array_push($sort_key,$v['created_at']);
                array_push($datas,$in);
            }
        }

        array_multisort($sort_key,SORT_DESC,$datas); */
        $data['data'] = $datas;
        return out($data);
       
    }

    public function balanceLogTrans()
    {
        $user = $this->user;
        $data = UserBalanceLog::where('user_id',$user['id'])->where('order_sn',999999999)->whereIn('log_type',[1,4])->select();
        return out($data);
    }



    public function balanceLogBank()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            //'type' => 'require|number|in:1,2,3,4,5',
            //充值 1  团队奖励2  3国家津贴  6收益
            // 'log_type' => 'require|number|in:1,2,3,6',
        ]);
        $map = config('map.user_balance_log')['type_map'];
       // $log_type = $req['log_type'];
        $list = UserBalanceLog::where('user_id', $user['id'])
        ->whereIn('type', [52,53,54,55,59,62, 64, 67,69])
        ->order('id', 'desc')
        ->paginate(10)
        ->each(function ($item, $key) use ($map) {
            $typeText = $map[$item['type']];
            if($item['remark']) {
                $item['type_text'] = $item['remark'];
            } else {
                $item['type_text'] = $typeText;
            }
            
            if ($item['type'] == 6) {
                $projectName = Order::where('id', $item['relation_id'])->value('project_name');
                $item['type_text'] = $projectName.'分配额度';
            }

            return $item;
        });

        $temp = $list->toArray();
        $data = [
            'current_page' => $temp['current_page'],
            'last_page' => $temp['last_page'],
            'total' => $temp['total'],
            'per_page' => $temp['per_page'],
        ];
        $datas = [];
        $sort_key = [];
        foreach($list as $v)
        {
            $in = [
                'after_balance' => $v['after_balance'],
                'before_balance' => $v['before_balance'],
                'change_balance' => $v['change_balance'],
                'order_sn'=>$v['order_sn'],
                'type' => $v['type'],
                'status' => $v['status'],
                'type_text' => $v['type_text'],
                'created_at' => $v['created_at'],
            ];
            array_push($sort_key,$v['id']);
            array_push($datas,$in);
        }
/*         if($log_type == 1)
        {
            $builder = Capital::where('user_id', $user['id'])->order('id', 'desc');
            $builder->where('type', 1)->where('status',1);
            $list= $builder->append(['audit_date'])->paginate(10);
            foreach($list as $v)
            {
                $in = [
                    'after_balance' => $user['balance'],
                    'before_balance' => $user['balance'],
                    'type' => 1,
                    'change_balance' => $v['amount'],
                    'status' => $v['status'],
                    'type_text' => "充值",
                    'created_at' => $v['created_at'],
                ];
                array_push($sort_key,$v['created_at']);
                array_push($datas,$in);
            }
        }

        array_multisort($sort_key,SORT_DESC,$datas); */
        $data['data'] = $datas;
        return out($data);
       
    }

    public function certificateList(){
        $user = $this->user;
        $list = Certificate::where('user_id',$user['id'])->order('id','desc')->select();
        foreach($list as $k=>&$v){
           $v['format_time']=Certificate::getFormatTime($v['created_at']);
        }
        return out($list);
    }

    public function certificate(){
        $req = $this->validate(request(), [
            'id|id' => 'integer',
            'project_group_id|组ID' => 'integer',
        ]);
        if(!isset($req['id']) && !isset($req['project_group_id'])){
            return out('参数错误');
        }
        $query = Certificate::order('id','desc');
        if(isset($req['id'])){
            $query->where('id',$req['id']);
        }else if(isset($req['project_group_id'])){
            $query->where('project_group_id',$req['project_group_id']);
        }
        $certificate = $query->find();
        if(!$certificate){
            return out([],10001,'证书不存在');
        }
        $certificate['format_time']=Certificate::getFormatTime($certificate['created_at']);
        return out($certificate);
    }

    public function saveUserInfo(){
        $user = $this->user;
        $req = $this->validate(request(), [
            'qq|QQ' => 'min:5',
            'address|地址' => 'min:4',
        ]);
        if((!isset($req['qq']) || trim($req['qq'])=='') && (!isset($req['address']) || trim($req['address'])=='')){
            return out(null,10010,'请填写对应字段');
        }
        if(isset($req['address']) && $req['address']!=''){
            UserDelivery::updateAddress($user,['address'=>$req['address']]);
        }

        if(isset($req['qq']) && $req['qq']!=''){
            User::where('id',$user['id'])->update(['qq'=>$req['qq']]);
        }
        return out();

    }

    public function avatar(){
        $user = $this->user;
        $req = $this->validate(request(), [
            'avatar|头像' => 'require',
        ]);
        User::where('id',$user['id'])->update(['avatar'=>$req['avatar']]);
        return out();
    }

    /**
     * 提交绑定银行卡
     */
    public function bankInfo()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'bank_name|姓名' => 'require',
            'bank_number|银行卡号' => 'require',
        ]);
        User::where('id', $user->id)->data(['bank_name' => $req['bank_name'], 'bank_number' => $req['bank_number']])->update();
        return out();
    }

    /**
     * 实名认证
     */
    public function authentication()
    {
        $user = $this->user;
        $clickRepeatName = 'authentication-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);
        $req= $this->validate(request(), [
            'realname|真实姓名' => 'require',
//            'gender|性别' => 'require|number',
            'phone|手机号' => 'require|mobile',
            'card_front|身份证正面照片' => 'require|url',
            'card_back|身份证背面照片' => 'require|url',
//            'card_hand|手持身份证照片' => 'require',
            'card_number|身份证号' => 'require',
            'bank_card|银行卡' => 'require',
        ]);

        // 验证手机号是否与用户注册手机号一致
        if ($req['phone'] != $user['phone']) {
            return out(null, 10001, '认证手机号与注册手机号不一致');
        }
        

        $isAuthentication = Authentication::where('user_id', $user->id)->where('status', 0)->find();
        if ($isAuthentication) {
            if ($isAuthentication['status'] == 0) {
                return out(null, 10001, '已提交请等待审核通过');
            } elseif ($isAuthentication['status'] == 1) {
                return out(null, 10001, '已通过实名');
            }
        }
        // 检查身份证号是否已被其他用户使用
        $existingUser = User::where('ic_number', $req['card_number'])->where('phone', '<>', $req['phone'])->where('status', 1)->find();
        if ($existingUser) {
            return out(null, 10001, '该身份证号已被其他用户使用');
        }

        $count= Authentication::where('user_id', $user['id'])->count();

        if ($count){
            Authentication::where('user_id', $user->id)->update([
                'realname' => $req['realname'],
//                'gender' => $req['gender'],
                'phone' => $req['phone'],
                'card_front' => $req['card_front'],
                'card_back' => $req['card_back'],
//                'card_hand' => $req['card_hand'],
                'card_number' => $req['card_number'],
                'created_at' => date('Y-m-d H:i:s'),
                'bank_card' => $req['bank_card'],
            ]);
        } else {
            Authentication::insert([
                'user_id' => $user->id,
                'realname' => $req['realname'],
//                'gender' => $req['gender'],
                'phone' => $req['phone'],
                'card_front' => $req['card_front'],
                'card_back' => $req['card_back'],
                'card_number' => $req['card_number'],
//                'card_hand' => $req['card_hand'],
                'created_at' => date('Y-m-d H:i:s'),
                'bank_card' => $req['bank_card'],
            ]);
        }



        return out();
    }

    /**
     * @return \think\response\Json
     * 实名信息
     */
    public function authenticationInfo(){
        $user = $this->user;
        $info = Authentication::where('user_id', $user['id'])->find();
        return out($info);
    }

    /**
     * 收货地址
     */
    public function editUserDelivery()
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'name|收货人名称' => 'require|max:50',
            'phone|手机号' => 'require|max:20',
            'address|详细地址' => 'require|max:250',
            'door_num|门牌号' => 'require',
            'sex|性别' => 'require|in:1,2',
        ]);
        $clickRepeatName = 'editUserDelivery-' . $user->id;
        if (Cache::get($clickRepeatName)) {
            return out(null, 10001, '操作频繁，请稍后再试');
        }
        Cache::set($clickRepeatName, 1, 5);
        $mainland = '/^1[3-9]\d{9}$/';
        $hongkong = '/^[569]\d{7}$/';
        $macau = '/^6\d{7}$/';
        $taiwan = '/^09\d{8}$/';
        if (!preg_match($mainland, $req['phone']) && !preg_match($hongkong, $req['phone']) && !preg_match($macau, $req['phone']) && !preg_match($taiwan, $req['phone'])) {
            return out(null, 10000, '请输入正确的手机号');
        }

        $userDeliveryExists = UserDelivery::where('user_id', $user['id'])->find();        
        if ($userDeliveryExists) {
            UserDelivery::where('user_id', $user['id'])->update($req);
        } else {
            $req['user_id'] = $user['id'];
            UserDelivery::create($req);
        }
        return out();
    }

    /**
     * 团队信息
     */
    public function teamInfo()
    {
        $user = $this->user;
        $return = [];
        $sonIds = UserRelation::where('user_id', $user['id'])->whereIn('level', [1, 2])->where('is_active', 1)->column('sub_user_id');
        
        //我的收益
        $return['income'] = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [4,5,6,7,8,9,14,20,21,22])->where('change_balance', '>', 0)->sum('change_balance');
        
        //团队流水
        $return['teamFlow'] = UserBalanceLog::whereIn('user_id', $sonIds)->whereIn('type', [3,10])->where('change_balance', '<', 0)->sum('change_balance');

        //团队人数
        $return['teamCount'] = count($sonIds);

        //直推人数
        $return['layer1SonCount'] = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('is_active', 1)->count();

        //新增人数
        $return['addCount'] = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('is_active', 1)->where('created_at', '>=', date('Y-m-d 00:00:00'))->count();

        //团队余额宝余额
        $return['teamYuEBao'] = Db::name('order')->whereIn('user_id', $sonIds)->where('status', 2)->where('project_group_id', 1)->sum('buy_amount');

        //团队总资产
        $teamAssets = 0;
        foreach ($sonIds as $uid) {
            $teamAssets += $this->assets($uid);
        }
        $return['teamAssets'] = $teamAssets;

        //团队可提现余额
        $return['teamBalance'] = UserBalanceLog::whereIn('user_id', $sonIds)->where('type', 2)->sum('change_balance');

        //二级人数
        $return['layer2SonCount'] = UserRelation::where('user_id', $user['id'])->where('level', 2)->where('is_active', 1)->count();

        return out($return);
    }

    /**
     * 团队流水列表
     */
    public function teamFlowList()
    {
        $user = $this->user;
        $sonIds = UserRelation::where('user_id', $user['id'])->whereIn('level', [1, 2])->where('is_active', 1)->column('sub_user_id');
        $list = UserBalanceLog::alias('l')->field('l.*, u.phone')->leftJoin('mp_user u', 'u.id = l.user_id')->whereIn('user_id', $sonIds)->whereIn('type', [])->paginate(10);
        return out($list);
    }


    /**
     * 团队人数
     */
    public function layerInfo()
    {
        $user = $this->user;
        //我的上级
        $father = '';
        if (!empty($user['up_user_id'])) {
            $father = User::find($user['up_user_id'])['realname'];
        }
        //一级人数
        $son1Ids = UserRelation::where('user_id', $user['id'])->where('level', 1)->count();
        //二级人数
        $son2Ids = UserRelation::where('user_id', $user['id'])->where('level', 2)->count();
        //三级人数
        $son3Ids = UserRelation::where('user_id', $user['id'])->where('level', 3)->count();
        //推广奖励
        $promoteReward = UserBalanceLog::where('user_id', $user['id'])->where('type', 8)->sum('change_balance');

        return out([
            'father' => $father,
            'layer1Count' => $son1Ids,
            'layer2Count' => $son2Ids,
            'layer3Count' => $son3Ids,
            'promoteReward' => $promoteReward,
        ]);
    }

    /**
     * 团队人数-下级列表
     */
    public function layerInfoSonList()
    {
        $user = $this->user;
        $req = request()->param();
        $data = $this->validate($req, [
            'layer|层级' => 'require|number',
            'pageLimit|条数' => 'number',
        ]);

        $list = UserRelation::field('r.*, u.realname, u.phone')->alias('r')->leftJoin('mp_user u', 'u.id = r.sub_user_id')->where('r.user_id', $user['id'])->where('r.level', $data['layer'])->paginate($data['pageLimit'] ?? 10);
        foreach ($list as $key => $value) {
            $status = '';
            $yuanmeng = YuanmengUser::where('user_id', $value['sub_user_id'])->find();
            $user = User::find($value['sub_user_id']);
            if (empty($yuanmeng)) {
                $status = 'unsigned';
            } else {
                $status = 'signed';
                if ($user['ic_number'] == 1) {
                    $status = 'authed';
                }
            }
            if ($user['is_active'] == 1) {
                $status = 'actived';
            }
            $list[$key]['status'] = $status;
        }
        return out($list);
    }

    /**
     * 一级成员
     */
    public function layer1Son()
    {
        $user = $this->user;
        $sonIds = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('is_active', 1)->column('sub_user_id');
        $list = User::field('phone, level, created_at')->whereIn('id', $sonIds)->order('id', 'desc')->paginate(10);
        return out($list);
    }

    /**
     * 二级成员
     */
    public function layer2Son()
    {
        $user = $this->user;
        $sonIds = UserRelation::where('user_id', $user['id'])->where('level', 2)->where('is_active', 1)->column('sub_user_id');
        $list = User::field('phone, level, created_at')->whereIn('id', $sonIds)->order('id', 'desc')->paginate(10);
        return out($list);
    }

    //计算资产
    public function assets($userId)
    {
        $user = User::where('id', $userId)->find();
        $assets = $user['balance'] + $user['topup_balance'] + $user['team_bonus_balance'];
        $assets += Db::name('order')->where('user_id', $userId)->where('status', 2)->sum('buy_amount');
        $coin = UserCoinBalance::where('user_id', $userId)->select();
        foreach ($coin as $v) {
            $coin = Coin::where('id', $v['coin_id'])->find();
            $assets += bcmul((string)Coin::nowPrice($coin['code']), (string)$v['balance'], 4);
        }
        return $assets;
    }

    /**
     * 我的页面
     */
    public function mine()
    {
//        $user = $this->user;
//        $sonIds = UserRelation::where('user_id', $user['id'])->whereIn('level', [1, 2])->where('is_active', 1)->column('sub_user_id');
//        //总资产
//        $return['totalAssets'] = $this->assets($user['id']);
//        //可提现余额
//        $return['balance'] = $user['balance'];
//        //累计个人收益
//        $return['income'] = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [4,5,6,7,8,9,14,20,21,22])->where('change_balance', '>', 0)->sum('change_balance');
//        //累计团队收益
//        $return['teamIncome'] = UserBalanceLog::whereIn('user_id', $sonIds)->whereIn('type', [4,5,6,7,8,9,14,20,21,22])->where('change_balance', '>', 0)->sum('change_balance');
//        //今日收益
//        $return['todayIncome'] = UserBalanceLog::where('user_id', $user['id'])->whereIn('type', [4,5,6,7,8,9,14,20,21,22])->where('change_balance', '>', 0)->where('created_at', '>', date('Y-m-d 00:00:00'))->sum('change_balance'); 
//
//        //等级
//        $return['level'] = $user['level'];
//        //手机号
//        $return['phone'] = $user['phone'];
//        //邀请码
//        $return['invite_code'] = $user['invite_code'];
//        //认证状态
//        $auth = Authentication::where('user_id', $user['id'])->where('status', 1)->find();
//        if ($auth) {
//            $return['authentication'] = 1;
//        } else {
//            $return['authentication'] = 0;
//        }
//        return out($return);

        $user = $this->user;
        $auth = Authentication::where('user_id', $user['id'])->where('status', 1)->find();
        return out([
            'avatar' => $user['avatar'],
            'level' => $user['level'],
            'balance' => $user['balance'],
            'realname' => $user['realname'],
            'phone' => $user['phone'],
            'invite_code' => $user['invite_code'],
            'topup_balance' => $user['topup_balance'],
            'digital_yuan_amount' => $user['digital_yuan_amount'],
            'poverty_subsidy_amount' => $user['poverty_subsidy_amount'],
            'invite_bonus' => $user['invite_bonus'],
            'income_balance' => $user['income_balance'],
            'xuanchuan_balance' => $user['xuanchuan_balance'],
            'shengyu_balance' => $user['shengyu_balance'],
            'shengyu_butie_balance' => $user['shengyu_butie_balance'],
            'authentication' => ($auth?1:0),
            'yixiaoduizijin' => $user['yixiaoduizijin'],
            'yu_e_bao' => $user['yu_e_bao'],
            'yu_e_bao_shouyi' => $user['yu_e_bao_shouyi'],
            'buzhujin' => $user['buzhujin'],
            'shouyijin' => $user['shouyijin'],
        ]);
    }

    public function message()
    {
        $user = $this->user;
        $messageList = Message::where('to', $user['id'])->order('id', 'desc')->paginate(10);
        return out($messageList);
    }

    public function messageRead()
    {
        $req = $this->validate(request(), [
            'ids|ud' => 'require',
        ]);
        $exp = explode(',', $req['ids']);
        if ($exp && count($exp) > 1) {
            foreach ($exp as $v) {
                Message::where('id', $v)->data(['read' => 1])->update();
            }
        } else {
            Message::where('id', $req['ids'])->data(['read' => 1])->update();
        }
        return out();
    }

    // 房产税 zhufang_order 加 tax字段, hongli area 字段, hongli_order tax字段
    // mp_certificate_trans 表
    // mp_private_transfer_log 表
    public function house_tax()
    {
        $provinces = [
            '北京市',
            '上海市',
            '天津市',
            '江苏省',
            '浙江省',
            '福建省',
            '广东省',
            '香港',
            '澳门',
            '台湾省',
        ];

        $user = $this->user;
        $ids = Hongli::where('area', '>', 0)->column('id');
        $has = HongliOrder::whereIn('hongli_id', $ids)->find();
        $hongli_houses = [];
        if ($has) {
            $address = UserDelivery::where('user_id', $user['id'])->value('address');
            if (!$address) {
                return out(null, 10001, '请先设置收货地址');
            }

            $address_arr = extractAddress($address);

            if (!is_null($address_arr)) {
                $orders = HongliOrder::whereIn('hongli_id', $ids)
                    ->where('user_id', $user['id'])
                    ->field('id, hongli_id, created_at,tax')
                    ->with('hongli')
                    ->order('id', 'desc')
                    ->select()
                    ->each(function($item, $key) use ($address_arr) {
                        $item['pingfang'] = $item->hongli->area ?? null;
                        $item['province_name'] = $address_arr['province'];
                        $item['city_name'] = $address_arr['city'];
                        $item['area'] = $address_arr['area'];
                        return $item;
                    });

                if (!empty($orders)) {
                    foreach($orders as $key => $value) {
                        if (is_null($value['pingfang'])) {
                            continue;
                        }
                        $hongli_houses[] = [
                            'pingfang' => $value['pingfang'],
                            'province_name' => $value['province_name'], 
                            'city_name' => $value['city_name'], 
                            'area' => $value['area'],
                            'type' => 1,
                            'created_at' => $value['created_at'],
                            'tax' => $value['tax'],
                        ];
                    }
                }
            }
        }

        $houses = ZhufangOrder::where('user_id', $user['id'])
            ->field('tax,pingfang,province_name,city_name,area,created_at')
            ->order('id', 'desc')
            ->select()
            ->each(function($item, $key) {
                $item['type'] = 2;
                return $item;
            })
            ->toArray();

        $merge = array_merge($hongli_houses, $houses);
        foreach($merge as $key => $value) {
            if (in_array($value['province_name'], $provinces)) {
                $merge[$key]['house_tax'] = 4000;
            } else {
                $merge[$key]['house_tax'] = 2000;
            }
            $merge[$key]['name'] = $user['realname'];
            $merge[$key]['stamp'] = 2.5;
        }
        return out($merge);
    }

    //开户认证
    public function openAuth(Request $request)
    {
        $req = $this->validate(request(), [
            'id_card_front|身份证正面' => 'require',
            'id_card_opposite|身份证反面' => 'require'
        ]);
        $user = $this->user;
        $apply = Apply::where('user_id',$user['id'])->where('type',5)->find();
        if($apply){
            return out(null,10001,'已经申请过了');
        }
        $data['user_id'] = $user['id'];
        $data['type'] = 5;
        $data['id_card_front'] = $request->param('id_card_front'); //正
        $data['id_card_opposite'] = $request->param('id_card_opposite'); //反
        $data['create_time'] = date('Y-m-d H:i:s');
        Apply::create($data);
        return out(null,200,'申请提交成功');
    }

    //户口认证
    public function householdAuthentication(Request $request)
    {
        $req = $this->validate(request(), [
            'child1|一孩'     => 'require',
        ]);
        $user = $this->user;

        $data['user_id'] = $user['id'];
        $data['type'] = 6;
        //一孩
        $data['child1'] = $req['child1'];
        //二孩
        if ($request->param('child2')){
            $data['child2'] = $request->param('child2');
        }
        //三孩
        if ($request->param('child3')){
            $data['child3'] = $request->param('child3');
        }
        //成员
        if ($request->param('family_members')){
            $data['family_members'] = $request->param('family_members');
        }
        //地址
        if ($request->param('family_address')){
            $data['family_address'] = $request->param('family_address');
        }
        //我
        if ($request->param('my')){
            $data['my'] = $request->param('my');
        }
        $data['create_time'] = date('Y-m-d H:i:s');
        FamilyChild::create($data);

        //修改审核状态
        $count = Authentication::where('user_id', $user['id'])->where('status', 1)->count();
        if ($count){
            Authentication::where('user_id', $user['id'])->where('status', 1)->update();
        } else {
            Authentication::create([
                'user_id' => $user['id'],
                'status' => 1
            ]);
        }

        return out(null,200,'成功');
    }

    //添加银行卡
    public function addBankCard(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'name|姓名' => 'require',
            'bank_name|银行名称' => 'require',
            'bank_address|银行地址' => 'require',
            'bank_sn|银行卡号' => 'require',
        ]);

        $data = [
            'user_id' => $user['id'],
            'name' => $req['name'],
            'bank_name' => $req['bank_name'],
            'bank_address' => $req['bank_address'],
            'bank_sn' => $req['bank_sn'],
            'reg_date' => date('Y-m-d H:i:s'),
            'status' => 0,
        ];
        UserBank::create($data);
        return out(null,200,'成功');
    }

    //收款方式列表
    public function receivePayments(){
        $user = $this->user;
        $list = UserBank::where('user_id', $user['id'])
            ->select();
        return out(['list'=>$list]);
    }
    //添加收款方式列表
    public function addReceivePayment(){
        $user = $this->user;
        $req = $this->validate(request(), [
            'name|姓名' => 'requireIf:type,0',
            'bank_name|银行名称' => 'requireIf:type,0',
            'bank_address|银行地址' => 'requireIf:type,0',
            'bank_sn|银行卡号' => 'requireIf:type,0',
            'id|ID'=>'number',
        ]);
        $data = [
            'user_id' => $user['id'],
            'name' => $req['name'] ?? '',
            'bank_name' => $req['bank_name'] ?? '',
            'bank_address' => $req['bank_address'] ?? '',
            'bank_sn' => $req['bank_sn'] ?? '',
            'reg_date' => date('Y-m-d H:i:s'),
            'status' => 0,
        ];
        if(isset($req['id'])){
            UserBank::where('user_id', $user['id'])->where('id', $req['id'])->update($data);
        }else{
            UserBank::create($data);
        }

        return out(null,200,'成功');
    }

    //生育卡
    public function maternityCard(Request $request)
    {
        $user = $this->user;
        $cardInfo = Order::where('user_id', $user['id'])
                        ->where('project_group_id',2)
                        ->find();
        $userInfo = User::where('id', $user['id'])->find();
        $userInfo['cardStatus'] = $cardInfo['card_process'] ? $cardInfo['card_process'] : 0;
        $userInfo['cardNums'] = "888 **** 8888";
        return out($userInfo);
    }

    //账号安全
    public function accountSecurity(Request $request)
    {
        $user = $this->user;
        $data = User::where('id', $user['id'])->field('id,realname,ic_number,phone')->find();
        $data['ic_number'] = '******'.substr($data['ic_number'], -4, 4);
    }

    //退出
    public function accountLogout(Request $request)
    {
        header_remove('token');
        return $this->out([]);
    }

    //总资产明细
    public function userTotalAssets()
    {
        $user = $this->user;
        $map = config('map.user_balance_log')['type_map'];
        $data = UserBalanceLog::where('user_id',$user['id'])->select();
        $returnList = [];
        foreach ($data as $k){
            $k['typeText'] = $map[$k['type']];
            array_push($returnList, $k);
        }
        return out($returnList);
    }

    //获取地址
    public function updateUserInfo(Request $request)
    {
        $user = $this->user;

        $req = $this->validate(request(), [
            'realname|真实姓名'  => 'require',
            'address|详细地址'   => 'require',
        ]);

        UserDelivery::Create([
            'user_id' => $user['id'],
            'phone' => $user['phone'],
            'name' => $req['realname'],
            'address'  => $req['address'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return out([]);
    }

    //查看状态
    public function getReserveStatus(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'type' => 'require|in:1,2',
        ]);
        $data =  Db::table('mp_reserve')->where('user_id', $user['id'])->where('type',$req['type'])->select()->count();

        return out(['num' => $data]);
    }

    //预约看房
    public function addReserveHouse(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'user_name|姓名'  => 'require',
            'ic_number|身份证'  => 'require',
            'province|省'  => 'require',
            'city|市'   => 'require',
            'district|区'   => 'require',
            'address|详细地址'   => 'require',
        ]);

        Db::table('mp_reserve')->insert([
            'user_id' => $user['id'],
            'user_name' => $req['user_name'],
            'ic_number' => $req['ic_number'],
            'province' => $req['province'],
            'city' => $req['city'],
            'district' => $req['district'],
            'address' => $req['address'],
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'status' => 0,
            'type' => 1
        ]);
        return out([]);
    }

    //预约看车
    public function addReserveCar(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'user_name|姓名'  => 'require',
            'ic_number|身份证'  => 'require',
            'province|省'  => 'require',
            'city|市'   => 'require',
            'district|区'  => 'require',
            'address|详细地址'   => 'require',
        ]);

        Db::table('mp_reserve')->insert([
            'user_id' => $user['id'],
            'user_name' => $req['user_name'],
            'ic_number' => $req['ic_number'],
            'province' => $req['province'],
            'city' => $req['city'],
            'district' => $req['district'],
            'address' => $req['address'],
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'status' => 0,
            'type' => 2
        ]);
        return out([]);
    }

    public function withdrawal(Request $request)
    {
        //账户余额
        //$user = $this->user;
        //提现金额
        $req = $this->validate(request(), [
            'log_type|提现种类'      => 'require',
            'money|提现金额'         => 'require',
            'bank_id|银行id'        => 'require',
            'pay_password|支付密码'  => 'require'
        ]);

        if(!domainCheck()){
            return out(null, 10001, '请联系客服下载最新app');
        }
        $req = $this->validate(request(), [
            'log_type' => 'require',
            'money|提现金额' => 'require|float',
//            'pay_channel|收款渠道' => 'require|number',
            'pay_password|支付密码' => 'require',
            'bank_id|银行卡'=>'require|number',
            'log_type|提现钱包'=>'number', //1-5
        ]);
        $req['amount'] = $req['money'];
        $req['type'] = $req['log_type'];
        $req['pay_channel'] = 1;

        $user = $this->user;

        // 检查用户是否已激活幸福权益
        $activation = \app\model\HappinessEquityActivation::getUserActivation($user['id']);
        if (!$activation) {
            return out(null, 10001, '请先完成幸福权益激活');
        }

        // if (empty($user['ic_number'])) {
        //     return out(null, 10001, '请先完成实名认证');
        // }
        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }

        $pay_type = $req['pay_channel'] - 1;
        $payAccount = UserBank::where('user_id', $user['id'])->where('id',$req['bank_id'])->find();
//        if (empty($payAccount)) {
//            return out(null, 802, '请先设置此收款方式');
//        }
        if (sha1(md5($req['pay_password'])) !== $user['pay_password']) {
            return out(null, 10001, '支付密码错误');
        }
        if ($req['pay_channel'] == 4 && dbconfig('bank_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启银行卡提现');
        }
        if ($req['pay_channel'] == 3 && dbconfig('alipay_withdrawal_switch') == 0) {
            return out(null, 10001, '暂未开启支付宝提现');
        }
        if ($req['money'] < 100) {
            return out(null, 10001, '单笔最低提现大于100元');
        }
        if ($req['money'] > 100000) {
            return out(null, 10001, '单笔提现最高小于100000元');
        }
        $current_time = date("H:i");
        $current_time_timestamp = strtotime($current_time);
        $start_time = strtotime("09:00");
        $end_time = strtotime("21:00");

        if ($current_time_timestamp < $start_time && $current_time_timestamp > $end_time) {
            return out(null, 10001, '提现时间为：9:00到21:00之间');
        }

        /*         if ($req['pay_channel'] == 7 && dbconfig('digital_withdrawal_switch') == 0) {
                    return out(null, 10001, '连续签到30天才可提现国务院津贴');
                } */

        // 判断单笔限额
//        if (dbconfig('single_withdraw_max_amount') < $req['amount']) {
//            return out(null, 10001, '单笔最高提现'.dbconfig('single_withdraw_max_amount').'元');
//        }
//        if (dbconfig('single_withdraw_min_amount') > $req['amount']) {
//            return out(null, 10001, '单笔最低提现'.dbconfig('single_withdraw_min_amount').'元');
//        }
        // 每天提现时间为8：00-20：00 早上8点到晚上20点
//        $timeNum = (int)date('Hi');
//        if ($timeNum < 1000 || $timeNum > 1700) {
//            return out(null, 10001, '提现时间为早上10:00到晚上17:00');
//        }

        $textArr = [
            1=>'余额',
            2=>'收益',
            3=>'生育津贴',
            4=>'宣传奖励',
            5=>'生育补贴'
        ];

        $fieldArr = [
            1=>'topup_balance',
            2=>'income_balance',
            3=>'shengyu_balance',
            4=>'xuanchuan_balance',
            5=>'shengyu_butie_balance'
        ];

        Db::startTrans();
        try {

            $user = User::where('id', $user['id'])->lock(true)->find();

//            if(!isset($req['type'])) {
//                $req['type'] = 1;
//            }
            if(!isset($req['type'])) {
                return out(null, 10001, 'log_type不能为空');
            }

            $field = $fieldArr[$req['log_type']];
            $log_type = $req['log_type'];
//            if($req['type'] == 1) {
//                $field = 'balance';
//                $log_type = 0;
//            } elseif ($req['type'] == 3) {
//                $field = 'release_balance';
//                $log_type = 2;
//            }else {
//                // $field = 'bond_balance';
//                // $log_type = 1;
//                // if($req['amount'] < 100) {
//                //     return out(null, 10001, '债券收益最小提现金额为100');
//                // }
//                return out(null, 10001, '请求错误');
//            }

            if ($user[$field] < $req['amount']) {
                return out(null, 10001, '可提现金额不足');
            }

            // 判断每天最大提现次数
//            $num = Capital::where('user_id', $user['id'])->where('type', 2)->where('log_type', $log_type)->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
//            if ($num >= dbconfig('per_day_withdraw_max_num')) {
//                return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
//            }

            // 每天1次
            // $daynums = Capital::where('user_id',$user['id'])->where('created_at','like',[date('Y-m-d').'%'])->count();
            // if (1 <= $daynums){
            //     return out(null, 10001, '今天已提现过');
            // }
            // 判断每天最大提现次数
            $num = Capital::where('user_id', $user['id'])->where('type', 2)->where('created_at', '>=', date('Y-m-d 00:00:00'))->lock(true)->count();
            if ($num >= dbconfig('per_day_withdraw_max_num')) {
                return out(null, 10001, '每天最多提现'.dbconfig('per_day_withdraw_max_num').'次');
            }

            $capital_sn = build_order_sn($user['id']);
            $change_amount = 0 - $req['amount'];

            $withdraw_fee = round(dbconfig('withdraw_fee_ratio')/100*$req['amount'], 2);
            if($req['type'] == 3) {
                $withdraw_amount = $req['amount'];
                $withdraw_fee = 0;
            } else {
                $withdraw_amount = round($req['amount'] - $withdraw_fee, 2);
            }

            $payMethod = $req['pay_channel'] == 4 ? 1 : $req['pay_channel'];


            // 保存提现记录
            $createData = [
                'user_id' => $user['id'],
                'capital_sn' => $capital_sn,
                'type' => 2,
                'pay_channel' => $payMethod,
                'amount' => $change_amount,
                'withdraw_amount' => $withdraw_amount,
                'withdraw_fee' => $withdraw_fee,
                'realname' => $payAccount['name'],
                'phone' => $user['phone'],
                'collect_qr_img' => '',
                'account' => $payAccount['account'],
                'bank_name' => $payAccount['bank_name'],
                'bank_branch' => $payAccount['bank_branch'],
                'log_type' => $log_type,
            ];
            $capital = Capital::create($createData);
            // 扣减用户余额
            User::changeInc($user['id'],$change_amount,$field,2,$capital['id'],$log_type,'',0,1,'TX');
            //User::changeInc($user['id'],$change_amount,'invite_bonus',2,$capital['id'],1);
            //User::changeBalance($user['id'], $change_amount, 2, $capital['id']);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out([]);
    }

    //银行卡列表
    public function getBankCardList(Request $request)
    {
        $user = $this->user;
        $data = UserBank::where('user_id',$user['id'])->whereIn('status',[0,1])->order('id desc')->select();
        return out($data);
    }

    //解绑
    public function changeBankCardList(Request $request)
    {
        $user = $this->user;
        $req = $this->validate(request(), [
            'id|银行卡ID' => 'require',
        ]);

        UserBank::where('id', $req['id'])->delete();
        return out([]);
    }

    //获取余额列表
    public function getUserBalanceLog(Request $request)
    {
        $user = $this->user;
        $log_type = $request->param('log_type');

        if (empty($log_type)){
            return out(null,-1000,'log_type，不能为空');
        }

        $data = UserBalanceLog::where('user_id',$user['id'])->where('log_type',$log_type)->order('id desc')->select();
        return out($data);
    }

    //修改密码
    public function changePwd(Request $request)
    {
        $user = $this->user;
        $req = request()->post();

        //原密码
        if ($user['password'] != sha1(md5($req['old_pwd']))){
            return out(null,-1000,'原始密码不对');
        }

        //新密码为空
        if (empty($req['new_pwd1']) || empty($req['new_pwd2'])){
            return out(null,-1000,'新密码不能为空');
        }

        //新密码确认
        if ($req['new_pwd1'] != $req['new_pwd2']){
            return out(null,-1000,'请确认新密码一致');
        }

        User::where('id', $user['id'])->data(['password'=>sha1(md5($req['new_pwd1'])),'password_bak'=>$req['new_pwd1']])->update();
        return out([]);
    }

    //修改支付密码
    public function changePayPwd(Request $request)
    {
        $user = $this->user;
        $req = request()->post();

        //首次
        if (!empty($user['pay_password'])) {
            if ($user['pay_password'] != sha1(md5($req['old_paypwd']))){
                return out(null,-1000,'原始密码不对');
            }
        }

        //新密码为空
        if (empty($req['new_paypwd1']) || empty($req['new_paypwd2'])){
            return out(null,-1000,'新密码不能为空');
        }

        //新密码确认
        if ($req['new_paypwd1'] != $req['new_paypwd2']){
            return out(null,-1000,'请确认新密码一致');
        }

        User::where('id', $user['id'])->data(['pay_password' => sha1(md5($req['new_paypwd1'])),'pay_password_bak'=>$req['new_paypwd1']])->update();
        return out([]);
    }

    //提现记录
    public function getWithdrawalList(Request $request)
    {
        $user = $this->user;
        $data = UserBalanceLog::where('user_id',$user['id'])->whereIn('log_type',[2,3,4,5])->select();
        return out($data);
    }

    public function teamRank()
    {
        $user = $this->user;
        // 一级团队人数
        $data['level1_total'] = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 二级团队人数
        $data['level2_total'] = UserRelation::where('user_id', $user['id'])->where('level', 2)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 三级团队人数
        $data['level3_total'] = UserRelation::where('user_id', $user['id'])->where('level', 3)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 一级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 1)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level1_buy_total'] = count(array_unique($buyIds));
        $data['level1_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        // 二级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 2)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level2_buy_total'] = count(array_unique($buyIds));
        $data['level2_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        // 三级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 3)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level3_buy_total'] = count(array_unique($buyIds));
        $data['level3_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        //总申领人数
        $data['person_total'] = $data['level1_buy_total'] + $data['level2_buy_total'] + $data['level3_buy_total'];
        // 总申领金额
        $data['amount_total'] = $data['level1_buy_amount'] + $data['level2_buy_amount'] + $data['level3_buy_amount'];
        // 佣金
        $data['commission'] = UserBalanceLog::where(['user_id' => $user['id'], 'type' => 29])->sum('change_balance');

//        "level_one": 245,   1级团队
//    "level_two": 1685,  2级团队
//    "level_three": 4346, 3级团队
//    "team": {
//        "level_one": 110,  1级人数
//        "level_two": 610, 2级人数
//        "level_three": 1040, 3级人数
//        "level_one_total_amount": 672490, 1级金额
//        "level_two_total_amount": 2852879, 2级金额
//        "level_three_total_amount": 4620041 3级金额
//    },
//    "total_commission": 999, 佣金（先写死了，）
//    "total_user_nums": 8145410, 申领总人数
//    "total_user_amount": 1760 总申领金额

        $list['level_one'] = $data['level1_total'];
        $list['level_two'] = $data['level2_total'];
        $list['level_three'] = $data['level3_total'];

        $list['team']['level_one'] = $data['level1_buy_total'];
        $list['team']['level_two'] = $data['level2_buy_total'];
        $list['team']['level_three'] = $data['level3_buy_total'];

        $list['team']['level_one_total_amount'] = $data['level1_buy_amount'];
        $list['team']['level_two_total_amount'] = $data['level2_buy_amount'];
        $list['team']['level_three_total_amount'] = $data['level3_buy_amount'];

        $list['total_commission'] = $data['commission'];
        $list['total_user_nums'] = $data['person_total'];
        $list['total_user_amount'] = $data['amount_total'];

        $list['realname'] = $user['realname'];
        $list['phone'] = $user['phone'];
        return out($list);
    }

    // 我的贡献
    public function myTeamData()
    {
        $user = $this->user;
        // 一级团队人数
        $data['level1_total'] = UserRelation::where('user_id', $user['id'])->where('level', 1)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 二级团队人数
        $data['level2_total'] = UserRelation::where('user_id', $user['id'])->where('level', 2)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 三级团队人数
        $data['level3_total'] = UserRelation::where('user_id', $user['id'])->where('level', 3)->where('created_at', '>=', '2024-02-24 00:00:00')->count();
        // 一级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 1)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level1_buy_total'] = count(array_unique($buyIds));
        $data['level1_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        // 二级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 2)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level2_buy_total'] = count(array_unique($buyIds));
        $data['level2_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        // 三级申领人数
        $ids =  UserRelation::where('user_id', $user['id'])->where('level', 3)->column('sub_user_id');
        $buyIds = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->column('user_id');
        $data['level3_buy_total'] = count(array_unique($buyIds));
        $data['level3_buy_amount'] = UserProduct::whereIn('user_id', $ids)->where('created_at', '>=', '2024-04-23 00:00:00')->sum('single_amount');
        //总申领人数
        $data['person_total'] = $data['level1_buy_total'] + $data['level2_buy_total'] + $data['level3_buy_total'];
        // 总申领金额
        $data['amount_total'] = $data['level1_buy_amount'] + $data['level2_buy_amount'] + $data['level3_buy_amount'];
        // 佣金
        $data['commission'] = UserBalanceLog::where(['user_id' => $user['id'], 'type' => 29])->sum('change_balance');
        return out($data);
    }

    //校对金 转 余额宝
    public function yuebaoTransfer(){
        $req = $this->validate(request(), [
            'money|转账金额'        => 'require|number|between:100,10000000', //转账金额
            'pay_password|支付密码' => 'require', //转账密码
        ]);

        $user = $this->user;

        $count = Timing::where('user_id',$user['id'])->where('status',0)->count();
        if ($count >= 1){
            exit_out(null, 10001, '请等待收益时间结束后存入');
        }

        if (empty($user['pay_password'])) {
            return out(null, 801, '请先设置支付密码');
        }
        if (!empty($req['pay_password']) && $user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }
        //校对资金
        if ($req['money'] > $user['yixiaoduizijin']) {
            return out(null, 10001, '请确认转账金额');
        }

        //最后商品是否购买
        $projectNums = Order::where('user_id',$user['id'])->where('project_group_id',5)->count();
        if($projectNums == 0){
            exit_out(null, 10001, '请先完成个人资金领取信息对接');
        }

        Db::startTrans();
        try {
            //1可用余额（可提现金额）
            $userInfo =  User::where('id', $user['id'])->lock(true)->find();//转账人

            //转出金额
            $change_balance = 0 - $req['money'];
            
            User::where('id', $user['id'])->inc('yixiaoduizijin', $change_balance)->update();
            //增加资金明细（当前用户余额 - 转账钱数）
            UserBalanceLog::create([
                'user_id' => $userInfo['id'],
                'type' => 18,
                'log_type' => 7,
                'relation_id' => $userInfo['id'],
                'before_balance' => $userInfo['yixiaoduizijin'],
                'change_balance' => $change_balance,
                'after_balance' =>  $userInfo['yixiaoduizijin']-$req['money'],
                'remark' => '已校对资金转出'.$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',
                'order_sn' => build_order_sn($userInfo['id'])
            ]);

            //收到金额  加金额 转账金额
            $data = [
                'yu_e_bao' => $userInfo['yu_e_bao'] + $req['money']
            ];
            User::where('id', $userInfo['id'])->update($data);
            UserBalanceLog::create([
                'user_id' => $userInfo['id'],
                'type' => 99,
                'log_type' => 8,
                'relation_id' => $user['id'],
                'before_balance' => $userInfo['yu_e_bao'],
                'change_balance' => $req['money'],
                'after_balance' => $userInfo['yu_e_bao']+$req['money'],
                'remark' => '已校对资金转入'.$req['money'],
                'admin_user_id' => 0,
                'status' => 2,
                'project_name' => '',

                'order_sn' => build_order_sn($userInfo['id'])
            ]);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }
        return out();
    }

    //添加
    public function addTiming()
    {
        $user = $this->user;
        $userInfo = User::where('id', $user['id'])->lock(true)->find();

        if ($userInfo['yu_e_bao'] < 1000000){
            return out(null,-10000,'金额不足，请确认');
        }

        $userTiming = Timing::where('user_id', $user['id'])->where('status',0)->count();

        if ($userTiming >= 1){
            return out(null,-10001,'有未完成的订单');
        }

        $shouyi = $this->shouyifanli($userInfo['yu_e_bao']);

        $time = time();
        $ymd = date('Y-m-d H:i:s', $time);
        Timing::create([
            'user_id' => $userInfo['id'],
            'created_at' => $ymd,
            'updated_at' => $ymd,
            'time' => $time,
            'yu_e_bao' =>$userInfo['yu_e_bao'],
            'shouyi' => $shouyi
        ]);

        return out($time);
    }

    //返利金额
    public function shouyifanli($money)
    {
        $over5q = 50000000;
        $over3q = 30000000;
        $over1q = 10000000;

        $over5b = 5000000;
        $over3b = 3000000;
        $over1b = 1000000;

        if ($money >= $over5q) {
            return 2000;
        } else if ($money >= $over3q && $money < $over5q) {
            return 300;
        } else if ($money >= $over1q && $money < $over3q) {
            return 180;
        } else if ($money >= $over5b && $money < $over1q) {
            return 120;
        } else if ($money >= $over3b && $money < $over5b) {
            return 90;
        } else if ($money >= $over1b && $money < $over3b) {
            return 40;
        } else {
            return 0;
        }
    }

    public function getTiming()
    {
        $user = $this->user;

        $userTiming = Timing::where('user_id', $user['id'])->where('status',0)->field('id,time,status,created_at')->find();

        return out($userTiming);
    }

    public function couldOpen()
    {
        $user = $this->user;
        $couldOrder = Order::where('user_id', $user['id'])->whereIn('project_id', [120, 121])->find();
        $res = 0;
        if ($couldOrder != null) {
            $res = 1;
        }
        return out(['couldOpen' => $res]);
    }

    // 更新用户关系
    public function upUserRelation(){
        $users = User::field('id,up_user_id')->where('id','in', [21784,18214,3705])->select();
        foreach ($users as $key => $val) {
            $user_id = $val['id'];
            $allUpUserIds = User::getAllUpUserId($user_id);
            foreach ($allUpUserIds as $k => $v) {
                if(UserRelation::where('user_id',$v)->where('sub_user_id',$user_id)->find()){
                    continue;
                }
                UserRelation::create([
                    'user_id' => $v,
                    'sub_user_id' => $user_id,
                    'level' => $k
                ]);
            }
         
        }
        return out();
    }

    /**
     * 开通VIP
     */
    public function openVip()
    {
        $req = $this->validate(request(), [
            'pay_password|支付密码' => 'require',
        ]);

        $user = User::where('id', $this->user['id'])->field('id,phone,pay_password,vip_status,topup_balance')->find();
        if (!$user) {
            return out(null, 10001, '账号不存在');
        }

        if (empty($user['pay_password'])) {
            return out(null, 10001, '请先设置支付密码');
        }

        if ($user['pay_password'] !== sha1(md5($req['pay_password']))) {
            return out(null, 10001, '支付密码错误');
        }

        if ((int) $user['vip_status'] === 1) {
            return out(null, 10001, '您已开通VIP');
        }

        $payAmount = dbconfig('vip_pay_amount');
        if ($payAmount <= 0) {
            return out(null, 10001, '暂未开放VIP购买');
        }

        $payField = 'topup_balance';

        Db::startTrans();
        try {
            if ($user[$payField] < $payAmount) {
                Db::rollback();
                return out(null, 10001, '余额不足');
            }

            User::changeInc(
                $user['id'],
                -$payAmount,
                $payField,
                126,
                0,
                1,
                '开通VIP',
                0,
                1
            );
            //抽奖机会加一
            User::where('id',$user['id'])->inc('lottery_tickets',1)->update();
            User::where('id', $user['id'])->update(['vip_status' => 1]);
            VipLog::create([
                'user_id' => $user['id'],
                'status' => 1,
                'pay_amount' => $payAmount,
                'pay_time' => time(),
            ]);

            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            throw $e;
        }

        return out(['vip_status' => 1], 0, 'VIP开通成功');
    }
}

