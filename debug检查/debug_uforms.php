<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));
}
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

echo "<h2>Uforms 调试信息</h2>";

try {
    $db = Typecho_Db::get();
    echo "<p>✓ 数据库连接成功</p>";
    
    // 检查表是否存在
    $tables = array('uforms_forms', 'uforms_fields', 'uforms_submissions');
    foreach ($tables as $table) {
        try {
            $count = $db->fetchObject($db->select('COUNT(*) as count')->from('table.' . $table))->count;
            echo "<p>✓ 表 {$table} 存在，记录数：{$count}</p>";
        } catch (Exception $e) {
            echo "<p>✗ 表 {$table} 不存在或有错误：{$e->getMessage()}</p>";
        }
    }
    
    // 检查插件是否激活
    echo "<h3>插件状态检查</h3>";
    try {
        $plugins = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'plugins'));
        if ($plugins) {
            $activated_plugins = unserialize($plugins['value']);
            if (isset($activated_plugins['activated']['Uforms'])) {
                echo "<p>✓ Uforms 插件已激活</p>";
                print_r($activated_plugins['activated']['Uforms']);
            } else {
                echo "<p>✗ Uforms 插件未激活</p>";
                echo "<p><strong>需要先激活插件！</strong></p>";
                
                // 显示如何激活
                echo "<h4>激活方法：</h4>";
                echo "<ol>";
                echo "<li>进入Typecho后台</li>";
                echo "<li>前往 控制台 > 插件管理</li>";
                echo "<li>找到 Uforms 插件并点击激活</li>";
                echo "</ol>";
            }
        }
    } catch (Exception $e) {
        echo "<p>✗ 无法检查插件状态：{$e->getMessage()}</p>";
    }
    
    // 检查路由表
    echo "<h3>路由信息</h3>";
    try {
        $routes = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'routingTable'));
        if ($routes) {
            $routing_table = unserialize($routes['value']);
            $uforms_routes = array();
            foreach ($routing_table as $pattern => $route) {
                if (strpos($pattern, 'uforms') !== false) {
                    $uforms_routes[$pattern] = $route;
                }
            }
            
            if (!empty($uforms_routes)) {
                echo "<p>✓ 找到Uforms路由：</p>";
                foreach ($uforms_routes as $pattern => $route) {
                    echo "<p>　├─ {$pattern} -> {$route['widget']}</p>";
                }
            } else {
                echo "<p>✗ 未找到Uforms路由</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p>✗ 无法检查路由：{$e->getMessage()}</p>";
    }
    
    // 测试表单数据
    echo "<h3>现有表单数据</h3>";
    try {
        $forms = $db->fetchAll($db->select('*')->from('table.uforms_forms')->limit(10));
        if (empty($forms)) {
            echo "<p>✗ 没有找到任何表单</p>";
        } else {
            foreach ($forms as $form) {
                echo "<p>表单: {$form['id']} - {$form['name']} - {$form['title']} - {$form['status']}</p>";
                
                // 检查字段
                $fields = $db->fetchAll($db->select('*')->from('table.uforms_fields')->where('form_id = ?', $form['id']));
                echo "<p>　└─ 字段数量: " . count($fields) . "</p>";
                if (!empty($fields)) {
                    foreach ($fields as $field) {
                        $required = $field['is_required'] ? '必填' : '可选';
                        echo "<p>　　├─ {$field['field_name']} ({$field['field_type']}) - {$required}</p>";
                    }
                }
            }
        }
    } catch (Exception $e) {
        echo "<p>✗ 无法查询表单数据：{$e->getMessage()}</p>";
    }
    
} catch (Exception $e) {
    echo "<p>✗ 错误：{$e->getMessage()}</p>";
    echo "<p>文件：{$e->getFile()}:{$e->getLine()}</p>";
    echo "<pre>跟踪：{$e->getTraceAsString()}</pre>";
}

echo "<h3>系统信息</h3>";
echo "<p>PHP版本：" . PHP_VERSION . "</p>";
try {
    echo "<p>Typecho版本：" . Typecho_Common::VERSION . "</p>";
} catch (Exception $e) {
    echo "<p>无法获取Typecho版本</p>";
}

// 检查文件权限
echo "<h3>文件权限检查</h3>";
$plugin_dir = __TYPECHO_ROOT_DIR__ . '/usr/plugins/Uforms';
if (is_dir($plugin_dir)) {
    echo "<p>✓ 插件目录存在: {$plugin_dir}</p>";
    $required_files = array('Plugin.php', 'Action.php', 'core/UformsHelper.php', 'frontend/front.php');
    foreach ($required_files as $file) {
        $filepath = $plugin_dir . '/' . $file;
        if (file_exists($filepath)) {
            echo "<p>✓ 文件存在: {$file}</p>";
        } else {
            echo "<p>✗ 文件缺失: {$file}</p>";
        }
    }
} else {
    echo "<p>✗ 插件目录不存在: {$plugin_dir}</p>";
}
?>
