<?php

namespace app\admin\controller;

use app\model\Butie;
use app\model\ButieOrder;

class ButieController extends AuthController
{
    //三农补贴项目列表
    public function setting()
    {
        $this->assign('data', Butie::order('sort', 'asc')->select()->toArray());
        return $this->fetch();  
    }

    //三农补贴申请记录
    public function order()
    {
        $req = request()->param();
        $builder = ButieOrder::alias('l')->field('l.*, b.name, u.phone');

        if (isset($req['butie_id']) && $req['butie_id'] !== '') {
            $builder->where('l.butie_id', $req['butie_id']);
        }

        if (isset($req['user_id']) && $req['user_id'] !== '') {
            $builder->where('l.user_id', $req['user_id']);
        }

        if (isset($req['phone']) && $req['phone'] !== '') {
            $builder->where('u.phone', $req['phone']);
        }
        
        $builder = $builder->leftJoin('mp_butie b', 'l.butie_id = b.id')->leftJoin('mp_user u', 'l.user_id = u.id')->order('l.id', 'desc');
        $list = $builder->paginate(['query' => $req]);
        $prize = Butie::select();
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
            'name|补贴项目名' => 'require',
            'price|领取金' => 'require|number',
            'butie|补贴金' => 'require|number',
            'sort|排序号' => 'number',
        ]);

        if ($cover_img = upload_file('cover_img', false,false)) {
            $req['cover_img'] = $cover_img;
        }

        if (!empty($req['id'])) {
            Butie::where('id', $req['id'])->update($req);
        } else {
            Butie::insert([
                'name' => $req['name'],
                'sort' => $req['sort'],
                'price' => $req['price'],
                'butie' => $req['butie'],
                'cover_img' => $req['cover_img'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return out();
    }

    public function butieAdd()
    {
        $req = request();
        if (!empty($req['id'])) {
            $data = Butie::where('id', $req['id'])->find();
            $this->assign('data', $data);
        }
        return $this->fetch();
    }
    
    public function del()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);
        Butie::destroy($req['id']);
        return out();
    }

    public function changeAdminUser()
    {
        $req = request()->post();

        Butie::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }
}
