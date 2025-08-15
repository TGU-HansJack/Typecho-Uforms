<?php
if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

$db = Typecho_Db::get();
$options = Helper::options();
$request = Typecho_Request::getInstance();
$user = Typecho_Widget::widget('Widget_User');

if (!$user->pass('administrator')) {
    throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
}

// 引入必要的文件
require_once 'core/UformsHelper.php';
require_once 'frontend/frontend-functions.php';

// 设置Ajax URL
$ajaxUrl = $options->adminUrl . 'extending.php?panel=Uforms%2Fajax.php';
