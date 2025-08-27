<?php

return array(
    // '常规管理' =>
    //     array(
    //         'icon' => 'fa-cubes',
    //         'url' =>
    //             array(
    //                 '登记数据' =>
    //                     array(
    //                         'icon' => 'fa-circle-o',
    //                         'url' => 'admin/User/promote',
    //                     ),
    //             ),
    //     ),







    '控制台' =>
        array(
            'icon' => 'fa-home',
            'url' => 'admin/Home/index',
        ),
    '常规管理' =>
        array(
            'icon' => 'fa-cubes',
            'url' =>
                array(
                    '消息通知' =>
                        array(
                            'icon' => 'fa-bell',
                            'url' => 'admin/Notice/messageList',
                        ),
                    '签到记录' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Signin/SigninLog',
                        ),
                    '奖品设置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Signin/setting',
                        ),
                    '奖品抽奖记录' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Signin/SigninPrizeLog',
                        ),
                    '内定人员列表' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Signin/luckyUser',
                        ),
                    '后台账号管理' =>
                        array(
                            'icon' => 'fa-users',
                            'url' => 'admin/AdminUser/adminUserList',
                        ),
                ),
        ),
        '交易管理' =>
        array(
            'icon' => 'fa-cubes',
            'url' =>
                array(
                    '项目管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Project/projectList',
                        ),
                    '同心同行配置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/ProjectTongxing/projectList',
                        ), 
                    '一次分红订单' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Order/orderList',
                        ),
                    '每日分红订单' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Order2/orderList',
                        ),
                    '体验订单' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/OrderTiyan/orderList',
                        ),
                    // '红色传承订单' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Order3/orderList',
                    //     ),
                    '定投管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/HeritageFund/index',
                        ),
                    '定投订单' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/OrderDingtou/orderList',
                        ),
                    '积分商品管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/PointsOnlyProduct/index',
                        ),
                    '积分商品订单' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/PointsOrder/orderList',
                        ),


                    // '升级缴费订单' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Order4/orderList',
                    //     ),
                    // '赠送订单' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Order/orderListFree',
                    //     ),
                    // '资金来源证明' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Order5/orderList',
                    //     ),
                    // '开户订单' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/UserOpen/userBalanceLogList',
                    //     ),
                    // '修改分红天数'=>array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/Order/addTime',
                    // ),

                    // '项目管理一期' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/Project1/projectList',
                    // ),
                    // '产品管理' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/product/productList',
                    // ),
                    // '购买产品记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Userproduct/userProductList',
                    //     ),
                ),
        ),
    '用户管理' =>
        array(
            'icon' => 'fa-user',
            'url' =>
                array(
                    '用户管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/User/userList',
                        ),
                    // '可疑用户列表' =>
                    //     array(
                    //         'icon' => 'fa-exclamation-triangle',
                    //         'url' => 'admin/User/suspiciousUserList',
                    //     ),
                    '用户资金明细' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/UserBalanceLog/userBalanceLogList',
                        ),
                    '余额导入' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/User/import',
                        ),
                    '实名认证审核' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/User/authentication',
                        ),
                    '专属补贴审核' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/ExclusiveLog/exclusiveLogList',
                        ),
                    // '生育卡' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/Card/cardList',
                    // ),
                    // '用户积分记录' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/UserBalanceLog/userIntegralLogList',
                    //     ),
                    // '收货地址' =>
                    // array(
                    //     'icon' => 'fa-circle-o',
                    //     'url' => 'admin/UserDelivery/userDeliveryList',
                    // ),
                ),
        ),
    '充值管理' =>
        array(
            'icon' => 'fa-gears',
            'url' =>
                array(
                '充值记录' =>
                    array(
                        'icon' => 'fa-circle-o',
                        'url' => 'admin/Capital/topupList',
                    ),
                '提现记录' =>
                    array(
                        'icon' => 'fa-circle-o',
                        'url' => 'admin/Capital/withdrawList',
                    ),
                ),
        ),
    '贷款管理' =>
        array(
            'icon' => 'fa-money',
            'url' =>
                array(
                    '贷款产品管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/LoanProduct/productList',
                        ),
                    '贷款申请管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/LoanApplication/applicationList',
                        ),

                    '还款计划管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/LoanRepayment/planList',
                        ),
                    '还款记录管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/LoanRepayment/recordList',
                        ),
                    '逾期管理' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/LoanRepayment/overdueList',
                        ),
                    '贷款配置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/LoanConfig/configList',
                        ),
                    '出资申请记录' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/InvestmentRecord/recordList',
                        ),
                    '出资返还记录' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/InvestmentReturn/returnList',
                        ),
                ),
        ),
    '设置中心' =>
        array(
            'icon' => 'fa-gears',
            'url' =>
                array(
                    '角色权限管理' =>
                        array(
                            'icon' => 'fa-users',
                            'url' => 'admin/AuthGroup/authGroupList',
                        ),
                    '支付渠道配置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/PaymentConfig/paymentConfigList',
                        ),
                    // '股权K线图' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/KlineChart/klineChart',
                    //     ),
                    // '会员等级管理' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/LevelConfig/levelConfigList',
                    //     ),
                    // '轮播图设置' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/Banner/bannerList',
                    //     ),
                    // '公司动态' =>
                    //     array(
                    //         'icon' => 'fa-circle-o',
                    //         'url' => 'admin/SystemInfo/companyInfoList',
                    //     ),
                    '系统信息设置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/SystemInfo/systemInfoList',
                        ),
                    '常规配置' =>
                        array(
                            'icon' => 'fa-circle-o',
                            'url' => 'admin/Setting/settingList',
                        ),
                    '转账配置' =>
                        array(
                            'icon' => 'fa-exchange',
                            'url' => 'admin/TransferConfig/index',
                        ),
                    '数据修正' =>
                        array(
                            'icon' => 'fa-database',
                            'url' => 'admin/Setting/dataCorrection1',
                        ),
                    '隐私协议管理' =>
                        array(
                            'icon' => 'fa-file-text',
                            'url' => 'admin/Agreement/index',
                        ),
                ),
        ),

 

);
