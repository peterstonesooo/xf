<?php

namespace app\admin\controller;

use app\model\product;
use app\model\User;
use app\model\Userproduct;

class productController extends AuthController
{
    public function productList()
    {
        $req = request()->param();

        $builder = product::order(['sort' => 'desc', 'id' => 'desc'])->where('is_delete', 0);
        if (isset($req['product_id']) && $req['product_id'] !== '') {
            $builder->where('id', $req['product_id']);
        }
        if (isset($req['name']) && $req['name'] !== '') {
            $builder->where('name', $req['name']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }
        $phoneArr = User::testUser();
        $ids = User::whereIn('phone', $phoneArr)->column('id');
        $data = $builder->paginate(['query' => $req])->each(function ($item, $key) use($ids){
            $item['type_txt'] = product::TYPE[$item->type] ?? '';
            $item['status_txt'] = product::STATUS[$item->status] ?? '';
            $item['group_txt'] = product::PRODUCT_GROUP[$item->product_group_id] ?? '';
            $item['buy_total'] = Userproduct::where(['product_id' => $item['id'], 'product_type' => 0])->whereNotIn('user_id', $ids)->count();
     });
        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('statusArr', product::STATUS);
        return $this->fetch();
    }

    public function showProduct()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = product::where('id', $req['id'])->find();
        }

        $this->assign('give', []);
        $this->assign('data', $data);
        $this->assign('typeArr', product::TYPE);
        $this->assign('group', product::PRODUCT_GROUP);

        return $this->fetch();
    }

    public function addProduct()
    {
        $req = $this->validate(request(), [
            'product_group_id|项目分组ID' => 'require|integer',
            'name|项目名称' => 'require|max:100',
            'details|项目简介' => 'max:2048',
            'info|申请说明' => 'max:4096',
            'score|学分' => 'max:32',
            'score_info|学分细则' => 'max:2048',
            'total_num|总份数' => 'require|integer',
            'single_amount|单份金额' => 'require|float',
            'invite_bonus|周期收益' => 'float',
            'team_bonus_balance|周期生育津贴' => 'float',
            'limit_buy|限购份数' => 'require|integer',
            'type|类型' => 'integer',
            'dividend_cycle|周期' => 'require|integer',
            'digital_yuan_amount|生育补贴' => 'require|float|min:0',
            'sort|排序号' => 'integer',
            'rate_display|展示比率' => 'max:32',
            'show_state|显示状态' => 'require|integer',
        ]);
        if ($img = upload_file('cover_img', false)) {
            $req['cover_img'] = $img;
            $req['details_img'] = $img;
        } else {
            $req['cover_img'] = '';
            $req['details_img'] = '';
        }
        $req['status'] = 0;
        product::create($req);
        return out();
    }

    public function editProduct()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'product_group_id|项目分组ID' => 'require|integer',
            'name|项目名称' => 'require|max:100',
            'details|项目简介' => 'max:2048',
            'info|申请说明' => 'max:4096',
            'score|学分' => 'max:32',
            'score_info|学分细则' => 'max:2048',
            'single_amount|单份金额' => 'require|float',
            'team_bonus_balance|周期生育津贴' => 'float',
            'invite_bonus|周期收益' => 'float',
            'total_num|总份数' => 'require|integer',
            'limit_buy|限购份数' => 'require|integer',
            'type|类型' => 'integer',
            'dividend_cycle|周期' => 'require|integer',
            'digital_yuan_amount|生育补贴' => 'require|float|min:0',
            'sort|排序号' => 'integer',
            'rate_display|展示比率' => 'max:32',
            'show_state|显示状态' => 'require|integer',
        ]);
        if ($img = upload_file('cover_img', false)) {
            $req['cover_img'] = $img;
            $req['details_img'] = $img;
        }
        $id = $req['id'];
        product::where('id', $id)->update($req);
        return out();
    }

    public function changeProduct()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        product::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function delProduct()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        product::where('id', $req['id'])->update(
            [
                'is_delete' => 1,
                'status' => 0,
            ]
        );

        return out();
    }
}
