<?php
/**
 * 上传性能监控脚本
 * 用于实时查看 upload_file 的性能日志
 */

// 设置日志文件路径（根据你的项目配置调整）
$logPath = __DIR__ . '/runtime/log/';

echo "=== 上传性能监控脚本 ===\n";
echo "监控目录: " . $logPath . "\n";
echo "按 Ctrl+C 停止监控\n\n";

// 获取最新的日志文件
function getLatestLogFile($logPath) {
    $files = glob($logPath . '*.log');
    if (empty($files)) {
        return null;
    }
    
    // 按修改时间排序，获取最新的日志文件
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    return $files[0];
}

// 解析日志行
function parseLogLine($line) {
    if (strpos($line, 'upload_file') !== false) {
        return $line;
    }
    return null;
}

// 格式化性能数据
function formatPerformanceData($logContent) {
    $lines = explode("\n", $logContent);
    $performanceData = [];
    
    foreach ($lines as $line) {
        if (strpos($line, 'upload_file Performance') !== false || strpos($line, 'upload_file Qiniu Error') !== false || strpos($line, 'upload_file Empty File Error') !== false) {
            // 提取JSON数据
            $data = null;
            
            // 从日志行中提取JSON数据
            if (preg_match('/upload_file Performance: (\{.*\})/', $line, $matches)) {
                $data = json_decode($matches[1], true);
                $type = 'upload_file';
            } elseif (preg_match('/upload_file Qiniu Error: (\{.*\})/', $line, $matches)) {
                $data = json_decode($matches[1], true);
                $type = 'upload_file_error';
            } elseif (preg_match('/upload_file Empty File Error: (\{.*\})/', $line, $matches)) {
                $data = json_decode($matches[1], true);
                $type = 'upload_file_error';
            }
            
            // 如果没有找到JSON，尝试从整个行中提取
            if (!$data && preg_match('/\{.*\}/', $line, $matches)) {
                $data = json_decode($matches[0], true);
                $type = strpos($line, 'Error') !== false ? 'upload_file_error' : 'upload_file';
            }
            
            // 如果仍然没有数据，创建基本结构
            if (!$data) {
                // 提取时间戳
                if (preg_match('/\[(.*?)\]/', $line, $timeMatches)) {
                    $timestamp = $timeMatches[1];
                } else {
                    $timestamp = date('Y-m-d H:i:s');
                }
                
                // 创建基本数据结构
                $data = [
                    'timestamp' => $timestamp,
                    'message' => 'upload_file Performance detected'
                ];
            }
            
            if ($data) {
                $performanceData[] = [
                    'type' => $type ?? 'upload_file',
                    'data' => $data,
                    'time' => date('Y-m-d H:i:s'),
                    'raw_line' => $line
                ];
            }
        }
    }
    
    return $performanceData;
}

// 显示性能数据
function displayPerformanceData($data) {
    if (empty($data)) {
        return;
    }
    
    echo "\n=== 性能数据 ===\n";
    foreach ($data as $item) {
        echo "时间: " . $item['time'] . "\n";
        echo "类型: " . $item['type'] . "\n";
        
        if ($item['type'] === 'upload_file' || $item['type'] === 'upload_file_error') {
            $d = $item['data'];
            
            // 显示基本信息
            if (isset($d['function'])) {
                echo "函数: " . $d['function'] . "\n";
            }
            if (isset($d['file_name'])) {
                echo "文件名: " . $d['file_name'] . "\n";
            }
            if (isset($d['file_exists'])) {
                echo "文件存在: " . ($d['file_exists'] ? '是' : '否') . "\n";
            }
            if (isset($d['file_size'])) {
                echo "文件大小: " . $d['file_size'] . " bytes\n";
            }
            if (isset($d['validation_time'])) {
                echo "验证耗时: " . $d['validation_time'] . "\n";
            }
            if (isset($d['upload_time'])) {
                echo "上传耗时: " . $d['upload_time'] . "\n";
            }
            if (isset($d['total_time'])) {
                echo "总耗时: " . $d['total_time'] . "\n";
            }
            if (isset($d['qiniu_savename'])) {
                echo "七牛文件名: " . $d['qiniu_savename'] . "\n";
            }
            if (isset($d['result_url'])) {
                echo "结果URL: " . $d['result_url'] . "\n";
            }
            if (isset($d['error'])) {
                echo "错误: " . $d['error'] . "\n";
            }
            
            // 如果没有具体数据，显示原始行
            if (count($d) <= 2) {
                echo "原始日志: " . $item['raw_line'] . "\n";
            }
        }
        echo "---\n";
    }
}

// 主监控循环
$lastFileSize = 0;
$lastLogFile = null;

while (true) {
    $currentLogFile = getLatestLogFile($logPath);
    
    if ($currentLogFile && file_exists($currentLogFile)) {
        $currentFileSize = filesize($currentLogFile);
        
        // 如果文件大小发生变化，说明有新日志
        if ($currentFileSize > $lastFileSize || $currentLogFile !== $lastLogFile) {
            $newContent = '';
            
            if ($lastFileSize > 0 && $currentLogFile === $lastLogFile) {
                // 读取新增的内容
                $handle = fopen($currentLogFile, 'r');
                fseek($handle, $lastFileSize);
                $newContent = fread($handle, $currentFileSize - $lastFileSize);
                fclose($handle);
            } else {
                // 读取整个文件（新文件）
                $newContent = file_get_contents($currentLogFile);
            }
            
            if (!empty($newContent)) {
                $performanceData = formatPerformanceData($newContent);
                displayPerformanceData($performanceData);
            }
            
            $lastFileSize = $currentFileSize;
            $lastLogFile = $currentLogFile;
        }
    }
    
    sleep(1); // 每秒检查一次
}
?> 