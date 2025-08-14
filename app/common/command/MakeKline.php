<?php

namespace app\common\command;

use think\facade\Db;
use app\model\Coin;
use app\model\KlineChartNew;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;

class MakeKline extends Command
{
    /**
     * 自动生成一些今日k线
     */
    protected function configure()
    {
        $this->setName('makeKline')->setDescription('自动生成一些今日k线');
    }

    protected function execute(Input $input, Output $output)
    {
        $coin = Coin::select();
        foreach ($coin as $value) {
            $todayData = KlineChartNew::where('code_id', $value['id'])->where('date', date('Y-m-d'))->find();
            if ($todayData) continue;
            $historyDataIdColumn = KlineChartNew::where('code_id', $value['id'])->limit(100)->column('id');
            $randId = $historyDataIdColumn[mt_rand(0, count($historyDataIdColumn) - 1)];
            $todayBaseData = KlineChartNew::find($randId);
            $todayNewData = [];
            $todayNewData['date'] = date('Y-m-d');
            $todayNewData['code_id'] = $value['id'];
            $todayNewData['created_at'] = date('Y-m-d H:i:s');
            for ($i=1; $i <= 25; $i++) { 
                $todayNewData['price' . $i] = $todayBaseData['price' . mt_rand(1, 25)];
            }
            KlineChartNew::insert($todayNewData);
        }
    }
}
