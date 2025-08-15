<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<div class="main">
    <div class="body container">
<?php
// 初始化request对象
$request = Typecho_Request::getInstance();
// 获取视图类型
$view_type = $request->get('type', 'overview');
// 获取日期范围参数，设置默认值为'7days'
$date_range = $request->get('date_range', '7days');
// 获取表单ID参数
$form_id = $request->get('form_id', '');

// 获取表单列表用于筛选
$forms = UformsHelper::getForms();
?>

<div class="view-container">
    <!-- 顶部导航 -->
    <div class="view-header">
        <h2>数据视图</h2>
        <nav class="view-nav">
            <a href="?view=view&type=overview" class="ui button <?php echo $view_type === 'overview' ? 'active' : ''; ?>">
                <i class="tachometer alternate icon"></i>概览
            </a>
            <a href="?view=view&type=submissions" class="ui button <?php echo $view_type === 'submissions' ? 'active' : ''; ?>">
                <i class="table icon"></i>提交数据
            </a>
            <a href="?view=view&type=calendar" class="ui button <?php echo $view_type === 'calendar' ? 'active' : ''; ?>">
                <i class="calendar alternate icon"></i>日历视图
            </a>
            <a href="?view=view&type=analytics" class="ui button <?php echo $view_type === 'analytics' ? 'active' : ''; ?>">
                <i class="chart line icon"></i>数据分析
            </a>
        </nav>
    </div>
    
    <!-- 顶部工具栏 -->
    <div class="view-toolbar">
        <div class="toolbar-left">
            <select id="form-filter" class="ui dropdown" onchange="updateView()">
                <option value="">所有表单</option>
                <?php foreach ($forms as $form): ?>
                <option value="<?php echo $form['id']; ?>" <?php echo $form_id == $form['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($form['title']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <?php if (in_array($view_type, array('overview', 'submissions', 'analytics'))): ?>
            <select id="date-range" class="ui dropdown" onchange="updateView()">
                <option value="7days" <?php echo $date_range === '7days' ? 'selected' : ''; ?>>最近7天</option>
                <option value="30days" <?php echo $date_range === '30days' ? 'selected' : ''; ?>>最近30天</option>
                <option value="3months" <?php echo $date_range === '3months' ? 'selected' : ''; ?>>最近3个月</option>
                <option value="1year" <?php echo $date_range === '1year' ? 'selected' : ''; ?>>最近1年</option>
                <option value="all" <?php echo $date_range === 'all' ? 'selected' : ''; ?>>全部时间</option>
            </select>
            <?php endif; ?>
            
            <button id="export-data" class="ui button">
                <i class="download icon"></i>导出数据
            </button>
        </div>
    </div>
    
    <!-- 视图内容 -->
    <div class="view-content">
        <?php
        switch ($view_type) {
            case 'overview':
                include dirname(__FILE__) . '/../views/overview.php';
                break;
            case 'submissions':
                include dirname(__FILE__) . '/../views/submissions.php';
                break;
            case 'calendar':
                include dirname(__FILE__) . '/../views/calendar.php';
                break;
            case 'analytics':
                include dirname(__FILE__) . '/../views/analytics.php';
                break;
            default:
                include dirname(__FILE__) . '/../views/overview.php';
        }
        ?>
    </div>
</div>

</div>
</div>

<script>
function updateView() {
    // 获取选择值
    var formFilter = document.getElementById('form-filter').value;
    var dateRange = document.getElementById('date-range').value;
    
    // 构建URL
    var url = '?view=view&type=<?php echo $view_type; ?>';
    if (formFilter) url += '&form_id=' + formFilter;
    if (dateRange) url += '&date_range=' + dateRange;
    
    // 重新加载页面
    window.location.href = url;
}

// 导出数据功能
document.addEventListener('DOMContentLoaded', function() {
    var exportButton = document.getElementById('export-data');
    if (exportButton) {
        exportButton.addEventListener('click', function() {
            var formFilter = document.getElementById('form-filter').value;
            var dateRange = document.getElementById('date-range').value;
            
            var url = '?view=export&type=<?php echo $view_type; ?>';
            if (formFilter) url += '&form_id=' + formFilter;
            if (dateRange) url += '&date_range=' + dateRange;
            
            window.open(url, '_blank');
        });
    }
});
</script>

    <link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Uforms/assets/css/view.css">