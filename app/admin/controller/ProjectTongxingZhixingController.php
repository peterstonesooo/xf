<?php

namespace app\admin\controller;

use app\model\ProjectTongxingZhixing;
use think\facade\Cache;

class ProjectTongxingZhixingController extends AuthController
{
    public function zhixingList()
    {
        $req = request()->param();

        $builder = ProjectTongxingZhixing::order(['order' => 'asc', 'id' => 'desc']);
        
        if (isset($req['id']) && $req['id'] !== '') {
            $builder->where('id', $req['id']);
        }
        if (isset($req['title']) && $req['title'] !== '') {
            $builder->where('title', 'like', '%' . $req['title'] . '%');
        }
        
        $data = $builder->paginate(['query' => $req]);

        $data->each(function ($item) {
            if (!empty($item['cover_img'])) {
                $coverImg = $item['cover_img'];
                if (!is_array($coverImg)) {
                    $decoded = json_decode($coverImg, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $coverImg = $decoded;
                    } else {
                        $coverImg = [$coverImg];
                    }
                }
                $coverImg = array_values(array_filter($coverImg, function ($img) {
                    return !empty($img);
                }));
                $item['cover_img'] = $coverImg;
            } else {
                $item['cover_img'] = [];
            }
            return $item;
        });

        $this->assign('req', $req);
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function showZhixing()
    {
        $req = request()->param();
        $data = [];
        if (!empty($req['id'])) {
            $data = ProjectTongxingZhixing::where('id', $req['id'])->find();
            if ($data && !empty($data['cover_img'])) {
                $coverImg = $data['cover_img'];
                if (!is_array($coverImg)) {
                    $decoded = json_decode($coverImg, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $coverImg = $decoded;
                    } else {
                        $coverImg = [$coverImg];
                    }
                }
                $data['cover_img'] = $coverImg;
            } elseif ($data) {
                $data['cover_img'] = [];
            }
        }
        
        $this->assign('data', $data);

        return $this->fetch();
    }

    public function addZhixing()
    {
        $req = $this->validate(request(), [
            'title|名称' => 'require|max:32',
            'city|帮助市' => 'max:255',
            'order|排序' => 'number',
        ]);
        
        if(Cache::get('project_tongxing_zhixing_addZhixing')){
            return out(null, 10001, '操作过于频繁，请稍后再试');
        }
        Cache::set('project_tongxing_zhixing_addZhixing', 1, 5);
        
        // 处理简介（富文本内容）
        $req['detial'] = request()->param('detial', '');
        $creatAtParam = request()->param('creat_at', '');
        if ($creatAtParam !== '') {
            $creatAtTimestamp = strtotime($creatAtParam);
            if ($creatAtTimestamp === false) {
                return out(null, 10001, '创建时间格式不正确');
            }
            $req['creat_at'] = date('Y-m-d H:i:s', $creatAtTimestamp);
        }
        
        // 处理封面图（多图）
        $cover_images = [];
        
        $existing_images = request()->param('existing_cover_img/a', []);
        if (!empty($existing_images)) {
            foreach ($existing_images as $img) {
                if (!empty($img)) {
                    $cover_images[] = $img;
                }
            }
        }
        
        $files = request()->file('new_cover_img');
        if ($files) {
            if (!is_array($files)) {
                $files = [$files];
            }
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
                        // 单个文件上传失败时忽略
                    }
                }
            }
        }
        
        $cover_images = array_values(array_filter($cover_images, function ($item) {
            return !empty($item);
        }));
        $req['cover_img'] = json_encode($cover_images, JSON_UNESCAPED_SLASHES);
        
        $zhixing = ProjectTongxingZhixing::create($req);

        return out(['id' => $zhixing->id]);
    }

    public function editZhixing()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number',
            'title|名称' => 'require|max:32',
            'city|帮助市' => 'max:255',
            'order|排序' => 'number',
        ]);
        
        // 处理简介（富文本内容）
        $req['detial'] = request()->param('detial', '');
        $creatAtParam = request()->param('creat_at', '');
        if ($creatAtParam !== '') {
            $creatAtTimestamp = strtotime($creatAtParam);
            if ($creatAtTimestamp === false) {
                return out(null, 10001, '创建时间格式不正确');
            }
            $req['creat_at'] = date('Y-m-d H:i:s', $creatAtTimestamp);
        }
        
        // 处理封面图（多图）
        $cover_images = [];
        
        $existing_images = request()->param('existing_cover_img/a', []);
        if (!empty($existing_images)) {
            foreach ($existing_images as $img) {
                if (!empty($img)) {
                    $cover_images[] = $img;
                }
            }
        }
        
        $files = request()->file('new_cover_img');
        if ($files) {
            if (!is_array($files)) {
                $files = [$files];
            }
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
                        // 单个文件上传失败时忽略
                    }
                }
            }
        }
        
        $cover_images = array_values(array_filter($cover_images, function ($item) {
            return !empty($item);
        }));
        $req['cover_img'] = json_encode($cover_images, JSON_UNESCAPED_SLASHES);
        
        ProjectTongxingZhixing::where('id', $req['id'])->update($req);

        return out(['id' => $req['id']]);
    }

    public function delZhixing()
    {
        $req = $this->validate(request(), [
            'id' => 'require|number'
        ]);

        ProjectTongxingZhixing::destroy($req['id']);

        return out();
    }
}

