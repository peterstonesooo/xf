<?php

namespace app\admin\controller;

use app\model\OrderTongxing;
use app\model\ProjectTongxing;
use think\facade\Cache;

class ProjectTongxingController extends AuthController
{
    public function projectList()
    {
        $req = request()->param();

        $builder = ProjectTongxing::order(['id' => 'desc']);
        
        if (isset($req['id']) && $req['id'] !== '') {
            $builder->where('id', $req['id']);
        }
        if (isset($req['name']) && $req['name'] !== '') {
            $builder->where('name', 'like', '%' . $req['name'] . '%');
        }
        
        $data = $builder->paginate(['query' => $req]);

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function donationList()
    {
        $req = request()->param();

        $builder = OrderTongxing::alias('ot')
            ->leftJoin('project_tongxing pt', 'ot.project_id = pt.id')
            ->leftJoin('user u', 'ot.user_id = u.id')
            ->where('ot.status', '>', 1)
            ->where('ot.pay_time', '>', 0)
            ->where('ot.created_at', '>=', '2025-11-08 00:00:00')
            ->order('ot.id', 'desc');

        if (!empty($req['id'])) {
            $builder->where('ot.id', $req['id']);
        }

        if (!empty($req['phone'])) {
            $builder->whereLike('u.phone', '%' . trim($req['phone']) . '%');
        }

        if (!empty($req['daterange_start'])) {
            $start = strtotime($req['daterange_start'] . ' 00:00:00');
            if ($start !== false) {
                $builder->where('ot.pay_time', '>=', $start);
            }
        }

        if (!empty($req['daterange_end'])) {
            $end = strtotime($req['daterange_end'] . ' 23:59:59');
            if ($end !== false) {
                $builder->where('ot.pay_time', '<=', $end);
            }
        }

        $data = $builder
            ->field('ot.*, pt.name as config_project_name, u.phone, u.realname')
            ->paginate(['query' => $req]);

        $statusMap = OrderTongxing::$statusMap;

        $this->assign('req', $req);
        $this->assign('data', $data);
        $this->assign('statusMap', $statusMap);

        return $this->fetch();
    }

    public function showProject()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = ProjectTongxing::where('id', $req['id'])->find();
            if ($data && !empty($data['amounts'])) {
                $data['amounts'] = is_array($data['amounts']) ? $data['amounts'] : json_decode($data['amounts'], true);
            }
            if ($data && !empty($data['cover_img'])) {
                $data['cover_img'] = is_array($data['cover_img']) ? $data['cover_img'] : json_decode($data['cover_img'], true);
            }
        }
        
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addProject()
    {
        $req = $this->validate(request(), [
            'name|名称' => 'require|max:32',
            'intro|简介' => 'max:255',
            'video_url|视频链接' => 'max:255',
            'help_province|帮助省' => 'number',
            'help_city|帮助市' => 'number',
            'help_district|帮助区' => 'number',
            'fund_goal|筹款目标' => 'number',
            'already_fund|已筹款金额' => 'number',
            'support_numbers|支持人数' => 'number',
        ]);
        
        if(Cache::get('project_tongxing_addProject')){
            return out(null, 10001, '操作过于频繁，请稍后再试');
        }
        Cache::set('project_tongxing_addProject', 1, 5);
        
        // 处理金额、说明数据
        $amounts = [];
        $amount_values = request()->param('amount_value/a', []);
        $amount_descriptions = request()->param('amount_description/a', []);
        
        for ($i = 0; $i < count($amount_values); $i++) {
            if (!empty($amount_values[$i])) {
                $amounts[] = [
                    'value' => $amount_values[$i],
                    'description' => $amount_descriptions[$i] ?? ''
                ];
            }
        }
        
        $req['amounts'] = $amounts;
        
        // 处理封面图（多图）
        $cover_images = [];
        $files = request()->file('new_cover_img');
        if ($files && is_array($files)) {
            foreach ($files as $file) {
                if ($file) {
                    try {
                        validate([
                            'file' => [
                                'fileSize' => 10 * 1024 * 1024,
                                'fileExt'  => 'png,jpg,jpeg,gif',
                            ]
                        ], [
                            'file.fileSize' => '文件太大',
                            'file.fileExt' => '不支持的文件后缀',
                        ])->check(['file' => $file]);
                        
                        $savename = \think\facade\Filesystem::disk('qiniu')->putFile('', $file);
                        $baseUrl = 'http://'.config('filesystem.disks.qiniu.domain').'/';
                        $cover_images[] = $baseUrl.str_replace("\\", "/", $savename);
                    } catch (\Exception $e) {
                        // 忽略单个文件上传失败
                    }
                }
            }
        }
        $req['cover_img'] = $cover_images;
        
        $details_img_error = null;
        if (isset($_FILES['details_img'])) {
            $details_img_error = $_FILES['details_img']['error'];
            if (is_array($details_img_error)) {
                $details_img_error = reset($details_img_error);
            }
        }
        if ($details_img_error === null || (int)$details_img_error !== UPLOAD_ERR_NO_FILE) {
            $req['details_img'] = upload_file('details_img');
        }
        
        // 处理视频（链接或上传）
        $video_file = request()->file('video_file');
        if (is_array($video_file)) {
            $video_file = reset($video_file);
        }
        $video_error = null;
        if (isset($_FILES['video_file'])) {
            $video_error = $_FILES['video_file']['error'];
            if (is_array($video_error)) {
                $video_error = reset($video_error);
            }
        }
        if ($video_file && method_exists($video_file, 'isValid') && $video_file->isValid()) {
            try {
                validate([
                    'file' => [
                        'fileSize' => 100 * 1024 * 1024, // 100MB
                        'fileExt'  => 'mp4,avi,mov,wmv,flv,webm',
                    ]
                ], [
                    'file.fileSize' => '视频文件太大，最大支持100MB',
                    'file.fileExt' => '不支持的视频格式',
                ])->check(['file' => $video_file]);
                
                $savename = \think\facade\Filesystem::disk('qiniu')->putFile('', $video_file);
                $baseUrl = 'http://'.config('filesystem.disks.qiniu.domain').'/';
                $req['video_url'] = $baseUrl.str_replace("\\", "/", $savename);
            } catch (\Exception $e) {
                return out(null, 10001, $e->getMessage());
            }
        } elseif ($video_error !== null && (int)$video_error !== UPLOAD_ERR_NO_FILE) {
            return out(null, 10001, '视频上传失败，请重试');
        }
        // 如果没有上传视频，则使用填写的链接（已在$req中）
        
        $project = ProjectTongxing::create($req);

        return out(['id' => $project->id]);
    }

    public function editProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'name|名称' => 'require|max:32',
            'intro|简介' => 'max:255',
            'video_url|视频链接' => 'max:255',
            'help_province|帮助省' => 'require',
            'help_city|帮助市' => 'require',
            'help_district|帮助区' => 'require',
            'fund_goal|筹款目标' => 'require',
            'already_fund|已筹款金额' => 'require',
            'support_numbers|支持人数' => 'require',
        ]);
        
        // 处理金额、说明数据
        $amounts = [];
        $amount_values = request()->param('amount_value/a', []);
        $amount_descriptions = request()->param('amount_description/a', []);
        
        for ($i = 0; $i < count($amount_values); $i++) {
            if (!empty($amount_values[$i])) {
                $amounts[] = [
                    'value' => $amount_values[$i],
                    'description' => $amount_descriptions[$i] ?? ''
                ];
            }
        }
        
        $req['amounts'] = $amounts;
        
        // 处理封面图（多图）
        $cover_images = [];
        
        // 保留现有的图片
        $existing_images = request()->param('existing_cover_img/a', []);
        if (!empty($existing_images)) {
            $cover_images = array_merge($cover_images, $existing_images);
        }
        
        // 添加新上传的图片
        $files = request()->file('new_cover_img');
        if ($files && is_array($files)) {
            foreach ($files as $file) {
                if ($file) {
                    try {
                        validate([
                            'file' => [
                                'fileSize' => 10 * 1024 * 1024,
                                'fileExt'  => 'png,jpg,jpeg,gif',
                            ]
                        ], [
                            'file.fileSize' => '文件太大',
                            'file.fileExt' => '不支持的文件后缀',
                        ])->check(['file' => $file]);
                        
                        $savename = \think\facade\Filesystem::disk('qiniu')->putFile('', $file);
                        $baseUrl = 'http://'.config('filesystem.disks.qiniu.domain').'/';
                        $cover_images[] = $baseUrl.str_replace("\\", "/", $savename);
                    } catch (\Exception $e) {
                        // 忽略单个文件上传失败
                    }
                }
            }
        }
        
        $req['cover_img'] = $cover_images;
        
        $details_img_error = null;
        if (isset($_FILES['details_img'])) {
            $details_img_error = $_FILES['details_img']['error'];
            if (is_array($details_img_error)) {
                $details_img_error = reset($details_img_error);
            }
        }
        if ($details_img_error === null || (int)$details_img_error !== UPLOAD_ERR_NO_FILE) {
            if ($img = upload_file('details_img', false, false)) {
                $req['details_img'] = $img;
            }
        }
        
        // 处理视频（链接或上传）
        $video_file = request()->file('video_file');
        if (is_array($video_file)) {
            $video_file = reset($video_file);
        }
        $video_error = null;
        if (isset($_FILES['video_file'])) {
            $video_error = $_FILES['video_file']['error'];
            if (is_array($video_error)) {
                $video_error = reset($video_error);
            }
        }
        if ($video_file && method_exists($video_file, 'isValid') && $video_file->isValid()) {
            try {
                validate([
                    'file' => [
                        'fileSize' => 100 * 1024 * 1024, // 100MB
                        'fileExt'  => 'mp4,avi,mov,wmv,flv,webm',
                    ]
                ], [
                    'file.fileSize' => '视频文件太大，最大支持100MB',
                    'file.fileExt' => '不支持的视频格式',
                ])->check(['file' => $video_file]);
                
                $savename = \think\facade\Filesystem::disk('qiniu')->putFile('', $video_file);
                $baseUrl = 'http://'.config('filesystem.disks.qiniu.domain').'/';
                $req['video_url'] = $baseUrl.str_replace("\\", "/", $savename);
            } catch (\Exception $e) {
                return out(null, 10001, $e->getMessage());
            }
        } elseif ($video_error !== null && (int)$video_error !== UPLOAD_ERR_NO_FILE) {
            return out(null, 10001, '视频上传失败，请重试');
        } else {
            // 如果保留现有视频
            $existing_video = request()->param('existing_video_url', '');
            if (!empty($existing_video)) {
                $req['video_url'] = $existing_video;
            }
            // 否则使用填写的视频链接（已在$req中）
        }
        
        ProjectTongxing::where('id', $req['id'])->update($req);

        return out(['id' => $req['id']]);
    }

    public function delProject()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        ProjectTongxing::destroy($req['id']);

        return out();
    }
}
