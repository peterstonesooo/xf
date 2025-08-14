<?php

namespace app\common\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use Qiniu\Storage\UploadManager;
use Qiniu\Auth;

use Exception;
use think\facade\Filesystem;
use think\facade\Log;
use think\File;

class Backup extends Command
{
    protected function configure()
    {
        $this->setName('backup')->setDescription('数据库备份');
    }

    public function execute(Input $input, Output $output)
    {
        ini_set ("memory_limit","-1");
        set_time_limit(0);

        $host = env('database.hostname');
        $user = env('database.username');
        $password = env('database.password');
        $database = env('database.database');
        $backup_file =  'jh'. date("Y-m-d") . '.sql'; // 备份文件名
        
        // 创建数据库备份
        $command = "mysqldump --opt -u $user -p".'"'.$password.'"'." -h $host $database > $backup_file";
        exec($command, $output, $returnVar);
        
        // 检查备份是否成功
        if ($returnVar === 0) {
            $uploadMgr = new UploadManager();
            $auth = new Auth('1BnmfXufgx8_vzzofjHC5OEYLJtAMJuXQbYn2mPy', 'py3KEa2W7wmJYHuXkdvoIxhFjl-eRZMBG060CKfW');
            $token = $auth->uploadToken('gfdgdaaa');
            list($ret, $error) = $uploadMgr->putFile($token, $backup_file, $backup_file);
            var_dump($ret, $error);
        } else {
            echo "备份失败";
        }
    }

    


}
