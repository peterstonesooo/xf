<?php

namespace app\api\controller;

use app\api\controller\AuthController;
use app\model\Vote;
use app\model\VoteRecord;
use app\model\User;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Request;

class VoteController extends AuthController
{
    /**
     * 报名接口 - 国家幸福大使评选
     */
    public function register()
    {
        try {
            $user = $this->user;
            $uid = $user['id'];
            $phone = $user['phone'];
            $realname = $user['realname'];
            
            // 参数验证
            if (empty($phone) || empty($realname)) {
                return out('', 0, '请先完善个人信息');
            }
            
            // 检查是否已报名
            if (Vote::isUserRegistered($uid)) {
                return out('', 0, '您已经报名过了');
            }

            $register_count = User::where('up_user_id', $uid)->where('shiming_status', 1)->count();
            if($register_count < 10 ){
                return out('', 0, '您当前直推实名人数不足10人');
            }
            
            // 创建投票记录（报名）
            $voteData = [
                'uid' => $uid,
                'phone' => $phone,
                'realname' => $realname,
                'title' => '国家幸福大使评选',
                'content' => '国家幸福大使评选活动报名',
                'vote_type' => 1, // 单选
                'options' => [
                    ['id' => 1, 'text' => '支持', 'votes' => 0],
                    ['id' => 2, 'text' => '不支持', 'votes' => 0]
                ], // 直接传递数组，模型会自动处理JSON编码
                'status' => 1,
                'is_anonymous' => 0,
                'max_votes' => 1,
                'start_time' => date('Y-m-d H:i:s'),
                'end_time' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'total_votes' => 0,
                'view_count' => 0,
                'is_deleted' => 0
            ];
            
            $vote = Vote::create($voteData);
            
            if ($vote) {
                return out(['vote_id' => $vote->id], 1, '报名成功');
            } else {
                return out('', 0, '报名失败');
            }
            
        } catch (\Exception $e) {
            return out('', 0, '系统错误：' . $e->getMessage());
        }
    }
    
    /**
     * 投票列表接口（带搜索功能）
     */
    public function list()
    {
        try {
            $page = input('page', 1);
            $limit = input('limit', 10);
            $keyword = input('keyword', ''); // 搜索关键词（手机号或姓名）
            
            $where = [
                ['is_deleted', '=', 0],
                ['status', '=', 1]
            ];
            
            // 搜索条件
            if (!empty($keyword)) {
                $where[] = ['phone|realname', 'like', '%' . $keyword . '%'];
            }
            
            $list = Vote::where($where)
                ->field('id,uid,phone,realname,title,content,total_votes,view_count,create_time')
                ->order('total_votes desc')
                ->paginate([
                    'list_rows' => $limit,
                    'page' => $page
                ]);
            
            // 对返回的数据进行加密处理
            $items = $list->items();
            foreach ($items as &$item) {
                $item['phone'] = $this->encryptPhone($item['phone']);
                $item['realname'] = $this->encryptRealname($item['realname']);
            }
            
            return out([
                'list' => $items,
                'total' => $list->total(),
                'page' => $page,
                'limit' => $limit
            ]);
            
        } catch (\Exception $e) {
            return out('', 0, '系统错误：' . $e->getMessage());
        }
    }
    
    /**
     * 投票接口（消耗投票票数，每人限一次）
     */
    public function vote()
    {
        try {
            $user = $this->user;
            $uid = $user['id'];
            $voteId = input('vote_id', 0);
            $selectedOption = 1; // 默认支持选项1
            
            // 参数验证
            if (empty($voteId)) {
                return out('', 0, '投票ID不能为空');
            }
            
            // 获取投票信息
            $vote = Vote::find($voteId);
            if (!$vote) {
                return out('', 0, '投票不存在');
            }
            
            // 检查投票状态
            // if ($vote->status != 1) {
            //     return out('', 0, '投票已关闭');
            // }
            
            // 检查投票时间
            // $now = date('Y-m-d H:i:s');
            // if ($vote->start_time && $now < $vote->start_time) {
            //     return out('', 0, '投票尚未开始');
            // }
            // if ($vote->end_time && $now > $vote->end_time) {
            //     return out('', 0, '投票已结束');
            // }
            
            // 移除重复投票限制，允许用户多次投票
            
            // 检查用户投票票数
            if (!isset($user['vote_tickets']) || $user['vote_tickets'] <= 0) {
                return out('', 0, '您的投票数量不足，请继续获取投票机会');
            }
            
            // 获取选项数据用于更新统计
            $options = $vote->options; // 已经是数组，不需要json_decode
            
            // 安全检查：确保options是数组
            if (!is_array($options)) {
                // 如果是对象，尝试转换为数组
                if (is_object($options)) {
                    $options = (array) $options;
                } else {
                    return out('', 0, '投票选项数据格式错误：' . gettype($options));
                }
            }
            
            // 由于我们固定选择选项1（支持），直接跳过选项验证
            // 如果选项数据有问题，在更新统计时会处理
            
            // 开始事务
            Db::startTrans();
            try {
                // 消耗投票票数
                User::changeInc($uid, -1, 'vote_tickets', 122, $voteId, 15, '投票消耗票数', 0, 2, 'VT');
                
                // 创建投票记录
                $recordData = [
                    'vote_id' => $voteId,
                    'uid' => $uid,
                    'phone' => $user['phone'] ?? '',
                    'realname' => $user['realname'] ?? '',
                    'selected_options' => [$selectedOption], // 直接传递数组，模型会自动处理JSON编码
                    'ip_address' => Request::ip(),
                    'user_agent' => Request::header('user-agent', ''),
                    'create_time' => date('Y-m-d H:i:s')
                ];
                
                VoteRecord::create($recordData);
                
                // 更新投票统计
                $vote->total_votes += 1;
                $vote->save();
                
                // 更新选项投票数
                $newOptions = [];
                $optionUpdated = false;
                foreach ($options as $option) {
                    // 安全检查：确保option是数组且有id字段
                    if (is_array($option) && isset($option['id'])) {
                        if ($option['id'] == $selectedOption) {
                            $option['votes'] = ($option['votes'] ?? 0) + 1;
                            $optionUpdated = true;
                        }
                        $newOptions[] = $option;
                    }
                }
                
                // 如果选项数据有问题，创建默认的选项结构
                if (!$optionUpdated) {
                    $newOptions = [
                        ['id' => 1, 'text' => '支持', 'votes' => 1],
                        ['id' => 2, 'text' => '不支持', 'votes' => 0]
                    ];
                }
                
                $vote->options = $newOptions; // 直接赋值数组，模型会自动处理JSON编码
                $vote->save();
                
                Db::commit();
                
                return out(['vote_id' => $voteId], 1, '感谢您宝贵的一票！您的支持已被国家真实记录，每一份信任都将汇聚成推动幸福中国的力量。');
                
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return out('', 0, '系统错误：' . $e->getMessage());
        }
    }
    
    /**
     * 获取投票详情
     */
    public function detail()
    {
        try {
            $voteId = input('vote_id', 0);
            
            if (empty($voteId)) {
                return out('', 0, '参数不能为空');
            }
            
            $vote = Vote::where('id', $voteId)
                ->where('is_deleted', 0)
                ->field('id,uid,phone,realname,title,content,options,total_votes,view_count,start_time,end_time,create_time')
                ->find();
            
            if (!$vote) {
                return out('', 0, '投票不存在');
            }
            
            // 增加浏览次数
            $vote->view_count += 1;
            $vote->save();
            
            return out($vote, 1, '获取成功');
            
        } catch (\Exception $e) {
            return out('', 0, '系统错误：' . $e->getMessage());
        }
    }
    
    /**
     * 检查用户投票状态
     */
    public function checkVoteStatus()
    {
        try {
            $user = $this->user;
            $uid = $user['id'];
            $voteId = input('vote_id', 0);
            
            if (empty($voteId)) {
                return out('', 0, '投票ID不能为空');
            }
            
            $data = [
                'can_vote' => true,
                'reason' => '',
                'vote_tickets' => $user['vote_tickets'] ?? 0
            ];
            
            // 检查投票票数
            if (!isset($user['vote_tickets']) || $user['vote_tickets'] <= 0) {
                $data['can_vote'] = false;
                $data['reason'] = '投票票数不足';
            }
            
            return out($data, 1, '获取成功');
            
        } catch (\Exception $e) {
            return out('', 0, '系统错误：' . $e->getMessage());
        }
    }
    
    /**
     * 获取用户投票票数
     */
    public function getUserTickets()
    {
        try {
            $user = $this->user;
            
            return out([
                'vote_tickets' => $user['vote_tickets'] ?? 0,
                'uid' => $user['id']
            ], 1, '获取成功');
            
        } catch (\Exception $e) {
            return out('', 0, '系统错误：' . $e->getMessage());
        }
    }
    
    /**
     * 调试接口 - 查看投票数据格式
     */
    public function debugVote()
    {
        try {
            $voteId = input('vote_id', 0);
            
            if (empty($voteId)) {
                return out('', 0, '投票ID不能为空');
            }
            
            $vote = Vote::find($voteId);
            if (!$vote) {
                return out('', 0, '投票不存在');
            }
            
            return out([
                'vote_id' => $vote->id,
                'options_raw' => $vote->getData('options'), // 原始数据
                'options_parsed' => $vote->options, // 解析后的数据
                'options_type' => gettype($vote->options),
                'options_count' => is_array($vote->options) ? count($vote->options) : 'not_array'
            ], 1, '调试信息');
            
        } catch (\Exception $e) {
            return out('', 0, '系统错误：' . $e->getMessage());
        }
    }
    
    /**
     * 加密手机号 - 中间几位用*替换
     * @param string $phone 手机号
     * @return string 加密后的手机号
     */
    private function encryptPhone($phone)
    {
        if (empty($phone) || strlen($phone) < 7) {
            return $phone;
        }
        
        $length = strlen($phone);
        if ($length == 11) {
            // 标准11位手机号：138****1234
            return substr($phone, 0, 3) . '****' . substr($phone, -4);
        } elseif ($length == 10) {
            // 10位手机号：138***1234
            return substr($phone, 0, 3) . '***' . substr($phone, -4);
        } else {
            // 其他长度的号码：保留前3位和后2位，中间用*替换
            $showLength = min(3, $length - 2);
            $hideLength = $length - $showLength - 2;
            $stars = str_repeat('*', $hideLength);
            return substr($phone, 0, $showLength) . $stars . substr($phone, -2);
        }
    }
    
    /**
     * 加密真实姓名 - 中间文字用*替换
     * @param string $realname 真实姓名
     * @return string 加密后的姓名
     */
    private function encryptRealname($realname)
    {
        if (empty($realname) || mb_strlen($realname, 'UTF-8') < 2) {
            return $realname;
        }
        
        $length = mb_strlen($realname, 'UTF-8');
        
        if ($length == 2) {
            // 2个字符：张*
            return mb_substr($realname, 0, 1, 'UTF-8') . '*';
        } elseif ($length == 3) {
            // 3个字符：张*明
            return mb_substr($realname, 0, 1, 'UTF-8') . '*' . mb_substr($realname, -1, 1, 'UTF-8');
        } elseif ($length >= 4) {
            // 4个字符及以上：张**明 或 张***明
            $stars = str_repeat('*', $length - 2);
            return mb_substr($realname, 0, 1, 'UTF-8') . $stars . mb_substr($realname, -1, 1, 'UTF-8');
        }
        
        return $realname;
    }
}
