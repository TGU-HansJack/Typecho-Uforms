<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Uforms 核心辅助类 - 扩展版本
 */
class UformsHelper
{
    private static $db = null;
    private static $cache = array();
    private static $pluginOptions = null;
    
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
     * 获取插件配置
     */
    public static function getPluginOptions() {
        if (self::$pluginOptions === null) {
            self::$pluginOptions = Helper::options()->plugin('Uforms');
        }
        return self::$pluginOptions;
    }
    
    /**
     * 缓存机制
     */
    private static function getCache($key) {
        return isset(self::$cache[$key]) ? self::$cache[$key] : null;
    }
    
    private static function setCache($key, $value, $ttl = 300) {
        self::$cache[$key] = array(
            'value' => $value,
            'expires' => time() + $ttl
        );
    }
    
    private static function isCacheValid($key) {
        return isset(self::$cache[$key]) && self::$cache[$key]['expires'] > time();
    }
    
    /**
     * 获取表单列表
     */
    public static function getForms($page = 1, $pageSize = 20, $status = null, $search = null) {
        $db = self::getDb();
        $select = $db->select('f.*')
                     ->from('table.uforms_forms f');
        
        // 搜索条件
        if ($search) {
            $select->where('f.title LIKE ? OR f.name LIKE ?', '%' . $search . '%', '%' . $search . '%');
        }
        
        // 状态筛选
        if ($status) {
            $select->where('f.status = ?', $status);
        }
        
        // 分页
        $offset = ($page - 1) * $pageSize;
        $select->order('f.modified_time', Typecho_Db::SORT_DESC)
               ->limit($pageSize)
               ->offset($offset);
        
        return $db->fetchAll($select);
    }
    
    /**
     * 获取表单总数
     */
    public static function getFormsCount($status = null, $search = null) {
        $cacheKey = "forms_count_{$status}_{$search}";
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        $select = $db->select('COUNT(*) AS count')->from('table.uforms_forms');
        
        if ($search) {
            $select->where('title LIKE ? OR name LIKE ?', '%' . $search . '%', '%' . $search . '%');
        }
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        $result = $db->fetchRow($select);
        $count = $result ? intval($result['count']) : 0;
        
        self::setCache($cacheKey, $count, 60);
        return $count;
    }
    
    /**
     * 根据ID获取表单
     */
    public static function getForm($id) {
        $cacheKey = "form_{$id}";
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        $form = $db->fetchRow($db->select()->from('table.uforms_forms')->where('id = ?', $id));
        
        if ($form) {
            // 解析JSON字段
            $form['config'] = json_decode($form['config'] ?? '{}', true);
            $form['settings'] = json_decode($form['settings'] ?? '{}', true);
        }
        
        self::setCache($cacheKey, $form, 300);
        return $form;
    }
    
    /**
     * 根据名称获取表单
     */
    public static function getFormByName($name) {
        $cacheKey = "form_name_{$name}";
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        $form = $db->fetchRow($db->select()->from('table.uforms_forms')->where('name = ?', $name));
        
        if ($form) {
            $form['config'] = json_decode($form['config'] ?? '{}', true);
            $form['settings'] = json_decode($form['settings'] ?? '{}', true);
        }
        
        self::setCache($cacheKey, $form, 300);
        return $form;
    }
    
    /**
     * 获取表单字段
     */
    public static function getFormFields($formId) {
        $cacheKey = "form_fields_{$formId}";
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        $fields = $db->fetchAll(
            $db->select()->from('table.uforms_fields')
               ->where('form_id = ?', $formId)
               ->order('sort_order', Typecho_Db::SORT_ASC)
        );
        
        // 解析字段配置
        foreach ($fields as &$field) {
            $field['field_config'] = json_decode($field['field_config'] ?? '{}', true);
        }
        
        self::setCache($cacheKey, $fields, 300);
        return $fields;
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
        
        $formId = $db->query($db->insert('table.uforms_forms')->rows($formData));
        
        // 清除相关缓存
        self::clearFormsCache();
        
        return $formId;
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
        
        $result = $db->query($db->update('table.uforms_forms')->rows($formData)->where('id = ?', $id));
        
        // 清除缓存
        unset(self::$cache["form_{$id}"]);
        self::clearFormsCache();
        
        return $result;
    }
    
    /**
     * 删除表单
     */
    public static function deleteForm($id) {
        $db = self::getDb();
        
        try {
            // 开始事务
            $db->query('START TRANSACTION');
            
            // 获取提交记录中的文件
            $submissions = $db->fetchAll($db->select()->from('table.uforms_submissions')->where('form_id = ?', $id));
            
            // 删除相关文件
            foreach ($submissions as $submission) {
                $files = $db->fetchAll($db->select()->from('table.uforms_files')->where('submission_id = ?', $submission['id']));
                foreach ($files as $file) {
                    if (file_exists($file['file_path'])) {
                        unlink($file['file_path']);
                    }
                }
            }
            
            // 删除数据库记录
            $db->query($db->delete('table.uforms_forms')->where('id = ?', $id));
            $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_notifications')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_files')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_calendar')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_stats')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_webhooks')->where('form_id = ?', $id));
            
            // 提交事务
            $db->query('COMMIT');
            
            // 清除缓存
            unset(self::$cache["form_{$id}"]);
            self::clearFormsCache();
            
            return true;
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * 保存表单字段
     */
    public static function saveFormFields($formId, $fields) {
        $db = self::getDb();
        
        try {
            // 开始事务
            $db->query('START TRANSACTION');
            
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
            
            // 提交事务
            $db->query('COMMIT');
            
            // 清除缓存
            unset(self::$cache["form_fields_{$formId}"]);
            
            return true;
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * 提交表单数据
     */
    public static function submitForm($formId, $data, $files = array()) {
        $db = self::getDb();
        $form = self::getForm($formId);
        
        if (!$form) {
            throw new Exception('表单不存在');
        }
        
        if ($form['status'] !== 'published') {
            throw new Exception('表单未发布');
        }
        
        try {
            // 开始事务
            $db->query('START TRANSACTION');
            
            // 验证表单数据
            $validationErrors = self::validateSubmissionData($formId, $data);
            if (!empty($validationErrors)) {
                throw new Exception('数据验证失败: ' . implode(', ', $validationErrors));
            }
            
            // 反垃圾检测
            if (self::isSpam($formId, $data)) {
                self::logSpam($formId, $data, 'Content detected as spam');
                throw new Exception('提交被识别为垃圾信息');
            }
            
            // 频率限制检测
            if (self::isRateLimited($formId)) {
                throw new Exception('提交过于频繁，请稍后再试');
            }
            
            // 保存提交记录
            $submissionData = array(
                'form_id' => $formId,
                'data' => json_encode($data),
                'ip' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'status' => 'new',
                'source' => 'web',
                'created_time' => time(),
                'modified_time' => time()
            );
            
            $submissionId = $db->query($db->insert('table.uforms_submissions')->rows($submissionData));
            
            // 处理文件上传
            if (!empty($files)) {
                self::processFileUploads($formId, $submissionId, $files);
            }
            
            // 更新表单提交计数
            $db->query($db->update('table.uforms_forms')
                          ->rows(array('submit_count' => new Typecho_Db_Query_Exception('`submit_count` + 1')))
                          ->where('id = ?', $formId));
            
            // 发送通知
            self::sendNotifications($formId, $submissionId, $data);
            
            // 触发Webhook
            self::triggerWebhooks($formId, $submissionId, $data);
            
            // 提交事务
            $db->query('COMMIT');
            
            // 清除缓存
            self::clearFormsCache();
            
            return $submissionId;
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * 验证提交数据
     */
    public static function validateSubmissionData($formId, $data) {
        $fields = self::getFormFields($formId);
        $errors = array();
        
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            $fieldConfig = $field['field_config'];
            $value = isset($data[$fieldName]) ? $data[$fieldName] : '';
            
            // 必填验证
            if ($field['is_required'] && empty($value)) {
                $errors[] = "{$field['field_label']}是必填项";
                continue;
            }
            
            // 跳过空值的其他验证
            if (empty($value)) {
                continue;
            }
            
            // 类型验证
            switch ($field['field_type']) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "{$field['field_label']}格式不正确";
                    }
                    break;
                    
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = "{$field['field_label']}格式不正确";
                    }
                    break;
                    
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[] = "{$field['field_label']}必须是数字";
                    } else {
                        if (isset($fieldConfig['min']) && $value < $fieldConfig['min']) {
                            $errors[] = "{$field['field_label']}不能小于{$fieldConfig['min']}";
                        }
                        if (isset($fieldConfig['max']) && $value > $fieldConfig['max']) {
                            $errors[] = "{$field['field_label']}不能大于{$fieldConfig['max']}";
                        }
                    }
                    break;
                    
                case 'tel':
                    if (!preg_match('/^[0-9\-\+\s\(\)]+$/', $value)) {
                        $errors[] = "{$field['field_label']}格式不正确";
                    }
                    break;
            }
            
            // 长度验证
            if (isset($fieldConfig['minLength']) && mb_strlen($value) < $fieldConfig['minLength']) {
                $errors[] = "{$field['field_label']}长度不能少于{$fieldConfig['minLength']}个字符";
            }
            
            if (isset($fieldConfig['maxLength']) && mb_strlen($value) > $fieldConfig['maxLength']) {
                $errors[] = "{$field['field_label']}长度不能超过{$fieldConfig['maxLength']}个字符";
            }
            
            // 正则表达式验证
            if (isset($fieldConfig['pattern']) && !empty($fieldConfig['pattern'])) {
                if (!preg_match('/' . $fieldConfig['pattern'] . '/', $value)) {
                    $message = $fieldConfig['errorMessage'] ?? "{$field['field_label']}格式不正确";
                    $errors[] = $message;
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * 反垃圾检测
     */
    public static function isSpam($formId, $data) {
        $options = self::getPluginOptions();
        
        if (!$options->enable_spam_filter) {
            return false;
        }
        
        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 检查IP黑名单
        if (self::isBlacklistedIP($ip)) {
            return true;
        }
        
        // 检查关键词过滤
        $content = implode(' ', array_values($data));
        if (self::containsSpamKeywords($content)) {
            return true;
        }
        
        // 检查提交频率
        if (self::isSubmittingTooFast($ip)) {
            return true;
        }
        
        // 检查蜜罐字段
        if (isset($data['honeypot']) && !empty($data['honeypot'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 记录垃圾信息
     */
    public static function logSpam($formId, $data, $reason) {
        $db = self::getDb();
        
        $logData = array(
            'form_id' => $formId,
            'ip' => self::getClientIP(),
            'reason' => $reason,
            'data' => json_encode($data),
            'created_time' => time()
        );
        
        return $db->query($db->insert('table.uforms_spam_log')->rows($logData));
    }
    
    /**
     * 频率限制检测
     */
    public static function isRateLimited($formId) {
        $options = self::getPluginOptions();
        $rateLimit = intval($options->rate_limit ?? 3);
        
        if ($rateLimit <= 0) {
            return false;
        }
        
        $db = self::getDb();
        $ip = self::getClientIP();
        $timeWindow = time() - 60; // 1分钟内
        
        $count = $db->fetchObject($db->select('COUNT(*) AS count')
                                     ->from('table.uforms_submissions')
                                     ->where('form_id = ? AND ip = ? AND created_time > ?', $formId, $ip, $timeWindow));
        
        return $count && $count->count >= $rateLimit;
    }
    
    /**
     * 处理文件上传
     */
    public static function processFileUploads($formId, $submissionId, $files) {
        $options = self::getPluginOptions();
        
        if (!$options->upload_enabled) {
            throw new Exception('文件上传功能已禁用');
        }
        
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms/files/';
        $maxSize = intval($options->upload_max_size ?? 5) * 1024 * 1024; // MB to bytes
        $allowedTypes = explode(',', $options->allowed_file_types ?? 'jpg,png,pdf,doc,txt');
        $allowedTypes = array_map('trim', $allowedTypes);
        
        $db = self::getDb();
        
        foreach ($files as $fieldName => $fileArray) {
            if (!is_array($fileArray['name'])) {
                $fileArray = array(
                    'name' => array($fileArray['name']),
                    'type' => array($fileArray['type']),
                    'tmp_name' => array($fileArray['tmp_name']),
                    'error' => array($fileArray['error']),
                    'size' => array($fileArray['size'])
                );
            }
            
            for ($i = 0; $i < count($fileArray['name']); $i++) {
                if ($fileArray['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                $originalName = $fileArray['name'][$i];
                $tmpName = $fileArray['tmp_name'][$i];
                $fileSize = $fileArray['size'][$i];
                $fileType = $fileArray['type'][$i];
                
                // 验证文件大小
                if ($fileSize > $maxSize) {
                    throw new Exception("文件 {$originalName} 大小超过限制");
                }
                
                // 验证文件类型
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedTypes)) {
                    throw new Exception("文件 {$originalName} 类型不被允许");
                }
                
                // 生成唯一文件名
                $fileName = uniqid() . '_' . time() . '.' . $extension;
                $filePath = $uploadDir . $fileName;
                
                // 移动文件
                if (!move_uploaded_file($tmpName, $filePath)) {
                    throw new Exception("文件 {$originalName} 上传失败");
                }
                
                // 保存文件记录
                $fileData = array(
                    'form_id' => $formId,
                    'submission_id' => $submissionId,
                    'field_name' => $fieldName,
                    'original_name' => $originalName,
                    'filename' => $fileName,
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'file_type' => $fileType,
                    'uploaded_by' => self::getCurrentUserId(),
                    'created_time' => time()
                );
                
                $db->query($db->insert('table.uforms_files')->rows($fileData));
            }
        }
    }
    
    /**
     * 发送通知
     */
    public static function sendNotifications($formId, $submissionId, $data) {
        $form = self::getForm($formId);
        $settings = $form['settings'] ?? array();
        
        // 发送管理员通知
        if (!empty($settings['adminNotification']['enabled'])) {
            self::sendAdminNotification($formId, $submissionId, $data, $settings['adminNotification']);
        }
        
        // 发送用户确认邮件
        if (!empty($settings['userNotification']['enabled'])) {
            self::sendUserNotification($formId, $submissionId, $data, $settings['userNotification']);
        }
    }
    
    /**
     * 发送管理员通知
     */
    public static function sendAdminNotification($formId, $submissionId, $data, $config) {
        $form = self::getForm($formId);
        $recipients = array_filter(array_map('trim', explode(',', $config['recipients'] ?? '')));
        
        if (empty($recipients)) {
            return;
        }
        
        $subject = self::parseNotificationTemplate($config['subject'] ?? '新的表单提交', $form, $data);
        $message = self::parseNotificationTemplate($config['message'] ?? '', $form, $data);
        
        foreach ($recipients as $recipient) {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                self::sendEmail($recipient, $subject, $message, $formId, $submissionId);
            }
        }
    }
    
    /**
     * 发送用户确认邮件
     */
    public static function sendUserNotification($formId, $submissionId, $data, $config) {
        $form = self::getForm($formId);
        $emailField = $config['emailField'] ?? '';
        
        if (empty($emailField) || empty($data[$emailField])) {
            return;
        }
        
        $userEmail = $data[$emailField];
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        
        $subject = self::parseNotificationTemplate($config['subject'] ?? '表单提交确认', $form, $data);
        $message = self::parseNotificationTemplate($config['message'] ?? '', $form, $data);
        
        self::sendEmail($userEmail, $subject, $message, $formId, $submissionId, 'user');
    }
    
    /**
     * 解析通知模板
     */
    public static function parseNotificationTemplate($template, $form, $data) {
        $replacements = array(
            '{form_title}' => $form['title'],
            '{form_name}' => $form['name'],
            '{submit_time}' => date('Y-m-d H:i:s'),
            '{ip_address}' => self::getClientIP(),
            '{user_agent}' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        // 替换字段值
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $replacements['{' . $key . '}'] = $value;
        }
        
        // 生成所有字段列表
        $fieldsText = '';
        $fields = self::getFormFields($form['id']);
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            $fieldLabel = $field['field_label'];
            $value = isset($data[$fieldName]) ? $data[$fieldName] : '';
            
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            $fieldsText .= "{$fieldLabel}: {$value}\n";
        }
        $replacements['{all_fields}'] = $fieldsText;
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * 发送邮件
     */
    public static function sendEmail($to, $subject, $message, $formId = null, $submissionId = null, $type = 'admin') {
        $options = self::getPluginOptions();
        
        if (!$options->enable_email) {
            return false;
        }
        
        $db = self::getDb();
        
        // 记录通知
        $notificationData = array(
            'form_id' => $formId,
            'submission_id' => $submissionId,
            'type' => $type,
            'recipient' => $to,
            'subject' => $subject,
            'message' => $message,
            'status' => 'pending',
            'created_time' => time()
        );
        
        $notificationId = $db->query($db->insert('table.uforms_notifications')->rows($notificationData));
        
        try {
            // 这里可以集成PHPMailer或使用WordPress的wp_mail函数
            $sent = self::sendEmailViaPHP($to, $subject, $message);
            
            // 更新通知状态
            $updateData = array(
                'status' => $sent ? 'sent' : 'failed',
                'sent_time' => time()
            );
            
            if (!$sent) {
                $updateData['error_message'] = 'Failed to send email';
            }
            
            $db->query($db->update('table.uforms_notifications')
                          ->rows($updateData)
                          ->where('id = ?', $notificationId));
            
            return $sent;
            
        } catch (Exception $e) {
            // 更新错误状态
            $db->query($db->update('table.uforms_notifications')
                          ->rows(array(
                              'status' => 'failed',
                              'error_message' => $e->getMessage()
                          ))
                          ->where('id = ?', $notificationId));
            
            return false;
        }
    }
    
    /**
     * 使用PHP mail()函数发送邮件
     */
    private static function sendEmailViaPHP($to, $subject, $message) {
        $headers = array(
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . Helper::options()->title . ' <noreply@' . $_SERVER['HTTP_HOST'] . '>',
            'Reply-To: noreply@' . $_SERVER['HTTP_HOST'],
            'X-Mailer: PHP/' . phpversion()
        );
        
        return mail($to, $subject, nl2br($message), implode("\r\n", $headers));
    }
    
    /**
     * 触发Webhooks
     */
    public static function triggerWebhooks($formId, $submissionId, $data) {
        $db = self::getDb();
        $webhooks = $db->fetchAll($db->select()->from('table.uforms_webhooks')
                                     ->where('form_id = ? OR form_id IS NULL', $formId)
                                     ->where('status = ?', 'active'));
        
        foreach ($webhooks as $webhook) {
            self::sendWebhook($webhook, $formId, $submissionId, $data);
        }
    }
    
    /**
     * 发送Webhook
     */
    public static function sendWebhook($webhook, $formId, $submissionId, $data) {
        $form = self::getForm($formId);
        
        $payload = array(
            'event' => 'form_submitted',
            'form_id' => $formId,
            'form_name' => $form['name'],
            'form_title' => $form['title'],
            'submission_id' => $submissionId,
            'data' => $data,
            'timestamp' => time(),
            'ip' => self::getClientIP()
        );
        
        $jsonPayload = json_encode($payload);
        
        // 创建签名
        $signature = hash_hmac('sha256', $jsonPayload, $webhook['token']);
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $webhook['target_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Uforms-Signature: sha256=' . $signature,
                'User-Agent: Uforms/2.0'
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // 记录Webhook日志
        $db = self::getDb();
        $logData = array(
            'webhook_id' => $webhook['id'],
            'payload' => $jsonPayload,
            'response' => $response,
            'http_code' => $httpCode,
            'error' => $error,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_time' => time()
        );
        
        $db->query($db->insert('table.uforms_webhook_logs')->rows($logData));
        
        return $httpCode >= 200 && $httpCode < 300;
    }
    
    /**
     * 获取客户端IP
     */
    public static function getClientIP() {
        $ipKeys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                       'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                                   FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * 获取当前用户ID
     */
    public static function getCurrentUserId() {
        $user = Typecho_Widget::widget('Widget_User');
        return $user->hasLogin() ? $user->uid : null;
    }
    
    /**
     * 获取统计数据
     */
    public static function getStats() {
        $cacheKey = 'uforms_stats';
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        
        // 总表单数
        $totalForms = $db->fetchObject($db->select('COUNT(*) AS count')->from('table.uforms_forms'));
        
        // 已发布表单数
        $publishedForms = $db->fetchObject($db->select('COUNT(*) AS count')
                                              ->from('table.uforms_forms')
                                              ->where('status = ?', 'published'));
        
        // 总提交数
        $totalSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')->from('table.uforms_submissions'));
        
        // 未读提交数
        $newSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                              ->from('table.uforms_submissions')
                                              ->where('status = ?', 'new'));
        
        // 今日提交数
        $todaySubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_submissions')
                                                ->where('created_time >= ?', strtotime('today')));
        
        // 本月提交数
        $monthSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_submissions')
                                                ->where('created_time >= ?', strtotime('first day of this month')));
        
        $stats = array(
            'total_forms' => $totalForms ? $totalForms->count : 0,
            'published_forms' => $publishedForms ? $publishedForms->count : 0,
            'total_submissions' => $totalSubmissions ? $totalSubmissions->count : 0,
            'new_submissions' => $newSubmissions ? $newSubmissions->count : 0,
            'today_submissions' => $todaySubmissions ? $todaySubmissions->count : 0,
            'month_submissions' => $monthSubmissions ? $monthSubmissions->count : 0
        );
        
        self::setCache($cacheKey, $stats, 60);
        return $stats;
    }
    
    /**
     * 清除缓存
     */
    public static function clearFormsCache() {
        $keysToRemove = array();
        foreach (array_keys(self::$cache) as $key) {
            if (strpos($key, 'forms_') === 0 || strpos($key, 'form_') === 0 || $key === 'uforms_stats') {
                $keysToRemove[] = $key;
            }
        }
        
        foreach ($keysToRemove as $key) {
            unset(self::$cache[$key]);
        }
    }
    
    /**
     * 生成表单URL
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
     * 格式化时间
     */
    public static function formatTime($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * 其他辅助方法...
     */
    private static function isBlacklistedIP($ip) {
        // 这里可以实现IP黑名单检查
        return false;
    }
    
    private static function containsSpamKeywords($content) {
        $spamKeywords = array('viagra', 'casino', 'porn', 'xxx');
        $content = strtolower($content);
        
        foreach ($spamKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private static function isSubmittingTooFast($ip) {
        $db = self::getDb();
        $recentSubmission = $db->fetchRow($db->select('created_time')
                                             ->from('table.uforms_submissions')
                                             ->where('ip = ?', $ip)
                                             ->order('created_time', Typecho_Db::SORT_DESC)
                                             ->limit(1));
        
        if ($recentSubmission && (time() - $recentSubmission['created_time']) < 10) {
            return true;
        }
        
        return false;
    }
}
