<?php

namespace app\admin\controller;

use app\model\SyCard;
use app\model\User;
use app\model\UserCardRecord;
use app\model\UserManageLog;

class CardController extends AuthController
{
    public function cardList()
    {
        $req = request()->param();
        $builder = UserCardRecord::order('id', 'desc')->with(['syCard']);
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('user_id', $user_ids);
        }
        if (!empty($req['card_id'])) {
            $builder->where('card_id', $req['card_id']);
        }
        if (!empty($req['start_date'])) {
            $builder->where('created_at', '>=', $req['start_date'].' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('created_at', '<=', $req['end_date'].' 23:59:59');
        }
        $builder1 = clone $builder;
        $total_amount = round($builder1->sum('fee'), 2);
        $this->assign('total_amount', $total_amount);

        $unique_user_num = $builder1->count('distinct user_id');

        if (!empty($req['export'])) {
            ini_set('memory_limit', '2048M');
            set_time_limit(0);
            $list = $builder->select();
            foreach ($list as $v) {
                if (empty($v['user']['realname'])) {
                    $v->user = '';
                } else {
                    $v->user = $v['user']['realname'].'（'.$v['user']['phone'].'）';
                }
                $v->real_amount = round(0 - $v['amount'], 2);
                $v->adminUser = $v['adminUser']['nickname'] ?? '';
                $v->account = empty($v->account) ? $v->phone : $v->account;
                $v->fee = round($v->real_amount * 0.012, 2);
            }
            create_excel($list, [
                'id' => 'ID',
                'user' => '用户',
                'capital_sn' => '单号',
                'withdraw_status_text' => '状态',
                'pay_channel_text' => '支付渠道',
                'real_amount' => '提现金额',
                'withdraw_amount' => '到账金额',
                'fee' => '手续费',
                'realname' => '收款人实名',
                'account' => '收款账号(卡号)',
                'adminUser' => '审核用户',
                'audit_remark' => '拒绝理由',
                'audit_date' => '审核时间',
                'created_at' => '创建时间'], '收益提现列表-'.date('YmdHis'));
        }
        $data = $builder->paginate(['query' => $req, 'list_rows' => $req['list_rows'] ?? 15])->each(function ($item, $key) {
                $item['title'] = $item->syCard['title'] ?? '';
                unset($item->syCard);
        });
        $card = SyCard::field('id,title')->order('id asc')->select()->toArray();

        $this->assign('card', $card);
        $this->assign('unique_user_num', $unique_user_num);
        $this->assign('total', $data->total());
        $this->assign('req', $req);
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function manageList()
    {
        $req = request()->param();
        $builder = UserManageLog::order('id', 'desc');
        if (isset($req['user']) && $req['user'] !== '') {
            $user_ids = User::where('phone', $req['user'])->column('id');
            $user_ids[] = $req['user'];
            $builder->whereIn('user_id', $user_ids);
        }
        if (!empty($req['start_date'])) {
            $builder->where('created_at', '>=', $req['start_date'].' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('created_at', '<=', $req['end_date'].' 23:59:59');
        }
        $builder1 = clone $builder;
        $ids = User::whereIn('phone', [
            '13569975260',
            '13810181213',
            '16521947228',
            '15586298850',
            '18577446091',
            '13846835677',  
            '13639826380',
            '13475074990', 
            '18239128151',  
            '15358235917', 
            '15032178105',   
            '13085107557', 
            '17531944101',   
            '13352467925', 
            '18526076023',
            '13068926713',
        ])->column('id');
        $idArr = User::where('up_user_id', 74440)->column('id');
        $userIds = array_merge($ids, $idArr);
        $userIds = array_values($userIds);
        $total_amount = round($builder1->whereNotIn('user_id', $userIds)->sum('fee'), 2);
        $this->assign('total_amount', $total_amount);

        $data = $builder->paginate(['query' => $req, 'list_rows' => $req['list_rows'] ?? 15])->each(function ($item, $key) {

        });

        $this->assign('total', $data->whereNotIn('user_id', $userIds)->total());
        $this->assign('req', $req);
        $this->assign('data', $data);
        return $this->fetch();
    }

}
