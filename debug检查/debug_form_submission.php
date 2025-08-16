<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));
}
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

echo "<h2>表单提交调试</h2>";

// 显示当前请求信息
echo "<h3>当前请求信息</h3>";
echo "<p><strong>请求方法:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";
echo "<p><strong>请求URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>HTTP_HOST:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";

if ($_POST) {
    echo "<h3>POST数据</h3>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    // 尝试手动插入数据测试
    if (isset($_POST['test_submit'])) {
        echo "<h3>测试数据库插入</h3>";
        try {
            $db = Typecho_Db::get();
            
            $test_data = array(
                'form_id' => 7, // test_contact 表单的ID
                'data' => json_encode($_POST),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'test',
                'status' => 'new',
                'source' => 'debug',
                'created_time' => time(),
                'modified_time' => time()
            );
            
            $insert_id = $db->query($db->insert('table.uforms_submissions')->rows($test_data));
            echo "<p>✓ 测试插入成功，ID: {$insert_id}</p>";
            
        } catch (Exception $e) {
            echo "<p>✗ 测试插入失败: {$e->getMessage()}</p>";
            echo "<p>错误追踪: {$e->getTraceAsString()}</p>";
        }
    }
} else {
    // 显示测试表单
    echo '<h3>测试POST提交</h3>';
    echo '<form method="post">';
    echo '<input name="name" placeholder="姓名" required /><br><br>';
    echo '<input name="email" type="email" placeholder="邮箱" required /><br><br>';
    echo '<textarea name="message" placeholder="留言" required></textarea><br><br>';
    echo '<button type="submit" name="test_submit">测试提交</button>';
    echo '</form>';
}

// 检查错误日志
echo "<h3>错误日志检查</h3>";
$log_files = array(
    '/error.log',
    '/usr/logs/error.log',
    '/logs/error.log',
    '/var/log/error.log'
);

$found_logs = false;
foreach ($log_files as $log_file) {
    $full_path = __TYPECHO_ROOT_DIR__ . $log_file;
    if (file_exists($full_path)) {
        $found_logs = true;
        echo "<h4>日志文件: {$full_path}</h4>";
        $logs = file_get_contents($full_path);
        $recent_logs = array_slice(explode("\n", $logs), -20); // 最后20行
        echo "<pre style='background:#f5f5f5;padding:10px;overflow:auto;max-height:200px;'>";
        foreach ($recent_logs as $line) {
            if (stripos($line, 'uforms') !== false || stripos($line, 'error') !== false) {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</pre>";
    }
}

if (!$found_logs) {
    echo "<p>未找到错误日志文件</p>";
}

// 检查PHP错误日志设置
echo "<h3>PHP日志设置</h3>";
echo "<p>log_errors: " . (ini_get('log_errors') ? '开启' : '关闭') . "</p>";
echo "<p>error_log: " . ini_get('error_log') . "</p>";
echo "<p>display_errors: " . (ini_get('display_errors') ? '开启' : '关闭') . "</p>";
?>
