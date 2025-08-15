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
}