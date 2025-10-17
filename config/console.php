<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'curd'	=>	'app\common\command\Curd',
        'rule'  =>  'app\common\command\Rule',
        'checkRelease'  =>  'app\common\command\CheckRelease',
        'activeRank'  =>  'app\common\command\ActiveRank',
        'checkSubsidy'  =>  'app\common\command\CheckSubsidy',
        'checkTeam'  =>  'app\common\command\CheckTeam',
        'checkTeamLeader'  =>  'app\common\command\CheckTeamLeader',
        'sendCashReward'  =>  'app\common\command\SendCashReward',
        'autoWithdrawAudit'  =>  'app\common\command\AutoWithdrawAudit',
        'genarateEthAdress' =>  'app\common\command\GenarateEthAdress',
        'checkAssetBonus'  =>  'app\common\command\CheckAssetBonus',
        'butie' => 'app\common\command\Butie',
        'makeKline' => 'app\common\command\MakeKline',
        'YuanMengChangeStatus' => 'app\common\command\YuanMengChangeStatus',
        'SignRewardAppend' => 'app\common\command\SignRewardAppend',
        'task'  =>  'app\common\command\Task',
        'tasks'  =>  'app\common\command\Tasks',
        'taskss'  =>  'app\common\command\Taskss',
        'tasksss'  =>  'app\common\command\Tasksss',
        'fix'  =>  'app\common\command\Fix',

        'checkBonus'  =>  'app\common\command\CheckBonus',
        'checkBonusDaily'  =>  'app\common\command\checkBonusDaily',
        'DailyMonthReward'  =>  'app\common\command\DailyMonthReward',

        'YuebaoBonus'  =>  'app\common\command\YuebaoBonus',
        'Test'  =>  'app\common\command\Test',
        'backup'  =>  'app\common\command\Backup',

        'GaoJiYongHuDay'  =>  'app\common\command\GaoJiYongHuDay',
        'GaoJiYongHuMonth'  =>  'app\common\command\GaoJiYongHuMonth',
        'GaoJiYongHu'  =>  'app\common\command\GaoJiYongHu',

        'Sengyuka'  =>  'app\common\command\Sengyuka',

        'Test1'  =>  'app\common\command\Test1',
        'sendDailyNotice'  =>  'app\common\command\SendDailyNotice',
        'settleDailyReturns' => 'app\common\command\SettleDailyReturns',
        'settleMonthlyReturns' => 'app\common\command\SettleMonthlyReturns',
        'Settleupdateorders' => 'app\common\command\Settleupdateorders',
        'Tiyan' => 'app\common\command\Tiyan',
        'MonthlyFenhong' => 'app\common\command\MonthlyFenhong',
        'RujinPointsTask' => 'app\common\command\RujinPointsTask',
        'DailyBonusReturn' => 'app\common\command\DailyBonusReturn',
        'checkLoanOverdue' => 'app\common\command\CheckLoanOverdue',
        'loanOverdueManager' => 'app\common\command\LoanOverdueManager',
        'investment:auto-return' => 'app\common\command\InvestmentAutoReturn',
        'repair:happiness_equity_reward' => 'app\common\command\RepairHappinessEquityReward',
        'fix:investment_interest_data' => 'app\common\command\FixInvestmentInterestData',
        'repair:balance-log' => 'app\common\command\RepairBalanceLog',
        'repair:balance-log-error' => 'app\common\command\RepairBalanceLogError',
        'correct:balance-log' => 'app\common\command\CorrectBalanceLog',
        'rollback:gongfu-bonus' => 'app\common\command\RollbackGongfuBonus',
        'rollback:happiness-team-reward' => 'app\common\command\RollbackHappinessTeamReward',
        'migrateUserActive' => 'app\common\command\MigrateUserActive',
        'vote:sync' => 'app\common\command\VoteDataSync',
        'migrateUserWallet' => 'app\common\command\MigrateUserWallet',
        'Settlezhuihui' => 'app\common\command\Settlezhuihui',
        'RujinPointsTaskBatch' => 'app\common\command\RujinPointsTaskBatch',
    ],
];
