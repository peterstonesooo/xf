<?php

namespace app\admin\controller;

use app\model\Banner;

class BannerController extends AuthController
{
    public function bannerList()
    {
        $req = request()->param();

        $builder = Banner::order('id', 'desc');
        if (isset($req['banner_id']) && $req['banner_id'] !== '') {
            $builder->where('id', $req['banner_id']);
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }
        if (isset($req['sort']) && $req['sort'] !== '') {
            $builder->where('sort', $req['sort']);
        }

        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showBanner()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = Banner::where('id', $req['id'])->find();
        }
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addBanner()
    {
        $req = $this->validate(request(), [
            'title|标题' => 'require',
            'sort|排序号' => 'integer',
            'jump_url|跳转链接' => 'max:255',
        ]);

        $req['img_url'] = upload_file('img_url');
        Banner::create($req);

        return out();
    }

    public function editBanner()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'title|标题' => 'require',
            'sort|排序号' => 'integer',
            'jump_url|跳转链接' => 'max:255',
        ]);

        if ($img_url = upload_file('img_url', false)) {
            $req['img_url'] = $img_url;
        }
        Banner::where('id', $req['id'])->update($req);

        return out();
    }

    public function changeBanner()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'field' => 'require',
            'value' => 'require',
        ]);

        Banner::where('id', $req['id'])->update([$req['field'] => $req['value']]);

        return out();
    }

    public function delBanner()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        Banner::destroy($req['id']);

        return out();
    }
}
