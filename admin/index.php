<?php
// 检查用户权限
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$db = Typecho_Db::get();
$options = Helper::options();
$request = Typecho_Request::getInstance();
$user = Typecho_Widget::widget('Widget_User');

if (!$user->pass('administrator')) {
    throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
}

// 安全的URL编码函数
if (!function_exists('safe_urlencode')) {
    function safe_urlencode($string) {
        return urlencode((string)($string ?? ''));
    }
}

// 引入必要的文件
require_once 'admin-functions.php';

// 处理 AJAX 请求
if ($request->isPost() && $request->get('action') === 'ajax') {
    require_once 'ajax.php';
    exit;
}

// 获取当前视图
$view = $request->get('view', 'manage');
$allowed_views = array('manage', 'create', 'view', 'notifications');

if (!in_array($view, $allowed_views)) {
    $view = 'manage';
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Uforms 表单系统</title>
    <?php if ($view === 'manage'): ?>
        <link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Uforms/assets/css/manage.css">
    <?php elseif ($view === 'create'): ?>
        <link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Uforms/assets/css/create.css">
    <?php elseif ($view === 'view'): ?>
        <link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Uforms/assets/css/view.css">
    <?php elseif ($view === 'notifications'): ?>
        <link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Uforms/assets/css/notifications.css">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Uforms/lib/fullcalendar/dist/index.global.min.css">
    <link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Uforms/lib/fomantic-ui/dist/semantic.min.css">
    <script src="<?php echo $options->adminStaticUrl; ?>js/jquery.js"></script>
    <script src="<?php echo $options->pluginUrl; ?>/Uforms/lib/echarts/echarts.min.js"></script>
    <script src="<?php echo $options->pluginUrl; ?>/Uforms/lib/fullcalendar/dist/index.global.min.js"></script>
    <script src="<?php echo $options->pluginUrl; ?>/Uforms/assets/js/sortable.min.js"></script>
</head>
<body>
    <div id="uforms-admin">
        <!-- 顶部导航 -->
        <div class="uforms-header">
            <h1>Uforms 表单系统</h1>
            <nav class="uforms-nav">
                <?php
                $base_url = $options->adminUrl . 'extending.php?panel=' . safe_urlencode('Uforms/index.php') . '&view=';
                ?>
                <a href="<?php echo $base_url . safe_urlencode('manage'); ?>" class="<?php echo $view === 'manage' ? 'active' : ''; ?>">
                    <i class="list icon"></i>管理
                </a>
                <a href="<?php echo $base_url . safe_urlencode('create'); ?>" class="<?php echo $view === 'create' ? 'active' : ''; ?>">
                    <i class="plus icon"></i>创建
                </a>
                <a href="<?php echo $base_url . safe_urlencode('view'); ?>" class="<?php echo $view === 'view' ? 'active' : ''; ?>">
                    <i class="chart line icon"></i>视图
                </a>
                <a href="<?php echo $base_url . safe_urlencode('notifications'); ?>" class="<?php echo $view === 'notifications' ? 'active' : ''; ?>">
                    <i class="bell outline icon"></i>通知
                </a>
            </nav>
        </div>

        <!-- 主内容区 -->
        <div class="uforms-main">
            <?php
            $file_path = dirname(__FILE__) . '/' . $view . '.php';
            if (file_exists($file_path)) {
                include $file_path;
            } else {
                echo '<div class="error">页面不存在：' . htmlspecialchars($view ?? 'unknown') . '</div>';
                include 'manage.php';  // 默认显示管理页面
            }
            ?>
        </div>
    </div>

    <script src="<?php echo $options->pluginUrl; ?>/Uforms/assets/js/admin.js"></script>
</body>
</html>

<?php
include 'footer.php';
?>