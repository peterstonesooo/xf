-- 批量事务SQL：为指定用户添加民生钱包余额并记录日志
-- 筛选条件：project_id = 15 的用户

START TRANSACTION;

-- 第一步：更新用户余额（批量更新）
UPDATE mp_user u
INNER JOIN (
    SELECT DISTINCT user_id 
    FROM mp_order_daily_bonus 
    WHERE project_id = 15
) odb ON u.id = odb.user_id
SET u.balance = u.balance + 50000.00;

-- 第二步：批量插入余额变动日志
INSERT INTO mp_user_balance_log (
    user_id,
    type,
    log_type,
    relation_id,
    before_balance,
    change_balance,
    after_balance,
    remark,
    admin_user_id,
    status,
    created_at,
    updated_at
)
SELECT 
    u.id as user_id,
    15 as type, -- 手动入金类型
    4 as log_type, -- 民生钱包类型
    0 as relation_id, -- 关联ID
    (u.balance - 50000.00) as before_balance, -- 更新前的余额
    50000.00 as change_balance, -- 变动金额
    u.balance as after_balance, -- 更新后的余额
    '项目15民生钱包批量添加' as remark,
    0 as admin_user_id, -- 管理员ID
    2 as status, -- 状态：成功
    NOW() as created_at,
    NOW() as updated_at
FROM mp_user u
INNER JOIN (
    SELECT DISTINCT user_id 
    FROM mp_order_daily_bonus 
    WHERE project_id = 15
) odb ON u.id = odb.user_id;

-- 提交事务
COMMIT;

-- 查询结果验证
SELECT 
    u.id as user_id,
    u.balance as current_balance,
    COUNT(ubl.id) as log_count,
    SUM(ubl.change_balance) as total_added
FROM mp_user u
LEFT JOIN mp_user_balance_log ubl ON u.id = ubl.user_id 
    AND ubl.remark = '项目15民生钱包批量添加'
WHERE u.id IN (
    SELECT DISTINCT user_id 
    FROM mp_order_daily_bonus 
    WHERE project_id = 15
)
GROUP BY u.id, u.balance
ORDER BY u.id; 