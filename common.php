<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 安全的URL编码函数
if (!function_exists('safe_urlencode')) {
    function safe_urlencode($string) {
        return urlencode((string)($string ?? ''));
    }
}

$options = Helper::options();
$ajaxUrl = $options->adminUrl . 'extending.php?panel=' . safe_urlencode('Uforms/ajax.php');

// 引入必要的文件
require_once 'core/UformsHelper.php';
require_once 'frontend/frontend-functions.php';