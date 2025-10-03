<?php

namespace app\admin\controller;

use app\model\Vote;
use app\model\User;
use think\facade\Db;

class VoteController extends AuthController
{
    /**
     * 投票管理列表
     */
    public function voteList()
    {
        $req = request()->param();

        $builder = Vote::alias('v')
            ->leftJoin('user u', 'v.uid = u.id')
            ->field('v.*, u.phone as user_phone, u.realname as user_realname');

        // 排序处理
        if (isset($req['sort']) && $req['sort'] !== '') {
            switch ($req['sort']) {
                case 'total_votes_desc':
                    $builder->order('v.total_votes', 'desc');
                    break;
                case 'total_votes_asc':
                    $builder->order('v.total_votes', 'asc');
                    break;
                case 'create_time_desc':
                    $builder->order('v.create_time', 'desc');
                    break;
                case 'create_time_asc':
                    $builder->order('v.create_time', 'asc');
                    break;
                default:
                    $builder->order('v.id', 'desc');
                    break;
            }
        } else {
            $builder->order('v.id', 'desc');
        }

        // 搜索条件
        if (isset($req['uid']) && $req['uid'] !== '') {
            $builder->where('v.uid', $req['uid']);
        }
        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('v.phone', 'like', '%' . $req['phone'] . '%');
        }
        if (isset($req['realname']) && $req['realname'] !== '') {
            $builder->where('v.realname', 'like', '%' . $req['realname'] . '%');
        }
        if (isset($req['title']) && $req['title'] !== '') {
            $builder->where('v.title', 'like', '%' . $req['title'] . '%');
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    /**
     * 显示投票详情/编辑页面
     */
    public function showVote()
    {
        $req = request()->get();
        $data = [];
        
        if (!empty($req['id'])) {
            $data = Vote::alias('v')
                ->leftJoin('user u', 'v.uid = u.id')
                ->field('v.*, u.phone as user_phone, u.realname as user_realname')
                ->where('v.id', $req['id'])
                ->find();
        }

        $this->assign('data', $data);
        return $this->fetch();
    }

    /**
     * 添加投票
     */
    public function addVote()
    {
        $req = request()->post();
        
        $this->validate($req, [
            'uid|用户ID' => 'require|number',
            'phone|手机号' => 'require|max:20',
            'realname|真实姓名' => 'require|max:50',
            'title|投票标题' => 'require|max:200',
            'content|投票内容' => 'max:1000',
            'vote_type|投票类型' => 'require|in:1,2',
            'options|投票选项' => 'require',
            'status|状态' => 'require|in:0,1',
            'is_anonymous|是否匿名' => 'require|in:0,1',
            'max_votes|最大投票数' => 'number',
            'start_time|开始时间' => 'date',
            'end_time|结束时间' => 'date',
        ]);

        // 处理投票选项
        if (is_string($req['options'])) {
            $req['options'] = json_decode($req['options'], true);
        }

        // 设置默认值
        $req['total_votes'] = 0;
        $req['view_count'] = 0;
        $req['is_deleted'] = 0;

        Vote::create($req);

        return out();
    }

    /**
     * 编辑投票票数
     */
    public function editVote()
    {
        $req = request()->post();
        
        try {
            $this->validate($req, [
                'id' => 'require|number',
                'total_votes|总票数' => 'require|number|min:0',
            ]);

            $result = Vote::where('id', $req['id'])->update(['total_votes' => $req['total_votes']]);
            
            if ($result) {
                return out();
            } else {
                return out(null, 400, '更新失败');
            }
        } catch (\Exception $e) {
            return out(null, 500, '系统错误：' . $e->getMessage());
        }
    }

    /**
     * 修改投票状态
     */
    public function changeVote()
    {
        $req = request()->post();

        Vote::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    /**
     * 删除投票
     */
    public function delVote()
    {
        $req = request()->post();
        $this->validate($req, [
            'id' => 'require|number'
        ]);

        Vote::where('id', $req['id'])->delete();

        return out();
    }

    /**
     * 获取用户信息（用于选择用户）
     */
    public function getUserInfo()
    {
        $req = request()->get();
        
        if (isset($req['phone']) && $req['phone'] !== '') {
            $user = User::where('phone', $req['phone'])->find();
            if ($user) {
                return out($user);
            }
        }
        
        return out(null, 400, '用户不存在');
    }
}
