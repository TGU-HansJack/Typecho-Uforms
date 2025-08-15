<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Uforms 动作处理类 - 完整版本
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
     * 动作分发
     */
    public function action() {
        $this->widget('Widget_User')->pass('subscriber');
        $this->on($this->request->isAjax())->ajax();
        $this->on(!$this->request->isAjax())->showForm();
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
        
        // 增加访问计数
        $this->db->query($this->db->update('table.uforms_forms')
                                  ->rows(array('view_count' => new Typecho_Db_Query_Exception('`view_count` + 1')))
                                  ->where('id = ?', $form['id']));
        
        $this->render($form);
    }
    
    /**
     * 通过ID显示表单
     */
    public function showFormById() {
        $this->showForm();
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
            $formName = $this->request->get('form_name');
            
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
            
            // 验证机器人检查
            $this->validateBotCheck();
            
            // 验证验证码
            if (!empty($form['settings']['enableCaptcha'])) {
                $this->validateCaptcha();
            }
            
            // 获取提交数据
            $data = $this->extractFormData($form);
            
            // 处理文件上传
            $files = array();
            if (!empty($_FILES)) {
                $files = $this->processFileUploads();
            }
            
            // 提交表单
            $submissionId = UformsHelper::submitForm($form['id'], $data, $files);
            
            // 返回成功响应
            $successMessage = $form['settings']['successMessage'] ?? '表单提交成功！';
            $successAction = $form['settings']['successAction'] ?? 'message';
            
            $response = array(
                'success' => true,
                'message' => $successMessage,
                'submission_id' => $submissionId,
                'action' => $successAction
            );
            
            // 处理成功后的动作
            switch ($successAction) {
                case 'redirect':
                    $response['redirect_url'] = $form['settings']['redirectUrl'] ?? '';
                    break;
                case 'block':
                    $response['success_block'] = $form['settings']['successBlock'] ?? '';
                    break;
            }
            
            // AJAX响应
            if ($this->request->isAjax()) {
                $this->response->throwJson($response);
            } else {
                // 非AJAX表单提交 - 重定向或显示结果页面
                if ($successAction === 'redirect' && !empty($response['redirect_url'])) {
                    $this->response->redirect($response['redirect_url']);
                } else {
                    $this->renderSuccessPage($form, $response);
                }
            }
            
        } catch (Exception $e) {
            $error = array(
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            );
            
            // 记录错误日志
            error_log('Uforms Submit Error: ' . $e->getMessage());
            
            if ($this->request->isAjax()) {
                $this->response->throwJson($error);
            } else {
                $this->renderErrorPage($error);
            }
        }
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
        
        $this->renderCalendar($form);
    }
    
    /**
     * API处理器
     */
    public function apiHandler() {
        $action = $this->request->get('action');
        
        switch ($action) {
            case 'submit':
                $this->submit();
                break;
            case 'validate':
                $this->validateField();
                break;
            case 'upload':
                $this->handleFileUpload();
                break;
            case 'calendar':
                $this->getCalendarData();
                break;
            default:
                $this->response->throwJson(array(
                    'success' => false,
                    'message' => '不支持的API操作'
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
            // 跨域提交检查（可选）
        }
    }
    
    /**
     * 验证验证码
     */
    private function validateCaptcha() {
        $captcha = $this->request->get('captcha');
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
     * 提取表单数据
     */
    private function extractFormData($form) {
        $fields = UformsHelper::getFormFields($form['id']);
        $data = array();
        
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            $fieldType = $field['field_type'];
            
            switch ($fieldType) {
                case 'checkbox':
                    $value = $this->request->getArray($fieldName);
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
     * 处理文件上传
     */
    private function processFileUploads() {
        $uploadedFiles = array();
        
        foreach ($_FILES as $fieldName => $fileArray) {
            if (empty($fileArray['name']) || $fileArray['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            $uploadedFiles[$fieldName] = $fileArray;
        }
        
        return $uploadedFiles;
    }
    
    /**
     * 渲染表单
     */
    private function render($form) {
        $fields = UformsHelper::getFormFields($form['id']);
        
        // 设置页面标题
        $this->archiveTitle = $form['title'];
        
        // 准备模板变量
        $this->form = $form;
        $this->fields = $fields;
        $this->formId = $form['id'];
        $this->formName = $form['name'];
        $this->formTitle = $form['title'];
        $this->formDescription = $form['description'];
        $this->formSettings = $form['settings'];
        $this->formConfig = $form['config'];
        
        // 渲染模板
        $this->render('form');
    }
    
    /**
     * 渲染成功页面
     */
    private function renderSuccessPage($form, $response) {
        $this->archiveTitle = '提交成功 - ' . $form['title'];
        $this->form = $form;
        $this->response = $response;
        $this->render('form-success');
    }
    
    /**
     * 渲染错误页面
     */
    private function renderErrorPage($error) {
        $this->archiveTitle = '提交失败';
        $this->error = $error;
        $this->render('form-error');
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
        
        $this->render('calendar');
    }
    
    /**
     * 字段验证API
     */
    private function validateField() {
        $formId = $this->request->get('form_id');
        $fieldName = $this->request->get('field_name');
        $value = $this->request->get('value');
        
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
                throw new Exception('字段不存在');
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
     * 验证单个字段值
     */
    private function validateFieldValue($field, $value) {
        $errors = array();
        $config = $field['field_config'];
        
        // 必填验证
        if ($field['is_required'] && empty($value)) {
            $errors[] = "{$field['field_label']}是必填项";
            return $errors; // 如果必填验证失败，不进行其他验证
        }
        
        // 如果值为空且非必填，跳过其他验证
        if (empty($value)) {
            return $errors;
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
                    if (isset($config['min']) && $value < $config['min']) {
                        $errors[] = "{$field['field_label']}不能小于{$config['min']}";
                    }
                    if (isset($config['max']) && $value > $config['max']) {
                        $errors[] = "{$field['field_label']}不能大于{$config['max']}";
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
        if (isset($config['minLength']) && mb_strlen($value) < $config['minLength']) {
            $errors[] = "{$field['field_label']}长度不能少于{$config['minLength']}个字符";
        }
        
        if (isset($config['maxLength']) && mb_strlen($value) > $config['maxLength']) {
            $errors[] = "{$field['field_label']}长度不能超过{$config['maxLength']}个字符";
        }
        
        // 正则表达式验证
        if (isset($config['pattern']) && !empty($config['pattern'])) {
            if (!preg_match('/' . $config['pattern'] . '/', $value)) {
                $message = $config['errorMessage'] ?? "{$field['field_label']}格式不正确";
                $errors[] = $message;
            }
        }
        
        return $errors;
    }
    
    /**
     * 处理单文件上传API
     */
    private function handleFileUpload() {
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
            
            // 上传文件
            $uploadResult = $this->uploadSingleFile($file, $fieldName);
            
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
     * 上传单个文件
     */
    private function uploadSingleFile($file, $fieldName) {
        $options = UformsHelper::getPluginOptions();
        
        if (!$options->upload_enabled) {
            throw new Exception('文件上传功能已禁用');
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('文件上传失败: ' . $this->getUploadErrorMessage($file['error']));
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
        
        // 验证文件大小
        if ($file['size'] > $maxSize) {
            throw new Exception('文件大小超过限制');
        }
        
        // 验证文件类型
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception('不支持的文件类型');
        }
        
        // 生成唯一文件名
        $fileName = uniqid() . '_' . time() . '.' . $extension;
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
            'url' => $this->options->siteUrl . 'usr/uploads/uforms/files/' . $fileName
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
                'description' => $event['event_description']
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
        $image = imagecreate(100, 30);
        $background = imagecolorallocate($image, 255, 255, 255);
        $textColor = imagecolorallocate($image, 0, 0, 0);
        
        imagestring($image, 5, 20, 5, $code, $textColor);
        
        // 添加干扰线
        for ($i = 0; $i < 5; $i++) {
            $lineColor = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imageline($image, mt_rand(0, 100), mt_rand(0, 30), mt_rand(0, 100), mt_rand(0, 30), $lineColor);
        }
        
        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
    }
}
