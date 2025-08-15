<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once dirname(__FILE__) . '/frontend-functions.php';

class UformsFront {
    
    public static function renderForm($form_name, $template = 'default') {
        global $db, $request;
        
        try {
            // 获取表单
            $form = $db->fetchRow(
                $db->select('*')->from('table.uforms_forms')
                   ->where('name = ? AND status = ?', $form_name, 'published')
            );
            
            if (!$form) {
                return '<div class="uform-error">表单不存在或未发布</div>';
            }
            
            // 获取表单字段
            $fields = $db->fetchAll(
                $db->select('*')->from('table.uforms_fields')
                   ->where('form_id = ?', $form['id'])
                   ->order('sort_order ASC')
            );
            
            $form_config = json_decode($form['config'], true) ?: array();
            $form_settings = json_decode($form['settings'], true) ?: array();
            
            // 处理表单提交
            if ($request->isPost() && $request->get('uform_name') === $form_name) {
                return self::handleFormSubmission($form, $fields, $form_settings);
            }
            
            // 渲染表单
            return self::renderFormHTML($form, $fields, $form_config, $form_settings, $template);
            
        } catch (Exception $e) {
            return '<div class="uform-error">表单加载失败: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    public static function handleFormSubmission($form, $fields, $settings) {
        global $db, $request;
        
        $errors = array();
        $form_data = array();
        $uploaded_files = array();
        
        // 检查是否启用表单
        if (empty($settings['enable_forms'])) {
            return '<div class="uform-error">表单已禁用</div>';
        }
        
        // 检查频率限制
        if (!self::checkRateLimit($form['id'])) {
            return '<div class="uform-error">提交过于频繁，请稍后再试</div>';
        }
        
        // 验证验证码
        if (!empty($settings['enable_captcha'])) {
            if (!self::validateCaptcha($request->get('captcha'))) {
                $errors['captcha'] = '验证码错误';
            }
        }
        
        // 验证reCAPTCHA
        if (!empty($settings['enable_recaptcha'])) {
            if (!self::validateRecaptcha($request->get('g-recaptcha-response'))) {
                $errors['recaptcha'] = 'reCAPTCHA验证失败';
            }
        }
        
        // 验证字段
        foreach ($fields as $field) {
            $field_name = $field['field_name'];
            $field_value = $request->get($field_name);
            
            // 处理文件上传
            if ($field['field_type'] === 'file' && isset($_FILES[$field_name])) {
                $file_result = self::handleFileUpload($_FILES[$field_name], $field, $form['id']);
                if ($file_result['success']) {
                    $form_data[$field_name] = $file_result['file_info'];
                    $uploaded_files[] = $file_result['file_info'];
                } else {
                    $errors[$field_name] = $file_result['error'];
                }
                continue;
            }
            
            // 验证必填字段
            if ($field['is_required'] && empty($field_value)) {
                $errors[$field_name] = '此字段为必填项';
                continue;
            }
            
            // 根据字段类型验证
            if (!empty($field_value)) {
                switch ($field['field_type']) {
                    case 'email':
                        if (!filter_var($field_value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field_name] = '请输入有效的邮箱地址';
                        }
                        break;
                    case 'url':
                        if (!filter_var($field_value, FILTER_VALIDATE_URL)) {
                            $errors[$field_name] = '请输入有效的网址';
                        }
                        break;
                    case 'number':
                        if (!is_numeric($field_value)) {
                            $errors[$field_name] = '请输入有效的数字';
                        }
                        break;
                }
            }
            
            $form_data[$field_name] = $field_value;
        }
        
        // 如果有错误，返回表单
        if (!empty($errors)) {
            return self::renderFormHTML($form, $fields, array(), $settings, 'default', $errors, $form_data);
        }
        
        // 保存提交数据
        $submission_data = array(
            'form_id' => $form['id'],
            'data' => json_encode($form_data),
            'ip' => UformsHelper::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'status' => 'new',
            'created_time' => time()
        );
        
        $submission_id = $db->query($db->insert('table.uforms_submissions')->rows($submission_data));
        
        // 保存文件记录
        foreach ($uploaded_files as $file_info) {
            $file_data = array(
                'submission_id' => $submission_id,
                'form_id' => $form['id'],
                'field_name' => $file_info['field_name'],
                'original_name' => $file_info['original_name'],
                'filename' => $file_info['filename'],
                'file_path' => $file_info['file_path'],
                'file_size' => $file_info['file_size'],
                'file_type' => $file_info['file_type'],
                'created_time' => time()
            );
            $db->query($db->insert('table.uforms_files')->rows($file_data));
        }
        
        // 更新提交次数
        $db->query($db->update('table.uforms_forms')
                      ->rows(array('submit_count' => new Typecho_Db_Query_Expression('submit_count + 1')))
                      ->where('id = ?', $form['id']));
        
        // 发送通知
        self::sendNotifications($form, $form_data, $settings);
        
        // 显示成功消息
        $success_message = !empty($settings['success_message']) ? 
                          $settings['success_message'] : 
                          '表单提交成功！感谢您的参与。';
        
        return '<div class="uform-success">' . htmlspecialchars($success_message) . '</div>';
    }
    