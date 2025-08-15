<?php
// 文件名：manage.php - 修复版本
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>

<div class="main">
    <div class="body container">
<?php
// 处理操作
$action = $request->get('action');
$form_id = $request->get('id');

if ($action) {
    switch ($action) {
        case 'delete':
            if ($form_id) {
                // 删除表单及相关数据
                $db->query($db->delete('table.uforms_forms')->where('id = ?', $form_id));
                $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $form_id));
                $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $form_id));
                $db->query($db->delete('table.uforms_notifications')->where('form_id = ?', $form_id));
                $db->query($db->delete('table.uforms_calendar')->where('form_id = ?', $form_id));
                
                $request->throwNotice('表单已删除');
            }
            break;
            
        case 'duplicate':
            if ($form_id) {
                // 复制表单
                $form = UformsHelper::getForm($form_id);
                if ($form) {
                    // 创建新表单
                    $new_form_data = array(
                        'name' => $form['name'] . '_copy_' . time(),
                        'title' => $form['title'] . ' (副本)',
                        'description' => $form['description'],
                        'config' => $form['config'],
                        'settings' => $form['settings'],
                        'status' => 'draft',
                        'author_id' => $user->uid,
                        'view_count' => 0,
                        'submit_count' => 0,
                        'created_time' => time(),
                        'modified_time' => time()
                    );
                    
                    $new_form_id = $db->query($db->insert('table.uforms_forms')->rows($new_form_data));
                    
                    // 复制字段
                    $fields = UformsHelper::getFormFields($form_id);
                    foreach ($fields as $field) {
                        $field_data = array(
                            'form_id' => $new_form_id,
                            'field_type' => $field['field_type'],
                            'field_name' => $field['field_name'],
                            'field_label' => $field['field_label'],
                            'field_config' => $field['field_config'],
                            'sort_order' => $field['sort_order'],
                            'is_required' => $field['is_required'],
                            'created_time' => time()
                        );
                        $db->query($db->insert('table.uforms_fields')->rows($field_data));
                    }
                    
                    $request->throwNotice('表单已复制');
                }
            }
            break;
            
        case 'change_status':
            if ($form_id) {
                $new_status = $request->get('status');
                $allowed_status = array('draft', 'published', 'archived');
                if (in_array($new_status, $allowed_status)) {
                    $db->query($db->update('table.uforms_forms')
                                 ->rows(array('status' => $new_status, 'modified_time' => time()))
                                 ->where('id = ?', $form_id));
                    
                    $request->throwNotice('状态已更新');
                }
            }
            break;
            
        case 'bulk_delete':
            $form_ids = $request->get('form_ids');
            if ($form_ids && is_array($form_ids)) {
                foreach ($form_ids as $id) {
                    $db->query($db->delete('table.uforms_forms')->where('id = ?', $id));
                    $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $id));
                    $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $id));
                    $db->query($db->delete('table.uforms_notifications')->where('form_id = ?', $id));
                    $db->query($db->delete('table.uforms_calendar')->where('form_id = ?', $id));
                }
                
                $request->throwNotice('批量删除完成');
            }
            break;
            
        case 'export':
            if ($form_id) {
                // 导出表单
                $form = UformsHelper::getForm($form_id);
                if ($form) {
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="uform_' . $form['name'] . '.json"');
                    echo json_encode($form);
                    exit;
                }
            }
            break;
    }
}

// 分页和筛选设置
$page = $request->get('page', 1);
$per_page = $request->get('per_page', Helper::options()->plugin('Uforms')->admin_per_page ?? 20);
$status_filter = $request->get('status');
$search = $request->get('search', '');

$offset = ($page - 1) * $per_page;

// 构建查询
$select = $db->select('f.*', 
                     'COUNT(s.id) as submission_count')
            ->from('table.uforms_forms f')
            ->join('table.uforms_submissions s', 'f.id = s.form_id', Typecho_Db::LEFT_JOIN)
            ->group('f.id');

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
$count_result = $db->fetchObject($count_select->select('COUNT(*) as count'));
$total = $count_result ? $count_result->count : 0;

// 获取分页数据 - 修复排序问题，避免使用DESC作为字段名
$forms = $db->fetchAll($select->order('f.modified_time', Typecho_Db::SORT_DESC)->limit($per_page)->offset($offset));

// 获取统计数据
$stats = UformsHelper::getStats();
?>

<div class="manage-container">
    <!-- 统计卡片 -->
    <div class="stats-cards">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="wpforms icon"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total_forms']; ?></div>
                <div class="stat-label">总表单数</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon published">
                <i class="check circle icon"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['published_forms']; ?></div>
                <div class="stat-label">已发布表单</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="inbox icon"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['total_submissions']; ?></div>
                <div class="stat-label">总提交数</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon new">
                <i class="envelope open outline icon"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $stats['new_submissions']; ?></div>
                <div class="stat-label">未读提交</div>
            </div>
        </div>
    </div>
    
    <!-- 操作工具栏 -->
    <div class="toolbar">
        <div class="toolbar-left">
            <a href="?view=create" class="ui primary button">
                <i class="plus icon"></i> 创建新表单
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
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>草稿</option>
                    <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>已发布</option>
                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>已归档</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- 表单列表 -->
    <div class="forms-table-container">
        <?php if (empty($forms)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="wpforms icon"></i>
            </div>
            <h3>暂无表单</h3>
            <p>创建您的第一个表单开始收集数据</p>
            <a href="?view=create" class="ui primary button">创建表单</a>
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
                            <a href="?view=create&id=<?php echo $form['id']; ?>" class="ui icon button action-edit" title="编辑">
                                <i class="pencil alternate icon"></i>
                            </a>
                            
                            <?php if ($form['status'] === 'published'): ?>
                            <a href="<?php echo UformsHelper::getFormUrl($form['id']); ?>" target="_blank" class="ui icon button action-view" title="查看">
                                <i class="eye icon"></i>
                            </a>
                            <button class="ui icon button action-code" data-form-id="<?php echo $form['id']; ?>" title="获取代码">
                                <i class="code icon"></i>
                            </button>
                            <?php endif; ?>
                            
                            <div class="ui icon top right pointing dropdown button action-more" title="更多操作">
                                <i class="ellipsis vertical icon"></i>
                                <div class="menu">
                                    <a class="item" href="?view=create&action=duplicate&id=<?php echo $form['id']; ?>">
                                        <i class="copy icon"></i> 复制表单
                                    </a>
                                    
                                    <?php if ($form['status'] !== 'published'): ?>
                                    <a class="item" href="?action=change_status&id=<?php echo $form['id']; ?>&status=published">
                                        <i class="paper plane icon"></i> 发布表单
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($form['status'] === 'published'): ?>
                                    <a class="item" href="?action=change_status&id=<?php echo $form['id']; ?>&status=draft">
                                        <i class="file alternate icon"></i> 转为草稿
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a class="item" href="?action=change_status&id=<?php echo $form['id']; ?>&status=archived">
                                        <i class="archive icon"></i> 归档表单
                                    </a>
                                    
                                    <div class="divider"></div>
                                    
                                    <a class="item" href="?view=submissions&form_id=<?php echo $form['id']; ?>">
                                        <i class="inbox icon"></i> 查看提交
                                    </a>
                                    
                                    <a class="item" href="?view=export&form_id=<?php echo $form['id']; ?>">
                                        <i class="download icon"></i> 导出数据
                                    </a>
                                    
                                    <div class="divider"></div>
                                    
                                    <a class="item danger" href="?action=delete&id=<?php echo $form['id']; ?>" 
                                       onclick="return confirm('确定要删除这个表单吗？所有提交数据也会被删除。')">
                                        <i class="trash alternate outline icon"></i> 删除表单
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
        <div class="ui pagination menu">
            <?php
            $total_pages = ceil($total / $per_page);
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            ?>
            
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="item">
                <i class="angle left icon"></i> 上一页
            </a>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
               class="item <?php echo $i === $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" class="item">
                下一页 <i class="angle right icon"></i>
            </a>
            <?php endif; ?>
            
            <div class="item pagination-info">
                共 <?php echo $total; ?> 条记录，第 <?php echo $page; ?> / <?php echo $total_pages; ?> 页
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- 获取代码弹窗 -->
<div id="code-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>表单嵌入代码</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="code-tabs">
                <button class="code-tab active" data-tab="link">直接链接</button>
                <button class="code-tab" data-tab="iframe">iframe嵌入</button>
                <button class="code-tab" data-tab="shortcode">短代码</button>
            </div>
            <div class="code-content">
                <div class="code-tab-content active" id="link-tab">
                    <p>将此链接分享给用户访问表单：</p>
                    <div class="code-input-group">
                        <input type="text" id="form-link" readonly value="<?php echo htmlspecialchars(UformsHelper::getFormUrl($form['id'])); ?>">
                        <button id="copy-link" class="btn">复制</button>
                    </div>
                </div>
                <div class="code-tab-content" id="iframe-tab">
                    <p>将此代码嵌入到您的网页中：</p>
                    <div class="code-input-group">
                        <textarea id="iframe-code" rows="4" readonly><?php echo htmlspecialchars('<iframe src="' . UformsHelper::getFormUrl($form['id']) . '" width="100%" height="600px" frameborder="0"></iframe>'); ?></textarea>
                        <button id="copy-iframe" class="btn">复制</button>
                    </div>
                    <div class="iframe-options">
                        <label>宽度: <input type="text" id="iframe-width" value="100%"></label>
                        <label>高度: <input type="text" id="iframe-height" value="600px"></label>
                        <button id="update-iframe" class="btn btn-small">更新代码</button>
                    </div>
                </div>
                <div class="code-tab-content" id="shortcode-tab">
                    <p>在Typecho文章或页面中使用：</p>
                    <div class="code-input-group">
                        <input type="text" id="shortcode" readonly value="<?php echo htmlspecialchars('[uforms id="' . $form['id'] . '"]'); ?>">
                        <button id="copy-shortcode" class="btn">复制</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 全选功能
    $('#select-all').on('change', function() {
        $('input[name="form_ids[]"]').prop('checked', this.checked);
        toggleBulkActions();
    });
    
    // 单选框变化
    $('input[name="form_ids[]"]').on('change', function() {
        toggleBulkActions();
        updateSelectAll();
    });
    
    function toggleBulkActions() {
        const checked = $('input[name="form_ids[]"]:checked').length;
        if (checked > 0) {
            $('.bulk-actions').show();
        } else {
            $('.bulk-actions').hide();
        }
    }
    
    function updateSelectAll() {
        const total = $('input[name="form_ids[]"]').length;
        const checked = $('input[name="form_ids[]"]:checked').length;
        
        $('#select-all').prop('indeterminate', checked > 0 && checked < total);
        $('#select-all').prop('checked', checked === total);
    }
    
    // 批量操作
    $('#apply-bulk').on('click', function() {
        const action = $('#bulk-action').val();
        const checked = $('input[name="form_ids[]"]:checked');
        
        if (!action) {
            alert('请选择要执行的操作');
            return;
        }
        
        if (checked.length === 0) {
            alert('请选择要操作的表单');
            return;
        }
        
        let confirmText = '';
        switch (action) {
            case 'delete':
                confirmText = '确定要删除选中的表单吗？所有相关数据都会被删除。';
                break;
            case 'publish':
                confirmText = '确定要发布选中的表单吗？';
                break;
            case 'draft':
                confirmText = '确定要将选中的表单转为草稿吗？';
                break;
            case 'archive':
                confirmText = '确定要归档选中的表单吗？';
                break;
        }
        
        if (confirm(confirmText)) {
            const form_ids = [];
            checked.each(function() {
                form_ids.push($(this).val());
            });
            
            const form = $('<form method="post"></form>');
            form.append('<input type="hidden" name="action" value="bulk_' + action + '">');
            form_ids.forEach(function(id) {
                form.append('<input type="hidden" name="form_ids[]" value="' + id + '">');
            });
            
            $('body').append(form);
            form.submit();
        }
    });
    
    // 搜索功能
    document.getElementById('search-btn').addEventListener('click', function() {
        var search = document.getElementById('search-input').value;
        var status = document.getElementById('status-filter').value;
        window.location.href = '?view=manage&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status);
    });
    
    document.getElementById('status-filter').addEventListener('change', function() {
        var search = document.getElementById('search-input').value;
        var status = this.value;
        window.location.href = '?view=manage&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status);
    });
    
    // 状态筛选
    $('#status-filter').on('change', function() {
        performSearch();
    });
    
    // 下拉菜单
    $('.dropdown').each(function() {
        const dropdown = $(this);
        const button = dropdown.find('.action-more');
        const menu = dropdown.find('.dropdown-menu');
        
        button.on('click', function(e) {
            e.stopPropagation();
            $('.dropdown-menu').not(menu).removeClass('show');
            menu.toggleClass('show');
        });
        
        $(document).on('click', function() {
            menu.removeClass('show');
        });
    });
    
    // 获取代码按钮
    $('.action-code').on('click', function() {
        const formId = $(this).data('form-id');
        showCodeModal(formId);
    });
    
    function showCodeModal(formId) {
        const siteUrl = '<?php echo Helper::options()->siteUrl; ?>';
        const formUrl = siteUrl + 'uforms/form/' + formId;
        const iframeCode = '<iframe src="' + formUrl + '" width="100%" height="600px" frameborder="0"></iframe>';
        const shortcode = '[uforms id="' + formId + '"]';
        
        $('#form-link').val(formUrl);
        $('#iframe-code').val(iframeCode);
        $('#shortcode').val(shortcode);
        
        $('#code-modal').show();
    }
    
    // 模态框操作
    $('.modal-close').on('click', function() {
        $(this).closest('.modal').hide();
    });
    
    $(document).on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $(e.target).hide();
        }
    });
});
</script>
