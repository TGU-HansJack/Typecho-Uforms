<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'UformsHelper.php';
require_once 'frontend/front.php';

/**
 * Uforms 动作处理类 - 完整集成版本
 */
class Uforms_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $db;
    private $options;
    private $request;
    private $response;
    
    /**
     * 初始化
     */
    public function __construct($request, $response, $params = null) {
        parent::__construct($request, $response, $params);
        $this->db = Typecho_Db::get();
        $this->options = Helper::options();
        $this->request = $request;
        $this->response = $response;
    }
    
    /**
     * 执行动作 - 主分发器
     */
    public function execute()
    {
        // 由action方法处理路由分发
    }
    
    /**
     * 动作分发
     */
    public function action() {
        $pathInfo = $this->request->getPathInfo();
        
        // 路由解析和分发
        if (preg_match('/^\/uforms\/form\/([^\/]+)/', $pathInfo, $matches)) {
            $this->request->setParam('name', $matches[1]);
            $this->showForm();
        } elseif (preg_match('/^\/uforms\/form\/(\d+)/', $pathInfo, $matches)) {
            $this->request->setParam('id', $matches[1]);
            $this->showFormById();
        } elseif (preg_match('/^\/uforms\/calendar\/(\d+)/', $pathInfo, $matches)) {
            $this->request->setParam('id', $matches[1]);
            $this->calendar();
        } elseif (preg_match('/^\/uforms\/api/', $pathInfo)) {
            $this->apiHandler();
        } elseif (preg_match('/^\/uforms\/submit/', $pathInfo)) {
            $this->submit();
        } elseif (preg_match('/^\/uforms\/captcha/', $pathInfo)) {
            $this->generateCaptcha();
        } else {
            // 传统处理方式
            $this->widget('Widget_User')->pass('subscriber');
            $this->on($this->request->isAjax())->ajax();
            $this->on(!$this->request->isAjax())->showForm();
        }
    }
    
    /**
     * 显示表单（通过名称或ID）
     */
    public function showForm() {
        $name = $this->request->get('name');
        $id = $this->request->get('id');
        
        if ($name) {
            $form = UformsHelper::getFormByName($name);
        } elseif ($id) {
            $form = UformsHelper::getForm($id);
        } else {
            throw new Typecho_Widget_Exception('表单不存在', 404);
        }
        
        if (!$form) {
            throw new Typecho_Widget_Exception('表单不存在', 404);
        }
        
        if ($form['status'] !== 'published') {
            throw new Typecho_Widget_Exception('表单未发布', 404);
        }
        
        // 记录访问统计
        $this->trackFormView($form['id']);
        
        // 获取表单字段和配置
        $fields = UformsHelper::getFormFields($form['id']);
        $config = json_decode($form['config'], true) ?: array();
        $settings = json_decode($form['settings'], true) ?: array();
        
        // 处理表单提交（非AJAX）
        if ($this->request->isPost() && 
            ($this->request->get('uform_name') === $name || $this->request->get('form_id') == $id)) {
            try {
                $result = $this->processFormSubmission($form, $fields, $settings);
                if ($result['success']) {
                    $this->renderFormPage($form, $fields, $config, $settings, $result['message']);
                    return;
                } else {
                    $this->renderFormPage($form, $fields, $config, $settings, $result['message']);
                    return;
                }
            } catch (Exception $e) {
                $errorMessage = '<div class="uform-error">提交失败: ' . htmlspecialchars($e->getMessage()) . '</div>';
                $this->renderFormPage($form, $fields, $config, $settings, $errorMessage);
                return;
            }
        }
        
        // 渲染表单页面
        $this->renderFormPage($form, $fields, $config, $settings);
    }
    
    /**
     * 通过ID显示表单
     */
    public function showFormById() {
        $id = $this->request->get('id');
        
        if (empty($id)) {
            throw new Typecho_Widget_Exception('表单ID不能为空', 404);
        }
        
        $form = UformsHelper::getForm($id);
        if (!$form || $form['status'] !== 'published') {
            throw new Typecho_Widget_Exception('表单不存在或未发布', 404);
        }
        
        // 如果有name字段，重定向到友好URL
        if (!empty($form['name'])) {
            $this->response->redirect(Helper::options()->siteUrl . 'uforms/form/' . $form['name']);
        } else {
            // 没有name则直接显示
            $this->request->setParam('id', $id);
            $this->showForm();
        }
    }
    
    /**
     * 处理表单提交
     */
    public function submit() {
        if (!$this->request->isPost()) {
            $this->response->throwJson(array(
                'success' => false,
                'message' => '请求方法错误'
            ));
        }
        
        try {
            $formId = $this->request->get('form_id');
            $formName = $this->request->get('uform_name') ?: $this->request->get('form_name');
            
            // 获取表单
            if ($formId) {
                $form = UformsHelper::getForm($formId);
            } elseif ($formName) {
                $form = UformsHelper::getFormByName($formName);
            } else {
                throw new Exception('表单ID或名称不能为空');
            }
            
            if (!$form) {
                throw new Exception('表单不存在');
            }
            
            if ($form['status'] !== 'published') {
                throw new Exception('表单未发布');
            }
            
            // 获取表单字段和设置
            $fields = UformsHelper::getFormFields($form['id']);
            $settings = json_decode($form['settings'], true) ?: array();
            
            // 处理提交
            $result = $this->processFormSubmission($form, $fields, $settings);
            
            // 返回响应
            $this->response->throwJson($result);
            
        } catch (Exception $e) {
            $error = array(
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            );
            
            // 记录错误日志
            error_log('Uforms Submit Error: ' . $e->getMessage());
            
            $this->response->throwJson($error);
        }
    }

        /**
     * 处理表单提交核心逻辑
     */
    private function processFormSubmission($form, $fields, $settings) {
        // 验证机器人检查
        $this->validateBotCheck();
        
        // 验证验证码
        if (!empty($settings['enableCaptcha'])) {
            $this->validateCaptcha();
        }
        
        // 验证频率限制
        $this->validateRateLimit($form['id']);
        
        // 获取提交数据
        $data = $this->extractFormData($fields);
        
        // 验证表单数据
        $validationErrors = $this->validateFormData($fields, $data);
        if (!empty($validationErrors)) {
            return array(
                'success' => false,
                'message' => '<div class="uform-errors"><ul><li>' . implode('</li><li>', $validationErrors) . '</li></ul></div>',
                'errors' => $validationErrors
            );
        }
        
        // 处理文件上传
        $files = array();
        if (!empty($_FILES)) {
            try {
                $files = $this->processFileUploads($fields);
            } catch (Exception $e) {
                return array(
                    'success' => false,
                    'message' => '<div class="uform-error">文件上传失败: ' . htmlspecialchars($e->getMessage()) . '</div>'
                );
            }
        }
        
        // 保存提交数据
        try {
            $submissionId = UformsHelper::submitForm($form['id'], $data, $files);
            
            // 发送通知邮件
            if (!empty($settings['enableEmailNotification'])) {
                $this->sendNotificationEmail($form, $data, $submissionId);
            }
            
            // 记录统计
            $this->trackFormSubmission($form['id']);
            
            // 处理日历事件
            if (!empty($settings['enable_calendar'])) {
                $this->processCalendarEvent($form['id'], $data, $submissionId);
            }
            
            // 返回成功响应
            $successMessage = $settings['successMessage'] ?? '表单提交成功！';
            $successAction = $settings['successAction'] ?? 'message';
            
            $response = array(
                'success' => true,
                'message' => '<div class="uform-success">' . htmlspecialchars($successMessage) . '</div>',
                'submission_id' => $submissionId,
                'action' => $successAction
            );
            
            // 处理成功后的动作
            switch ($successAction) {
                case 'redirect':
                    $response['redirect_url'] = $settings['redirectUrl'] ?? $settings['redirect_url'] ?? '';
                    break;
                case 'block':
                    $response['success_block'] = $settings['successBlock'] ?? '';
                    break;
                case 'refresh':
                    $response['refresh'] = true;
                    break;
            }
            
            return $response;
            
        } catch (Exception $e) {
            throw new Exception('数据保存失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 验证机器人检查
     */
    private function validateBotCheck() {
        // 检查蜜罐字段
        $honeypot = $this->request->get('uforms_honeypot', '');
        if (!empty($honeypot)) {
            throw new Exception('机器人检测失败');
        }
        
        // 检查时间戳（防止过快提交）
        $timestamp = $this->request->get('uforms_timestamp');
        if ($timestamp && (time() - intval($timestamp)) < 3) {
            throw new Exception('提交过快，请稍后重试');
        }
        
        // 检查来源
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!empty($referer) && strpos($referer, $host) === false) {
            // 跨域提交检查（可配置）
            $options = UformsHelper::getPluginOptions();
            if (!empty($options->strict_referer_check)) {
                throw new Exception('来源验证失败');
            }
        }
    }
    
    /**
     * 验证验证码
     */
    private function validateCaptcha() {
        $captcha = $this->request->get('captcha');
        session_start();
        $sessionCaptcha = $_SESSION['uforms_captcha'] ?? '';
        
        if (empty($captcha) || empty($sessionCaptcha)) {
            throw new Exception('验证码不能为空');
        }
        
        if (strtolower($captcha) !== strtolower($sessionCaptcha)) {
            throw new Exception('验证码错误');
        }
        
        // 清除使用过的验证码
        unset($_SESSION['uforms_captcha']);
    }
    
    /**
     * 验证提交频率限制
     */
    private function validateRateLimit($formId) {
        $ip = UformsHelper::getClientIP();
        $timeLimit = 60; // 1分钟内限制
        $maxSubmissions = 5; // 最多5次提交
        
        $recentSubmissions = $this->db->fetchAll(
            $this->db->select('COUNT(*) as count')
                     ->from('table.uforms_submissions')
                     ->where('form_id = ? AND ip = ? AND created_time > ?', 
                            $formId, $ip, time() - $timeLimit)
        );
        
        if (!empty($recentSubmissions) && $recentSubmissions[0]['count'] >= $maxSubmissions) {
            throw new Exception('提交过于频繁，请稍后重试');
        }
    }
    
    /**
     * 提取表单数据
     */
    private function extractFormData($fields) {
        $data = array();
        
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            $fieldType = $field['field_type'];
            
            switch ($fieldType) {
                case 'checkbox':
                    $value = $this->request->getArray($fieldName, array());
                    break;
                    
                case 'file':
                    // 文件字段不在这里处理，在processFileUploads中处理
                    continue 2;
                    
                default:
                    $value = $this->request->get($fieldName, '');
            }
            
            // 清理数据
            if (is_array($value)) {
                $value = array_map('trim', $value);
                $value = array_filter($value, function($v) { return $v !== ''; });
            } else {
                $value = trim($value);
            }
            
            $data[$fieldName] = $value;
        }
        
        return $data;
    }
    
    /**
     * 验证表单数据
     */
    private function validateFormData($fields, $data) {
        $errors = array();
        
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            $fieldLabel = $field['field_label'] ?: $fieldName;
            $fieldType = $field['field_type'];
            $isRequired = $field['is_required'] == 1;
            $config = json_decode($field['field_config'], true) ?: array();
            
            $value = isset($data[$fieldName]) ? $data[$fieldName] : '';
            
            // 必填验证
            if ($isRequired && empty($value)) {
                $errors[] = $fieldLabel . '是必填项';
                continue;
            }
            
            // 如果值为空且非必填，跳过其他验证
            if (empty($value)) {
                continue;
            }
            
            // 类型验证
            $fieldErrors = $this->validateFieldValue($field, $value);
            $errors = array_merge($errors, $fieldErrors);
        }
        
        return $errors;
    }

        /**
     * 验证单个字段值
     */
    private function validateFieldValue($field, $value) {
        $errors = array();
        $fieldLabel = $field['field_label'] ?: $field['field_name'];
        $fieldType = $field['field_type'];
        $config = json_decode($field['field_config'], true) ?: array();
        
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
            case 'decimal':
                if (!is_numeric($value)) {
                    $errors[] = $fieldLabel . '必须是数字';
                } else {
                    if (isset($config['min']) && $value < $config['min']) {
                        $errors[] = $fieldLabel . '不能小于' . $config['min'];
                    }
                    if (isset($config['max']) && $value > $config['max']) {
                        $errors[] = $fieldLabel . '不能大于' . $config['max'];
                    }
                }
                break;
                
            case 'tel':
                if (!preg_match('/^[0-9\-\+\s\(\)]+$/', $value)) {
                    $errors[] = $fieldLabel . '格式不正确';
                }
                break;
                
            case 'password':
                if (isset($config['minLength']) && strlen($value) < $config['minLength']) {
                    $errors[] = $fieldLabel . '长度不能少于' . $config['minLength'] . '个字符';
                }
                break;
        }
        
        // 长度验证
        if (isset($config['minLength']) && mb_strlen($value) < $config['minLength']) {
            $errors[] = $fieldLabel . '长度不能少于' . $config['minLength'] . '个字符';
        }
        
        if (isset($config['maxLength']) && mb_strlen($value) > $config['maxLength']) {
            $errors[] = $fieldLabel . '长度不能超过' . $config['maxLength'] . '个字符';
        }
        
        // 正则表达式验证
        if (isset($config['pattern']) && !empty($config['pattern'])) {
            if (!preg_match('/' . $config['pattern'] . '/', $value)) {
                $message = $config['errorMessage'] ?? $fieldLabel . '格式不正确';
                $errors[] = $message;
            }
        }
        
        // 选项验证（单选、多选）
        if (in_array($fieldType, array('radio', 'select', 'checkbox')) && isset($config['options'])) {
            $validOptions = array();
            foreach ($config['options'] as $option) {
                $validOptions[] = $option['value'];
            }
            
            if (is_array($value)) {
                foreach ($value as $v) {
                    if (!in_array($v, $validOptions)) {
                        $errors[] = $fieldLabel . '包含无效选项';
                        break;
                    }
                }
            } else {
                if (!in_array($value, $validOptions)) {
                    $errors[] = $fieldLabel . '选项无效';
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * 处理文件上传
     */
    private function processFileUploads($fields) {
        $uploadedFiles = array();
        $options = UformsHelper::getPluginOptions();
        
        if (!$options->upload_enabled) {
            throw new Exception('文件上传功能已禁用');
        }
        
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms/files/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('无法创建上传目录');
            }
        }
        
        $maxSize = intval($options->upload_max_size ?? 5) * 1024 * 1024;
        $allowedTypes = explode(',', $options->allowed_file_types ?? 'jpg,png,pdf,doc,txt');
        $allowedTypes = array_map('trim', $allowedTypes);
        
        foreach ($_FILES as $fieldName => $fileArray) {
            if (empty($fileArray['name']) || $fileArray['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            // 查找对应的字段配置
            $fieldConfig = null;
            foreach ($fields as $field) {
                if ($field['field_name'] === $fieldName && $field['field_type'] === 'file') {
                    $fieldConfig = json_decode($field['field_config'], true) ?: array();
                    break;
                }
            }
            
            if (!$fieldConfig) {
                throw new Exception('未找到字段配置: ' . $fieldName);
            }
            
            // 处理多文件上传
            if (is_array($fileArray['name'])) {
                $files = array();
                for ($i = 0; $i < count($fileArray['name']); $i++) {
                    if (empty($fileArray['name'][$i]) || $fileArray['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    
                    $file = array(
                        'name' => $fileArray['name'][$i],
                        'type' => $fileArray['type'][$i],
                        'tmp_name' => $fileArray['tmp_name'][$i],
                        'error' => $fileArray['error'][$i],
                        'size' => $fileArray['size'][$i]
                    );
                    
                    $files[] = $this->uploadSingleFile($file, $fieldConfig, $maxSize, $allowedTypes);
                }
                $uploadedFiles[$fieldName] = $files;
            } else {
                $uploadedFiles[$fieldName] = $this->uploadSingleFile($fileArray, $fieldConfig, $maxSize, $allowedTypes);
            }
        }
        
        return $uploadedFiles;
    }
    
    /**
     * 上传单个文件
     */
    private function uploadSingleFile($file, $fieldConfig, $maxSize, $allowedTypes) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('文件上传失败: ' . $this->getUploadErrorMessage($file['error']));
        }
        
        // 验证文件大小
        $fileMaxSize = isset($fieldConfig['maxSize']) ? intval($fieldConfig['maxSize']) * 1024 * 1024 : $maxSize;
        if ($file['size'] > $fileMaxSize) {
            throw new Exception('文件大小超过限制(' . ($fileMaxSize / 1024 / 1024) . 'MB)');
        }
        
        // 验证文件类型
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileAllowedTypes = isset($fieldConfig['allowedTypes']) ? explode(',', $fieldConfig['allowedTypes']) : $allowedTypes;
        $fileAllowedTypes = array_map('trim', $fileAllowedTypes);
        
        if (!in_array($extension, $fileAllowedTypes)) {
            throw new Exception('不支持的文件类型: ' . $extension);
        }
        
        // 验证文件内容
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'zip' => 'application/zip'
        );
        
        if (isset($allowedMimes[$extension]) && $mimeType !== $allowedMimes[$extension]) {
            throw new Exception('文件类型验证失败');
        }
        
        // 生成唯一文件名
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms/files/';
        $filePath = $uploadDir . $fileName;
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('文件保存失败');
        }
        
        return array(
            'filename' => $fileName,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type'],
            'mime_type' => $mimeType,
            'url' => $this->options->siteUrl . 'usr/uploads/uforms/files/' . $fileName,
            'path' => $filePath
        );
    }
    
    /**
     * 获取上传错误信息
     */
    private function getUploadErrorMessage($errorCode) {
        $errors = array(
            UPLOAD_ERR_INI_SIZE => '文件大小超过系统限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '文件上传停止由于扩展'
        );
        
        return isset($errors[$errorCode]) ? $errors[$errorCode] : '未知上传错误';
    }

        /**
     * 显示日历
     */
    public function calendar() {
        $id = $this->request->get('id');
        
        if (!$id) {
            throw new Typecho_Widget_Exception('表单ID不能为空', 404);
        }
        
        $form = UformsHelper::getForm($id);
        if (!$form) {
            throw new Typecho_Widget_Exception('表单不存在', 404);
        }
        
        if ($form['status'] !== 'published') {
            throw new Typecho_Widget_Exception('表单未发布', 404);
        }
        
        $settings = json_decode($form['settings'], true) ?: array();
        
        // 检查是否启用日历功能
        if (empty($settings['enable_calendar'])) {
            throw new Typecho_Widget_Exception('此表单未启用日历功能', 404);
        }
        
        $this->renderCalendar($form);
    }
    
    /**
     * 渲染日历
     */
    private function renderCalendar($form) {
        // 获取日历事件
        $events = $this->db->fetchAll(
            $this->db->select()->from('table.uforms_calendar')
                     ->where('form_id = ?', $form['id'])
                     ->order('start_time', Typecho_Db::SORT_ASC)
        );
        
        $this->archiveTitle = '日历 - ' . $form['title'];
        $this->form = $form;
        $this->events = $events;
        
        // 渲染日历模板
        include dirname(__FILE__) . '/templates/calendar.php';
        exit;
    }
    
    /**
     * 处理日历事件
     */
    private function processCalendarEvent($formId, $data, $submissionId) {
        // 寻找日期时间字段
        $dateFields = array();
        $timeFields = array();
        
        foreach ($data as $fieldName => $value) {
            if (strpos($fieldName, 'date') !== false || strpos($fieldName, 'time') !== false) {
                if (strpos($fieldName, 'time') !== false) {
                    $timeFields[$fieldName] = $value;
                } else {
                    $dateFields[$fieldName] = $value;
                }
            }
        }
        
        // 如果找到日期相关字段，创建日历事件
        if (!empty($dateFields) || !empty($timeFields)) {
            $eventTitle = $data['title'] ?? $data['name'] ?? '表单提交';
            $eventDescription = $data['description'] ?? $data['content'] ?? '';
            
            $startTime = null;
            $endTime = null;
            $allDay = true;
            
            // 解析日期时间
            foreach ($dateFields as $fieldName => $value) {
                if (!empty($value)) {
                    $startTime = strtotime($value);
                    break;
                }
            }
            
            foreach ($timeFields as $fieldName => $value) {
                if (!empty($value) && $startTime) {
                    $allDay = false;
                    $timeValue = strtotime($value);
                    $startTime = strtotime(date('Y-m-d', $startTime) . ' ' . date('H:i:s', $timeValue));
                    break;
                }
            }
            
            if ($startTime) {
                $eventData = array(
                    'form_id' => $formId,
                    'submission_id' => $submissionId,
                    'title' => $eventTitle,
                    'description' => $eventDescription,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'all_day' => $allDay ? 1 : 0,
                    'color' => '#3788d8',
                    'status' => 'confirmed',
                    'created_time' => time()
                );
                
                $this->db->query($this->db->insert('table.uforms_calendar')->rows($eventData));
            }
        }
    }
    
    /**
     * 发送通知邮件
     */
    private function sendNotificationEmail($form, $data, $submissionId) {
        $options = UformsHelper::getPluginOptions();
        
        if (!$options->enable_email) {
            return;
        }
        
        $settings = json_decode($form['settings'], true) ?: array();
        $notificationEmail = $settings['notificationEmail'] ?? $options->admin_email;
        
        if (empty($notificationEmail)) {
            return;
        }
        
        // 构建邮件内容
        $subject = '表单提交通知 - ' . $form['title'];
        
        $content = "<h3>表单提交详情</h3>\n";
        $content .= "<p><strong>表单名称:</strong> " . htmlspecialchars($form['title']) . "</p>\n";
        $content .= "<p><strong>提交时间:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
        $content .= "<p><strong>提交ID:</strong> " . $submissionId . "</p>\n";
        $content .= "<p><strong>IP地址:</strong> " . UformsHelper::getClientIP() . "</p>\n\n";
        
        $content .= "<h4>提交数据:</h4>\n";
        $content .= "<table border='1' cellpadding='5' cellspacing='0'>\n";
        
        foreach ($data as $fieldName => $value) {
            $displayValue = is_array($value) ? implode(', ', $value) : $value;
            $content .= "<tr>\n";
            $content .= "<td><strong>" . htmlspecialchars($fieldName) . "</strong></td>\n";
            $content .= "<td>" . htmlspecialchars($displayValue) . "</td>\n";
            $content .= "</tr>\n";
        }
        
        $content .= "</table>\n";
        
        // 发送邮件
        try {
            $this->sendEmail($notificationEmail, $subject, $content);
        } catch (Exception $e) {
            error_log('Uforms Email Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 发送邮件
     */
    private function sendEmail($to, $subject, $content) {
        $options = UformsHelper::getPluginOptions();
        
        if ($options->email_method === 'smtp') {
            // 使用SMTP发送
            require_once dirname(__FILE__) . '/lib/PHPMailer/PHPMailer.php';
            require_once dirname(__FILE__) . '/lib/PHPMailer/SMTP.php';
            require_once dirname(__FILE__) . '/lib/PHPMailer/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            try {
                $mail->isSMTP();
                $mail->Host = $options->smtp_host;
                $mail->SMTPAuth = true;
                $mail->Username = $options->smtp_username;
                $mail->Password = $options->smtp_password;
                $mail->SMTPSecure = $options->smtp_secure ?: 'tls';
                $mail->Port = intval($options->smtp_port ?: 587);
                $mail->CharSet = 'UTF-8';
                
                $mail->setFrom($options->smtp_from_email ?: $options->smtp_username, 
                              $options->smtp_from_name ?: 'Uforms');
                $mail->addAddress($to);
                
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $content;
                
                $mail->send();
            } catch (Exception $e) {
                throw new Exception('SMTP邮件发送失败: ' . $e->getMessage());
            }
        } else {
            // 使用系统mail函数
            $headers = array(
                'From: ' . ($options->system_from_email ?: 'noreply@' . $_SERVER['HTTP_HOST']),
                'Reply-To: ' . ($options->system_from_email ?: 'noreply@' . $_SERVER['HTTP_HOST']),
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: Uforms'
            );
            
            if (!mail($to, $subject, $content, implode("\r\n", $headers))) {
                throw new Exception('系统邮件发送失败');
            }
        }
    }
    
    /**
     * API处理器
     */
    public function apiHandler() {
        $action = $this->request->get('action');
        
        header('Content-Type: application/json; charset=UTF-8');
        
        try {
            switch ($action) {
                case 'submit':
                    $this->submit();
                    break;
                    
                case 'validate':
                    $this->handleApiValidate();
                    break;
                    
                case 'upload':
                    $this->handleApiUpload();
                    break;
                    
                case 'calendar_events':
                    $this->handleApiCalendarEvents();
                    break;
                    
                case 'get_calendar_data':
                    $this->getCalendarData();
                    break;
                    
                case 'captcha':
                    $this->generateCaptcha();
                    break;
                    
                default:
                    throw new Exception('未知的API操作: ' . $action);
            }
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ));
        }
    }
    
    /**
     * AJAX处理
     */
    public function ajax() {
        $this->apiHandler();
    }
    
    /**
     * 字段验证API
     */
    private function handleApiValidate() {
        $formId = $this->request->get('form_id');
        $fieldName = $this->request->get('field_name');
        $fieldType = $this->request->get('field_type');
        $value = $this->request->get('field_value') ?: $this->request->get('value');
        $fieldConfig = $this->request->get('field_config');
        
        if (!$formId || !$fieldName) {
            $this->response->throwJson(array(
                'success' => false,
                'message' => '参数不完整'
            ));
        }
        
        try {
            // 获取字段配置
            $fields = UformsHelper::getFormFields($formId);
            $field = null;
            
            foreach ($fields as $f) {
                if ($f['field_name'] === $fieldName) {
                    $field = $f;
                    break;
                }
            }
            
            if (!$field) {
                // 如果没有找到字段，使用传入的参数构造字段信息
                $field = array(
                    'field_name' => $fieldName,
                    'field_type' => $fieldType,
                    'field_label' => $fieldName,
                    'field_config' => $fieldConfig ?: '{}',
                    'is_required' => 0
                );
            }
            
            // 验证字段值
            $errors = $this->validateFieldValue($field, $value);
            
            $this->response->throwJson(array(
                'success' => empty($errors),
                'errors' => $errors,
                'message' => empty($errors) ? '验证通过' : implode(', ', $errors)
            ));
            
        } catch (Exception $e) {
            $this->response->throwJson(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * 处理单文件上传API
     */
    private function handleApiUpload() {
        try {
            if (empty($_FILES['file'])) {
                throw new Exception('没有文件被上传');
            }
            
            $file = $_FILES['file'];
            $formId = $this->request->get('form_id');
            $fieldName = $this->request->get('field_name');
            
            if (!$formId || !$fieldName) {
                throw new Exception('参数不完整');
            }
            
            // 验证表单存在
            $form = UformsHelper::getForm($formId);
            if (!$form) {
                throw new Exception('表单不存在');
            }
            
            // 获取字段配置
            $fields = UformsHelper::getFormFields($formId);
            $fieldConfig = array();
            
            foreach ($fields as $field) {
                if ($field['field_name'] === $fieldName && $field['field_type'] === 'file') {
                    $fieldConfig = json_decode($field['field_config'], true) ?: array();
                    break;
                }
            }
            
            $options = UformsHelper::getPluginOptions();
            $maxSize = intval($options->upload_max_size ?? 5) * 1024 * 1024;
            $allowedTypes = explode(',', $options->allowed_file_types ?? 'jpg,png,pdf,doc,txt');
            $allowedTypes = array_map('trim', $allowedTypes);
            
            // 上传文件
            $uploadResult = $this->uploadSingleFile($file, $fieldConfig, $maxSize, $allowedTypes);
            
            $this->response->throwJson(array(
                'success' => true,
                'data' => $uploadResult,
                'message' => '文件上传成功'
            ));
            
        } catch (Exception $e) {
            $this->response->throwJson(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * 处理日历事件API（兼容旧版）
     */
    private function handleApiCalendarEvents() {
        $this->getCalendarData();
    }
    
    /**
     * 获取日历数据API
     */
    private function getCalendarData() {
        $formId = $this->request->get('form_id');
        $start = $this->request->get('start');
        $end = $this->request->get('end');
        
        if (!$formId) {
            $this->response->throwJson(array(
                'success' => false,
                'message' => '表单ID不能为空'
            ));
        }
        
        $form = UformsHelper::getForm($formId);
        if (!$form) {
            $this->response->throwJson(array(
                'success' => false,
                'message' => '表单不存在'
            ));
        }
        
        $select = $this->db->select()->from('table.uforms_calendar')
                           ->where('form_id = ?', $formId);
        
        if ($start) {
            $select->where('start_time >= ?', strtotime($start));
        }
        
        if ($end) {
            $select->where('start_time <= ?', strtotime($end));
        }
        
        $events = $this->db->fetchAll($select);
        
        $calendarEvents = array();
        foreach ($events as $event) {
            $calendarEvents[] = array(
                'id' => $event['id'],
                'title' => $event['title'],
                'start' => date('Y-m-d\TH:i:s', $event['start_time']),
                'end' => $event['end_time'] ? date('Y-m-d\TH:i:s', $event['end_time']) : null,
                'allDay' => $event['all_day'] == 1,
                'color' => $event['color'],
                'status' => $event['status'],
                'description' => $event['event_description'] ?: $event['description'],
                'extendedProps' => array(
                    'status' => $event['status'],
                    'description' => $event['event_description'] ?: $event['description'],
                    'submission_id' => $event['submission_id']
                )
            );
        }
        
        $this->response->throwJson(array(
            'success' => true,
            'events' => $calendarEvents
        ));
    }

        /**
     * 生成验证码
     */
    public function generateCaptcha() {
        session_start();
        
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= mt_rand(0, 9);
        }
        
        $_SESSION['uforms_captcha'] = $code;
        
        // 创建验证码图片
        $width = 100;
        $height = 30;
        $image = imagecreate($width, $height);
        
        // 设置颜色
        $background = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 0, 0, 0);
        $noiseColor = imagecolorallocate($image, 200, 200, 200);
        
        // 添加噪点
        for ($i = 0; $i < 100; $i++) {
            imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $noiseColor);
        }
        
        // 添加干扰线
        for ($i = 0; $i < 5; $i++) {
            $lineColor = imagecolorallocate($image, mt_rand(150, 220), mt_rand(150, 220), mt_rand(150, 220));
            imageline($image, mt_rand(0, $width), mt_rand(0, $height), 
                     mt_rand(0, $width), mt_rand(0, $height), $lineColor);
        }
        
        // 绘制验证码
        $fontSize = 5;
        $x = ($width - strlen($code) * imagefontwidth($fontSize)) / 2;
        $y = ($height - imagefontheight($fontSize)) / 2;
        
        imagestring($image, $fontSize, $x, $y, $code, $textColor);
        
        // 输出图片
        header('Content-Type: image/png');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        imagepng($image);
        imagedestroy($image);
        exit;
    }
    
    /**
     * 记录表单访问
     */
    private function trackFormView($formId) {
        $ip = UformsHelper::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // 记录访问统计
        $statData = array(
            'form_id' => $formId,
            'ip' => $ip,
            'user_agent' => substr($userAgent, 0, 500),
            'referer' => substr($referer, 0, 500),
            'action' => 'view',
            'created_time' => time()
        );
        
        try {
            $this->db->query($this->db->insert('table.uforms_stats')->rows($statData));
        } catch (Exception $e) {
            error_log('Uforms Stats Error: ' . $e->getMessage());
        }
        
        // 更新表单访问计数
        try {
            $this->db->query($this->db->update('table.uforms_forms')
                                     ->expression('view_count', 'view_count + 1')
                                     ->where('id = ?', $formId));
        } catch (Exception $e) {
            error_log('Uforms View Count Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 记录表单提交统计
     */
    private function trackFormSubmission($formId) {
        $ip = UformsHelper::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 记录提交统计
        $statData = array(
            'form_id' => $formId,
            'ip' => $ip,
            'user_agent' => substr($userAgent, 0, 500),
            'action' => 'submit',
            'created_time' => time()
        );
        
        try {
            $this->db->query($this->db->insert('table.uforms_stats')->rows($statData));
        } catch (Exception $e) {
            error_log('Uforms Stats Error: ' . $e->getMessage());
        }
        
        // 更新表单提交计数
        try {
            $this->db->query($this->db->update('table.uforms_forms')
                                     ->expression('submit_count', 'submit_count + 1')
                                     ->where('id = ?', $formId));
        } catch (Exception $e) {
            error_log('Uforms Submit Count Error: ' . $e->getMessage());
        }
    }
    
    /**
     * 渲染表单页面
     */
    private function renderFormPage($form, $fields, $config, $settings, $content = null) {
        // 设置页面标题和元信息
        $this->archiveTitle = $form['title'];
        $this->form = $form;
        $this->fields = $fields;
        $this->formId = $form['id'];
        $this->formName = $form['name'];
        $this->formTitle = $form['title'];
        $this->formDescription = $form['description'];
        $this->formSettings = $settings;
        $this->formConfig = $config;
        $this->content = $content;
        
        // 检查嵌入模式
        if (!empty($settings['embed_mode']) && $settings['embed_mode'] === 'iframe') {
            // iframe模式，使用简单布局
            $this->renderIframePage($form, $fields, $config, $settings, $content);
        } else {
            // 完整页面模式
            $this->renderFullPage($form, $fields, $config, $settings, $content);
        }
    }
    
    /**
     * 渲染iframe页面
     */
    private function renderIframePage($form, $fields, $config, $settings, $content) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>' . htmlspecialchars($form['title']) . '</title>';
        
        // 加载样式
        echo '<link rel="stylesheet" href="' . Helper::options()->pluginUrl . '/Uforms/assets/css/uforms.css">';
        echo '<link rel="stylesheet" href="' . Helper::options()->pluginUrl . '/Uforms/assets/css/frontend.css">';
        
        // 自定义样式
        if (!empty($settings['customCSS'])) {
            echo '<style>' . $settings['customCSS'] . '</style>';
        }
        
        echo '</head><body class="uforms-iframe" style="margin: 0; padding: 20px;">';
        
        if ($content) {
            echo $content;
        } else {
            // 使用前端类渲染表单
            if (class_exists('UformsFront')) {
                echo UformsFront::renderFormHTML($form, $fields, $config, $settings, 'iframe');
            } else {
                echo $this->renderBasicFormHTML($form, $fields, $config, $settings);
            }
        }
        
        // 加载脚本
        echo '<script src="' . Helper::options()->pluginUrl . '/Uforms/assets/js/uforms.js"></script>';
        echo '<script src="' . Helper::options()->pluginUrl . '/Uforms/assets/js/frontend.js"></script>';
        
        echo '</body></html>';
        exit;
    }
    
    /**
     * 渲染完整页面
     */
    private function renderFullPage($form, $fields, $config, $settings, $content) {
        // 设置页面变量供模板使用
        $formId = $form['id'];
        $formName = $form['name'];
        $formTitle = $form['title'];
        $formDescription = $form['description'];
        
        // 渲染模板
        include dirname(__FILE__) . '/templates/form.php';
        exit;
    }
    
    /**
     * 渲染基本表单HTML（备用方案）
     */
    private function renderBasicFormHTML($form, $fields, $config, $settings) {
        $html = '<div class="uforms-container">';
        
        if (!empty($form['description'])) {
            $html .= '<div class="form-description">' . nl2br(htmlspecialchars($form['description'])) . '</div>';
        }
        
        $html .= '<form class="uform" method="post" enctype="multipart/form-data">';
        $html .= '<input type="hidden" name="uform_name" value="' . htmlspecialchars($form['name']) . '">';
        $html .= '<input type="hidden" name="form_id" value="' . $form['id'] . '">';
        $html .= '<input type="hidden" name="uforms_timestamp" value="' . time() . '">';
        $html .= '<input type="text" name="uforms_honeypot" style="display:none;" tabindex="-1" autocomplete="off">';
        
        foreach ($fields as $field) {
            $html .= $this->renderBasicField($field);
        }
        
        // 验证码
        if (!empty($settings['enableCaptcha'])) {
            $html .= '<div class="form-group">';
            $html .= '<label>验证码 <span class="required">*</span></label>';
            $html .= '<img src="' . Helper::options()->siteUrl . 'uforms/captcha" id="captcha-image" style="cursor:pointer;" onclick="this.src=this.src+\'?t=\'+new Date().getTime()">';
            $html .= '<input type="text" name="captcha" required maxlength="4" placeholder="请输入验证码">';
            $html .= '</div>';
        }
        
        $html .= '<div class="form-actions">';
        $html .= '<button type="submit" class="btn-submit">提交</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 渲染基本字段HTML
     */
    private function renderBasicField($field) {
        $config = json_decode($field['field_config'], true) ?: array();
        $required = $field['is_required'] ? ' required' : '';
        $requiredMark = $field['is_required'] ? ' <span class="required">*</span>' : '';
        
        $html = '<div class="form-group">';
        
        if (!empty($field['field_label'])) {
            $html .= '<label for="' . htmlspecialchars($field['field_name']) . '">' . 
                     htmlspecialchars($field['field_label']) . $requiredMark . '</label>';
        }
        
        switch ($field['field_type']) {
            case 'text':
            case 'email':
            case 'tel':
            case 'url':
                $html .= '<input type="' . $field['field_type'] . '" name="' . htmlspecialchars($field['field_name']) . '" ' .
                         'id="' . htmlspecialchars($field['field_name']) . '"' . $required;
                if (!empty($config['placeholder'])) {
                    $html .= ' placeholder="' . htmlspecialchars($config['placeholder']) . '"';
                }
                $html .= '>';
                break;
                
            case 'textarea':
                $html .= '<textarea name="' . htmlspecialchars($field['field_name']) . '" ' .
                         'id="' . htmlspecialchars($field['field_name']) . '"' . $required;
                if (!empty($config['placeholder'])) {
                    $html .= ' placeholder="' . htmlspecialchars($config['placeholder']) . '"';
                }
                $html .= '></textarea>';
                break;
                
            case 'number':
                $html .= '<input type="number" name="' . htmlspecialchars($field['field_name']) . '" ' .
                         'id="' . htmlspecialchars($field['field_name']) . '"' . $required;
                if (isset($config['min'])) {
                    $html .= ' min="' . $config['min'] . '"';
                }
                if (isset($config['max'])) {
                    $html .= ' max="' . $config['max'] . '"';
                }
                $html .= '>';
                break;
                
            case 'file':
                $html .= '<input type="file" name="' . htmlspecialchars($field['field_name']) . '" ' .
                         'id="' . htmlspecialchars($field['field_name']) . '"' . $required;
                if (!empty($config['allowedTypes'])) {
                    $extensions = explode(',', $config['allowedTypes']);
                    $accept = array();
                    foreach ($extensions as $ext) {
                        $accept[] = '.' . trim($ext);
                    }
                    $html .= ' accept="' . implode(',', $accept) . '"';
                }
                $html .= '>';
                break;
                
            default:
                $html .= '<input type="text" name="' . htmlspecialchars($field['field_name']) . '" ' .
                         'id="' . htmlspecialchars($field['field_name']) . '"' . $required . '>';
        }
        
        if (!empty($field['field_description'])) {
            $html .= '<div class="field-help">' . htmlspecialchars($field['field_description']) . '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 渲染成功页面
     */
    private function renderSuccessPage($form, $response) {
        $this->archiveTitle = '提交成功 - ' . $form['title'];
        $this->form = $form;
        $this->response = $response;
        include dirname(__FILE__) . '/templates/form-success.php';
        exit;
    }
    
    /**
     * 渲染错误页面
     */
    private function renderErrorPage($error) {
        $this->archiveTitle = '提交失败';
        $this->error = $error;
        include dirname(__FILE__) . '/templates/form-error.php';
        exit;
    }
}
