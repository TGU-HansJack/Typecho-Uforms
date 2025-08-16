<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));
}
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

echo "<h2>Uforms 插件激活工具</h2>";

try {
    $db = Typecho_Db::get();
    
    // 检查插件文件是否存在
    $plugin_file = __TYPECHO_ROOT_DIR__ . '/usr/plugins/Uforms/Plugin.php';
    if (!file_exists($plugin_file)) {
        throw new Exception('插件文件不存在：' . $plugin_file);
    }
    
    // 加载插件
    require_once $plugin_file;
    
    // 检查插件类是否存在
    if (!class_exists('Uforms_Plugin')) {
        throw new Exception('插件类 Uforms_Plugin 不存在');
    }
    
    echo "<p>✓ 插件文件加载成功</p>";
    
    // 获取当前激活的插件列表
    $plugins_data = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'plugins'));
    
    if ($plugins_data) {
        $plugins = unserialize($plugins_data['value']);
    } else {
        $plugins = array('activated' => array(), 'deactivated' => array());
    }
    
    // 检查插件是否已激活
    if (isset($plugins['activated']['Uforms'])) {
        echo "<p>✓ Uforms 插件已经激活</p>";
    } else {
        echo "<p>正在激活 Uforms 插件...</p>";
        
        try {
            // 调用插件的激活方法
            $result = Uforms_Plugin::activate();
            echo "<p>✓ 插件激活成功: {$result}</p>";
            
            // 更新插件状态
            $plugins['activated']['Uforms'] = array(
                'version' => '2.0.0',
                'handle' => 'Uforms_Plugin',
                'file' => 'Uforms/Plugin.php'
            );
            
            // 从停用列表中移除（如果存在）
            unset($plugins['deactivated']['Uforms']);
            
            // 更新数据库
            $db->query($db->update('table.options')
                         ->rows(array('value' => serialize($plugins)))
                         ->where('name = ?', 'plugins'));
            
            echo "<p>✓ 插件状态已更新到数据库</p>";
            
        } catch (Exception $e) {
            echo "<p>✗ 插件激活失败: {$e->getMessage()}</p>";
            echo "<p>错误详情: {$e->getTraceAsString()}</p>";
        }
    }
    
    // 验证路由是否已添加
    echo "<h3>验证路由</h3>";
    $routes_data = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'routingTable'));
    if ($routes_data) {
        $routes = unserialize($routes_data['value']);
        $uforms_routes = 0;
        foreach ($routes as $pattern => $route) {
            if (strpos($pattern, 'uforms') !== false) {
                $uforms_routes++;
                echo "<p>✓ 路由: {$pattern}</p>";
            }
        }
        echo "<p>总共找到 {$uforms_routes} 个Uforms路由</p>";
    }
    
    echo "<h3>下一步</h3>";
    echo "<p>1. 刷新debug页面检查状态</p>";
    echo "<p>2. 创建测试表单</p>";
    echo "<p>3. 测试表单功能</p>";
    
} catch (Exception $e) {
    echo "<p>✗ 激活失败: {$e->getMessage()}</p>";
    echo "<p>文件: {$e->getFile()}:{$e->getLine()}</p>";
    echo "<pre>跟踪: {$e->getTraceAsString()}</pre>";
}
?>
