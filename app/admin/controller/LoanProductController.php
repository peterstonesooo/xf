<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\model\LoanProduct;
use app\model\LoanProductGradient;
use think\facade\Db;
use think\facade\View;

class LoanProductController extends AuthController
{
    /**
     * 默认方法 - 重定向到产品列表
     */
    public function index()
    {
        return $this->redirect('loanproduct/productList');
    }

    /**
     * 产品列表
     */
    public function productList()
    {
        $req = request()->param();
        
        $builder = LoanProduct::with(['gradients']);
        
        // 搜索条件
        if (!empty($req['name'])) {
            $builder->where('name', 'like', '%' . $req['name'] . '%');
        }
        if (isset($req['status']) && $req['status'] !== '') {
            $builder->where('status', $req['status']);
        }
        
        $builder->order('sort desc, id desc');
        
        $data = $builder->paginate(['query' => $req])->each(function ($item, $key) {
            $item->status_text = $item->getStatusTextAttr(null, $item->toArray());
            $item->interest_type_text = $item->getInterestTypeTextAttr(null, $item->toArray());
            $item->gradient_count = $item->gradients()->count();
            return $item;
        });

        View::assign('req', $req);
        View::assign('data', $data);
        View::assign('statusMap', LoanProduct::$statusMap);
        View::assign('interestTypeMap', LoanProduct::$interestTypeMap);

        return View::fetch('loan_product/product_list');
    }

    /**
     * 显示产品表单
     */
    public function showProduct()
    {
        $id = request()->param('id');
        $data = null;
        
        if ($id) {
            $data = LoanProduct::with(['gradients'])->find($id);
            if (!$data) {
                return $this->error('产品不存在');
            }
        }

        View::assign('data', $data);
        View::assign('statusMap', LoanProduct::$statusMap);
        View::assign('interestTypeMap', LoanProduct::$interestTypeMap);

        return View::fetch('loan_product/show_product');
    }

    /**
     * 保存产品
     */
    public function saveProduct()
    {
        try {
            $req = request()->param();
            
            // 调试信息
            \think\facade\Log::info('保存产品请求数据: ' . json_encode($req, JSON_UNESCAPED_UNICODE));
            
            // 验证数据
            $validate = [
                'name' => 'require|max:100',
                'min_amount' => 'require|float|egt:0',
                'max_amount' => 'require|float|egt:0',
                'interest_type' => 'require|in:1,2',
                'overdue_interest_rate' => 'require|float|egt:0',
                'max_overdue_days' => 'require|integer|egt:0',
                'status' => 'require|in:0,1',
                'sort' => 'integer|egt:0'
            ];
            
            $this->validate($req, $validate);

            if ($req['min_amount'] >= $req['max_amount']) {
                return $this->error('最小金额不能大于等于最大金额');
            }

            Db::startTrans();
            try {
                if (!empty($req['id'])) {
                    // 更新
                    $product = LoanProduct::find($req['id']);
                    if (!$product) {
                        throw new \Exception('产品不存在');
                    }
                    $product->save($req);
                } else {
                    // 新增
                    $product = LoanProduct::create($req);
                }

                // 处理梯度设置
                if (!empty($req['gradients'])) {
                    // 删除原有梯度
                    LoanProductGradient::where('product_id', $product->id)->delete();

                    // 添加新梯度
                    foreach ($req['gradients'] as $gradient) {
                        if (!empty($gradient['loan_days']) && !empty($gradient['installment_count']) && isset($gradient['interest_rate'])) {
                            LoanProductGradient::create([
                                'product_id' => $product->id,
                                'loan_days' => $gradient['loan_days'],
                                'installment_count' => $gradient['installment_count'],
                                'interest_rate' => $gradient['interest_rate'],
                                'status' => 1
                            ]);
                        }
                    }
                }

                Db::commit();
                \think\facade\Log::info('产品保存成功');
                return out(null, 200, '保存成功');
            } catch (\Exception $e) {
                Db::rollback();
                \think\facade\Log::error('产品保存失败: ' . $e->getMessage());
                return out(null, 500, '保存失败：' . $e->getMessage());
            }
        } catch (\Exception $e) {
            \think\facade\Log::error('产品保存验证失败: ' . $e->getMessage());
            return out(null, 500, '保存失败：' . $e->getMessage());
        }
    }

    /**
     * 删除产品
     */
    public function deleteProduct()
    {
        $id = request()->param('id');
        
        if (!$id) {
            return $this->error('参数错误');
        }

        Db::startTrans();
        try {
            $product = LoanProduct::find($id);
            if (!$product) {
                throw new \Exception('产品不存在');
            }

            // 检查是否有贷款申请
            $applicationCount = $product->applications()->count();
            if ($applicationCount > 0) {
                throw new \Exception('该产品已有贷款申请，无法删除');
            }

            // 删除梯度
            LoanProductGradient::where('product_id', $id)->delete();
            
            // 删除产品
            $product->delete();

            Db::commit();
            return $this->success('删除成功');
        } catch (\Exception $e) {
            Db::rollback();
            return $this->error('删除失败：' . $e->getMessage());
        }
    }

    /**
     * 修改状态
     */
    public function changeStatus()
    {
        $id = request()->param('id');
        $status = request()->param('status');
        
        if (!$id || !in_array($status, [0, 1])) {
            return $this->error('参数错误');
        }

        $product = LoanProduct::find($id);
        if (!$product) {
            return $this->error('产品不存在');
        }

        $product->status = $status;
        $product->save();

        return $this->success('状态修改成功');
    }
}
