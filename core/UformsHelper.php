<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 启用详细错误日志
ini_set('log_errors', 1);
ini_set('error_log', __TYPECHO_ROOT_DIR__ . '/uforms_error.log');
error_reporting(E_ALL);

class UformsHelper {
    
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
        return $db->fetchRow(
            $db->select()->from('table.uforms_forms')
               ->where('name = ? AND status = ?', $name, 'published')
        );
    }
    
    /**
     * 验证字段
     */
    public static function validateField($type, $value, $config) {
        $errors = array();
        
        // 根据类型验证
        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = '邮箱格式不正确';
                }
                break;
                
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = 'URL格式不正确';
                }
                break;
                
            case 'number':
            case 'decimal':
                if (!is_numeric($value)) {
                    $errors[] = '必须是数字';
                } else {
                    $numValue = floatval($value);
                    if (isset($config['min']) && $numValue < $config['min']) {
                        $errors[] = "不能小于{$config['min']}";
                    }
                    if (isset($config['max']) && $numValue > $config['max']) {
                        $errors[] = "不能大于{$config['max']}";
                    }
                }
                break;
                
            case 'tel':
                if (!preg_match('/^[0-9\-\+\s\(\)]+$/', $value)) {
                    $errors[] = '电话号码格式不正确';
                }
                break;
                
            case 'text':
            case 'textarea':
                $length = mb_strlen($value, 'UTF-8');
                if (isset($config['minLength']) && $length < $config['minLength']) {
                    $errors[] = "长度不能少于{$config['minLength']}个字符";
                }
                if (isset($config['maxLength']) && $length > $config['maxLength']) {
                    $errors[] = "长度不能超过{$config['maxLength']}个字符";
                }
                if (!empty($config['pattern']) && !preg_match('/' . $config['pattern'] . '/', $value)) {
                    $message = $config['errorMessage'] ?? '格式不正确';
                    $errors[] = $message;
                }
                break;
                
            case 'password':
                $length = mb_strlen($value, 'UTF-8');
                if (isset($config['minLength']) && $length < $config['minLength']) {
                    $errors[] = "密码长度不能少于{$config['minLength']}个字符";
                }
                break;
                
            case 'file':
                // 文件验证在上传时处理
                break;
        }
        
        return $errors;
    }
    
    /**
     * 获取表单字段
     */
    public static function getFormFields($form_id) {
        $db = Typecho_Db::get();
        return $db->fetchAll(
            $db->select()->from('table.uforms_fields')
               ->where('form_id = ?', $form_id)
               ->order('sort_order', Typecho_Db::SORT_ASC)
        );
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
     * 格式化时间
     */
    public static function formatTime($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
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
     * 发送邮件通知
     */
    public static function sendEmailNotification($to, $subject, $message, $form_data = array()) {
        // 检查是否启用邮件功能
        $settings_result = Typecho_Db::get()->fetchRow(
            Typecho_Db::get()->select('value')->from('table.options')
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
     * 清理过期数据
     */
    public static function cleanupExpiredData($days = 365) {
        $db = Typecho_Db::get();
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
     * 删除表单及相关数据
     */
    public static function deleteForm($formId) {
        $db = self::getDb();
        
        // 删除表单
        $db->query($db->delete('table.uforms_forms')->where('id = ?', $formId));
        
        // 删除相关字段
        $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $formId));
        
        // 删除相关提交
        $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $formId));
        
        // 删除相关文件
        $files = $db->fetchAll(
            $db->select('file_path')->from('table.uforms_files')
               ->where('form_id = ?', $formId)
        );
        
        foreach ($files as $file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
        
        $db->query($db->delete('table.uforms_files')->where('form_id = ?', $formId));
        
        // 删除其他相关数据
        try {
            $db->query($db->delete('table.uforms_notifications')->where('form_id = ?', $formId));
            $db->query($db->delete('table.uforms_calendar')->where('form_id = ?', $formId));
            $db->query($db->delete('table.uforms_stats')->where('form_id = ?', $formId));
            $db->query($db->delete('table.uforms_versions')->where('form_id = ?', $formId));
        } catch (Exception $e) {
            // 某些表可能不存在，忽略错误
            error_log('Uforms: Some cleanup operations failed: ' . $e->getMessage());
        }
        
        return true;
    }
    
    /**
     * 复制表单
     */
    public static function duplicateForm($originalId, $newName = null, $newTitle = null) {
        $originalForm = self::getForm($originalId);
        if (!$originalForm) {
            return false;
        }
        
        try {
            $user = Typecho_Widget::widget('Widget_User');
            $userId = $user->hasLogin() ? $user->uid : 1;
        } catch (Exception $e) {
            $userId = 1;
        }
        
        $db = self::getDb();
        
        // 生成唯一名称
        if (!$newName) {
            $newName = $originalForm['name'] . '_copy';
            $counter = 1;
            while ($db->fetchRow($db->select('id')->from('table.uforms_forms')->where('name = ?', $newName))) {
                $newName = $originalForm['name'] . '_copy_' . $counter;
                $counter++;
            }
        }
        
        // 准备新表单数据
        $newData = array(
            'name' => $newName,
            'title' => $newTitle ?: ($originalForm['title'] . ' (副本)'),
            'description' => $originalForm['description'],
            'config' => $originalForm['config'],
            'settings' => $originalForm['settings'],
            'status' => 'draft',
            'author_id' => $userId,
            'view_count' => 0,
            'submit_count' => 0,
            'version' => 1,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        // 创建新表单
        $newFormId = $db->query($db->insert('table.uforms_forms')->rows($newData));
        
        if ($newFormId) {
            // 复制字段
            $fields = self::getFormFields($originalId);
            foreach ($fields as $field) {
                $fieldData = array(
                    'form_id' => $newFormId,
                    'field_type' => $field['field_type'],
                    'field_name' => $field['field_name'],
                    'field_label' => $field['field_label'],
                    'field_config' => $field['field_config'],
                    'sort_order' => $field['sort_order'],
                    'is_required' => $field['is_required'],
                    'is_enabled' => $field['is_enabled'],
                    'created_time' => time()
                );
                
                $db->query($db->insert('table.uforms_fields')->rows($fieldData));
            }
            
            return $newFormId;
        }
        
        return false;
    }
    
    /**
     * 生成表单别名
     */
    public static function generateSlug($name, $title) {
        // 使用表单名称作为基础，如果不可用则使用标题
        $slug = $name ?: strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $title));
        $slug = trim($slug, '-');
        
        // 确保唯一性
        $db = self::getDb();
        $baseSlug = $slug;
        $counter = 1;
        
        while ($db->fetchRow($db->select('id')->from('table.uforms_forms')->where('slug = ?', $slug))) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * 检查垃圾内容
     */
    public static function isSpam($data) {
        // 简单的垃圾内容检测
        $spam_keywords = array('viagra', 'casino', 'lottery', 'winner', 'congratulations');
        
        foreach ($data as $value) {
            if (is_string($value)) {
                $value_lower = strtolower($value);
                foreach ($spam_keywords as $keyword) {
                    if (strpos($value_lower, $keyword) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * 记录垃圾内容
     */
    public static function logSpam($form_id, $ip, $reason, $data) {
        $db = self::getDb();
        
        $spam_data = array(
            'form_id' => $form_id,
            'ip' => $ip,
            'reason' => $reason,
            'data' => json_encode($data),
            'created_time' => time()
        );
        
        return $db->query($db->insert('table.uforms_spam_log')->rows($spam_data));
    }
}
?>
