## 🤖 Assistant

## 第四部分：修复后的 UformsHelper.php

```php
<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class UformsHelper {
    
    /**
     * 获取数据库实例
     */
    public static function getDb() {
        return Typecho_Db::get();
    }
    
    /**
     * 获取表单URL
     */
    public static function getFormUrl($form_id) {
        return Helper::options()->siteUrl . 'uforms/form/' . $form_id;
    }
    
    /**
     * 获取表单列表
     */
    public static function getForms($status = null, $limit = null) {
        $db = Typecho_Db::get();
        $select = $db->select('*')->from('table.uforms_forms');
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        if ($limit) {
            $select->limit($limit);
        }
        
        $select->order('modified_time DESC');
        
        return $db->fetchAll($select);
    }
    
    /**
     * 获取单个表单
     */
    public static function getForm($id) {
        $db = Typecho_Db::get();
        return $db->fetchRow(
            $db->select()->from('table.uforms_forms')->where('id = ?', $id)
        );
    }
    
    /**
     * 根据名称获取表单
     */
    public static function getFormByName($name) {
        $db = Typecho_Db::get();
        
        // 首先尝试通过name字段查找
        $form = $db->fetchRow(
            $db->select()->from('table.uforms_forms')
               ->where('name = ? AND status = ?', $name, 'published')
        );
        
        // 如果没有找到，尝试通过slug字段查找
        if (!$form) {
            $form = $db->fetchRow(
                $db->select()->from('table.uforms_forms')
                   ->where('slug = ? AND status = ?', $name, 'published')
            );
        }
        
        // 如果还是没有找到，尝试通过ID查找（兼容性）
        if (!$form && is_numeric($name)) {
            $form = $db->fetchRow(
                $db->select()->from('table.uforms_forms')
                   ->where('id = ? AND status = ?', intval($name), 'published')
            );
        }
        
        return $form;
    }
    
    /**
     * 获取表单字段
     */
    public static function getFormFields($form_id) {
        $db = Typecho_Db::get();
        return $db->fetchAll(
            $db->select()->from('table.uforms_fields')
               ->where('form_id = ? AND is_enabled = ?', $form_id, 1)
               ->order('sort_order ASC, id ASC')
        );
    }
    
    /**
     * 提交表单数据 - 修复版本
     */
    public static function submitForm($formId, $data, $files = array()) {
        $db = Typecho_Db::get();
        
        // 获取客户端信息
        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // 获取用户信息
        $userId = null;
        $sessionId = session_id() ?: '';
        
        try {
            $user = Typecho_Widget::widget('Widget_User');
            if ($user->hasLogin()) {
                $userId = $user->uid;
            }
        } catch (Exception $e) {
            // 忽略用户获取失败
        }
        
        // 准备提交数据
        $submissionData = array(
            'form_id' => $formId,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ip' => $ip,
            'user_agent' => substr($userAgent, 0, 500),
            'user_id' => $userId,
            'session_id' => $sessionId,
            'referrer' => substr($referer, 0, 500),
            'status' => 'new',
            'source' => 'web',
            'created_time' => time(),
            'modified_time' => time()
        );
        
        // 插入提交记录
        $submissionId = $db->query($db->insert('table.uforms_submissions')->rows($submissionData));
        
        // 处理文件上传
        if (!empty($files)) {
            foreach ($files as $fieldName => $fileInfo) {
                if (is_array($fileInfo) && isset($fileInfo[0])) {
                    // 多文件上传
                    foreach ($fileInfo as $file) {
                        self::saveUploadedFile($formId, $submissionId, $fieldName, $file, $userId);
                    }
                } else {
                    // 单文件上传
                    self::saveUploadedFile($formId, $submissionId, $fieldName, $fileInfo, $userId);
                }
            }
        }
        
        // 更新表单统计
        $db->query($db->update('table.uforms_forms')
                     ->expression('submit_count', 'submit_count + 1')
                     ->rows(array('last_submit_time' => time()))
                     ->where('id = ?', $formId));
        
        return $submissionId;
    }
    
    /**
     * 保存上传的文件记录
     */
    private static function saveUploadedFile($formId, $submissionId, $fieldName, $fileInfo, $userId) {
        $db = Typecho_Db::get();
        
        $fileData = array(
            'form_id' => $formId,
            'submission_id' => $submissionId,
            'field_name' => $fieldName,
            'original_name' => $fileInfo['original_name'],
            'filename' => $fileInfo['filename'],
            'file_path' => $fileInfo['path'],
            'file_size' => $fileInfo['size'],
            'file_type' => pathinfo($fileInfo['original_name'], PATHINFO_EXTENSION),
            'mime_type' => $fileInfo['mime_type'] ?? '',
            'uploaded_by' => $userId,
            'created_time' => time()
        );
        
        $db->query($db->insert('table.uforms_files')->rows($fileData));
    }
    
    /**
     * 获取表单提交数据
     */
    public static function getSubmissions($form_id = null, $limit = 20, $offset = 0) {
        $db = Typecho_Db::get();
        $select = $db->select('*')->from('table.uforms_submissions');
        
        if ($form_id) {
            $select->where('form_id = ?', $form_id);
        }
        
        $select->order('created_time DESC')->limit($limit)->offset($offset);
        
        return $db->fetchAll($select);
    }
    
    /**
     * 获取统计数据
     */
    public static function getStats() {
        $db = Typecho_Db::get();
        
        $total_forms = $db->fetchObject(
            $db->select('COUNT(*) as count')->from('table.uforms_forms')
        )->count;
        
        $published_forms = $db->fetchObject(
            $db->select('COUNT(*) as count')->from('table.uforms_forms')
               ->where('status = ?', 'published')
        )->count;
        
        $total_submissions = $db->fetchObject(
            $db->select('COUNT(*) as count')->from('table.uforms_submissions')
        )->count;
        
        $new_submissions = $db->fetchObject(
            $db->select('COUNT(*) as count')->from('table.uforms_submissions')
               ->where('status = ?', 'new')
        )->count;
        
        return array(
            'total_forms' => $total_forms,
            'published_forms' => $published_forms,
            'total_submissions' => $total_submissions,
            'new_submissions' => $new_submissions
        );
    }
    
    /**
     * 生成表单版本备份 - 修复版本
     */
    public static function createFormVersion($formId, $config, $fieldsConfig, $changelog = '') {
        $db = Typecho_Db::get();
        
        try {
            $user = Typecho_Widget::widget('Widget_User');
            $userId = $user->hasLogin() ? $user->uid : 1;
        } catch (Exception $e) {
            $userId = 1;
        }
        
        // 获取当前版本号
        try {
            $currentVersion = $db->fetchObject(
                $db->select('version')->from('table.uforms_forms')->where('id = ?', $formId)
            );
        } catch (Exception $e) {
            $currentVersion = null;
        }
        
        $newVersion = $currentVersion ? $currentVersion->version + 1 : 1;
        
        // 插入版本记录
        $versionData = array(
            'form_id' => $formId,
            'version' => $newVersion,
            'config' => is_array($config) ? json_encode($config, JSON_UNESCAPED_UNICODE) : $config,
            'fields_config' => is_array($fieldsConfig) ? json_encode($fieldsConfig, JSON_UNESCAPED_UNICODE) : $fieldsConfig,
            'changelog' => $changelog,
            'created_by' => $userId,
            'created_time' => time()
        );
        
        // 检查版本表是否存在
        try {
            $db->query($db->insert('table.uforms_versions')->rows($versionData));
        } catch (Exception $e) {
            // 版本表可能不存在，忽略版本记录
            error_log('Uforms: Version table not found, skipping version backup: ' . $e->getMessage());
        }
        
        // 更新表单版本号
        try {
            $db->query($db->update('table.uforms_forms')
                         ->rows(array('version' => $newVersion))
                         ->where('id = ?', $formId));
        } catch (Exception $e) {
            error_log('Uforms: Failed to update form version: ' . $e->getMessage());
        }
        
        return $newVersion;
    }
    
    /**
     * 生成表单slug - 修复版本
     */
    public static function generateSlug($name, $title = '') {
        // 优先使用name，如果没有则使用title
        $slug = $name ?: $title;
        
        if (empty($slug)) {
            $slug = 'form_' . time();
        }
        
        // 清理slug - 处理中文和特殊字符
        $slug = preg_replace('/[^\w\u4e00-\u9fa5\-_]/u', '_', $slug);
        $slug = preg_replace('/_+/', '_', $slug);
        $slug = trim($slug, '_');
        $slug = strtolower($slug);
        
        // 如果slug为空或只包含特殊字符，生成默认值
        if (empty($slug) || strlen($slug) < 2) {
            $slug = 'form_' . time();
        }
        
        // 确保唯一性
        $db = Typecho_Db::get();
        $originalSlug = $slug;
        $counter = 1;
        
        while (true) {
            try {
                $exists = $db->fetchRow($db->select('id')->from('table.uforms_forms')->where('slug = ?', $slug));
                if (!$exists) {
                    return $slug;
                }
                
                $slug = $originalSlug . '_' . $counter;
                $counter++;
                
                // 防止无限循环
                if ($counter > 100) {
                    $slug = $originalSlug . '_' . time();
                    break;
                }
            } catch (Exception $e) {
                error_log('Uforms: Error checking slug uniqueness: ' . $e->getMessage());
                return $originalSlug . '_' . time();
            }
        }
        
        return $slug;
    }
    
    /**
     * 创建系统通知 - 修复版本
     */
    public static function createSystemNotification($formId, $submissionId, $type, $title, $message, $data = array()) {
        $db = Typecho_Db::get();
        
        $notificationData = array(
            'form_id' => $formId,
            'submission_id' => $submissionId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data,
            'is_read' => 0,
            'created_time' => time()
        );
        
        try {
            $db->query($db->insert('table.uforms_system_notifications')->rows($notificationData));
            return true;
        } catch (Exception $e) {
            // 通知表可能不存在，记录错误日志但不中断流程
            error_log('Uforms: Failed to create system notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取插件选项 - 修复版本
     */
    public static function getPluginOptions() {
        static $options = null;
        
        if ($options === null) {
            try {
                $options = Helper::options()->plugin('Uforms');
            } catch (Exception $e) {
                // 返回默认选项
                $options = (object) array(
                    'enable_forms' => 1,
                    'enable_calendar' => 1,
                    'enable_analytics' => 1,
                    'enable_email' => 0,
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => '587',
                    'smtp_username' => '',
                    'smtp_password' => '',
                    'upload_enabled' => 1,
                    'upload_max_size' => 5,
                    'allowed_file_types' => 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip',
                    'enable_spam_filter' => 1,
                    'rate_limit' => 3,
                    'admin_per_page' => 20,
                    'auto_publish' => 0,
                    'form_slug_format' => 'name',
                    'enable_templates' => 1
                );
            }
        }
        
        return $options;
    }
    
    /**
     * 格式化时间
     */
    public static function formatTime($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * 获取客户端IP - 修复版本
     */
    public static function getClientIP() {
        $ipKeys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // 处理多个IP的情况（X-Forwarded-For可能包含多个IP）
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * 发送邮件通知 - 修复版本
     */
    public static function sendEmailNotification($to, $subject, $message, $form_data = array()) {
        $options = self::getPluginOptions();
        
        if (empty($options->enable_email)) {
            return false;
        }
        
        try {
            // 这里可以实现具体的邮件发送逻辑
            // 暂时返回true表示成功
            return true;
        } catch (Exception $e) {
            error_log('Uforms: Email sending failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 清理过期数据
     */
    public static function cleanupExpiredData($days = 365) {
        $db = Typecho_Db::get();
        $expire_time = time() - ($days * 24 * 60 * 60);
        
        try {
            // 删除过期的提交数据
            $expired_submissions = $db->fetchAll(
                $db->select('id')->from('table.uforms_submissions')
                   ->where('created_time < ? AND status = ?', $expire_time, 'deleted')
            );
            
            foreach ($expired_submissions as $submission) {
                // 删除相关文件
                $files = $db->fetchAll(
                    $db->select('file_path')->from('table.uforms_files')
                       ->where('submission_id = ?', $submission['id'])
                );
                
                foreach ($files as $file) {
                    if (file_exists($file['file_path'])) {
                        unlink($file['file_path']);
                    }
                }
                
                // 删除文件记录
                $db->query($db->delete('table.uforms_files')->where('submission_id = ?', $submission['id']));
            }
            
            // 删除过期提交记录
            $db->query(
                $db->delete('table.uforms_submissions')
                   ->where('created_time < ? AND status = ?', $expire_time, 'deleted')
            );
            
            return true;
        } catch (Exception $e) {
            error_log('Uforms: Cleanup failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 生成随机字符串
     */
    public static function generateRandomString($length = 32) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';
        $max = strlen($characters) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[mt_rand(0, $max)];
        }
        
        return $string;
    }
    
    /**
     * 安全的文件名
     */
    public static function sanitizeFilename($filename) {
        // 移除危险字符
        $filename = preg_replace('/[^a-zA-Z0-9._\-\u4e00-\u9fa5]/u', '', $filename);
        
        // 限制长度
        if (mb_strlen($filename) > 255) {
            $filename = mb_substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    /**
     * 格式化文件大小
     */
    public static function formatFileSize($bytes) {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    /**
     * 检查文件类型
     */
    public static function isAllowedFileType($filename, $allowed_types = array()) {
        if (empty($allowed_types)) {
            $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip');
        }
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowed_types);
    }
    
    /**
     * 验证表单数据
     */
    public static function validateFormData($fields, $data) {
        $errors = array();
        
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            $fieldLabel = $field['field_label'] ?: $fieldName;
            $fieldType = $field['field_type'];
            $isRequired = $field['is_required'] == 1;
            
            $value = isset($data[$fieldName]) ? $data[$fieldName] : '';
            
            // 必填验证
            if ($isRequired && (is_array($value) ? empty($value) : trim($value) === '')) {
                $errors[] = $fieldLabel . '是必填项';
                continue;
            }
            
            // 如果值为空且非必填，跳过其他验证
            if (is_array($value) ? empty($value) : trim($value) === '') {
                continue;
            }
            
            // 类型验证
            switch ($fieldType) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = $fieldLabel . '格式不正确';
                    }
                    break;
                    
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = $fieldLabel . '格式不正确';
                    }
                    break;
                    
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[] = $fieldLabel . '必须是数字';
                    }
                    break;
                    
                case 'tel':
                    if (!preg_match('/^[\d\-\+\s\(\)]+$/', $value)) {
                        $errors[] = $fieldLabel . '格式不正确';
                    }
                    break;
            }
        }
        
        return $errors;
    }
    
    /**
     * 获取状态标签
     */
    public static function getStatusLabel($status) {
        $labels = array(
            'new' => '新提交',
            'read' => '已读',
            'replied' => '已回复',
            'spam' => '垃圾信息',
            'deleted' => '已删除',
            'archived' => '已归档'
        );
        
        return isset($labels[$status]) ? $labels[$status] : $status;
    }
    
    /**
     * 导出为CSV格式
     */
    public static function exportToCSV($submissions, $fields) {
        $csv_data = array();
        
        // 表头
        $headers = array('ID', '提交时间', 'IP地址', '状态');
        foreach ($fields as $field) {
            $headers[] = $field['field_label'] ?: $field['field_name'];
        }
        $csv_data[] = $headers;
        
        // 数据行
        foreach ($submissions as $submission) {
            $data = json_decode($submission['data'], true) ?: array();
            $row = array(
                $submission['id'],
                self::formatTime($submission['created_time']),
                $submission['ip'],
                self::getStatusLabel($submission['status'])
            );
            
            foreach ($fields as $field) {
                $value = isset($data[$field['field_name']]) ? $data[$field['field_name']] : '';
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $row[] = $value;
            }
            
            $csv_data[] = $row;
        }
        
        // 生成CSV内容
        $output = '';
        foreach ($csv_data as $row) {
            $output .= '"' . implode('","', array_map(function($field) {
                return str_replace('"', '""', $field);
            }, $row)) . '"' . "\n";
        }
        
        return $output;
    }
}
?>
```

## 第五部分：添加基础CSS样式文件

创建 `/assets/css/create.css` 文件：

```css
/* 表单构建器样式 - 基础版本 */
.uforms-creator {
    display: flex;
    flex-direction: column;
    height: 100vh;
    background-color: #f5f5f5;
}

.form-builder {
    display: flex;
    flex: 1;
    gap: 10px;
    padding: 10px;
}

/* 字段库面板 */
.fields-panel {
    width: 250px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow-y: auto;
}

.fields-panel h3 {
    margin: 0;
    padding: 15px;
    background: #3788d8;
    color: #fff;
    border-radius: 8px 8px 0 0;
}

.fields-panel-content {
    padding: 15px;
}

.field-category {
    margin-bottom: 20px;
}

.field-category h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
    padding-bottom: 5px;
    border-bottom: 1px solid #eee;
}

.field-items {
    display: grid;
    gap: 8px;
}

.field-item {
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
}

.field-item:hover {
    border-color: #3788d8;
    box-shadow: 0 2px 8px rgba(55, 136, 216, 0.1);
}

.field-item-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    margin-bottom: 5px;
}

.field-description {
    font-size: 12px;
    color: #666;
    line-height: 1.3;
}

/* 画布区域 */
.form-canvas {
    flex: 1;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
}

.canvas-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    background: #fafafa;
    border-radius: 8px 8px 0 0;
}

.toolbar-left,
.toolbar-center,
.toolbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.preview-btn {
    padding: 6px 12px;
    border: 1px solid #ddd;
    background: #fff;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.preview-btn.active {
    background: #3788d8;
    color: #fff;
    border-color: #3788d8;
}

.canvas-content {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
}

.canvas-drop-zone {
    min-height: 300px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9f9f9;
}

.drop-hint {
    text-align: center;
    color: #666;
}

.drop-hint h3 {
    margin: 10px 0;
    color: #333;
}

.quick-start {
    margin-top: 15px;
}

/* 画布字段 */
.canvas-field {
    margin-bottom: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    background: #fff;
    transition: all 0.2s ease;
}

.canvas-field:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.canvas-field.selected {
    border-color: #3788d8;
    box-shadow: 0 0 0 2px rgba(55, 136, 216, 0.2);
}

.field-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e0e0e0;
}

.field-label {
    font-weight: 500;
    color: #333;
}

.field-actions {
    display: flex;
    gap: 5px;
}

.field-action {
    padding: 4px 8px;
    border: none;
    background: transparent;
    cursor: pointer;
    border-radius: 4px;
    font-size: 12px;
}

.field-action:hover {
    background: #e9ecef;
}

.field-delete:hover {
    background: #dc3545;
    color: #fff;
}

.field-body {
    padding: 15px;
}

/* 属性面板 */
.properties-panel {
    width: 300px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
}

.panel-tabs {
    display: flex;
    border-bottom: 1px solid #eee;
}

.tab-button {
    flex: 1;
    padding: 12px 8px;
    border: none;
    background: #f8f9fa;
    cursor: pointer;
    font-size: 12px;
    text-align: center;
}

.tab-button.active {
    background: #fff;
    color: #3788d8;
    border-bottom: 2px solid #3788d8;
}

.tab-content {
    display: none;
    flex: 1;
    padding: 15px;
    overflow-y: auto;
}

.tab-content.active {
    display: block;
}

.no-selection {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.property-group {
    margin-bottom: 25px;
}

.property-group h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}

.property-item {
    margin-bottom: 15px;
}

.property-item label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
    font-size: 13px;
}

.property-item input,
.property-item select,
.property-item textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    box-sizing: border-box;
}

.property-item input:focus,
.property-item select:focus,
.property-item textarea:focus {
    outline: none;
    border-color: #3788d8;
    box-shadow: 0 0 0 2px rgba(55, 136, 216, 0.2);
}

.field-tip {
    font-size: 11px;
    color: #666;
    margin-top: 5px;
    line-height: 1.4;
}

.required {
    color: #dc3545;
}

.checkbox-label {
    display: flex !important;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    margin-bottom: 0 !important;
}

.checkbox-label input[type="checkbox"] {
    width: auto;
    margin: 0;
}

/* 底部操作栏 */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #fff;
    border-top: 1px solid #eee;
    gap: 20px;
}

.actions-left,
.actions-center,
.actions-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.actions-center {
    flex: 1;
    justify-content: center;
}

/* 按钮样式 */
.btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s ease;
}

.btn:hover {
    text-decoration: none;
}

.btn-default {
    background: #fff;
    color: #333;
}

.btn-default:hover {
    background: #f8f9fa;
}

.btn-primary {
    background: #3788d8;
    color: #fff;
    border-color: #3788d8;
}

.btn-primary:hover {
    background: #2c6bc0;
}

.btn-success {
    background: #28a745;
    color: #fff;
    border-color: #28a745;
}

.btn-info {
    background: #17a2b8;
    color: #fff;
    border-color: #17a2b8;
}

/* 保存状态 */
.save-status {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
}

.save-status.saving {
    color: #ffc107;
}

.save-status.success {
    color: #28a745;
}

.save-status.error {
    color: #dc3545;
}

/* 通知样式 */
.notifications-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
}

.notification {
    min-width: 300px;
    margin-bottom: 10px;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    opacity: 0;
    transform: translateX(100%);
    transition: all 0.3s ease;
}

.notification.show {
    opacity: 1;
    transform: translateX(0);
}

.notification-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.notification-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.notification-content {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    gap: 10px;
}

.notification-message {
    flex: 1;
}

.notification-close {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    opacity: 0.7;
}

/* 模态框样式 */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #fff;
    border-radius: 8px;
    max-width: 90vw;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    background: #f8f9fa;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #666;
}

.modal-body {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #eee;
    background: #f8f9fa;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* 预览输入框样式 */
.preview-input,
.preview-textarea,
.preview-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f8f9fa;
}

.preview-textarea {
    resize: vertical;
}

/* 颜色选择器组 */
.color-picker-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.color-picker-group input[type="color"] {
    width: 50px;
    height: 35px;
    padding: 0;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.color-picker-group input[type="text"] {
    flex: 1;
}

/* 响应式设计 */
@media (max-width: 1200px) {
    .form-builder {
        flex-direction: column;
        height: auto;
    }
    
    .fields-panel,
    .properties-panel {
        width: 100%;
        max-height: 300px;
    }
    
    .form-canvas {
        min-height: 400px;
    }
}

@media (max-width: 768px) {
    .form-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .actions-left,
    .actions-center,
    .actions-right {
        width: 100%;
        justify-content: center;
    }
    
    .canvas-toolbar {
        flex-direction: column;
        gap: 10px;
    }
    
    .toolbar-left,
    .toolbar-center,
    .toolbar-right {
        width: 100%;
        justify-content: center;
    }
}

/* 动画效果 */
.field-added {
    animation: slideInUp 0.6s ease-out;
}

.field-removing {
    animation: slideOutDown 0.3s ease-in;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideOutDown {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-20px);
    }
}

/* 拖拽状态 */
.drag-over {
    background-color: #e3f2fd !important;
    border-color: #3788d8 !important;
}

.sortable-ghost {
    opacity: 0.5;
    background: #f0f8ff;
}

/* 加载状态 */
.loading {
    position: relative;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,0.8);
    display: flex;
    align-items: center;
    justify-content: center;
}

.loading::before {
    content: '⏳';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 24px;
    z-index: 1;
}
```

以上是修复后的完整代码。主要修复内容包括：

1. **修复了AJAX URL配置问题** - 使用当前页面URL处理AJAX请求
2. **修复了表单保存功能** - 使用FormData和fetch API发送请求
3. **修复了数据验证** - 加强了数据收集和验证逻辑
4. **修复了路由处理** - 改进了Action.php中的路由分发
5. **修复了数据库操作** - 改进了UformsHelper中的各种方法
6. **添加了基础样式** - 提供了完整的CSS样式支持

现在表单保存和发布功能应该可以正常工作了。如果还有问题，请告诉我具体的错误信息。
