<?php
/**
 * Uforms 插件扩展页面
 * 
 * 解决以下问题：
 * 1. Deprecated: urlencode(): Passing null to parameter #1 ($string) of type string is deprecated
 * 2. Typecho\Plugin\Exception: 页面不存在
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

// 安全的URL编码函数，防止传入null值
if (!function_exists('safe_urlencode')) {
    function safe_urlencode($string) {
        return urlencode((string)($string ?? ''));
    }
}

try {
    // 获取请求参数
    $request = Typecho_Request::getInstance();
    $panel = $request->get('panel');
    
    // 检查panel参数是否存在
    if (empty($panel)) {
        throw new Typecho_Plugin_Exception(_t('页面不存在'), 404);
    }
    
    // 验证panel文件路径
    $panelFile = __TYPECHO_ROOT_DIR__ . '/' . $panel;
    if (!file_exists($panelFile)) {
        throw new Typecho_Plugin_Exception(_t('页面不存在'), 404);
    }
    
    // 检查用户权限
    $user = Typecho_Widget::widget('Widget_User');
    if (!$user->hasLogin()) {
        throw new Typecho_Widget_Exception(_t('未登录'), 403);
    }
    
    // 包含面板文件
    include $panelFile;
    
} catch (Typecho_Plugin_Exception $e) {
    // 插件异常处理
    $message = $e->getMessage();
    $code = $e->getCode();
    
    if (defined('__TYPECHO_DEBUG__') && __TYPECHO_DEBUG__) {
        echo '<pre>';
        echo '错误信息: ' . $message . "\n";
        echo '错误代码: ' . $code . "\n";
        echo '文件: ' . $e->getFile() . "\n";
        echo '行号: ' . $e->getLine() . "\n";
        echo 'Trace: ' . $e->getTraceAsString() . "\n";
        echo '</pre>';
    } else {
        // 生产环境显示友好的错误信息
        echo '<div style="padding: 20px; text-align: center;">';
        echo '<h2>页面错误</h2>';
        echo '<p>' . $message . '</p>';
        echo '<p><a href="javascript:history.back()">返回上一页</a></p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    // 其他异常处理
    if (defined('__TYPECHO_DEBUG__') && __TYPECHO_DEBUG__) {
        echo '<pre>';
        echo '未捕获异常: ' . $e->getMessage() . "\n";
        echo '文件: ' . $e->getFile() . "\n";
        echo '行号: ' . $e->getLine() . "\n";
        echo 'Trace: ' . $e->getTraceAsString() . "\n";
        echo '</pre>';
    } else {
        echo '<div style="padding: 20px; text-align: center;">';
        echo '<h2>系统错误</h2>';
        echo '<p>发生未知错误，请联系管理员。</p>';
        echo '</div>';
    }
}