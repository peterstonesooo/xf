<?php

namespace app\admin\controller;

use app\model\Hongli;
use app\model\HongliOrder;

class HongliController extends AuthController
{
    //三农红利项目列表
    public function setting()
    {
        $this->assign('data', Hongli::order('sort', 'asc')->select()->toArray());
        return $this->fetch();  
    }

    //三农红利申请记录
    public function order()
    {
        $req = request()->param();
        $builder = HongliOrder::alias('l')->field('l.*, h.name, u.phone, u.realname,y.user_address');

        if (isset($req['hongli_id']) && $req['hongli_id'] !== '') {
            $builder->where('l.hongli_id', $req['hongli_id']);
        }

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('l.user_id', $req['user_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }

        if (!empty($req['start_date'])) {
            $builder->where('l.created_at', '>=', $req['start_date'] . ' 00:00:00');
        }
        if (!empty($req['end_date'])) {
            $builder->where('l.created_at', '<=', $req['end_date'] . ' 23:59:59');
        }
        
        $builder = $builder->leftJoin('mp_hongli h', 'l.hongli_id = h.id')->leftJoin('mp_user u', 'l.user_id = u.id')->leftJoin('mp_yuanmeng_user y', 'l.user_id = y.user_id')->order('l.id', 'desc');

        if (!empty($req['export'])) {
            
            $list = $builder->select();
            
            // foreach ($list as $v) {
            //     $v->address=$v['yuanmeng']['user_address'] ?? '';
            // }
            //echo 1;exit;
            create_excel($list, [
                'id' => '序号',
                'phone' => '用户',
                'realname'=>'姓名',  
                'price' => '领取金',
                'name' => '项目名称',
                'user_address' => '地址',
                'created_at' => '创建时间'
            ], '三农红利记录-' . date('YmdHis'));
        }
        $list = $builder->paginate(['query' => $req]);
        $prize = Hongli::select();
        $this->assign('prize', $prize);
        $this->assign('data', $list);
        $this->assign('req', $req);
        return $this->fetch();
    }

    //项目设置提交
    public function editConfig()
    {
        $req = $this->validate(request(), [
            'id' => 'number',
            'name|红利项目名' => 'require',
            'price|领取金' => 'require|number',
            'sort|排序号' => 'number',
        ]);

        if ($cover_img = upload_file('cover_img', false,false)) {
            $req['cover_img'] = $cover_img;
        }

        if (!empty($req['id'])) {
            Hongli::where('id', $req['id'])->update($req);
        } else {
            Hongli::insert([
                'name' => $req['name'],
                'sort' => $req['sort'],
                'price' => $req['price'],
                'cover_img' => $req['cover_img'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return out();
    }

    public function hongliAdd()
    {
        $req = request();
        if (!empty($req['id'])) {
            $data = Hongli::where('id', $req['id'])->find();
            $this->assign('data', $data);
        }
        return $this->fetch();
    }
    
    public function del()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        Hongli::destroy($req['id']);
        return out();
    }

    public function changeAdminUser()
    {
        $req = request()->post();

        Hongli::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }
}
