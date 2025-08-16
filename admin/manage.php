<?php
// 文件名：manage.php - 修复版本
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 安全的URL编码函数
if (!function_exists('safe_urlencode')) {
    function safe_urlencode($string) {
        return urlencode((string)($string ?? ''));
    }
}

// 引入必要的文件
require_once 'admin-functions.php';

// 获取当前用户
$user = Typecho_Widget::widget('Widget_User');
$db = Typecho_Db::get();
$options = Helper::options();
$request = Typecho_Request::getInstance();

// 获取分页参数
$page = max(1, intval($request->get('page', 1)));
$per_page = $options->plugin('Uforms')->admin_per_page ?? 20;

// 获取搜索和筛选参数
$search = $request->get('search', '');
$status_filter = $request->get('status', '');

// 构建查询条件
$select = $db->select('f.*')->from('table.uforms_forms f');

// 搜索条件
if ($search) {
    $select->where('f.title LIKE ? OR f.name LIKE ?', '%' . $search . '%', '%' . $search . '%');
}

// 状态筛选
if ($status_filter) {
    $select->where('f.status = ?', $status_filter);
}

// 获取总数
$count_select = clone $select;
$total = $db->fetchObject($count_select->select('COUNT(*) AS count'))->count;
$total_pages = ceil($total / $per_page);

// 应用分页
$offset = ($page - 1) * $per_page;
$select->order('f.modified_time', Typecho_Db::SORT_DESC)
       ->limit($per_page)
       ->offset($offset);

// 获取表单列表
$forms = $db->fetchAll($select);

// 获取每个表单的提交数
foreach ($forms as &$form) {
    $form['submission_count'] = $db->fetchObject(
        $db->select('COUNT(*) AS count')->from('table.uforms_submissions')
           ->where('form_id = ?', $form['id'])
    )->count;
}
unset($form); // 释放引用

// 处理批量操作
if ($request->isPost() && $request->get('action')) {
    $action = $request->get('action');
    $form_ids = $request->getArray('form_ids');
    
    if (!empty($form_ids) && is_array($form_ids)) {
        switch ($action) {
            case 'bulk_delete':
                foreach ($form_ids as $form_id) {
                    // 删除表单相关的字段
                    $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $form_id));
                    
                    // 删除表单相关的提交数据
                    $submissions = $db->fetchAll($db->select('id')->from('table.uforms_submissions')->where('form_id = ?', $form_id));
                    foreach ($submissions as $submission) {
                        // 删除提交相关的文件
                        $db->query($db->delete('table.uforms_files')->where('submission_id = ?', $submission['id']));
                    }
                    $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $form_id));
                    
                    // 删除表单本身
                    $db->query($db->delete('table.uforms_forms')->where('id = ?', $form_id));
                }
                break;
                
            case 'bulk_publish':
                foreach ($form_ids as $form_id) {
                    $db->query($db->update('table.uforms_forms')
                                  ->rows(array('status' => 'published', 'modified_time' => time()))
                                  ->where('id = ?', $form_id));
                }
                break;
                
            case 'bulk_draft':
                foreach ($form_ids as $form_id) {
                    $db->query($db->update('table.uforms_forms')
                                  ->rows(array('status' => 'draft', 'modified_time' => time()))
                                  ->where('id = ?', $form_id));
                }
                break;
                
            case 'bulk_archive':
                foreach ($form_ids as $form_id) {
                    $db->query($db->update('table.uforms_forms')
                                  ->rows(array('status' => 'archived', 'modified_time' => time()))
                                  ->where('id = ?', $form_id));
                }
                break;
        }
        
        // 重定向以避免重复提交
        $redirect_url = $options->adminUrl . 'extending.php?panel=' . safe_urlencode('Uforms/index.php');
        if ($search) {
            $redirect_url .= '&search=' . safe_urlencode($search);
        }
        if ($status_filter) {
            $redirect_url .= '&status=' . safe_urlencode($status_filter);
        }
        if ($page > 1) {
            $redirect_url .= '&page=' . safe_urlencode((string)$page);
        }
        header('Location: ' . $redirect_url);
        exit;
    }
}
?>

<div class="main">
    <div class="body container">
        <div class="page-title">表单管理</div>

        <div class="forms-toolbar">
            <div class="toolbar-left">
                <a href="<?php echo $options->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/index.php'); ?>&view=<?php echo safe_urlencode('create'); ?>" class="ui primary button">
                    <i class="plus icon"></i> 新建表单
                </a>
                <div class="bulk-actions" style="display: none;">
                    <select id="bulk-action" class="ui dropdown">
                        <option value="">批量操作</option>
                        <option value="delete">删除选中</option>
                        <option value="publish">发布选中</option>
                        <option value="draft">转为草稿</option>
                        <option value="archive">归档选中</option>
                    </select>
                    <button id="apply-bulk" class="ui button">应用</button>
                </div>
            </div>
            
            <div class="toolbar-right">
                <div class="ui icon input">
                    <input type="text" id="search-input" placeholder="搜索表单..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    <i id="search-btn" class="search icon"></i>
                </div>
                
                <div class="filter-box">
                    <select id="status-filter" class="ui dropdown">
                        <option value="">所有状态</option>
                        <option value="draft" <?php echo ($status_filter === 'draft') ? 'selected' : ''; ?>>草稿</option>
                        <option value="published" <?php echo ($status_filter === 'published') ? 'selected' : ''; ?>>已发布</option>
                        <option value="archived" <?php echo ($status_filter === 'archived') ? 'selected' : ''; ?>>已归档</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="forms-table-container">
            <?php if (empty($forms)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="wpforms icon"></i>
                </div>
                <h3>暂无表单</h3>
                <p>创建您的第一个表单开始收集数据</p>
                <a href="?view=<?php echo safe_urlencode('create'); ?>" class="ui primary button">创建表单</a>
            </div>
            <?php else: ?>
            <table class="ui celled table forms-table">
                <thead>
                    <tr>
                        <th class="check-column">
                            <div class="ui checkbox">
                                <input type="checkbox" id="select-all">
                                <label for="select-all"></label>
                            </div>
                        </th>
                        <th class="title-column">表单名称</th>
                        <th class="status-column">状态</th>
                        <th class="submissions-column">提交数</th>
                        <th class="author-column">创建者</th>
                        <th class="date-column">修改时间</th>
                        <th class="actions-column">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $form): ?>
                    <?php
                    $submission_count = isset($form['submission_count']) ? $form['submission_count'] : 0;
                    
                    $author = $db->fetchRow(
                        $db->select('screenName')->from('table.users')
                           ->where('uid = ?', $form['author_id'])
                    );
                    ?>
                    <tr>
                        <td class="check-column">
                            <div class="ui checkbox">
                                <input type="checkbox" name="form_ids[]" value="<?php echo $form['id']; ?>">
                                <label for="form_<?php echo $form['id']; ?>"></label>
                            </div>
                        </td>
                        <td class="title-column">
                            <div class="form-title">
                                <strong><?php echo htmlspecialchars($form['title']); ?></strong>
                                <div class="form-meta">
                                    <span class="form-name"><?php echo htmlspecialchars($form['name']); ?></span>
                                    <?php if ($form['description']): ?>
                                    <span class="form-description"><?php echo htmlspecialchars(mb_substr($form['description'], 0, 50)); ?>...</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="status-column">
                            <span class="ui label status-badge status-<?php echo $form['status']; ?>">
                                <?php
                                $status_labels = array(
                                    'draft' => '草稿',
                                    'published' => '已发布',
                                    'archived' => '已归档'
                                );
                                echo $status_labels[$form['status']] ?? $form['status'];
                                ?>
                            </span>
                        </td>
                        <td class="submissions-column">
                            <a href="?view=submissions&form_id=<?php echo $form['id']; ?>" class="submissions-link">
                                <?php echo $submission_count; ?>
                            </a>
                        </td>
                        <td class="author-column">
                            <?php echo htmlspecialchars($author ? $author['screenName'] : '未知'); ?>
                        </td>
                        <td class="date-column">
                            <span title="<?php echo UformsHelper::formatTime($form['modified_time']); ?>">
                                <?php echo Typecho_I18n::dateWord($form['modified_time'], time()); ?>
                            </span>
                        </td>
                        <td class="actions-column">
                            <div class="row-actions">
                                <a href="<?php echo $options->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/index.php'); ?>&view=<?php echo safe_urlencode('create'); ?>&id=<?php echo safe_urlencode((string)($form['id'] ?? '')); ?>" class="ui icon button action-edit" title="编辑">
                                    <i class="pencil alternate icon"></i>
                                </a>
                                
                                <?php if ($form['status'] === 'published'): ?>
                                <a href="<?php echo UformsHelper::getFormUrl($form['id']); ?>" target="_blank" class="ui icon button action-view" title="查看">
                                    <i class="eye icon"></i>
                                </a>
                                <button class="ui icon button action-code" data-form-id="<?php echo htmlspecialchars((string)($form['id'] ?? '')); ?>" title="获取代码">
                                    <i class="code icon"></i>
                                </button>
                                <?php endif; ?>
                                
                                <div class="ui icon top right pointing dropdown button action-more" title="更多操作">
                                    <i class="ellipsis vertical icon"></i>
                                    <div class="menu">
                                        <a class="item" href="<?php echo $options->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/index.php'); ?>&view=<?php echo safe_urlencode('create'); ?>&action=<?php echo safe_urlencode('duplicate'); ?>&id=<?php echo safe_urlencode((string)($form['id'] ?? '')); ?>">
                                            <i class="copy icon"></i> 复制表单
                                        </a>
                                        
                                        <?php if ($form['status'] !== 'published'): ?>
                                        <a class="item" href="?action=<?php echo safe_urlencode('change_status'); ?>&id=<?php echo safe_urlencode((string)($form['id'] ?? '')); ?>&status=<?php echo safe_urlencode('published'); ?>">
                                            <i class="paper plane icon"></i> 发布表单
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($form['status'] !== 'draft'): ?>
                                        <a class="item" href="?action=<?php echo safe_urlencode('change_status'); ?>&id=<?php echo safe_urlencode((string)($form['id'] ?? '')); ?>&status=<?php echo safe_urlencode('draft'); ?>">
                                            <i class="file alternate icon"></i> 转为草稿
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($form['status'] !== 'archived'): ?>
                                        <a class="item" href="?action=<?php echo safe_urlencode('change_status'); ?>&id=<?php echo safe_urlencode((string)($form['id'] ?? '')); ?>&status=<?php echo safe_urlencode('archived'); ?>">
                                            <i class="archive icon"></i> 归档表单
                                        </a>
                                        <?php endif; ?>
                                        
                                        <div class="dropdown-divider"></div>
                                        
                                        <a class="item action-delete danger" href="?action=<?php echo safe_urlencode('delete'); ?>&id=<?php echo safe_urlencode((string)($form['id'] ?? '')); ?>" data-id="<?php echo $form['id']; ?>">
                                            <i class="trash alternate icon"></i> 删除表单
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- 分页 -->
            <?php if ($total > $per_page): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="<?php echo $options->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/index.php'); ?>&page=<?php echo safe_urlencode((string)($page - 1)); ?><?php echo $search ? '&search=' . safe_urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . safe_urlencode($status_filter) : ''; ?>" class="prev">上一页</a>
                <?php endif; ?>
                
                <span class="current">第 <?php echo $page; ?> 页，共 <?php echo $total_pages; ?> 页</span>
                
                <?php if ($page < $total_pages): ?>
                <a href="<?php echo $options->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/index.php'); ?>&page=<?php echo safe_urlencode((string)($page + 1)); ?><?php echo $search ? '&search=' . safe_urlencode($search) : ''; ?><?php echo $status_filter ? '&status=' . safe_urlencode($status_filter) : ''; ?>" class="next">下一页</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 代码获取弹窗 -->
<div id="code-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>获取表单代码</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="code-tabs">
                <button class="code-tab active" data-tab="link">直接链接</button>
                <button class="code-tab" data-tab="iframe">iframe嵌入</button>
                <button class="code-tab" data-tab="shortcode">短代码</button>
                <button class="code-tab" data-tab="api">API接口</button>
            </div>
            <div class="code-content">
                <div class="code-tab-content active" id="link-tab">
                    <label>表单访问链接：</label>
                    <div class="code-input-group">
                        <input type="text" id="form-link" readonly>
                        <button id="copy-link" class="btn">复制链接</button>
                    </div>
                    <div class="code-example">
                        <p>用户可以通过此链接直接访问表单</p>
                    </div>
                </div>
                <div class="code-tab-content" id="iframe-tab">
                    <label>iframe嵌入代码：</label>
                    <div class="code-input-group">
                        <textarea id="iframe-code" rows="4" readonly></textarea>
                        <button id="copy-iframe" class="btn">复制代码</button>
                    </div>
                    <div class="iframe-options">
                        <div class="form-group">
                            <label>宽度:</label>
                            <input type="text" id="iframe-width" value="100%" placeholder="例如: 100% 或 800px">
                        </div>
                        <div class="form-group">
                            <label>高度:</label>
                            <input type="text" id="iframe-height" value="600px" placeholder="例如: 600px">
                        </div>
                        <button id="update-iframe" class="btn">更新代码</button>
                    </div>
                </div>
                <div class="code-tab-content" id="shortcode-tab">
                    <label>短代码：</label>
                    <div class="code-input-group">
                        <input type="text" id="shortcode" readonly>
                        <button id="copy-shortcode" class="btn">复制短代码</button>
                    </div>
                    <div class="code-example">
                        <p>在文章或页面中使用：</p>
                        <code>[uforms name="contact"]</code><br>
                        <code>[uforms id="123"]</code>
                    </div>
                </div>
                <div class="code-tab-content" id="api-tab">
                    <label>API提交地址：</label>
                    <div class="code-input-group">
                        <input type="text" id="api-url" readonly>
                        <button id="copy-api" class="btn">复制API</button>
                    </div>
                    <div class="api-docs">
                        <h5>API文档：</h5>
                        <p><strong>方法：</strong>POST</p>
                        <p><strong>内容类型：</strong>application/json</p>
                        <p><strong>请求示例：</strong></p>
                        <pre><code>{
  "field_name_1": "value1",
  "field_name_2": "value2"
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.page-title {
    font-size: 24px;
    font-weight: bold;
    padding: 30px 0 20px;
    color: #333;
}

.forms-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    flex-wrap: wrap;
    gap: 15px;
}

.toolbar-left, .toolbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.toolbar-right {
    margin-left: auto;
}

.ui.input {
    min-width: 250px;
}

.filter-box {
    min-width: 120px;
}

.check-column {
    width: 40px;
    text-align: center;
}

.title-column {
    min-width: 200px;
}

.status-column {
    width: 100px;
}

.submissions-column {
    width: 80px;
    text-align: center;
}

.author-column {
    width: 120px;
}

.date-column {
    width: 150px;
}

.actions-column {
    width: 150px;
    text-align: center;
}

.form-title strong {
    display: block;
    font-size: 16px;
    margin-bottom: 5px;
}

.form-meta {
    font-size: 12px;
    color: #666;
}

.form-name {
    display: block;
    color: #999;
    margin-bottom: 3px;
}

.form-description {
    display: block;
    color: #999;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.status-badge {
    font-size: 12px;
    padding: 4px 8px;
}

.status-draft {
    background-color: #ffc107;
    color: #212529;
}

.status-published {
    background-color: #28a745;
    color: #fff;
}

.status-archived {
    background-color: #6c757d;
    color: #fff;
}

.submissions-link {
    color: #007bff;
    text-decoration: none;
    font-weight: bold;
}

.submissions-link:hover {
    text-decoration: underline;
}

.row-actions {
    display: flex;
    gap: 5px;
    justify-content: center;
}

.action-edit, .action-view, .action-code, .action-more {
    padding: 6px 8px;
}

.action-more {
    min-width: auto;
}

.danger {
    color: #dc3545 !important;
}

.dropdown-divider {
    height: 1px;
    background-color: #dee2e6;
    margin: 0.5rem 0;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin-bottom: 10px;
    color: #333;
}

.empty-state p {
    margin-bottom: 20px;
    color: #666;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px 0;
    gap: 10px;
}

.pagination a {
    display: inline-block;
    padding: 8px 12px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #007bff;
    text-decoration: none;
    border-radius: 4px;
}

.pagination a:hover {
    background: #e9ecef;
}

.pagination .current {
    padding: 8px 12px;
    color: #333;
}

.pagination .prev, .pagination .next {
    font-weight: bold;
}

/* 模态框样式 */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
}

.modal-body {
    padding: 20px;
}

.code-tabs {
    display: flex;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
}

.code-tab {
    padding: 10px 15px;
    background: none;
    border: none;
    cursor: pointer;
    border-bottom: 2px solid transparent;
}

.code-tab.active {
    border-bottom-color: #007bff;
    color: #007bff;
    font-weight: bold;
}

.code-content {
    min-height: 200px;
}

.code-tab-content {
    display: none;
}

.code-tab-content.active {
    display: block;
}

.code-input-group {
    display: flex;
    margin-bottom: 15px;
    gap: 10px;
}

.code-input-group input,
.code-input-group textarea {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: monospace;
}

.btn {
    padding: 8px 12px;
    background: #007bff;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn:hover {
    background: #0056b3;
}

.iframe-options {
    display: flex;
    gap: 10px;
    align-items: end;
    flex-wrap: wrap;
}

.iframe-options .form-group {
    flex: 1;
    min-width: 120px;
}

.iframe-options input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.code-example {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-top: 15px;
}

.code-example code {
    display: block;
    padding: 5px 0;
    font-family: monospace;
}

.api-docs pre {
    background: #2d2d2d;
    color: #f8f8f2;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    margin: 10px 0;
    font-size: 13px;
}

pre code {
    background: none;
    padding: 0;
    color: inherit;
}

/* 响应式样式 */
@media (max-width: 768px) {
    .page-title {
        padding: 20px;
        font-size: 20px;
    }
    
    .forms-toolbar {
        padding: 15px 20px;
        flex-direction: column;
        align-items: stretch;
    }
    
    .toolbar-left,
    .toolbar-right {
        flex-direction: column;
        align-items: stretch;
    }
    
    .forms-table-container {
        padding: 0 20px 20px;
        overflow-x: auto;
    }
    
    .forms-table {
        min-width: 800px;
    }
    
    .form-title strong {
        font-size: 14px;
    }
    
    .date-column {
        font-size: 12px;
    }
    
    .code-input-group {
        flex-direction: column;
    }
    
    .iframe-options {
        flex-direction: column;
        align-items: stretch;
    }
    
    .iframe-options input {
        width: auto;
    }
}
</style>

<script>
$(document).ready(function() {
    // 全选功能
    $('#select-all').change(function() {
        const checked = $(this).is(':checked');
        $('input[name="form_ids[]"]').prop('checked', checked);
        toggleBulkActions();
    });
    
    // 单个选择
    $('input[name="form_ids[]"]').change(function() {
        toggleBulkActions();
        updateSelectAll();
    });
    
    // 切换批量操作按钮显示
    function toggleBulkActions() {
        const checked = $('input[name="form_ids[]"]:checked').length;
        $('.bulk-actions').toggle(checked > 0);
    }
    
    // 更新全选状态
    function updateSelectAll() {
        const total = $('input[name="form_ids[]"]').length;
        const checked = $('input[name="form_ids[]"]:checked').length;
        
        $('#select-all').prop('indeterminate', checked > 0 && checked < total);
        $('#select-all').prop('checked', checked === total);
    }
    
    // 批量操作
    $('#apply-bulk').click(function() {
        const action = $('#bulk-action').val();
        if (!action) {
            alert('请选择操作');
            return;
        }
        
        const formIds = $('input[name="form_ids[]"]:checked').map(function() {
            return this.value;
        }).get();
        
        if (formIds.length === 0) {
            alert('请至少选择一个表单');
            return;
        }
        
        if (action === 'delete' && !confirm('确定要删除选中的表单吗？')) {
            return;
        }
        
        // 构建表单并提交
        const form = $('<form>').attr('method', 'post').attr('action', '?action=bulk_' + action);
        formIds.forEach(function(id) {
            form.append($('<input>').attr('type', 'hidden').attr('name', 'form_ids[]').val(id));
        });
        $('body').append(form);
        form.submit();
    });
    
    // 搜索功能
    $('#search-btn').click(function() {
        const keyword = $('#search-input').val();
        let url = '<?php echo $options->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/index.php'); ?>';
        if (keyword) {
            url += '&search=' + encodeURIComponent(keyword);
        }
        window.location.href = url;
    });
    
    $('#search-input').keypress(function(e) {
        if (e.which === 13) {
            $('#search-btn').click();
        }
    });
    
    // 状态筛选
    $('#status-filter').change(function() {
        const status = $(this).val();
        const search = $('#search-input').val();
        let url = '<?php echo $options->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/index.php'); ?>';
        if (search) url += '&search=' + encodeURIComponent(search);
        if (status) url += '&status=' + encodeURIComponent(status);
        window.location.href = url;
    });
    
    // 获取代码弹窗
    $('.action-code').click(function() {
        const formId = $(this).data('form-id');
        if (formId) {
            showCodeModal(formId);
        } else {
            alert('无法获取表单ID');
        }
    });
    
    // 代码模态框相关功能
    $('.modal-close').click(function() {
        $(this).closest('.modal').removeClass('show');
    });
    
    $('.modal').click(function(e) {
        if (e.target === this) {
            $(this).removeClass('show');
        }
    });
    
    // 代码标签切换
    $('.code-tab').click(function() {
        const tab = $(this).data('tab');
        
        $('.code-tab').removeClass('active');
        $(this).addClass('active');
        
        $('.code-tab-content').removeClass('active');
        $('#' + tab + '-tab').addClass('active');
    });
    
    // 更新iframe代码
    $('#update-iframe').click(function() {
        const width = $('#iframe-width').val() || '100%';
        const height = $('#iframe-height').val() || '600px';
        const formUrl = $('#form-link').val();
        
        const iframeCode = `<iframe src="${formUrl}" width="${width}" height="${height}" frameborder="0"></iframe>`;
        $('#iframe-code').val(iframeCode);
    });
    
    // 复制代码功能
    $('#copy-link, #copy-iframe, #copy-shortcode, #copy-api').click(function() {
        const targetId = $(this).attr('id').replace('copy-', '');
        let targetElement;
        
        switch (targetId) {
            case 'link':
                targetElement = $('#form-link');
                break;
            case 'iframe':
                targetElement = $('#iframe-code');
                break;
            case 'shortcode':
                targetElement = $('#shortcode');
                break;
            case 'api':
                targetElement = $('#api-url');
                break;
        }
        
        if (targetElement) {
            targetElement[0].select();
            document.execCommand('copy');
            
            // 显示复制成功提示
            const originalText = $(this).text();
            $(this).text('已复制');
            setTimeout(() => {
                $(this).text(originalText);
            }, 2000);
        }
    });
});

// 显示代码弹窗
function showCodeModal(formId) {
    // 获取表单代码
    $.ajax({
        url: '<?php echo $options->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/index.php'); ?>&action=ajax',
        method: 'POST',
        data: {
            ajax_action: 'get_form_code',
            form_id: formId
        },
        success: function(response) {
            if (response.success) {
                $('#form-link').val(response.data.link);
                $('#iframe-code').val(response.data.iframe);
                $('#shortcode').val(response.data.shortcode);
                $('#api-url').val(response.data.api);
                
                $('#code-modal').addClass('show');
            } else {
                alert('获取代码失败: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX请求失败:', xhr, status, error);
            console.log('响应文本:', xhr.responseText);
            alert('获取代码失败，请重试。状态: ' + status + ', 错误: ' + error);
        }
    });
}
</script>