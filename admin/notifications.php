<?php
require_once 'admin-functions.php';

if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<div class="main">
    <div class="body container">
<?php
// 处理操作
$request = Typecho_Request::getInstance();
$action = $request->get('action');
$notification_id = $request->get('id');

if ($action && $notification_id) {
    switch ($action) {
        case 'mark_read':
            $db->query($db->update('table.uforms_notifications')
                         ->rows(array('is_read' => 1, 'read_time' => time()))
                         ->where('id = ?', $notification_id));
            $request->throwNotice('通知已标记为已读');
            break;
            
        case 'mark_unread':
            $db->query($db->update('table.uforms_notifications')
                         ->rows(array('is_read' => 0, 'read_time' => 0))
                         ->where('id = ?', $notification_id));
            $request->throwNotice('通知已标记为未读');
            break;
            
        case 'delete':
            $db->query($db->delete('table.uforms_notifications')
                         ->where('id = ?', $notification_id));
            $request->throwNotice('通知已删除');
            break;
    }
}

// 处理批量操作
$bulk_action = $request->get('bulk_action');
if ($bulk_action) {
    $selected_notifications = $request->get('notifications', array());
    
    if (!empty($selected_notifications) && is_array($selected_notifications)) {
        switch ($bulk_action) {
            case 'mark_read':
                foreach ($selected_notifications as $id) {
                    $db->query($db->update('table.uforms_notifications')
                                 ->rows(array('is_read' => 1, 'read_time' => time()))
                                 ->where('id = ?', $id));
                }
                $request->throwNotice('选中的通知已标记为已读');
                break;
                
            case 'mark_unread':
                foreach ($selected_notifications as $id) {
                    $db->query($db->update('table.uforms_notifications')
                                 ->rows(array('is_read' => 0, 'read_time' => 0))
                                 ->where('id = ?', $id));
                }
                $request->throwNotice('选中的通知已标记为未读');
                break;
                
            case 'delete':
                foreach ($selected_notifications as $id) {
                    $db->query($db->delete('table.uforms_notifications')
                                 ->where('id = ?', $id));
                }
                $request->throwNotice('选中的通知已删除');
                break;
        }
    }
}

// 分页和筛选设置
$page = $request->get('page', 1);
$per_page = $request->get('per_page', Helper::options()->plugin('Uforms')->admin_per_page ?? 20);
$filter = $request->get('filter', 'all');

$offset = ($page - 1) * $per_page;

// 构建查询
$select = $db->select('n.*', 'f.title as form_title')
            ->from('table.uforms_notifications n')
            ->join('table.uforms_forms f', 'n.form_id = f.id', Typecho_Db::LEFT_JOIN);

// 筛选条件
if ($filter === 'unread') {
    $select->where('n.is_read = ?', 0);
}

$total = $db->fetchObject(
    $select->select('COUNT(*) as count')
)->count;

$notifications = $db->fetchAll(
    $select->order('n.created_time', Typecho_Db::SORT_DESC)->limit($per_page)->offset($offset)
);

// 获取未读通知数量
$unread_count = $db->fetchObject(
    $db->select('COUNT(*) as count')
       ->from('table.uforms_notifications')
       ->where('is_read = ?', 0)
)->count;
?>

<div class="notifications-container">
    <!-- 通知统计 -->
    <div class="notifications-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $unread_count; ?></div>
            <div class="stat-label">未读通知</div>
        </div>
    </div>
    
    <!-- 通知操作栏 -->
    <div class="notifications-toolbar">
        <div class="toolbar-left">
            <a href="?view=notifications&filter=all" class="ui button <?php echo $filter === 'all' ? 'active' : ''; ?>">
                全部通知
            </a>
            <a href="?view=notifications&filter=unread" class="ui button <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                未读通知
            </a>
        </div>
        
        <div class="toolbar-right">
            <button id="mark-all-read" class="ui button" onclick="markAllRead()">
                <i class="check circle icon"></i>标记全部已读
            </button>
        </div>
    </div>
    
    <!-- 通知列表 -->
    <form method="post" id="notifications-form">
        <div class="notifications-list">
            <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="bell outline icon"></i>
                <p>暂无通知</p>
            </div>
            <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
            <div class="notification-item <?php echo !empty($notification['is_read']) ? 'read' : 'unread'; ?>">
                <div class="notification-checkbox">
                    <div class="ui checkbox">
                        <input type="checkbox" name="notifications[]" value="<?php echo htmlspecialchars($notification['id'] ?? ''); ?>">
                        <label></label>
                    </div>
                </div>
                
                <div class="notification-icon">
                    <?php
                    $icon_map = array(
                        'new_submission' => 'inbox',
                        'form_published' => 'paper plane',
                        'form_updated' => 'pencil alternate',
                        'system' => 'cog',
                        'error' => 'exclamation triangle'
                    );
                    $type = $notification['type'] ?? 'system';
                    $icon = $icon_map[$type] ?? 'info circle';
                    ?>
                    <i class="<?php echo htmlspecialchars($icon); ?> icon"></i>
                </div>
                
                <div class="notification-content">
                    <div class="notification-header">
                        <h4 class="notification-title"><?php echo htmlspecialchars($notification['title'] ?? '无标题'); ?></h4>
                        <div class="notification-meta">
                            <span class="notification-time">
                                <?php 
                                $created_time = $notification['created_time'] ?? time();
                                echo Typecho_I18n::dateWord($created_time, time()); 
                                ?>
                            </span>
                            <?php if (!empty($notification['form_title'])): ?>
                            <span class="notification-form">
                                <i class="wpforms icon"></i> <?php echo htmlspecialchars($notification['form_title']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="notification-body">
                        <p><?php echo htmlspecialchars($notification['message'] ?? ''); ?></p>
                    </div>
                    
                    <div class="notification-actions">
                        <?php if (empty($notification['is_read'])): ?>
                        <a href="?view=notifications&action=mark_read&id=<?php echo htmlspecialchars($notification['id'] ?? ''); ?>" 
                           class="ui icon button action-read" title="标记已读">
                            <i class="check icon"></i>
                        </a>
                        <?php else: ?>
                        <a href="?view=notifications&action=mark_unread&id=<?php echo htmlspecialchars($notification['id'] ?? ''); ?>" 
                           class="ui icon button action-unread" title="标记未读">
                            <i class="undo icon"></i>
                        </a>
                        <?php endif; ?>
                        <?php 
                        $type = $notification['type'] ?? '';
                        $submission_id = $notification['submission_id'] ?? null;
                        if ($type === 'new_submission' && $submission_id): 
                        ?>
                        <a href="#" class="ui icon button action-view" title="查看提交">
                            <i class="eye icon"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($notification['data'])): ?>
                        <a href="#" class="ui icon button action-info" title="详细信息">
                            <i class="info circle icon"></i>
                        </a>
                        <?php endif; ?>
                        
                        <a href="?view=notifications&action=delete&id=<?php echo htmlspecialchars($notification['id'] ?? ''); ?>" 
                           class="ui icon button action-delete" title="删除通知" 
                           onclick="return confirm('确定要删除这条通知吗？')">
                            <i class="trash alternate outline icon"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- 批量操作 -->
        <?php if (!empty($notifications)): ?>
        <div class="bulk-actions">
            <select name="bulk_action" class="ui dropdown">
                <option value="">批量操作</option>
                <option value="mark_read">标记已读</option>
                <option value="mark_unread">标记未读</option>
                <option value="delete">删除</option>
            </select>
            <button type="submit" class="ui button">应用</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
function markAllRead() {
    if (confirm('确定要标记所有通知为已读吗？')) {
        // 实现标记全部已读的逻辑
        alert('此功能需要后端支持，此处仅为演示');
    }
}
</script>

    <link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Uforms/assets/css/notifications.css">

</div>
</div>