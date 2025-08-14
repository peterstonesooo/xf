-- 简洁事务SQL：为指定用户添加民生钱包余额并记录日志
-- 筛选条件：project_id = 15 的用户

START TRANSACTION;

-- 更新用户余额
UPDATE mp_user 
SET balance = balance + 50000.00
WHERE id IN (
    SELECT DISTINCT user_id 
    FROM mp_order_daily_bonus 
    WHERE project_id = 15
);

-- 插入余额变动日志
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
    u.id,
    15, -- 手动入金类型
    4,  -- 民生钱包类型
    0,  -- 关联ID
    (u.balance - 50000.00), -- 更新前的余额
    50000.00, -- 变动金额
    u.balance, -- 更新后的余额
    '项目15民生钱包批量添加',
    0,  -- 管理员ID
    2,  -- 状态：成功
    NOW(),
    NOW()
FROM mp_user u
WHERE u.id IN (
    SELECT DISTINCT user_id 
    FROM mp_order_daily_bonus 
    WHERE project_id = 15
);

COMMIT;

-- 验证结果
SELECT 
    u.id as user_id,
    u.balance as current_balance,
    COUNT(ubl.id) as log_count
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