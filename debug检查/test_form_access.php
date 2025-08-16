<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));
}
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

// 启用错误日志
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/uforms_error.log');
error_reporting(E_ALL);

echo "<h2>Uforms 表单访问测试</h2>";

// 测试路由解析
echo "<h3>路由测试</h3>";

// 模拟访问表单URL
$test_urls = array(
    '/uforms/form/test_contact',
    '/uforms/form/7', // ID方式
    '/uforms/submit'
);

foreach ($test_urls as $url) {
    echo "<h4>测试URL: {$url}</h4>";
    
    // 检查路由匹配
    if (preg_match('/^\/uforms\/form\/([^\/]+)/', $url, $matches)) {
        echo "<p>✓ 匹配表单名称路由: {$matches[1]}</p>";
    } elseif (preg_match('/^\/uforms\/form\/(\d+)/', $url, $matches)) {
        echo "<p>✓ 匹配表单ID路由: {$matches[1]}</p>";
    } elseif (preg_match('/^\/uforms\/submit/', $url)) {
        echo "<p>✓ 匹配提交路由</p>";
    } else {
        echo "<p>✗ 无匹配路由</p>";
    }
}

// 测试表单渲染
echo "<h3>表单渲染测试</h3>";

try {
    require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/Uforms/core/UformsHelper.php';
    require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/Uforms/frontend/front.php';
    
    $form = UformsHelper::getFormByName('test_contact');
    if ($form) {
        echo "<p>✓ 获取表单成功: {$form['title']}</p>";
        
        $fields = UformsHelper::getFormFields($form['id']);
        echo "<p>✓ 获取字段成功，数量: " . count($fields) . "</p>";
        
        // 测试渲染
        $html = UformsFront::renderFormHTML($form, $fields, array(), array(), 'test');
        if (strlen($html) > 100) {
            echo "<p>✓ 表单HTML渲染成功，长度: " . strlen($html) . " 字符</p>";
        } else {
            echo "<p>✗ 表单HTML渲染可能有问题，长度: " . strlen($html) . " 字符</p>";
        }
        
    } else {
        echo "<p>✗ 未找到test_contact表单</p>";
    }
    
} catch (Exception $e) {
    echo "<p>✗ 表单测试失败: {$e->getMessage()}</p>";
}

// 生成实际测试链接
echo "<h3>实际测试链接</h3>";
$base_url = 'http://' . $_SERVER['HTTP_HOST'];
$test_form_url = $base_url . '/uforms/form/test_contact';

echo "<p><a href='{$test_form_url}' target='_blank'>测试表单链接: {$test_form_url}</a></p>";

// 创建直接POST测试
echo "<h3>直接POST测试</h3>";
echo "<p>使用以下表单测试直接提交到表单URL：</p>";

echo '<form method="post" action="' . $test_form_url . '" target="_blank">';
echo '<input name="uform_name" type="hidden" value="test_contact" />';
echo '<input name="name" placeholder="姓名" value="测试用户" required /><br><br>';
echo '<input name="email" type="email" placeholder="邮箱" value="test@example.com" required /><br><br>';
echo '<select name="subject" required>';
echo '<option value="">请选择咨询类型</option>';
echo '<option value="general" selected>一般咨询</option>';
echo '<option value="technical">技术支持</option>';
echo '</select><br><br>';
echo '<textarea name="message" placeholder="留言" required>这是一条测试留言</textarea><br><br>';
echo '<button type="submit">提交到表单URL</button>';
echo '</form>';

echo "<h3>检查结果</h3>";
echo "<p>1. 点击上面的表单链接，查看表单是否正常显示</p>";
echo "<p>2. 使用直接POST测试提交数据</p>";
echo "<p>3. 提交后检查 uforms_error.log 文件中的错误信息</p>";
echo "<p>4. 再次运行 debug_uforms.php 查看提交记录数量</p>";
?>
