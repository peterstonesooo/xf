#!/bin/bash

# 贷款逾期管理定时任务脚本
# 用于宝塔服务器环境

# 设置项目根目录（请根据实际情况修改）
PROJECT_PATH="/www/wwwroot/xf"
# 或者如果是当前目录
# PROJECT_PATH="/Applications/XAMPP/xamppfiles/htdocs/xf"

# 设置日志目录
LOG_DIR="$PROJECT_PATH/runtime"
LOG_FILE="$LOG_DIR/loan_overdue.log"
ERROR_LOG="$LOG_DIR/loan_overdue_error.log"

# 创建日志目录（如果不存在）
mkdir -p "$LOG_DIR"

# 记录开始时间
echo "==========================================" >> "$LOG_FILE"
echo "贷款逾期检查开始时间: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG_FILE"
echo "==========================================" >> "$LOG_FILE"

# 切换到项目目录
cd "$PROJECT_PATH"

# 检查项目目录是否存在
if [ ! -f "think" ]; then
    echo "错误: 项目目录不存在或think文件不存在" >> "$ERROR_LOG"
    echo "当前目录: $(pwd)" >> "$ERROR_LOG"
    exit 1
fi

# 运行贷款逾期检查命令
# 使用nohup在后台运行，并将输出重定向到日志文件
nohup php think loanOverdueManager -a check >> "$LOG_FILE" 2>> "$ERROR_LOG" &

# 获取进程ID
PID=$!
echo "贷款逾期检查进程已启动，PID: $PID" >> "$LOG_FILE"

# 等待进程完成
wait $PID

# 记录结束时间
echo "==========================================" >> "$LOG_FILE"
echo "贷款逾期检查结束时间: $(date '+%Y-%m-%d %H:%M:%S')" >> "$LOG_FILE"
echo "==========================================" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

# 清理日志文件（保留最近7天的日志）
find "$LOG_DIR" -name "loan_overdue*.log" -mtime +7 -delete

echo "脚本执行完成"
