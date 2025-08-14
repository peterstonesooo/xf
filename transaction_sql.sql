-- 事务SQL：为指定用户添加民生钱包余额并记录日志
-- 筛选条件：project_id = 15 的用户

START TRANSACTION;

-- 声明变量
DECLARE done INT DEFAULT FALSE;
DECLARE user_id_val INT;
DECLARE current_balance DECIMAL(10,2);
DECLARE new_balance DECIMAL(10,2);
DECLARE change_amount DECIMAL(10,2) DEFAULT 50000.00;

-- 声明游标
DECLARE user_cursor CURSOR FOR 
    SELECT DISTINCT user_id 
    FROM mp_order_daily_bonus 
    WHERE project_id = 15;

-- 声明异常处理
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

-- 打开游标
OPEN user_cursor;

-- 开始循环处理每个用户
user_loop: LOOP
    -- 获取下一个用户ID
    FETCH user_cursor INTO user_id_val;
    
    -- 如果没有更多记录，退出循环
    IF done THEN
        LEAVE user_loop;
    END IF;
    
    -- 获取用户当前余额
    SELECT balance INTO current_balance 
    FROM mp_user 
    WHERE id = user_id_val 
    FOR UPDATE;
    
    -- 计算新余额
    SET new_balance = current_balance + change_amount;
    
    -- 更新用户余额
    UPDATE mp_user 
    SET balance = new_balance 
    WHERE id = user_id_val;
    
    -- 记录余额变动日志
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
    ) VALUES (
        user_id_val,
        15, -- 手动入金类型
        4,  -- 民生钱包类型
        0,  -- 关联ID（这里设为0）
        current_balance,
        change_amount,
        new_balance,
        '项目15民生钱包批量添加',
        0,  -- 管理员ID（这里设为0）
        2,  -- 状态：成功
        NOW(),
        NOW()
    );
    
END LOOP;

-- 关闭游标
CLOSE user_cursor;

-- 提交事务
COMMIT;

-- 查询结果验证
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