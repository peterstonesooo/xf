<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\model\PointsOnlyProduct;
use think\facade\View;

class PointsOnlyProductController extends AuthController
{
    public function index()
    {
        $product_name = input('param.product_name');
        $product_status = input('param.product_status');
        $req = $this->validate(request(), [
            'product_name|商品名称'=> 'max:32',
            'product_status|是否上架'=> 'number|in:1,0',
        ]);
        $where = [];
        if (!empty($req['product_name'])) {
            $where[] = ['product_name', 'like', '%' . $product_name . '%'];
        }
        if (array_key_exists('product_status', $req)) {
            $where[] = ['product_status', '=', $product_status];
        }

        // 处理排序
        $sort = input('param.sort', '');
        $order = [];
        if ($sort === 'points_asc') {
            $order = ['points_price', 'asc'];
        } elseif ($sort === 'points_desc') {
            $order = ['points_price', 'desc'];
        } elseif ($sort === 'stock_asc') {
            $order = ['stock_quantity', 'asc'];
        } elseif ($sort === 'stock_desc') {
            $order = ['stock_quantity', 'desc'];
        } else {
            $order = ['points_price', 'desc'];
        }

        $data = PointsOnlyProduct::where($where)
            ->order($order[0], $order[1])
            ->paginate(10);

        View::assign('data', $data);
        View::assign('req', input('param.'));
        return View::fetch();
    }

    public function add()
    {
        if (request()->isPost()) {
            $req = $this->validate(request(), [
                'product_name|商品名称' => 'require',
                'product_description|商品描述' => 'require|max:100',
                'points_price|金额' => 'float',
                'stock_quantity|库存' => 'number',
                'product_status|是否上架' => 'require|in:0,1',
            ]);

            try {
                $req['product_image_url'] = upload_file('product_image_url');
                $req['create_time'] =  date('Y-m-d H:i:s', time());
                $req['update_time'] =  date('Y-m-d H:i:s', time());
//                var_dump($req);
                PointsOnlyProduct::create($req);

            } catch (\Exception $e) {
                return json(['code' => 1, 'msg' => $e->getMessage()]);
            }

            return json(['code' => 0, 'msg' => '添加成功']);
        }

        return View::fetch();
    }

    public function edit()
    {
        $req = request();
        $this->validate($req, [
            'id' => 'require|number',
        ]);
        $info = PointsOnlyProduct::find($req['id']);
        if (!$info) {
            return json(['code' => 1, 'msg' => '商品不存在']);
        }

        if (request()->isPost()) {
            $req = $this->validate(request(), [
                'id|商品ID' => 'require|number',
                'product_name|商品名称' => 'require',
                'product_description|商品描述' => 'require|max:100',
                'points_price|积分价格' => 'require|float|gt:0',
                'stock_quantity|库存' => 'require|number|egt:0',
                'product_status|是否上架' => 'require|in:0,1',
            ]);

            try {
                // 处理图片上传
                if ($img = upload_file('product_image_url', false,false)) {
                    $req['product_image_url'] = $img;
                }
                
                PointsOnlyProduct::update($req);
            } catch (\Exception $e) {
                return json(['code' => 1, 'msg' => '编辑失败：' . $e->getMessage()]);
            }

            return json(['code' => 0, 'msg' => '编辑成功']);
        }

        View::assign('data', $info);
        return View::fetch();
    }

    public function del()
    {
        $id = input('param.id');

        try {
            PointsOnlyProduct::destroy($id);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '删除失败']);
        }

        return json(['code' => 0, 'msg' => '删除成功']);
    }

    public function status()
    {
        $id = input('param.id');
        $status = input('param.status');

        try {
            PointsOnlyProduct::update(['id' => $id, 'product_status' => $status]);
        } catch (\Exception $e) {
            return json(['code' => 1, 'msg' => '操作失败']);
        }

        return json(['code' => 0, 'msg' => '操作成功']);
    }
} 