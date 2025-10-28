<?php

namespace app\admin\controller;

use app\model\NoticeMessage;
use app\model\NoticeMessageUser;
use app\model\Project;
use app\model\User;
use think\facade\Cache;
use think\facade\Db;

class ProjectController extends AuthController
{
    /**
     * 处理周期返利数据格式转换
     * @param array $data 原始数据
     * @return array|null 转换后的数据
     */
    private function processReturnData($data)
    {
        if (empty($data)) {
            return null;
        }
        
        $result = [];
        $daysData = [];
        $amountData = [];
        
        // 检查是否是新的格式（days_ 和钱包类型前缀）
        $hasNewFormat = false;
        $walletTypes = ['zhenxing_wallet', 'puhui', 'gongfu_amount', 'minsheng_amount'];
        foreach ($data as $key => $value) {
            if (strpos($key, 'days_') === 0) {
                $hasNewFormat = true;
                break;
            }
            // 检查是否是钱包类型字段
            foreach ($walletTypes as $walletType) {
                if (strpos($key, $walletType . '_') === 0) {
                    $hasNewFormat = true;
                    break 2;
                }
            }
        }
        
        if ($hasNewFormat) {
            // 新格式：days_ 和各个钱包类型前缀
            $daysData = [];
            $walletTypes = ['zhenxing_wallet', 'puhui', 'gongfu_amount', 'minsheng_amount'];
            $walletData = [];
            
            foreach ($data as $key => $value) {
                if (strpos($key, 'days_') === 0) {
                    $daysData[$key] = $value;
                } else {
                    // 检查是否是钱包类型字段
                    foreach ($walletTypes as $walletType) {
                        if (strpos($key, $walletType . '_') === 0) {
                            $walletData[$key] = $value;
                            break;
                        }
                    }
                }
            }
            
            // 匹配天数和各个钱包金额，转换为新格式
            $index = 0;
            foreach ($daysData as $daysKey => $days) {
                if (empty($days)) continue;
                
                $timestamp = str_replace('days_', '', $daysKey);
                $record = ['day' => (int)$days];
                
                // 收集该记录的所有钱包金额
                foreach ($walletTypes as $walletType) {
                    $walletKey = $walletType . '_' . $timestamp;
                    if (isset($walletData[$walletKey]) && !empty($walletData[$walletKey])) {
                        $record[$walletType] = (float)$walletData[$walletKey];
                    }
                }
                
                // 只有当至少有一个钱包金额不为空时才添加记录
                if (count($record) > 1) { // 除了day字段外还有其他字段
                    $result[$index] = $record;
                    $index++;
                }
            }
        } else {
            // 旧格式：直接是天数和金额的键值对，转换为新格式
            $index = 0;
            foreach ($data as $key => $value) {
                if (is_numeric($key) && is_numeric($value)) {
                    $result[$index] = [
                        'day' => (int)$key,
                        'huimin' => (float)$value
                    ];
                    $index++;
                }
            }
        }
        
        return !empty($result) ? $result : null;
    }
    
    public function projectList()
    {
        $req = request()->param();

        $builder = Project::order(['sort' => 'desc', 'id' => 'desc']);
        if (isset($req['project_id']) && $req['project_id'] !== '') {
            $builder->where('id', $req['project_id']);
        }
        if (isset($req['project_group_id']) && $req['project_group_id'] !== '') {
            $builder->where('project_group_id', $req['project_group_id']);
        }
        if (isset($req['name']) && $req['name'] !== '') {
            $builder->where('name', $req['name']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }
        if (isset($req['is_recommend']) && $req['is_recommend'] !== '') {
            $builder->where('is_recommend', $req['is_recommend']);
        }
        if (isset($req['class']) && $req['class'] !== '') {
            $builder->where('class', $req['class']);
        }
        $builder->where('project_group_id' ,'<>',17);
        $data = $builder->paginate(['query' => $req]);
        $groups = config('map.project.group');
        $this->assign('groups',$groups);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showProject()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = Project::where('id', $req['id'])->find();
        }
        //赠送项目
        $give = Project::select();
        if(!empty($data['give'])){
            $data['give'] = json_decode($data['give'],true);
        }
        $groups = config('map.project.group');
        $this->assign('groups',$groups);
        $this->assign('give',$give);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addProject()
    {
        $req = $this->validate(request(), [
            'project_group_id|项目分组ID' => 'require|integer',
            'name|项目名称' => 'require|max:100',
            // 'name_background|项目背景名称' => 'max:100',
            'single_amount|单份金额' => 'float',
            // 'min_amount|最小购买金额' => 'float',
            // 'max_amount|最大购买金额' => 'float',
            // 'daily_bonus_ratio|单份日分红金额' => 'float',
            // 'dividend_cycle|分红周期' => 'max:32',
            'status|状态' => 'require|integer',
            'period|周期' => 'number',
            'is_recommend|是否推荐' => 'require|integer',
            'support_pay_methods|支付方式' => 'require|max:100',
            'sort|排序号' => 'integer',
            'sum_amount|总补贴金额' => 'float',
            'gongfu_amount|共富金' => 'float',
            'gongfu_right_now|共富金-立即发放' => 'float',
            'minsheng_amount|民生金' => 'float',
            'zhenxing_wallet|振兴钱包' => 'float',
            'zhenxing_right_now|振兴金-立即发放' => 'float',
            'puhui|普惠金' => 'float',
            'purchase_limit_per_user|每人限购数量' => 'integer|>=:0',
            'rebate_rate|返佣比例' => 'float',
            // 'virtually_progress|虚拟进度' => 'integer',
            'total_quota|总名额' => 'max:32',
            'remaining_quota|剩余名额' => 'max:32',
            'total_stock|总份额' => 'integer|>=:0',
            'remaining_stock|剩余份额' => 'integer|>=:0',
            // 'quota_level|限购等级' => 'max:32',
            'sale_status|销售状态' => 'max:32',
            'is_daily_bonus|是否每日返利' => 'max:32',
            'daily_bonus_ratio|每日返利比例' => 'max:32',
            'sale_time|预售时间' => 'max:32',
            'open_date|发行开始日' => 'max:32',
            'end_date|发行开始日' => 'max:32',
            'year_income|年收益' => 'float',
            'huimin_days_return|惠民金周期返利' => 'array',
        ]);
        if(Cache::get('project_addProject')){
            return out(null, 10001, '操作过于频繁，请稍后再试');
        }
        Cache::set('project_addProject', 1, 5);
        $req['intro'] = request()->param('intro', '');
        $methods = explode(',', $req['support_pay_methods']);
        $req['support_pay_methods'] = json_encode($methods);
        if(empty($req['sale_time'])) {
            $req['sale_time'] = null;
        }
        if(empty($req['open_date'])) {
            $req['open_date'] = '';
        }
        if(empty($req['end_date'])) {
            $req['end_date'] = '';
        }
        if(isset($req['is_daily_bonus']) && $req['is_daily_bonus'] == 1){
            $req['rebate_rate'] = 0;
        }else{
            $req['daily_bonus_ratio'] = 0;
        }
        
        // 处理惠民金周期返利数据
        $req['huimin_days_return'] = $this->processReturnData($req['huimin_days_return'] ?? []);
        
        $req['cover_img'] = upload_file('cover_img');
        // $req['details_img'] = upload_file('details_img');
        $req['details_img'] = '';
        $project = Project::create($req);

        return out(['id' => $project->id]);
    }

    public function editProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'project_group_id|项目分组ID' => 'require|integer',
            'name|项目名称' => 'require|max:100',
            // 'name_background|项目背景名称' => 'max:100',
            'single_amount|单份金额' => 'float',
            'status|状态' => 'require|integer',
            'is_daily_bonus|是否每日返利' => 'max:32',
            'daily_bonus_ratio|每日返利比例' => 'max:32',
            // 'min_amount|最小购买金额' => 'float',
            // 'max_amount|最大购买金额' => 'float',
            // 'daily_bonus_ratio|单份日分红金额' => 'float',
            'dividend_cycle|返现次数' => 'max:32',
            'period|周期' => 'number',
            // 'single_gift_digital_yuan|单份赠送国家津贴' => 'integer',
            'is_recommend|是否推荐' => 'require|integer',
            'support_pay_methods|支持的支付方式' => 'require|max:100',
            'sort|排序号' => 'integer',
            'sum_amount|总补贴金额' => 'float',
            'gongfu_amount|共富金' => 'float',
            'gongfu_right_now|共富金-立即发放' => 'float',
            'minsheng_amount|民生金' => 'float',
            'zhenxing_wallet|振兴钱包' => 'float',
            'zhenxing_right_now|振兴金-立即发放' => 'float',
            'puhui|普惠金' => 'float',
            'purchase_limit_per_user|每人限购数量' => 'integer|>=:0',
            'rebate_rate|返佣比例' => 'float',
            // 'virtually_progress|虚拟进度' => 'integer',
            'total_quota|总名额' => 'max:32',
            'remaining_quota|剩余名额' => 'max:32',
            'total_stock|总份额' => 'integer|>=:0',
            'remaining_stock|剩余份额' => 'integer|>=:0',
            // 'quota_level|限购等级' => 'max:32',
            'sale_status|销售状态' => 'max:32',
            'sale_time|预售时间' => 'max:32',
            'open_date|发行开始日' => 'max:32',
            'end_date|发行开始日' => 'max:32',
            'year_income|年收益' => 'float',
            'huimin_days_return|惠民金周期返利' => 'array',
        ]);
        $req['intro'] = request()->param('intro', '');
        $methods = explode(',', $req['support_pay_methods']);
        $req['support_pay_methods'] = json_encode($methods);

        if(empty($req['sale_time'])) {
            $req['sale_time'] = null;
        }
        if(empty($req['open_date'])) {
            $req['open_date'] = '';
        }
        if(empty($req['end_date'])) {
            $req['end_date'] = '';
        }
        if(isset($req['is_daily_bonus']) && $req['is_daily_bonus'] == 1){
            $req['rebate_rate'] = 0;
        }else{
            $req['daily_bonus_ratio'] = 0;
        }
        
        // 处理惠民金周期返利数据
        $req['huimin_days_return'] = $this->processReturnData($req['huimin_days_return'] ?? []);
        
        if ($img = upload_file('cover_img', false,false)) {
            $req['cover_img'] = $img;
        }
        // if($img = upload_file('details_img', false,false)){
        //     $req['details_img'] = $img;
        // }
        Project::where('id', $req['id'])->update($req);

        return out(['id' => $req['id']]);
    }

    public function changeProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        Project::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    /**
     * 更新项目剩余份额
     */
    public function updateStock()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'remaining_stock' => 'require|number|>=:0',
            'percentage' => 'require|float|>=:0|<=:100'
        ]);

        // 获取项目信息
        $project = Project::where('id', $req['id'])->find();
        if (!$project) {
            return out(null, 10001, '项目不存在');
        }

        // 验证剩余份额不能大于总份额
        if (isset($project['total_stock']) && $req['remaining_stock'] > $project['total_stock']) {
            return out(null, 10002, '剩余份额不能大于总份额');
        }

        // 更新剩余份额
        Project::where('id', $req['id'])->update([
            'remaining_stock' => $req['remaining_stock']
        ]);

        return out([
            'id' => $req['id'],
            'remaining_stock' => $req['remaining_stock'],
            'total_stock' => $project['total_stock']
        ]);
    }

    public function delProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        Project::destroy($req['id']);

        return out();
    }

    /**
     * 发送项目通知
     */
    public function sendProjectNotice()
    {
        $req = $this->validate(request(), [
            'project_id' => 'require|number',
            'project_name' => 'require|max:100'
        ]);
        Db::startTrans();
        try {
            // 获取所有用户ID
            $userIds = User::where('status', 1)->column('id');
            
            // 创建消息
            $message = NoticeMessage::create([
                'title' => '新项目上线通知',
                'content' => "新项目【{$req['project_name']}】已上线，请及时查看！",
                'type' => NoticeMessage::TYPE_SYSTEM,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            // 为每个用户创建消息记录
            $data = [];
            foreach ($userIds as $userId) {
                $data[] = [
                    'user_id' => $userId,
                    'message_id' => $message->id,
                    'is_read' => 0,
                    'read_time' => null
                ];
            }
            
            // 批量插入消息记录
            NoticeMessageUser::insertAll($data);
            Db::commit();
            return out(['message_id' => $message->id]);
        } catch (\Exception $e) {
            Db::rollback();
            return out(null, 10001, '发送通知失败：' . $e->getMessage());
        }
    }

    /**
     * 获取项目详情（用于弹框显示）
     */
    public function getProjectDetail()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        $project = Project::find($req['id']);
        if (!$project) {
            return out(null, 10001, '项目不存在');
        }

        // 处理惠民金周期返利数据
        $huiminData = null;
        if (!empty($project['huimin_days_return'])) {
            // 检查是否已经是数组，如果是字符串则解析JSON
            if (is_string($project['huimin_days_return'])) {
                $huiminData = json_decode($project['huimin_days_return'], true);
            } else {
                $huiminData = $project['huimin_days_return'];
            }
        }

        $project['huimin_days_return'] = $huiminData;

        return out($project);
    }
}
