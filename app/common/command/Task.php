<?php

namespace app\common\command;

use app\model\Apply;
use app\model\AssetOrder;
use app\model\Capital;
use app\model\CertificateTrans;
use app\model\EnsureOrder;
use app\model\FamilyChild;
use app\model\Order;
use app\model\PassiveIncomeRecord;
use app\model\RelationshipRewardLog;
use app\model\ShopOrder;
use app\model\User;
use app\model\UserBalanceLog;
use app\model\UserDelivery;
use app\model\UserRelation;
use DateInterval;
use DatePeriod;
use DateTime;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

use Exception;
use think\facade\Filesystem;
use think\facade\Log;
use think\File;

class Task extends Command
{
    protected function configure()
    {
        $this->setName('task')->setDescription('测试脚本');
    }

    public function aaa($user_id, $data = []) 
    {
        $fa = User::whereIn('up_user_id',$user_id)->column('id');
        if(!empty($fa)) {
            $data = array_merge($fa, $data);
            return $this->aaa($fa, $data);
        }
        
        return $data;

    }

    public function execute(Input $input, Output $output)
    {
        ini_set ("memory_limit","-1");
        set_time_limit(0);


        // $data = UserBalanceLog::where('type', 105)->select();
        // foreach ($data as $key => $value) {
        //     //User::changeInc($value['user_id'], abs($value['change_balance']), 'private_bank_balance', 69, $value->id, 1, '风控止付');
        //     $user = User::where('id', $value['user_id'])->find();
        //     if($user['buzhujin'] >= $value['change_balance']) {
        //          $aa = User::where('id', $value['user_id'])->dec('buzhujin', $value['change_balance'])->update();
        //          if($aa) {
        //              UserBalanceLog::where('id', $value['id'])->delete();
        //          }
        //     } else {
        //          file_put_contents('6666.txt', $value['id'].PHP_EOL, FILE_APPEND);
        //     }
 
         
        // }

        //  exit;


        $startDate = '2024-11-01'; // 指定开始日期
        $daysToGenerate = 47; // 生成日期的数量
        
        $date = new DateTime($startDate);
        $dateInterval = new DateInterval('P1D'); // 每天
        $datePeriod = new DatePeriod($date, $dateInterval, $daysToGenerate);
        
        $list = [];
        foreach ($datePeriod as $day) {
            $amount = round(Capital::where('status', 2)->where('type', 1)->where('created_at', '>=', $day->format('Y-m-d')." 00:00:00")->where('created_at', '<=', $day->format('Y-m-d')." 23:59:59")->sum('amount'), 2);
            $list[] = [
                'day' => $day->format('Y-m-d'),
                'amount' => $amount,
            ];
        }

        create_excel_file($list, [
            'day' => '日期',
            'amount' => '充值金额',
        ], '111111');
        exit;


        // $data =  Capital::where('id', 620942)->where('type',2)->where('status', 3)->whereTime('created_at','today')->where('log_type',9)->select();

        // foreach ($data as $v){
        //     $userInfo = User::where('id',$v['user_id'])->find();
        //     $amount = 0 - $v['amount'];
        //     // $update = [
        //     //     // 生育补贴 - 提现
        //     //     'shengyu_butie_balance' => $userInfo['shengyu_butie_balance'] - $amount,
        //     //     // 补助金 + 提现
        //     //     //'buzhujin' => $userInfo['buzhujin'] + $amount,
        //     // ];
        //     // User::where('id',$v['user_id'])->update($update);

        //     //生成日志
        //     //User::changeInc($v['user_id'], $amount,'buzhujin',102, $v['id'],9,'提现失败',0,1,'TX');
        //     User::changeInc($v['user_id'], $amount, 'buzhujin', 13, $v['id'], 9, '审核拒绝');
        // }
        // exit;

        $list = User::select();
        $data = [];
        foreach ($list as $key => $value) {
            $order = Order::where('user_id', $value['id'])->where('project_group_id', 5)->find();
            $aa = Order::where('user_id', $value['id'])->where('project_group_id', 4)->find();
            if(!$order && $aa) {
                $data[$key] = $value;
            }
        }

       create_excel_file($data, [
           'phone' => '手机号',
           'realname' => '姓名',
       ], '未买最后阶段买了保障系列');

       exit;

        // $data = UserBalanceLog::where('type', 100)->where('log_type', 5)->where('created_at', '>=', '2024-11-25 00:00:00')->select();
        // foreach ($data as $key => $value) {
        //     User::changeInc($value['user_id'],100000,'yixiaoduizijin',100,$value['relation_id'],7);
        // }
        // $dataa = UserBalanceLog::where('type', 17)->where('log_type', 5)->where('created_at', '>=', '2024-11-25 00:00:00')->select();
        // foreach ($dataa as $key => $value) {
        //     User::changeInc($value['user_id'],100,'yixiaoduizijin',17,$value['relation_id'],7);
        // }
        exit;

        // $data = Db::connect('demo')->table('mp_user')->select();
        // foreach ($data as $key => $value) {
        //    User::where('id', $value['id'])->update([
        //     //'shengyu_butie_balance' => $value['income_balance'],
        //     'topup_balance' => $value['poverty_subsidy_amount'],
        //     // 'shengyu_balance' => $value['team_bonus_balance'],
        //     // 'xuanchuan_balance' => $value['balance'],
        //     // 'income_balance' => floatval($value['invite_bonus'] + $value['digital_yuan_amount']),
        //    ]);
        // }




        // $data = FamilyChild::select();
        // foreach ($data as $k => $v) {
        //     if (!empty($v['my']) && strpos($v['my'],'https://www.chire.icu') !== false){
        //         $a = file_get_contents($v['my']);
        //         // 生成临时文件名
        //         $tempFile = tempnam(sys_get_temp_dir(), 'TMP_');
        //         // 写入临时文件
        //         file_put_contents($tempFile, $a);
        //         // 创建ThinkPHP File对象
        //         $file = new File($tempFile);
        //         $savename = Filesystem::disk('qiniu')->putFile('', $file);
        //         $baseUrl = 'http://'.config('filesystem.disks.qiniu.domain').'/';
        //         $c =  $baseUrl.str_replace("\\", "/", $savename);
        //         unlink($tempFile);
        //         FamilyChild::where('id', $v['id'])->update(['my' => $c]);
        //     }

        // }
//        exit;
        
    


        // $data = CertificateTrans::select();
        // Db::startTrans();
        // try {
        //     foreach ($data as $key => $value) {
        //         $user = User::where('id', $value['user_id'])->find();
        //         $pay_amount = 3000;
        //         $relation = UserRelation::where('sub_user_id', $user['id'])->select();
        //         $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
        //         foreach ($relation as $v) {
        //             $reward = round(dbconfig($map[$v['level']])/100*$pay_amount, 2);
        //             if($reward > 0){
        //                 User::changeInc($v['user_id'],$reward,'balance',8,$user['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
        //                 RelationshipRewardLog::insert([
        //                     'uid' => $v['user_id'],
        //                     'reward' => $reward,
        //                     'son' => $user['id'],
        //                     'son_lay' => $v['level'],
        //                     'created_at' => date('Y-m-d H:i:s')
        //                 ]);
        //             }
        //         }
        //         User::where('id',$user['id'])->inc('invest_amount',$pay_amount)->update();
        //         User::upLevel($user['id']);
        //     }
        //     Db::commit();
        // }catch(\Exception $e){
        //     Db::rollback();
        //     echo  $e->getMessage().$e->getLine();
        //     throw $e;
        // }

        // exit;

        // $list = User::where('status', 1)->select();
        // foreach ($list as $v) {
        //     $v->account_type = $v['user']['phone'] ?? '';
        //     $v->address = UserDelivery::where('user_id', $v['id'])->find()['address'] ?? '';
        //     $v->total_num = UserRelation::where('user_id', $v['id'])->count();
        //     $v->active_num = UserRelation::where('user_id', $v['id'])->where('is_active', 1)->count();
        //     $v->charge = round(Capital::where('user_id', $v['id'])->where('status', 2)->where('type', 1)->sum('amount'), 2);
        //     $v->withdrawl = round(0 - Capital::where('user_id', $v['id'])->where('status', 2)->where('type', 2)->sum('amount'), 2);
        // }
        // create_excel_file($list, [
        //     'realname' => '名字',
        //     'phone' => '手机号',
        //     'address' => '地址',
        //     'total_num' => '团队人数',
        //     'active_num' => '团队激活人数',
        //     'charge' => '充值',
        //     'withdrawl' => '提现',
        // ], '记录-' . date('YmdHis'));

        // exit;

        // $data = UserBalanceLog::where('type', 51)->where('created_at', '>', '2024-08-01 00:00:00')->select();
        // Db::startTrans();
        // try {
        //     foreach ($data as $key => $value) {
        //         UserBalanceLog::where('id', $value['id'])->update([
        //             'type' => 62
        //         ]);
        //     }
        //     Db::commit();
        // }catch(\Exception $e){
        //     Db::rollback();
        //     echo  $e->getMessage().$e->getLine();
        //     throw $e;
        // }

        // exit;

        $data = UserBalanceLog::where('type', 64)->select();
        Db::startTrans();
        try {
            foreach ($data as $key => $value) {
                User::changeInc($value['user_id'], abs($value['change_balance']), 'private_bank_balance', 69, $value->id, 1, '风控止付');
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            echo  $e->getMessage().$e->getLine();
            throw $e;
        }

         exit;
        // $order = Order::where('status', 6)->where('project_group_id', 2)->select();
        // foreach ($order as $key => $value) {
        //     $add = bcadd($value['gain_bonus'], $value['all_bonus'], 2);
        //     $money = bcsub($add, $value['checkingAmount'], 2);
        //     User::where('id', $value['user_id'])->inc('all_digit_balance', $money)->update();
        // }
        // exit;
        // UserBalanceLog::where('type', 8)->where('created_at', '2024-05-09 23:05:28')->delete();
        // UserBalanceLog::where('type', 8)->where('created_at', '2024-05-09 23:05:27')->delete();
        // $data = Order::where('project_group_id', 2)->where('sign_img_url', '')->select();
        // foreach ($data as $key => $value) {
        //     $aa = Db::connect('demo')->table('mp_order')->where('id', $value['id'])->find();
        //     if($aa) {
        //         Order::where('id', $value['id'])->update(['sign_img_url' => $aa['sign_img_url']]);
        //     }
        // }

        // exit;



        // $data = UserBalanceLog::where('remark', '7.17参会邀请奖励')->select();
        // Db::startTrans();
        // try {
        //     foreach ($data as $key => $value) {
        //         //User::changeInc($value['id'],$value['amount'],'balance',99,$value['id'],2, $value['remark'],'',1,'IM');
        //         $user = User::where('id', $value['user_id'])->find();
        //         if($user) {
        //             if($value['change_balance'] > $user['balance']) {
        //                 echo $value['change_balance'].'-'.$value['user_id'] . '-' . $user['balance'] . '|||';
        //                 if ($user['balance'] < $value['change_balance']) {
        //                     echo '【'.$value['user_id'].'】';
        //                     continue;
        //                 }
        //                 User::where('id', $value['user_id'])->inc('balance',-$value['change_balance'])->update();
        //             }
        //         }
        //         UserBalanceLog::where('id', $value['id'])->delete();
        //     }
        //     Db::commit();
        // }catch(\Exception $e){
        //     Db::rollback();
        //     echo  $e->getMessage().$e->getLine();
        //     throw $e;
        // }
        // exit;
        // $data = Order::whereIn('project_group_id',[2,3])->where('updated_at', '<=', '2024-05-09 23:05:28')->select();
        // Db::startTrans();
        
        // try {
        //     foreach ($data as $key => $value) {
        //         $relation = UserRelation::where('sub_user_id', $value['user_id'])->select();
        //         $user = User::where('id', $value['user_id'])->find();
        //         $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
        //         foreach ($relation as $v) {
        //             $reward = round(dbconfig($map[$v['level']])/100*$value['price'], 2);
        //             if($reward > 0){
        //                 User::changeInc($v['user_id'],$reward,'balance',8,$value['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
        //                 RelationshipRewardLog::insert([
        //                     'uid' => $v['user_id'],
        //                     'reward' => $reward,
        //                     'son' => $value['user_id'],
        //                     'son_lay' => $v['level'],
        //                     'created_at' => date('Y-m-d H:i:s')
        //                 ]);
        //             }
        //         }
        //     }
        //     Db::commit();
        // }catch(\Exception $e){
        //     Db::rollback();
        //     echo  $e->getMessage().$e->getLine();
        //     throw $e;
        // }

        // $data = User::select();
        // Db::startTrans();
        
        // try {
        //         foreach ($data as $key => $value) {
        //             User::upLevela($value['id']);
        //         }
            //     $user = User::where('id', $value['son'])->find();
            //     if($user['balance'] < $value['reward']) {
            //         file_put_contents('1.txt', $value['son'].PHP_EOL, FILE_APPEND);
            //     } else {
            //         User::where('id', $value['son'])->inc('balance',-$value['reward'])->update();
            //         file_put_contents('2.txt', $value['son'].'--'.$value['reward'].PHP_EOL, FILE_APPEND);
            //     }
                // $relation = UserRelation::where('sub_user_id', $value['user_id'])->select();
                // $user = User::where('id', $value['user_id'])->find();
                // $map = [1 => 'first_team_reward_ratio', 2 => 'second_team_reward_ratio', 3 => 'third_team_reward_ratio'];
                // foreach ($relation as $v) {
                //     $reward = round(dbconfig($map[$v['level']])/100*$value['price'], 2);
                //     if($reward > 0){
                //         User::changeInc($v['user_id'],$reward,'balance',8,$value['id'],2,'团队奖励'.$v['level'].'级'.$user['realname'],0,2,'TD');
                //         RelationshipRewardLog::insert([
                //             'uid' => $v['user_id'],
                //             'reward' => $reward,
                //             'son' => $value['user_id'],
                //             'son_lay' => $v['level'],
                //             'created_at' => date('Y-m-d H:i:s')
                //         ]);
                //     }
                // }
                //}
        //     Db::commit();
        // }catch(\Exception $e){
        //     Db::rollback();
        //     echo  $e->getMessage().$e->getLine();
        //     throw $e;
        // }
    }

    


}
