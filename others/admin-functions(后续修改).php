<?php
if (!defined('__TYPECHO_ADMIN__')) {
    exit;
}

$db = Typecho_Db::get();
$options = Helper::options();
$request = Typecho_Request::getInstance();
$user = Typecho_Widget::widget('Widget_User');

if (!$user->pass('administrator')) {
    throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
}

// 公共函数
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
        
        $select->order('modified_time', Typecho_Db::SORT_DESC);
        
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
        
        $select->order('created_time', Typecho_Db::SORT_DESC)->limit($limit)->offset($offset);
        
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
}

// 检查并包含必要的辅助类
if (!class_exists('UformsHelper')) {
    require_once dirname(__FILE__) . '/UformsHelper.php';
}
?>
