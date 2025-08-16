<?php
// 获取搜索和筛选参数
$search = $request->get('search', '');
$status_filter = $request->get('status', '');
$page = max(1, $request->get('page', 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 处理批量操作
if ($request->getMethod() === 'POST') {
    $action = $request->get('action');
    $submission_ids = $request->get('submission_ids', array());
    
    if ($action && !empty($submission_ids)) {
        switch ($action) {
            case 'mark_read':
                foreach ($submission_ids as $id) {
                    $db->query($db->update('table.uforms_submissions')
                                 ->rows(array('status' => 'read'))
                                 ->where('id = ?', $id));
                }
                echo '<div class="message success">已标记为已读</div>';
                break;
                
            case 'mark_unread':
                foreach ($submission_ids as $id) {
                    $db->query($db->update('table.uforms_submissions')
                                 ->rows(array('status' => 'new'))
                                 ->where('id = ?', $id));
                }
                echo '<div class="message success">已标记为未读</div>';
                break;
                
            case 'mark_spam':
                foreach ($submission_ids as $id) {
                    $db->query($db->update('table.uforms_submissions')
                                 ->rows(array('status' => 'spam'))
                                 ->where('id = ?', $id));
                }
                echo '<div class="message success">已标记为垃圾邮件</div>';
                break;
                
            case 'delete':
                foreach ($submission_ids as $id) {
                    $db->query($db->delete('table.uforms_submissions')->where('id = ?', $id));
                    $db->query($db->delete('table.uforms_files')->where('submission_id = ?', $id));
                }
                echo '<div class="message success">删除成功</div>';
                break;
        }
    }
}

// 构建查询
$select = $db->select('s.*, f.title as form_title, f.name as form_name')
             ->from('table.uforms_submissions s')
             ->join('table.uforms_forms f', 's.form_id = f.id');

$where_conditions = array();
$where_values = array();

if ($form_id) {
    $where_conditions[] = 's.form_id = ?';
    $where_values[] = $form_id;
}

if ($status_filter) {
    $where_conditions[] = 's.status = ?';
    $where_values[] = $status_filter;
}

if ($search) {
    $where_conditions[] = '(s.data LIKE ? OR s.ip LIKE ?)';
    $where_values[] = '%' . $search . '%';
    $where_values[] = '%' . $search . '%';
}

if (!empty($where_conditions)) {
    $select->where(implode(' AND ', $where_conditions), ...$where_values);
}

// 获取总数
$count_select = clone $select;
$total = $db->fetchObject($count_select->select('COUNT(*) as count'))->count;

// 获取分页数据
$submissions = $db->fetchAll($select->order('s.created_time DESC')->limit($per_page)->offset($offset));
?>

<div class="submissions-view">
    <!-- 操作工具栏 -->
    <div class="submissions-toolbar">
        <div class="toolbar-left">
            <div class="bulk-actions" style="display: none;">
                <select id="bulk-action">
                    <option value="">批量操作</option>
                    <option value="mark_read">标记为已读</option>
                    <option value="mark_unread">标记为未读</option>
                    <option value="mark_spam">标记为垃圾</option>
                    <option value="delete">删除</option>
                </select>
                <button id="apply-bulk" class="btn">应用</button>
            </div>
        </div>
        
        <div class="toolbar-right">
            <div class="search-box">
                <input type="text" id="search-input" placeholder="搜索提交内容..." value="<?php echo htmlspecialchars($search); ?>">
                <button id="search-btn"><i class="icon-search"></i></button>
            </div>
            
            <div class="filter-box">
                <select id="status-filter">
                    <option value="">所有状态</option>
                    <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>未读</option>
                    <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>已读</option>
                    <option value="spam" <?php echo $status_filter === 'spam' ? 'selected' : ''; ?>>垃圾</option>
                </select>
            </div>
            
            <button id="refresh-data" class="btn" title="刷新数据">
                <i class="icon-refresh"></i>
            </button>
        </div>
    </div>
    
    <!-- 提交列表 -->
    <?php if (empty($submissions)): ?>
    <div class="empty-state">
        <div class="empty-icon">
            <i class="icon-submissions-empty"></i>
        </div>
        <h3>暂无提交数据</h3>
        <p>当用户提交表单后，数据将在这里显示</p>
    </div>
    <?php else: ?>
    <div class="submissions-table-container">
        <table class="submissions-table">
            <thead>
                <tr>
                    <th class="check-column">
                        <input type="checkbox" id="select-all">
                    </th>
                    <th class="form-column">表单</th>
                    <th class="content-column">内容预览</th>
                    <th class="status-column">状态</th>
                    <th class="ip-column">IP地址</th>
                    <th class="date-column">提交时间</th>
                    <th class="actions-column">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $submission): ?>
                <?php 
                $data = json_decode($submission['data'], true);
                $has_files = $db->fetchObject(
                    $db->select('COUNT(*) as count')
                       ->from('table.uforms_files')
                       ->where('submission_id = ?', $submission['id'])
                )->count > 0;
                ?>
                <tr class="submission-row status-<?php echo $submission['status']; ?>">
                    <td class="check-column">
                        <input type="checkbox" name="submission_ids[]" value="<?php echo $submission['id']; ?>">
                    </td>
                    <td class="form-column">
                        <div class="form-info">
                            <strong><?php echo htmlspecialchars($submission['form_title']); ?></strong>
                            <div class="form-meta">
                                <span class="form-id">ID: <?php echo $submission['form_id']; ?></span>
                                <?php if ($has_files): ?>
                                <span class="has-attachments" title="包含附件">
                                    <i class="icon-attachment"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="content-column">
                        <div class="content-preview">
                            <?php
                            if (!empty($data)) {
                                $preview_count = 0;
                                foreach ($data as $key => $value) {
                                    if ($preview_count >= 2) break;
                                    
                                    if (is_array($value)) {
                                        $value = implode(', ', $value);
                                    }
                                    
                                    $short_value = mb_strlen($value) > 30 ? mb_substr($value, 0, 30) . '...' : $value;
                                    echo '<div class="field-preview">';
                                    echo '<span class="field-name">' . htmlspecialchars($key) . ':</span> ';
                                    echo '<span class="field-value">' . htmlspecialchars($short_value) . '</span>';
                                    echo '</div>';
                                    $preview_count++;
                                }
                                
                                if (count($data) > 2) {
                                    echo '<div class="more-fields">还有 ' . (count($data) - 2) . ' 个字段...</div>';
                                }
                            } else {
                                echo '<span class="no-data">无数据</span>';
                            }
                            ?>
                        </div>
                    </td>
                    <td class="status-column">
                        <select class="status-select" data-submission-id="<?php echo $submission['id']; ?>">
                            <option value="new" <?php echo $submission['status'] === 'new' ? 'selected' : ''; ?>>未读</option>
                            <option value="read" <?php echo $submission['status'] === 'read' ? 'selected' : ''; ?>>已读</option>
                            <option value="spam" <?php echo $submission['status'] === 'spam' ? 'selected' : ''; ?>>垃圾</option>
                        </select>
                    </td>
                    <td class="ip-column">
                        <span class="ip-address" title="<?php echo htmlspecialchars($submission['user_agent']); ?>">
                            <?php echo htmlspecialchars($submission['ip']); ?>
                        </span>
                    </td>
                    <td class="date-column">
                        <span title="<?php echo UformsHelper::formatTime($submission['created_time']); ?>">
                            <?php echo Typecho_I18n::dateWord($submission['created_time'], time()); ?>
                        </span>
                    </td>
                    <td class="actions-column">
                        <div class="row-actions">
                            <button class="action-view" data-submission-id="<?php echo $submission['id']; ?>" title="查看详情">
                                <i class="icon-eye"></i>
                            </button>
                            
                            <button class="action-export" data-submission-id="<?php echo $submission['id']; ?>" title="导出">
                                <i class="icon-export"></i>
                            </button>
                            
                            <div class="dropdown">
                                <button class="action-more" title="更多操作">
                                    <i class="icon-more"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a href="#" class="action-add-note" data-submission-id="<?php echo $submission['id']; ?>">
                                        <i class="icon-note"></i> 添加备注
                                    </a>
                                    
                                    <?php if ($has_files): ?>
                                    <a href="#" class="action-download-files" data-submission-id="<?php echo $submission['id']; ?>">
                                        <i class="icon-download"></i> 下载附件
                                    </a>
                                    <?php endif; ?>
                                    
                                    <div class="dropdown-divider"></div>
                                    
                                    <a href="#" class="action-delete danger" data-submission-id="<?php echo $submission['id']; ?>">
                                        <i class="icon-delete"></i> 删除
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
            <?php
            $total_pages = ceil($total / $per_page);
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            $base_url = '?view=view&type=submissions';
            if ($form_id) $base_url .= '&form_id=' . $form_id;
            if ($search) $base_url .= '&search=' . safe_urlencode($search);
            if ($status_filter) $base_url .= '&status=' . $status_filter;
            ?>
            
            <?php if ($page > 1): ?>
            <a href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>" class="prev">
                <i class="icon-prev"></i> 上一页
            </a>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="<?php echo $base_url; ?>&page=<?php echo $i; ?>" 
               class="<?php echo $i === $page ? 'current' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>" class="next">
                下一页 <i class="icon-next"></i>
            </a>
            <?php endif; ?>
            
            <div class="pagination-info">
                共 <?php echo $total; ?> 条记录，第 <?php echo $page; ?> / <?php echo $total_pages; ?> 页
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 查看详情模态框 -->
<div id="view-submission-modal" class="modal large-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>提交详情</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="submission-details">
                <div class="details-header">
                    <div class="submission-meta">
                        <div class="meta-item">
                            <label>表单：</label>
                            <span id="detail-form-name"></span>
                        </div>
                        <div class="meta-item">
                            <label>状态：</label>
                            <span id="detail-status" class="status-badge"></span>
                        </div>
                        <div class="meta-item">
                            <label>提交时间：</label>
                            <span id="detail-time"></span>
                        </div>
                        <div class="meta-item">
                            <label>IP地址：</label>
                            <span id="detail-ip"></span>
                        </div>
                    </div>
                </div>
                
                <div class="details-content">
                    <div class="form-data">
                        <h4>表单数据</h4>
                        <div id="submission-data"></div>
                    </div>
                    
                    <div class="attachments-section" style="display: none;">
                        <h4>附件</h4>
                        <div id="submission-files"></div>
                    </div>
                    
                    <div class="notes-section">
                        <h4>备注</h4>
                        <textarea id="submission-notes" rows="3" placeholder="添加内部备注..."></textarea>
                        <button id="save-notes" class="btn btn-small">保存备注</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button id="export-submission" class="btn">导出数据</button>
            <button class="btn btn-default modal-close">关闭</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 全选功能
    $('#select-all').on('change', function() {
        $('input[name="submission_ids[]"]').prop('checked', this.checked);
        toggleBulkActions();
    });
    
    $('input[name="submission_ids[]"]').on('change', function() {
        toggleBulkActions();
        updateSelectAll();
    });
    
    function toggleBulkActions() {
        const checked = $('input[name="submission_ids[]"]:checked').length;
        $('.bulk-actions').toggle(checked > 0);
    }
    
    function updateSelectAll() {
        const total = $('input[name="submission_ids[]"]').length;
        const checked = $('input[name="submission_ids[]"]:checked').length;
        
        $('#select-all').prop('indeterminate', checked > 0 && checked < total);
        $('#select-all').prop('checked', checked === total);
    }
    
    // 批量操作
    $('#apply-bulk').on('click', function() {
        const action = $('#bulk-action').val();
        const checked = $('input[name="submission_ids[]"]:checked');
        
        if (!action) {
            alert('请选择要执行的操作');
            return;
        }
        
        if (checked.length === 0) {
            alert('请选择要操作的提交');
            return;
        }
        
        let confirmText = '';
        switch (action) {
            case 'mark_read':
                confirmText = '确定要标记为已读吗？';
                break;
            case 'mark_unread':
                confirmText = '确定要标记为未读吗？';
                break;
            case 'mark_spam':
                confirmText = '确定要标记为垃圾邮件吗？';
                break;
            case 'delete':
                confirmText = '确定要删除选中的提交吗？此操作不可恢复。';
                break;
        }
        
        if (confirm(confirmText)) {
            const submission_ids = [];
            checked.each(function() {
                submission_ids.push($(this).val());
            });
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: action,
                    submission_ids: submission_ids
                },
                success: function(response) {
                    location.reload();
                },
                error: function() {
                    alert('操作失败，请重试');
                }
            });
        }
    });
    
    // 状态快速更改
    $('.status-select').on('change', function() {
        const submissionId = $(this).data('submission-id');
        const newStatus = $(this).val();
        
        $.ajax({
            url: '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'change_status',
                submission_id: submissionId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // 更新行的CSS类
                    const row = $('input[value="' + submissionId + '"]').closest('tr');
                    row.removeClass('status-new status-read status-spam')
                       .addClass('status-' + newStatus);
                } else {
                    alert('状态更新失败');
                }
            },
            error: function() {
                alert('状态更新失败');
            }
        });
    });
    
    // 查看详情
    $('.action-view').on('click', function() {
        const submissionId = $(this).data('submission-id');
        loadSubmissionDetails(submissionId);
    });
    
    function loadSubmissionDetails(submissionId) {
        $.ajax({
            url: '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/ajax.php'); ?>',
            method: 'GET',
            data: {
                action: 'get_submission',
                submission_id: submissionId
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // 填充基本信息
                    $('#detail-form-name').text(data.form_title);
                    $('#detail-status').removeClass().addClass('status-badge status-' + data.status)
                                      .text(getStatusLabel(data.status));
                    $('#detail-time').text(data.created_time);
                    $('#detail-ip').text(data.ip);
                    
                    // 填充表单数据
                    const formData = JSON.parse(data.data);
                    let dataHtml = '<div class="data-grid">';
                    
                    for (const [key, value] of Object.entries(formData)) {
                        const displayValue = Array.isArray(value) ? value.join(', ') : value;
                        dataHtml += `
                            <div class="data-item">
                                <div class="data-label">${escapeHtml(key)}</div>
                                <div class="data-value">${escapeHtml(displayValue)}</div>
                            </div>
                        `;
                    }
                    dataHtml += '</div>';
                    $('#submission-data').html(dataHtml);
                    
                    // 加载附件
                    if (data.files && data.files.length > 0) {
                        let filesHtml = '<div class="files-list">';
                        data.files.forEach(file => {
                            filesHtml += `
                                <div class="file-item">
                                    <i class="icon-file"></i>
                                    <span class="file-name">${escapeHtml(file.original_name)}</span>
                                    <span class="file-size">${formatFileSize(file.file_size)}</span>
                                    <a href="#" class="download-file" data-file-id="${file.id}">下载</a>
                                </div>
                            `;
                        });
                        filesHtml += '</div>';
                        $('#submission-files').html(filesHtml);
                        $('.attachments-section').show();
                    } else {
                        $('.attachments-section').hide();
                    }
                    
                    // 填充备注
                    $('#submission-notes').val(data.notes || '');
                    
                    // 显示模态框
                    $('#view-submission-modal').show();
                    
                    // 设置导出按钮
                    $('#export-submission').data('submission-id', submissionId);
                } else {
                    alert('加载详情失败');
                }
            },
            error: function() {
                alert('加载详情失败');
            }
        });
    }
    
    // 保存备注
    $('#save-notes').on('click', function() {
        const submissionId = $('#export-submission').data('submission-id');
        const notes = $('#submission-notes').val();
        
        $.ajax({
            url: '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'save_notes',
                submission_id: submissionId,
                notes: notes
            },
            success: function(response) {
                if (response.success) {
                    alert('备注保存成功');
                } else {
                    alert('备注保存失败');
                }
            },
            error: function() {
                alert('备注保存失败');
            }
        });
    });
    
    // 搜索功能
    $('#search-btn').on('click', function() {
        performSearch();
    });
    
    $('#search-input').on('keypress', function(e) {
        if (e.which === 13) {
            performSearch();
        }
    });
    
    $('#status-filter').on('change', function() {
        performSearch();
    });
    
    function performSearch() {
        const search = $('#search-input').val();
        const status = $('#status-filter').val();
        let url = '?view=view&type=submissions';
        
        <?php if ($form_id): ?>
        url += '&form_id=<?php echo $form_id; ?>';
        <?php endif; ?>
        
        if (search) {
            url += '&search=' + encodeURIComponent(search);
        }
        
        if (status) {
            url += '&status=' + encodeURIComponent(status);
        }
        
        window.location.href = url;
    }
    
    // 刷新数据
    $('#refresh-data').on('click', function() {
        location.reload();
    });
    
    // 模态框关闭
    $('.modal-close').on('click', function() {
        $(this).closest('.modal').hide();
    });
    
    $(document).on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $(e.target).hide();
        }
    });
    
    // 工具函数
    function getStatusLabel(status) {
        const labels = {
            'new': '未读',
            'read': '已读',
            'spam': '垃圾'
        };
        return labels[status] || status;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});
</script>
