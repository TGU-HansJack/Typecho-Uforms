<?php
/**
 * Uforms Ajax处理
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once dirname(__FILE__) . '/frontend-functions.php';
require_once dirname(__FILE__) . '/../core/UformsHelper.php';

// 处理Ajax请求
try {
    $request = Typecho_Request::getInstance();
    $action = $request->get('action');
    
    if (!$action) {
        throw new Exception('缺少action参数');
    }
    
    // 根据action调用相应的方法
    switch ($action) {
        case 'get_forms':
            handleGetForms();
            break;
            
        case 'get_form':
            handleGetForm();
            break;
            
        case 'save_form':
            handleSaveForm();
            break;
            
        case 'delete_form':
            handleDeleteForm();
            break;
            
        case 'duplicate_form':
            handleDuplicateForm();
            break;
            
        case 'get_submission':
            handleGetSubmission();
            break;
            
        case 'update_submission_status':
            handleUpdateSubmissionStatus();
            break;
            
        case 'delete_submission':
            handleDeleteSubmission();
            break;
            
        case 'get_submissions':
            handleGetSubmissions();
            break;
            
        case 'export_submissions':
            handleExportSubmissions();
            break;
            
        case 'get_stats':
            handleGetStats();
            break;
            
        case 'track_view':
            handleTrackView();
            break;
            
        case 'save_field':
            handleSaveField();
            break;
            
        case 'delete_field':
            handleDeleteField();
            break;
            
        case 'sort_fields':
            handleSortFields();
            break;
            
        case 'test_email':
            handleTestEmail();
            break;
            
        case 'test_slack':
            handleTestSlack();
            break;
            
        case 'upload_file':
            handleUploadFile();
            break;
            
        default:
            throw new Exception('未知的action: ' . $action);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => false,
        'message' => $e->getMessage()
    ));
}

function handleSaveForm() {
    global $db, $request, $user;
    
    $form_id = $request->get('form_id');
    $form_data = array(
        'name' => trim($request->get('form_name')),
        'title' => trim($request->get('form_title')),
        'description' => trim($request->get('form_description')),
        'config' => json_encode($request->get('form_config', array())),
        'settings' => json_encode($request->get('form_settings', array())),
        'status' => $request->get('form_status', 'draft'),
        'modified_time' => time()
    );
    
    // 验证数据
    if (empty($form_data['name']) || empty($form_data['title'])) {
        throw new Exception('表单名称和标题不能为空');
    }
    
    // 检查名称是否重复
    $where_conditions = array('name = ?');
    $where_values = array($form_data['name']);
    
    if ($form_id) {
        $where_conditions[] = 'id != ?';
        $where_values[] = $form_id;
    }
    
    $existing = $db->fetchRow(
        $db->select('id')->from('table.uforms_forms')
           ->where(implode(' AND ', $where_conditions), ...$where_values)
    );
    
    if ($existing) {
        throw new Exception('表单名称已存在');
    }
    
    if ($form_id) {
        // 更新表单
        $db->query($db->update('table.uforms_forms')
                     ->rows($form_data)
                     ->where('id = ?', $form_id));
        
        // 删除现有字段
        $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $form_id));
    } else {
        // 创建新表单
        $form_data['author_id'] = $user->uid;
        $form_data['created_time'] = time();
        $form_id = $db->query($db->insert('table.uforms_forms')->rows($form_data));
    }
    
    // 保存字段
    $fields = $request->get('fields', array());
    if (!empty($fields)) {
        foreach ($fields as $index => $field) {
            $field_data = array(
                'form_id' => $form_id,
                'field_type' => $field['type'],
                'field_name' => $field['name'],
                'field_label' => $field['label'],
                'field_config' => json_encode($field['config'] ?? array()),
                'sort_order' => $index,
                'is_required' => !empty($field['required']) ? 1 : 0,
                'created_time' => time()
            );
            
            $db->query($db->insert('table.uforms_fields')->rows($field_data));
        }
    }
    
    // 创建通知
    UformsHelper::createNotification(
        $form_id,
        $form_id ? 'form_updated' : 'form_created',
        $form_id ? '表单已更新' : '新表单已创建',
        '表单"' . $form_data['title'] . '"已' . ($form_id ? '更新' : '创建')
    );
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'form_id' => $form_id,
        'message' => '表单保存成功'
    ));
}

function handleGetForm() {
    global $db, $request;
    
    $form_id = $request->get('form_id');
    if (!$form_id) {
        throw new Exception('表单ID不能为空');
    }
    
    $form = UformsHelper::getForm($form_id);
    if (!$form) {
        throw new Exception('表单不存在');
    }
    
    $fields = UformsHelper::getFormFields($form_id);
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'form' => $form,
        'fields' => $fields
    ));
}

function handleDeleteForm() {
    global $db, $request;
    
    $form_id = $request->get('form_id');
    if (!$form_id) {
        throw new Exception('表单ID不能为空');
    }
    
    // 删除相关数据
    $db->query($db->delete('table.uforms_forms')->where('id = ?', $form_id));
    $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $form_id));
    $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $form_id));
    $db->query($db->delete('table.uforms_notifications')->where('form_id = ?', $form_id));
    $db->query($db->delete('table.uforms_calendar')->where('form_id = ?', $form_id));
    $db->query($db->delete('table.uforms_files')->where('form_id = ?', $form_id));
    $db->query($db->delete('table.uforms_stats')->where('form_id = ?', $form_id));
    
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'message' => '表单删除成功'));
}

function handleDuplicateForm() {
    global $db, $request, $user;
    
    $form_id = $request->get('form_id');
    if (!$form_id) {
        throw new Exception('表单ID不能为空');
    }
    
    $original_form = UformsHelper::getForm($form_id);
    if (!$original_form) {
        throw new Exception('原表单不存在');
    }
    
    // 复制表单
    $new_form_data = array(
        'name' => $original_form['name'] . '_copy_' . time(),
        'title' => $original_form['title'] . ' (副本)',
        'description' => $original_form['description'],
        'config' => $original_form['config'],
        'settings' => $original_form['settings'],
        'status' => 'draft',
        'author_id' => $user->uid,
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
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'new_form_id' => $new_form_id,
        'message' => '表单复制成功'
    ));
}

function handleGetSubmission() {
    global $db, $request;
    
    $submission_id = $request->get('submission_id');
    if (!$submission_id) {
        throw new Exception('提交ID不能为空');
    }
    
    $submission = $db->fetchRow(
        $db->select('s.*, f.title as form_title')
           ->from('table.uforms_submissions s')
           ->join('table.uforms_forms f', 's.form_id = f.id')
           ->where('s.id = ?', $submission_id)
    );
    
    if (!$submission) {
        throw new Exception('提交记录不存在');
    }
    
    // 获取附件
    $files = $db->fetchAll(
        $db->select('*')->from('table.uforms_files')
           ->where('submission_id = ?', $submission_id)
    );
    
    // 格式化时间
    $submission['created_time'] = UformsHelper::formatTime($submission['created_time']);
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'data' => $submission,
        'files' => $files
    ));
}

function handleChangeStatus() {
    global $db, $request;
    
    $submission_id = $request->get('submission_id');
    $status = $request->get('status');
    
    if (!$submission_id || !$status) {
        throw new Exception('参数不完整');
    }
    
    $allowed_statuses = array('new', 'read', 'spam', 'deleted');
    if (!in_array($status, $allowed_statuses)) {
        throw new Exception('无效的状态');
    }
    
    $db->query($db->update('table.uforms_submissions')
                 ->rows(array('status' => $status, 'modified_time' => time()))
                 ->where('id = ?', $submission_id));
    
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'message' => '状态更新成功'));
}

function handleSaveNotes() {
    global $db, $request;
    
    $submission_id = $request->get('submission_id');
    $notes = $request->get('notes');
    
    if (!$submission_id) {
        throw new Exception('提交ID不能为空');
    }
    
    $db->query($db->update('table.uforms_submissions')
                 ->rows(array('notes' => $notes, 'modified_time' => time()))
                 ->where('id = ?', $submission_id));
    
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'message' => '备注保存成功'));
}

function handleGetCalendarEvents() {
    global $db, $request;
    
    $form_id = $request->get('form_id');
    $start = $request->get('start');
    $end = $request->get('end');
    
    $events = array();
    
    // 获取日历事件
    $calendar_select = $db->select('*')->from('table.uforms_calendar');
    
    if ($form_id) {
        $calendar_select->where('form_id = ?', $form_id);
    }
    
    if ($start && $end) {
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        $calendar_select->where('start_time <= ? AND (end_time >= ? OR end_time IS NULL)', $end_time, $start_time);
    }
    
    $calendar_events = $db->fetchAll($calendar_select);
    
    foreach ($calendar_events as $event) {
        $events[] = array(
            'id' => 'calendar_' . $event['id'],
            'title' => $event['title'],
            'start' => date('c', $event['start_time']),
            'end' => $event['end_time'] ? date('c', $event['end_time']) : null,
            'allDay' => $event['all_day'],
            'color' => $event['color'] ?: '#3788d8',
            'type' => 'calendar',
            'status' => $event['status'],
            'form_id' => $event['form_id'],
            'form_title' => $event['form_title'],
            'description' => $event['description']
        );
    }
    
    // 获取提交事件（包含日期字段的表单提交）
    if ($start && $end) {
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        
        $submission_select = $db->select('s.*, f.title as form_title')
                                ->from('table.uforms_submissions s')
                                ->join('table.uforms_forms f', 's.form_id = f.id')
                                ->where('s.created_time >= ? AND s.created_time <= ?', $start_time, $end_time);
        
        if ($form_id) {
            $submission_select->where('s.form_id = ?', $form_id);
        }
        
        $submissions = $db->fetchAll($submission_select);
        
        foreach ($submissions as $submission) {
            $data = json_decode($submission['data'], true);
            $has_date_field = false;
            
            // 检查是否包含日期字段
            foreach ($data as $key => $value) {
                if (preg_match('/date|time|日期|时间/i', $key) && !empty($value)) {
                    $date_value = strtotime($value);
                    if ($date_value) {
                        $events[] = array(
                            'id' => 'submission_' . $submission['id'],
                            'title' => $submission['form_title'] . ' - 提交',
                            'start' => date('c', $date_value),
                            'color' => '#27ae60',
                            'type' => 'submission',
                            'status' => $submission['status'],
                            'form_id' => $submission['form_id'],
                            'form_title' => $submission['form_title'],
                            'submission_id' => $submission['id'],
                            'data' => $submission['data']
                        );
                        $has_date_field = true;
                        break;
                    }
                }
            }
            
            // 如果没有日期字段，使用提交时间
            if (!$has_date_field) {
                $events[] = array(
                    'id' => 'submission_' . $submission['id'],
                    'title' => $submission['form_title'] . ' - 提交',
                    'start' => date('c', $submission['created_time']),
                    'color' => '#27ae60',
                    'type' => 'submission',
                    'status' => $submission['status'],
                    'form_id' => $submission['form_id'],
                    'form_title' => $submission['form_title'],
                    'submission_id' => $submission['id'],
                    'data' => $submission['data']
                );
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'events' => $events));
}

function handleSaveCalendarEvent() {
    global $db, $request, $user;
    
    $event_data = array(
        'form_id' => $request->get('form_id') ?: null,
        'title' => trim($request->get('title')),
        'start_time' => strtotime($request->get('start')),
        'end_time' => $request->get('end') ? strtotime($request->get('end')) : null,
        'all_day' => $request->get('all_day') ? 1 : 0,
        'status' => $request->get('status', 'available'),
        'color' => $request->get('color', '#3788d8'),
        'description' => trim($request->get('description', '')),
        'author_id' => $user->uid,
        'created_time' => time()
    );
    
    if (empty($event_data['title']) || !$event_data['start_time']) {
        throw new Exception('事件标题和开始时间不能为空');
    }
    
    // 获取表单标题
    if ($event_data['form_id']) {
        $form = $db->fetchRow(
            $db->select('title')->from('table.uforms_forms')
               ->where('id = ?', $event_data['form_id'])
        );
        $event_data['form_title'] = $form ? $form['title'] : '';
    }
    
    $event_id = $db->query($db->insert('table.uforms_calendar')->rows($event_data));
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'event_id' => $event_id,
        'message' => '事件保存成功'
    ));
}

function handleDeleteCalendarEvent() {
    global $db, $request;
    
    $event_id = $request->get('event_id');
    if (!$event_id) {
        throw new Exception('事件ID不能为空');
    }
    
    // 解析事件ID
    if (strpos($event_id, 'calendar_') === 0) {
        $real_id = substr($event_id, 9);
        $db->query($db->delete('table.uforms_calendar')->where('id = ?', $real_id));
    } else {
        throw new Exception('只能删除自定义日历事件');
    }
    
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'message' => '事件删除成功'));
}

function handleTestSlack() {
    global $request;
    
    $webhook = $request->get('webhook');
    if (!$webhook) {
        throw new Exception('Webhook URL不能为空');
    }
    
    $message = array(
        'text' => 'Uforms 测试消息',
        'username' => 'Uforms',
        'attachments' => array(
            array(
                'color' => 'good',
                'fields' => array(
                    array(
                        'title' => '测试时间',
                        'value' => date('Y-m-d H:i:s'),
                        'short' => true
                    ),
                    array(
                        'title' => '状态',
                        'value' => 'Slack集成测试成功',
                        'short' => true
                    )
                )
            )
        )
    );
    
    $result = UformsHelper::sendSlackMessage($webhook, $message);
    
    if (!$result) {
        throw new Exception('Slack消息发送失败');
    }
    
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'message' => 'Slack测试成功'));
}

function handleUploadFile() {
    global $db, $request, $user;
    
    if (!isset($_FILES['file'])) {
        throw new Exception('没有上传文件');
    }
    
    $file = $_FILES['file'];
    $form_id = $request->get('form_id');
    $field_name = $request->get('field_name');
    
    // 验证文件
    $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip');
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        throw new Exception('不支持的文件类型');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('文件大小超过限制');
    }
    
    // 创建上传目录
    $upload_dir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // 生成文件名
    $filename = uniqid() . '.' . $file_ext;
    $filepath = $upload_dir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('文件上传失败');
    }
    
    // 保存文件记录
    $file_data = array(
        'form_id' => $form_id,
        'field_name' => $field_name,
        'original_name' => $file['name'],
        'filename' => $filename,
        'file_path' => $filepath,
        'file_size' => $file['size'],
        'file_type' => $file['type'],
        'uploaded_by' => $user->uid,
        'created_time' => time()
    );
    
    $file_id = $db->query($db->insert('table.uforms_files')->rows($file_data));
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'file_id' => $file_id,
        'filename' => $filename,
        'original_name' => $file['name'],
        'size' => $file['size']
    ));
}

function handleDeleteFile() {
    global $db, $request;
    
    $file_id = $request->get('file_id');
    if (!$file_id) {
        throw new Exception('文件ID不能为空');
    }
    
    $file = $db->fetchRow(
        $db->select('*')->from('table.uforms_files')->where('id = ?', $file_id)
    );
    
    if (!$file) {
        throw new Exception('文件不存在');
    }
    
    // 删除物理文件
    if (file_exists($file['file_path'])) {
        unlink($file['file_path']);
    }
    
    // 删除数据库记录
    $db->query($db->delete('table.uforms_files')->where('id = ?', $file_id));
    
    header('Content-Type: application/json');
    echo json_encode(array('success' => true, 'message' => '文件删除成功'));
}

function handleValidateField() {
    global $request;
    
    $field_type = $request->get('field_type');
    $field_value = $request->get('field_value');
    $field_config = $request->get('field_config', array());
    
    if (is_string($field_config)) {
        $field_config = json_decode($field_config, true) ?: array();
    }
    
    $errors = UformsHelper::validateField($field_type, $field_value, $field_config);
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => empty($errors),
        'errors' => $errors
    ));
}

function handleGetFieldOptions() {
    global $db, $request;
    
    $field_type = $request->get('field_type');
    $search = $request->get('search', '');
    
    $options = array();
    
    // 根据字段类型返回不同的选项
    switch ($field_type) {
        case 'select':
        case 'radio':
        case 'checkbox':
            // 这里可以从数据库或配置文件获取预定义选项
            $predefined_options = array(
                '是', '否',
                '男', '女', '其他',
                '一般', '良好', '优秀',
                '北京', '上海', '广州', '深圳',
                '非常不满意', '不满意', '一般', '满意', '非常满意'
            );
            
            if ($search) {
                $options = array_filter($predefined_options, function($option) use ($search) {
                    return stripos($option, $search) !== false;
                });
            } else {
                $options = $predefined_options;
            }
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'options' => array_values($options)
    ));
}

function handlePreviewForm() {
    global $request;
    
    $form_data = $request->get('form_data', array());
    $fields = $request->get('fields', array());
    
    // 生成预览HTML
    ob_start();
    include dirname(__FILE__) . '/templates/form-preview.php';
    $html = ob_get_clean();
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'html' => $html
    ));
}

function handleExportSubmissions() {
    global $db, $request;
    
    $form_id = $request->get('form_id');
    $format = $request->get('format', 'csv');
    $date_from = $request->get('date_from');
    $date_to = $request->get('date_to');
    
    if (!$form_id) {
        throw new Exception('表单ID不能为空');
    }
    
    $form = UformsHelper::getForm($form_id);
    if (!$form) {
        throw new Exception('表单不存在');
    }
    
    // 构建查询
    $select = $db->select('*')->from('table.uforms_submissions')
                 ->where('form_id = ?', $form_id);
    
    if ($date_from) {
        $select->where('created_time >= ?', strtotime($date_from));
    }
    
    if ($date_to) {
        $select->where('created_time <= ?', strtotime($date_to . ' 23:59:59'));
    }
    
    $submissions = $db->fetchAll($select->order('created_time DESC'));
    
    // 获取字段
    $fields = UformsHelper::getFormFields($form_id);
    
    if ($format === 'csv') {
        $csv_data = UformsHelper::exportToCSV($submissions, $fields);
        
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => true,
            'data' => $csv_data,
            'filename' => $form['name'] . '_submissions_' . date('Y-m-d') . '.csv'
        ));
    } else {
        throw new Exception('不支持的导出格式');
    }
}

function handleGetStats() {
    global $db;
    
    $stats = UformsHelper::getStats();
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'data' => $stats
    ));
}

function handleGetRecentSubmissions() {
    global $db, $request;
    
    $limit = intval($request->get('limit', 10));
    
    $submissions = $db->fetchAll(
        $db->select('s.id, s.form_id, s.status, s.created_time, f.title as form_title')
           ->from('table.uforms_submissions s')
           ->join('table.uforms_forms f', 's.form_id = f.id')
           ->order('s.created_time DESC')
           ->limit($limit)
    );
    
    foreach ($submissions as &$submission) {
        $submission['status_label'] = UformsHelper::getStatusLabel($submission['status']);
        $submission['created_time'] = UformsHelper::formatTime($submission['created_time']);
    }
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'data' => $submissions
    ));
}

function handleGetUnreadNotifications() {
    global $db;
    
    $count = $db->fetchObject(
        $db->select('COUNT(*) as count')
           ->from('table.uforms_system_notifications')
           ->where('is_read = ?', 0)
    )->count;
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'count' => $count
    ));
}

function handleBatchDelete() {
    global $db, $request;
    
    $items = $request->get('items', array());
    if (empty($items)) {
        throw new Exception('没有选择要删除的项目');
    }
    
    $count = 0;
    foreach ($items as $id) {
        $db->query($db->delete('table.uforms_submissions')->where('id = ?', $id));
        $db->query($db->delete('table.uforms_files')->where('submission_id = ?', $id));
        $count++;
    }
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'message' => "成功删除 {$count} 项"
    ));
}

function handleBatchMarkRead() {
    global $db, $request;
    
    $items = $request->get('items', array());
    if (empty($items)) {
        throw new Exception('没有选择要标记的项目');
    }
    
    $count = 0;
    foreach ($items as $id) {
        $db->query($db->update('table.uforms_submissions')
                     ->rows(array('status' => 'read', 'modified_time' => time()))
                     ->where('id = ?', $id));
        $count++;
    }
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'message' => "成功标记 {$count} 项为已读"
    ));
}

function handleBatchMarkSpam() {
    global $db, $request;
    
    $items = $request->get('items', array());
    if (empty($items)) {
        throw new Exception('没有选择要标记的项目');
    }
    
    $count = 0;
    foreach ($items as $id) {
        $db->query($db->update('table.uforms_submissions')
                     ->rows(array('status' => 'spam', 'modified_time' => time()))
                     ->where('id = ?', $id));
        $count++;
    }
    
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => true,
        'message' => "成功标记 {$count} 项为垃圾邮件"
    ));
}
?>
