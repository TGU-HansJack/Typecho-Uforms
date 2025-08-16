<?php
// 启用错误日志
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/uforms_error.log');
error_reporting(E_ALL);

// 测试日志记录
error_log('=== Uforms Error Log Test ===');
error_log('Log file created at: ' . date('Y-m-d H:i:s'));

echo "错误日志已启用，日志文件：" . __DIR__ . '/uforms_error.log';
?>
