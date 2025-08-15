<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Uforms 后台功能函数库
 */

// 权限验证函数
function uforms_check_permission($permission = 'administrator') {
    $user = Typecho_Widget::widget('Widget_User');
    
    if (!$user->hasLogin()) {
        return false;
    }
    
    // 检查用户权限
    switch ($permission) {
        case 'administrator':
            return $user->pass('administrator', true);
        case 'editor':
            return $user->pass('editor', true) || $user->pass('administrator', true);
        case 'contributor':
            return $user->pass('contributor', true) || $user->pass('editor', true) || $user->pass('administrator', true);
        default:
            return $user->hasLogin();
    }
}

// 获取当前用户
function uforms_get_current_user() {
    return Typecho_Widget::widget('Widget_User');
}

// 输出防护函数
function uforms_escape_html($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Uforms 核心辅助类
 */
class UformsHelper
{
    private static $db = null;
    
    /**
     * 获取数据库实例
     */
    public static function getDb() {
        if (self::$db === null) {
            self::$db = Typecho_Db::get();
        }
        return self::$db;
    }
    
    /**
     * 获取表单URL
     */
    public static function getFormUrl($formId, $useId = true) {
        $options = Helper::options();
        
        if ($useId) {
            return $options->siteUrl . 'uforms/form/' . $formId;
        } else {
            $form = self::getForm($formId);
            if ($form) {
                return $options->siteUrl . 'uforms/form/' . $form['name'];
            }
        }
        
        return $options->siteUrl . 'uforms/form/' . $formId;
    }
    
    /**
     * 获取表单列表
     */
    public static function getForms($page = 1, $pageSize = 20, $status = null, $search = null) {
        $db = self::getDb();
        $select = $db->select()->from('table.uforms_forms');
        
        // 搜索条件
        if ($search) {
            $select->where('title LIKE ? OR name LIKE ?', '%' . $search . '%', '%' . $search . '%');
        }
        
        // 状态筛选
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        // 分页
        $offset = ($page - 1) * $pageSize;
        $select->order('modified_time', Typecho_Db::SORT_DESC)
               ->limit($pageSize)
               ->offset($offset);
        
        return $db->fetchAll($select);
    }
    
    /**
     * 获取表单总数
     */
    public static function getFormsCount($status = null, $search = null) {
        $db = self::getDb();
        $select = $db->select('COUNT(*) AS count')->from('table.uforms_forms');
        
        if ($search) {
            $select->where('title LIKE ? OR name LIKE ?', '%' . $search . '%', '%' . $search . '%');
        }
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        $result = $db->fetchRow($select);
        return $result ? intval($result['count']) : 0;
    }
    
    /**
     * 根据ID获取表单
     */
    public static function getForm($id) {
        $db = self::getDb();
        return $db->fetchRow($db->select()->from('table.uforms_forms')->where('id = ?', $id));
    }
    
    /**
     * 根据名称获取表单
     */
    public static function getFormByName($name) {
        $db = self::getDb();
        return $db->fetchRow($db->select()->from('table.uforms_forms')->where('name = ?', $name));
    }
    
    /**
     * 获取表单字段
     */
    public static function getFormFields($formId) {
        $db = self::getDb();
        return $db->fetchAll(
            $db->select()->from('table.uforms_fields')
               ->where('form_id = ?', $formId)
               ->order('sort_order', Typecho_Db::SORT_ASC)
        );
    }
    
    /**
     * 创建表单
     */
    public static function createForm($data) {
        $db = self::getDb();
        $user = Typecho_Widget::widget('Widget_User');
        
        $formData = array(
            'name' => $data['name'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'config' => is_array($data['config']) ? json_encode($data['config']) : ($data['config'] ?? '{}'),
            'settings' => is_array($data['settings']) ? json_encode($data['settings']) : ($data['settings'] ?? '{}'),
            'status' => $data['status'] ?? 'draft',
            'author_id' => $user->uid,
            'view_count' => 0,
            'submit_count' => 0,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        return $db->query($db->insert('table.uforms_forms')->rows($formData));
    }
    
    /**
     * 更新表单
     */
    public static function updateForm($id, $data) {
        $db = self::getDb();
        
        $formData = array(
            'name' => $data['name'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'config' => is_array($data['config']) ? json_encode($data['config']) : ($data['config'] ?? '{}'),
            'settings' => is_array($data['settings']) ? json_encode($data['settings']) : ($data['settings'] ?? '{}'),
            'status' => $data['status'] ?? 'draft',
            'modified_time' => time()
        );
        
        return $db->query($db->update('table.uforms_forms')->rows($formData)->where('id = ?', $id));
    }
    
    /**
     * 删除表单
     */
    public static function deleteForm($id) {
        $db = self::getDb();
        
        // 删除表单
        $db->query($db->delete('table.uforms_forms')->where('id = ?', $id));
        
        // 删除相关字段
        $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $id));
        
        // 删除相关提交
        $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $id));
        
        // 删除相关通知
        $db->query($db->delete('table.uforms_notifications')->where('form_id = ?', $id));
        
        // 删除相关文件
        $db->query($db->delete('table.uforms_files')->where('form_id = ?', $id));
        
        // 删除日历事件
        $db->query($db->delete('table.uforms_calendar')->where('form_id = ?', $id));
        
        return true;
    }
    
    /**
     * 保存表单字段
     */
    public static function saveFormFields($formId, $fields) {
        $db = self::getDb();
        
        // 删除现有字段
        $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $formId));
        
        // 插入新字段
        if (is_array($fields)) {
            foreach ($fields as $index => $field) {
                if (!isset($field['name']) || !isset($field['type'])) {
                    continue;
                }
                
                $fieldData = array(
                    'form_id' => $formId,
                    'field_type' => $field['type'],
                    'field_name' => $field['name'],
                    'field_label' => $field['label'] ?? '',
                    'field_config' => json_encode($field),
                    'sort_order' => $field['sortOrder'] ?? $index,
                    'is_required' => !empty($field['required']) ? 1 : 0,
                    'created_time' => time()
                );
                
                $db->query($db->insert('table.uforms_fields')->rows($fieldData));
            }
        }
        
        return true;
    }
    
    /**
     * 获取表单提交记录
     */
    public static function getSubmissions($formId, $page = 1, $pageSize = 20, $status = null) {
        $db = self::getDb();
        $select = $db->select()->from('table.uforms_submissions')->where('form_id = ?', $formId);
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        $offset = ($page - 1) * $pageSize;
        $select->order('created_time', Typecho_Db::SORT_DESC)
               ->limit($pageSize)
               ->offset($offset);
        
        return $db->fetchAll($select);
    }
    
    /**
     * 获取提交记录总数
     */
    public static function getSubmissionsCount($formId, $status = null) {
        $db = self::getDb();
        $select = $db->select('COUNT(*) AS count')
                     ->from('table.uforms_submissions')
                     ->where('form_id = ?', $formId);
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        $result = $db->fetchRow($select);
        return $result ? intval($result['count']) : 0;
    }
    
    /**
     * 获取通知记录
     */
    public static function getNotifications($page = 1, $pageSize = 20, $isRead = null, $formId = null) {
        $db = self::getDb();
        $select = $db->select()->from('table.uforms_notifications');
        
        if ($isRead !== null) {
            $select->where('is_read = ?', $isRead ? 1 : 0);
        }
        
        if ($formId) {
            $select->where('form_id = ?', $formId);
        }
        
        $offset = ($page - 1) * $pageSize;
        $select->order('created_time', Typecho_Db::SORT_DESC)
               ->limit($pageSize)
               ->offset($offset);
        
        return $db->fetchAll($select);
    }
    
    /**
     * 获取通知记录总数
     */
    public static function getNotificationsCount($isRead = null, $formId = null) {
        $db = self::getDb();
        $select = $db->select('COUNT(*) AS count')->from('table.uforms_notifications');
        
        if ($isRead !== null) {
            $select->where('is_read = ?', $isRead ? 1 : 0);
        }
        
        if ($formId) {
            $select->where('form_id = ?', $formId);
        }
        
        $result = $db->fetchRow($select);
        return $result ? intval($result['count']) : 0;
    }
    
    /**
     * 标记通知为已读
     */
    public static function markNotificationAsRead($id) {
        $db = self::getDb();
        return $db->query($db->update('table.uforms_notifications')
                             ->rows(array('is_read' => 1, 'read_time' => time()))
                             ->where('id = ?', $id));
    }
    
    /**
     * 获取统计数据
     */
    public static function getStats() {
        $db = self::getDb();
        
        // 总表单数
        $totalForms = $db->fetchObject($db->select('COUNT(*) AS count')->from('table.uforms_forms'));
        
        // 已发布表单数
        $publishedForms = $db->fetchObject($db->select('COUNT(*) AS count')
                                              ->from('table.uforms_forms')
                                              ->where('status = ?', 'published'));
        
        // 总提交数
        $totalSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')->from('table.uforms_submissions'));
        
        // 未读通知数
        $newNotifications = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_notifications')
                                                ->where('is_read = ?', 0));
        
        // 今日提交数
        $todaySubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_submissions')
                                                ->where('created_time >= ?', strtotime('today')));
        
        // 本月提交数
        $monthSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_submissions')
                                                ->where('created_time >= ?', strtotime('first day of this month')));
        
        return array(
            'total_forms' => $totalForms ? $totalForms->count : 0,
            'published_forms' => $publishedForms ? $publishedForms->count : 0,
            'total_submissions' => $totalSubmissions ? $totalSubmissions->count : 0,
            'new_submissions' => $totalSubmissions ? $totalSubmissions->count : 0,
            'new_notifications' => $newNotifications ? $newNotifications->count : 0,
            'today_submissions' => $todaySubmissions ? $todaySubmissions->count : 0,
            'month_submissions' => $monthSubmissions ? $monthSubmissions->count : 0
        );
    }
    
    /**
     * 验证字段
     */
    public static function validateField($type, $value, $config) {
        $errors = array();
        
        // 必填检查
        if (!empty($config['required']) && empty($value)) {
            $errors[] = '此字段为必填项';
            return $errors;
        }
        
        if (empty($value)) {
            return $errors; // 非必填且为空，无需继续验证
        }
        
        // 根据类型验证
        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = '请输入有效的邮箱地址';
                }
                break;
                
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = '请输入有效的URL地址';
                }
                break;
                
            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = '请输入有效的数字';
                } else {
                    $num_value = floatval($value);
                    if (isset($config['min']) && $num_value < $config['min']) {
                        $errors[] = '数值不能小于 ' . $config['min'];
                    }
                    if (isset($config['max']) && $num_value > $config['max']) {
                        $errors[] = '数值不能大于 ' . $config['max'];
                    }
                }
                break;
                
            case 'tel':
                if (!preg_match('/^[\d\-\+\s\(\)]+$/', $value)) {
                    $errors[] = '请输入有效的电话号码';
                }
                break;
                
            case 'text':
            case 'textarea':
                $length = mb_strlen($value, 'UTF-8');
                if (isset($config['minLength']) && $length < $config['minLength']) {
                    $errors[] = '内容长度不能少于 ' . $config['minLength'] . ' 个字符';
                }
                if (isset($config['maxLength']) && $length > $config['maxLength']) {
                    $errors[] = '内容长度不能超过 ' . $config['maxLength'] . ' 个字符';
                }
                if (!empty($config['pattern']) && !preg_match('/' . $config['pattern'] . '/', $value)) {
                    $errors[] = $config['errorMessage'] ?: '输入格式不正确';
                }
                break;
        }
        
        return $errors;
    }
    
    /**
     * 发送邮件通知
     */
    public static function sendEmailNotification($to, $subject, $message, $form_data = array()) {
        // 检查是否启用邮件功能
        $settings_result = self::getDb()->fetchRow(
            self::getDb()->select('value')->from('table.options')
               ->where('name = ?', 'plugin:Uforms')
        );
        
        if (!$settings_result) {
            return false;
        }
        
        $settings = unserialize($settings_result['value']);
        if (empty($settings['enable_email'])) {
            return false;
        }
        
        // 使用PHPMailer发送邮件
        require_once dirname(__FILE__) . '/../lib/PHPMailer/PHPMailer.php';
        require_once dirname(__FILE__) . '/../lib/PHPMailer/SMTP.php';
        require_once dirname(__FILE__) . '/../lib/PHPMailer/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        
        try {
            // SMTP配置
            if (!empty($settings['smtp_host'])) {
                $mail->isSMTP();
                $mail->Host = $settings['smtp_host'];
                $mail->Port = $settings['smtp_port'] ?: 587;
                $mail->SMTPAuth = true;
                $mail->Username = $settings['smtp_username'];
                $mail->Password = $settings['smtp_password'];
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // 发件人和收件人
            $mail->setFrom($settings['smtp_username'], Helper::options()->title);
            $mail->addAddress($to);
            
            // 邮件内容
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            return $mail->send();
        } catch (Exception $e) {
            error_log('邮件发送失败: ' . $mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * 发送Slack通知
     */
    public static function sendSlackMessage($webhook, $message) {
        $payload = json_encode($message);
        
        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ));
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code == 200;
    }
    
    /**
     * 格式化时间
     */
    public static function formatTime($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * 格式化相对时间
     */
    public static function timeAgo($timestamp) {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . '天前';
        } elseif ($diff < 31536000) {
            return floor($diff / 2592000) . '个月前';
        } else {
            return floor($diff / 31536000) . '年前';
        }
    }
    
    /**
     * 格式化文件大小
     */
    public static function formatFileSize($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * 获取客户端IP
     */
    public static function getClientIP() {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '0.0.0.0';
    }
    
    /**
     * 生成随机字符串
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * 安全的文件名
     */
    public static function sanitizeFilename($filename) {
        // 移除危险字符
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // 限制长度
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    /**
     * 检查文件类型
     */
    public static function isAllowedFileType($filename, $allowed_types = array()) {
        if (empty($allowed_types)) {
            $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt');
        }
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowed_types);
    }
    
    /**
     * 生成缩略图
     */
    public static function generateThumbnail($source_path, $thumb_path, $max_width = 200, $max_height = 200) {
        if (!file_exists($source_path)) {
            return false;
        }
        
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return false;
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $mime = $image_info['mime'];
        
        // 计算缩略图尺寸
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = $width * $ratio;
        $new_height = $height * $ratio;
        
        // 创建画布
        $thumb = imagecreatetruecolor($new_width, $new_height);
        
        // 根据图片类型处理
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source = imagecreatefrompng($source_path);
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }
        
        // 生成缩略图
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        // 保存缩略图
        $result = false;
        switch ($mime) {
            case 'image/jpeg':
                $result = imagejpeg($thumb, $thumb_path, 90);
                break;
            case 'image/png':
                $result = imagepng($thumb, $thumb_path);
                break;
            case 'image/gif':
                $result = imagegif($thumb, $thumb_path);
                break;
        }
        
        // 释放资源
        imagedestroy($source);
        imagedestroy($thumb);
        
        return $result;
    }


        /**
     * 验证表单配置
     */
    public static function validateFormConfig($config) {
        $errors = array();
        
        if (empty($config['name'])) {
            $errors[] = '表单名称不能为空';
        }
        
        if (empty($config['title'])) {
            $errors[] = '表单标题不能为空';
        }
        
        if (!empty($config['name']) && !preg_match('/^[a-zA-Z0-9_-]+$/', $config['name'])) {
            $errors[] = '表单名称只能包含字母、数字、下划线和短横线';
        }
        
        return $errors;
    }
    
    /**
     * 生成唯一表单名称
     */
    public static function generateUniqueFormName($baseName) {
        $db = self::getDb();
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($baseName));
        $originalName = $name;
        $counter = 1;
        
        while (true) {
            $exists = $db->fetchRow($db->select('id')->from('table.uforms_forms')->where('name = ?', $name));
            if (!$exists) {
                return $name;
            }
            
            $name = $originalName . '_' . $counter;
            $counter++;
        }
    }
    
    /**
     * 复制表单
     */
    public static function duplicateForm($originalId, $newName = null, $newTitle = null) {
        $originalForm = self::getForm($originalId);
        if (!$originalForm) {
            return false;
        }
        
        $user = Typecho_Widget::widget('Widget_User');
        
        // 准备新表单数据
        $newData = array(
            'name' => $newName ?? self::generateUniqueFormName($originalForm['name'] . '_copy'),
            'title' => $newTitle ?? ($originalForm['title'] . ' (副本)'),
            'description' => $originalForm['description'],
            'config' => $originalForm['config'],
            'settings' => $originalForm['settings'],
            'status' => 'draft',
            'author_id' => $user->uid,
            'view_count' => 0,
            'submit_count' => 0,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        // 创建新表单
        $newFormId = self::createForm($newData);
        
        if ($newFormId) {
            // 复制字段
            $fields = self::getFormFields($originalId);
            $fieldsData = array();
            
            foreach ($fields as $field) {
                $fieldsData[] = json_decode($field['field_config'], true);
            }
            
            self::saveFormFields($newFormId, $fieldsData);
            return $newFormId;
        }
        
        return false;
    }
    
    /**
     * 批量更新表单状态
     */
    public static function bulkUpdateStatus($formIds, $status) {
        if (!is_array($formIds) || empty($formIds)) {
            return false;
        }
        
        $allowedStatus = array('draft', 'published', 'archived');
        if (!in_array($status, $allowedStatus)) {
            return false;
        }
        
        $db = self::getDb();
        $placeholders = str_repeat('?,', count($formIds) - 1) . '?';
        
        $query = "UPDATE `{$db->getPrefix()}uforms_forms` SET `status` = ?, `modified_time` = ? WHERE `id` IN ({$placeholders})";
        $params = array_merge(array($status, time()), $formIds);
        
        return $db->query($query, $params);
    }
    
    /**
     * 批量删除表单
     */
    public static function bulkDeleteForms($formIds) {
        if (!is_array($formIds) || empty($formIds)) {
            return false;
        }
        
        foreach ($formIds as $formId) {
            self::deleteForm($formId);
        }
        
        return true;
    }
    
    /**
     * 导出表单配置
     */
    public static function exportForm($formId) {
        $form = self::getForm($formId);
        if (!$form) {
            return false;
        }
        
        $fields = self::getFormFields($formId);
        
        $exportData = array(
            'form' => $form,
            'fields' => $fields,
            'export_time' => time(),
            'plugin_version' => '2.0.0'
        );
        
        return $exportData;
    }
    
    /**
     * 导入表单配置
     */
    public static function importForm($importData, $newName = null) {
        if (!is_array($importData) || !isset($importData['form'])) {
            return false;
        }
        
        $formData = $importData['form'];
        $fieldsData = $importData['fields'] ?? array();
        
        // 创建新表单
        $user = Typecho_Widget::widget('Widget_User');
        $newFormData = array(
            'name' => $newName ?? self::generateUniqueFormName($formData['name']),
            'title' => $formData['title'] . ' (导入)',
            'description' => $formData['description'],
            'config' => $formData['config'],
            'settings' => $formData['settings'],
            'status' => 'draft',
            'author_id' => $user->uid,
            'view_count' => 0,
            'submit_count' => 0,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        $newFormId = self::createForm($newFormData);
        
        if ($newFormId && !empty($fieldsData)) {
            // 导入字段
            $fields = array();
            foreach ($fieldsData as $field) {
                $fields[] = json_decode($field['field_config'], true);
            }
            
            self::saveFormFields($newFormId, $fields);
        }
        
        return $newFormId;
    }
    
    /**
     * 清理旧数据
     */
    public static function cleanupOldData($days = 30) {
        $db = self::getDb();
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        // 清理旧的通知记录
        $db->query("DELETE FROM `{$db->getPrefix()}uforms_notifications` WHERE `created_time` < ? AND `is_read` = 1", $cutoffTime);
        
        // 清理旧的统计记录
        $db->query("DELETE FROM `{$db->getPrefix()}uforms_stats` WHERE `created_time` < ?", $cutoffTime);
        
        // 清理旧的垃圾内容日志
        $db->query("DELETE FROM `{$db->getPrefix()}uforms_spam_log` WHERE `created_time` < ?", $cutoffTime);
        
        return true;
    }
    
    /**
     * 清理过期数据（扩展版）
     */
    public static function cleanupExpiredData($days = 365) {
        $db = self::getDb();
        $expire_time = time() - ($days * 24 * 60 * 60);
        
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
        
        // 删除过期通知记录
        $db->query(
            $db->delete('table.uforms_notifications')
               ->where('created_time < ?', $expire_time)
        );
        
        // 删除过期系统通知
        $db->query(
            $db->delete('table.uforms_system_notifications')
               ->where('created_time < ? AND is_read = ?', $expire_time, 1)
        );
    }
    
    /**
     * 获取字段配置模板
     */
    public static function getFieldTemplates() {
        return array(
            'contact_form' => array(
                'name' => '联系表单',
                'fields' => array(
                    array('type' => 'text', 'name' => 'name', 'label' => '姓名', 'required' => true),
                    array('type' => 'email', 'name' => 'email', 'label' => '邮箱', 'required' => true),
                    array('type' => 'tel', 'name' => 'phone', 'label' => '电话', 'required' => false),
                    array('type' => 'textarea', 'name' => 'message', 'label' => '留言内容', 'required' => true, 'rows' => 5)
                )
            ),
            'registration_form' => array(
                'name' => '报名表单',
                'fields' => array(
                    array('type' => 'text', 'name' => 'name', 'label' => '姓名', 'required' => true),
                    array('type' => 'email', 'name' => 'email', 'label' => '邮箱', 'required' => true),
                    array('type' => 'tel', 'name' => 'phone', 'label' => '手机号', 'required' => true),
                    array('type' => 'radio', 'name' => 'gender', 'label' => '性别', 'required' => true, 
                           'options' => array(array('label' => '男', 'value' => 'male'), array('label' => '女', 'value' => 'female'))),
                    array('type' => 'date', 'name' => 'birthday', 'label' => '出生日期', 'required' => false),
                    array('type' => 'textarea', 'name' => 'note', 'label' => '备注', 'required' => false)
                )
            ),
            'survey_form' => array(
                'name' => '调查问卷',
                'fields' => array(
                    array('type' => 'radio', 'name' => 'age_group', 'label' => '年龄段', 'required' => true,
                           'options' => array(
                               array('label' => '18-25岁', 'value' => '18-25'),
                               array('label' => '26-35岁', 'value' => '26-35'),
                               array('label' => '36-45岁', 'value' => '36-45'),
                               array('label' => '46岁以上', 'value' => '46+')
                           )),
                    array('type' => 'checkbox', 'name' => 'interests', 'label' => '兴趣爱好', 'required' => false,
                           'options' => array(
                               array('label' => '运动', 'value' => 'sports'),
                               array('label' => '音乐', 'value' => 'music'),
                               array('label' => '读书', 'value' => 'reading'),
                               array('label' => '旅行', 'value' => 'travel')
                           )),
                    array('type' => 'rating', 'name' => 'satisfaction', 'label' => '满意度评分', 'required' => true, 'max' => 5),
                    array('type' => 'textarea', 'name' => 'suggestions', 'label' => '意见建议', 'required' => false)
                )
            ),
            'booking_form' => array(
                'name' => '预约表单',
                'fields' => array(
                    array('type' => 'text', 'name' => 'name', 'label' => '姓名', 'required' => true),
                    array('type' => 'tel', 'name' => 'phone', 'label' => '联系电话', 'required' => true),
                    array('type' => 'email', 'name' => 'email', 'label' => '邮箱', 'required' => true),
                    array('type' => 'datetime', 'name' => 'appointment_time', 'label' => '预约时间', 'required' => true),
                    array('type' => 'select', 'name' => 'service_type', 'label' => '服务类型', 'required' => true,
                           'options' => array(
                               array('label' => '咨询', 'value' => 'consultation'),
                               array('label' => '维修', 'value' => 'repair'),
                               array('label' => '安装', 'value' => 'installation')
                           )),
                    array('type' => 'textarea', 'name' => 'description', 'label' => '详细说明', 'required' => false)
                )
            ),
            'feedback_form' => array(
                'name' => '反馈表单',
                'fields' => array(
                    array('type' => 'text', 'name' => 'name', 'label' => '姓名', 'required' => false),
                    array('type' => 'email', 'name' => 'email', 'label' => '邮箱', 'required' => true),
                    array('type' => 'select', 'name' => 'category', 'label' => '反馈类型', 'required' => true,
                           'options' => array(
                               array('label' => '建议', 'value' => 'suggestion'),
                               array('label' => '投诉', 'value' => 'complaint'),
                               array('label' => '表扬', 'value' => 'praise'),
                               array('label' => '其他', 'value' => 'other')
                           )),
                    array('type' => 'rating', 'name' => 'satisfaction', 'label' => '满意度', 'required' => true, 'max' => 5),
                    array('type' => 'textarea', 'name' => 'content', 'label' => '反馈内容', 'required' => true, 'rows' => 5),
                    array('type' => 'file', 'name' => 'attachment', 'label' => '相关附件', 'required' => false)
                )
            )
        );
    }
    
    /**
     * 应用模板到表单
     */
    public static function applyTemplate($formId, $templateName) {
        $templates = self::getFieldTemplates();
        if (!isset($templates[$templateName])) {
            return false;
        }
        
        $template = $templates[$templateName];
        return self::saveFormFields($formId, $template['fields']);
    }
    
    /**
     * 记录操作日志
     */
    public static function logAction($action, $formId = null, $details = null) {
        $user = Typecho_Widget::widget('Widget_User');
        $db = self::getDb();
        
        $logData = array(
            'user_id' => $user->uid,
            'action' => $action,
            'form_id' => $formId,
            'details' => is_array($details) ? json_encode($details) : $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_time' => time()
        );
        
        // 这里可以扩展为更完整的日志系统
        return true;
    }
    
    /**
     * 获取表单默认配置
     */
    public static function getDefaultFormConfig() {
        return array(
            'layout' => array(
                'columns' => 1,
                'spacing' => 'medium',
                'alignment' => 'left'
            ),
            'styling' => array(
                'theme' => 'default',
                'primary_color' => '#007cba',
                'border_radius' => '4px',
                'input_height' => 'medium'
            ),
            'behavior' => array(
                'ajax_submit' => true,
                'show_progress' => false,
                'scroll_to_top' => true,
                'auto_save' => false
            ),
            'validation' => array(
                'show_errors_inline' => true,
                'highlight_required' => true,
                'validate_on_blur' => true
            ),
            'submission' => array(
                'success_action' => 'message',
                'success_message' => '表单提交成功！',
                'redirect_url' => '',
                'prevent_duplicate' => true,
                'save_to_database' => true
            ),
            'notifications' => array(
                'enable_admin_notification' => true,
                'admin_email' => '',
                'enable_user_notification' => false,
                'user_email_field' => 'email'
            ),
            'security' => array(
                'enable_captcha' => false,
                'enable_honeypot' => true,
                'rate_limiting' => true,
                'max_submissions_per_hour' => 10
            )
        );
    }
    
    /**
     * 获取字段默认配置
     */
    public static function getDefaultFieldConfig($type) {
        $defaults = array(
            'text' => array(
                'label' => '文本',
                'placeholder' => '',
                'maxLength' => 255,
                'required' => false,
                'width' => 'full'
            ),
            'textarea' => array(
                'label' => '多行文本',
                'placeholder' => '',
                'rows' => 5,
                'maxLength' => 1000,
                'required' => false,
                'width' => 'full'
            ),
            'email' => array(
                'label' => '邮箱',
                'placeholder' => 'example@domain.com',
                'required' => false,
                'width' => 'full'
            ),
            'tel' => array(
                'label' => '电话',
                'placeholder' => '',
                'required' => false,
                'width' => 'full'
            ),
            'number' => array(
                'label' => '数字',
                'placeholder' => '',
                'min' => '',
                'max' => '',
                'step' => 1,
                'required' => false,
                'width' => 'full'
            ),
            'select' => array(
                'label' => '下拉选择',
                'options' => array(
                    array('label' => '选项1', 'value' => 'option1'),
                    array('label' => '选项2', 'value' => 'option2')
                ),
                'required' => false,
                'width' => 'full'
            ),
            'radio' => array(
                'label' => '单选',
                'options' => array(
                    array('label' => '选项1', 'value' => 'option1'),
                    array('label' => '选项2', 'value' => 'option2')
                ),
                'required' => false,
                'layout' => 'vertical',
                'width' => 'full'
            ),
            'checkbox' => array(
                'label' => '多选',
                'options' => array(
                    array('label' => '选项1', 'value' => 'option1'),
                    array('label' => '选项2', 'value' => 'option2')
                ),
                'required' => false,
                'layout' => 'vertical',
                'width' => 'full'
            ),
            'file' => array(
                'label' => '文件上传',
                'accept' => '',
                'maxSize' => '5MB',
                'multiple' => false,
                'required' => false,
                'width' => 'full'
            ),
            'date' => array(
                'label' => '日期',
                'format' => 'Y-m-d',
                'required' => false,
                'width' => 'half'
            ),
            'time' => array(
                'label' => '时间',
                'format' => 'H:i',
                'required' => false,
                'width' => 'half'
            ),
            'datetime' => array(
                'label' => '日期时间',
                'format' => 'Y-m-d H:i',
                'required' => false,
                'width' => 'full'
            ),
            'rating' => array(
                'label' => '评分',
                'max' => 5,
                'required' => false,
                'width' => 'half'
            ),
            'slider' => array(
                'label' => '滑块',
                'min' => 0,
                'max' => 100,
                'step' => 1,
                'required' => false,
                'width' => 'full'
            ),
            'hidden' => array(
                'value' => '',
                'width' => 'full'
            )
        );
        
        return $defaults[$type] ?? array();
    }
}

// 兼容函数
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function escapeHtml($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// 数据库连接检查
$db = Typecho_Db::get();
$options = Helper::options();
$request = Typecho_Request::getInstance();
$user = Typecho_Widget::widget('Widget_User');

if (!$user->pass('administrator')) {
    throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
}
?>
