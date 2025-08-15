<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 扩展前端辅助函数

/**
 * 渲染表单HTML
 */
function renderUformHTML($form, $fields, $config = array(), $settings = array()) {
    $theme = $config['theme'] ?? 'default';
    $html = '<div class="uform uform-' . $theme . '" data-form-id="' . $form['id'] . '" data-form-name="' . htmlspecialchars($form['name']) . '">';
    
    // 表单头部
    if (!empty($form['title']) || !empty($form['description'])) {
        $html .= '<div class="uform-header">';
        if (!empty($form['title'])) {
            $html .= '<h2 class="uform-title">' . htmlspecialchars($form['title']) . '</h2>';
        }
        if (!empty($form['description'])) {
            $html .= '<div class="uform-description">' . nl2br(htmlspecialchars($form['description'])) . '</div>';
        }
        $html .= '</div>';
    }
    
    // 表单内容
    $html .= '<form class="uform-form" method="post" enctype="multipart/form-data">';
    $html .= '<input type="hidden" name="uform_name" value="' . htmlspecialchars($form['name']) . '">';
    $html .= '<input type="hidden" name="form_id" value="' . $form['id'] . '">';
    
    // 蜜罐字段
    if (!empty($settings['enableHoneypot'])) {
        $html .= '<input type="text" name="website" style="display:none;" tabindex="-1" autocomplete="off">';
    }
    
    // 渲染字段
    foreach ($fields as $field) {
        $html .= renderUformField($field);
    }
    
    // 验证码
    if (!empty($settings['enableCaptcha'])) {
        $html .= renderCaptchaField();
    }
    
    // 提交按钮
    $html .= '<div class="uform-actions">';
    $html .= '<button type="submit" class="uform-submit btn btn-primary">';
    $html .= htmlspecialchars($settings['submitText'] ?? '提交');
    $html .= '</button>';
    $html .= '</div>';
    
    $html .= '</form>';
    $html .= '</div>';
    
    return $html;
}

/**
 * 渲染单个字段
 */
function renderUformField($field) {
    $config = json_decode($field['field_config'], true) ?: array();
    $type = $field['field_type'];
    $name = $field['field_name'];
    $label = $field['field_label'];
    $required = $field['is_required'] ? 'required' : '';
    $cssClass = !empty($config['cssClass']) ? ' ' . $config['cssClass'] : '';
    $cssId = !empty($config['cssId']) ? ' id="' . $config['cssId'] . '"' : '';
    
    $html = '<div class="uform-field uform-field-' . $type . ' width-' . ($config['width'] ?? 'full') . $cssClass . '"' . $cssId . '>';
    
    // 字段标签
    if ($label && !in_array($type, ['heading', 'paragraph', 'divider', 'hidden'])) {
        $html .= '<label class="uform-label" for="field-' . $name . '">';
        $html .= htmlspecialchars($label);
        if ($field['is_required']) {
            $html .= ' <span class="required-mark">*</span>';
        }
        $html .= '</label>';
    }
    
    // 字段输入控件
    $html .= renderFieldInput($type, $name, $config, $required);
    
    // 帮助文本
    if (!empty($config['help'])) {
        $html .= '<div class="uform-help">' . htmlspecialchars($config['help']) . '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * 渲染字段输入控件
 */
function renderFieldInput($type, $name, $config, $required) {
    $placeholder = !empty($config['placeholder']) ? ' placeholder="' . htmlspecialchars($config['placeholder']) . '"' : '';
    $defaultValue = $config['defaultValue'] ?? $config['value'] ?? '';
    $fieldId = 'field-' . $name;
    
    switch ($type) {
        case 'text':
        case 'email':
        case 'url':
        case 'tel':
        case 'password':
            return '<input type="' . $type . '" name="' . $name . '" id="' . $fieldId . '" value="' . htmlspecialchars($defaultValue) . '"' . $placeholder . ' ' . $required . ' class="uform-input">';
            
        case 'textarea':
            return '<textarea name="' . $name . '" id="' . $fieldId . '" rows="' . ($config['rows'] ?? 4) . '"' . $placeholder . ' ' . $required . ' class="uform-textarea">' . htmlspecialchars($defaultValue) . '</textarea>';
            
        case 'number':
            $min = isset($config['min']) ? ' min="' . $config['min'] . '"' : '';
            $max = isset($config['max']) ? ' max="' . $config['max'] . '"' : '';
            $step = isset($config['step']) ? ' step="' . $config['step'] . '"' : '';
            return '<input type="number" name="' . $name . '" id="' . $fieldId . '" value="' . htmlspecialchars($defaultValue) . '"' . $placeholder . $min . $max . $step . ' ' . $required . ' class="uform-input">';
            
        case 'select':
            $html = '<select name="' . $name . '" id="' . $fieldId . '" ' . $required . ' class="uform-select">';
            if (!$required) {
                $html .= '<option value="">' . ($config['placeholder'] ?? '请选择') . '</option>';
            }
            if (!empty($config['options'])) {
                foreach ($config['options'] as $option) {
                    $selected = $option['value'] === $defaultValue ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars($option['value']) . '"' . $selected . '>' . htmlspecialchars($option['label']) . '</option>';
                }
            }
            $html .= '</select>';
            return $html;
            
        case 'radio':
            $html = '<div class="uform-radio-group">';
            if (!empty($config['options'])) {
                foreach ($config['options'] as $index => $option) {
                    $checked = $option['value'] === $defaultValue ? ' checked' : '';
                    $optionId = $fieldId . '_' . $index;
                    $html .= '<label class="uform-radio-label" for="' . $optionId . '">';
                    $html .= '<input type="radio" name="' . $name . '" id="' . $optionId . '" value="' . htmlspecialchars($option['value']) . '"' . $checked . ' ' . $required . '>';
                    $html .= '<span class="radio-text">' . htmlspecialchars($option['label']) . '</span>';
                    $html .= '</label>';
                }
            }
            $html .= '</div>';
            return $html;
            
        case 'checkbox':
            $html = '<div class="uform-checkbox-group">';
            if (!empty($config['options'])) {
                foreach ($config['options'] as $index => $option) {
                    $optionId = $fieldId . '_' . $index;
                    $html .= '<label class="uform-checkbox-label" for="' . $optionId . '">';
                    $html .= '<input type="checkbox" name="' . $name . '[]" id="' . $optionId . '" value="' . htmlspecialchars($option['value']) . '">';
                    $html .= '<span class="checkbox-text">' . htmlspecialchars($option['label']) . '</span>';
                    $html .= '</label>';
                }
            }
            $html .= '</div>';
            return $html;
            
        case 'file':
            $multiple = !empty($config['multiple']) ? ' multiple' : '';
            $accept = !empty($config['accept']) ? ' accept="' . htmlspecialchars($config['accept']) . '"' : '';
            $html = '<input type="file" name="' . $name . ($multiple ? '[]' : '') . '" id="' . $fieldId . '"' . $accept . $multiple . ' ' . $required . ' class="uform-file">';
            if (!empty($config['maxSize'])) {
                $html .= '<div class="file-info">最大文件大小: ' . formatBytes($config['maxSize'] * 1024 * 1024) . '</div>';
            }
            return $html;
            
        case 'date':
            $min = !empty($config['minDate']) ? ' min="' . $config['minDate'] . '"' : '';
            $max = !empty($config['maxDate']) ? ' max="' . $config['maxDate'] . '"' : '';
            return '<input type="date" name="' . $name . '" id="' . $fieldId . '" value="' . htmlspecialchars($defaultValue) . '"' . $min . $max . ' ' . $required . ' class="uform-input">';
            
        case 'time':
            return '<input type="time" name="' . $name . '" id="' . $fieldId . '" value="' . htmlspecialchars($defaultValue) . '" ' . $required . ' class="uform-input">';
            
        case 'datetime':
            return '<input type="datetime-local" name="' . $name . '" id="' . $fieldId . '" value="' . htmlspecialchars($defaultValue) . '" ' . $required . ' class="uform-input">';
            
        case 'range':
            $min = $config['min'] ?? 0;
            $max = $config['max'] ?? 100;
            $step = $config['step'] ?? 1;
            $value = $defaultValue ?: $min;
            return '<input type="range" name="' . $name . '" id="' . $fieldId . '" min="' . $min . '" max="' . $max . '" step="' . $step . '" value="' . $value . '" class="uform-range" oninput="this.nextElementSibling.textContent=this.value"><output class="range-output">' . $value . '</output>';
            
        case 'color':
            return '<input type="color" name="' . $name . '" id="' . $fieldId . '" value="' . ($defaultValue ?: '#000000') . '" class="uform-color">';
            
        case 'heading':
            $level = $config['level'] ?? 'h3';
            return '<' . $level . ' class="uform-heading">' . htmlspecialchars($config['text'] ?? '标题') . '</' . $level . '>';
            
        case 'paragraph':
            return '<div class="uform-paragraph">' . nl2br(htmlspecialchars($config['text'] ?? '段落文本')) . '</div>';
            
        case 'divider':
            return '<hr class="uform-divider">';
            
        case 'hidden':
            return '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($config['value'] ?? '') . '">';
            
        default:
            return '<div class="uform-unknown">未知字段类型: ' . htmlspecialchars($type) . '</div>';
    }
}

/**
 * 渲染验证码字段
 */
function renderCaptchaField() {
    // 简单的数学验证码
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $answer = $num1 + $num2;
    
    $_SESSION['captcha_answer'] = $answer;
    
    $html = '<div class="uform-field uform-captcha">';
    $html .= '<label class="uform-label">验证码 <span class="required-mark">*</span></label>';
    $html .= '<div class="captcha-question">' . $num1 . ' + ' . $num2 . ' = ?</div>';
    $html .= '<input type="number" name="captcha" required class="uform-input captcha-input" placeholder="请输入计算结果">';
    $html .= '</div>';
    
    return $html;
}

/**
 * 验证验证码
 */
function validateCaptcha($userAnswer) {
    if (!isset($_SESSION['captcha_answer'])) {
        return false;
    }
    
    $correctAnswer = $_SESSION['captcha_answer'];
    unset($_SESSION['captcha_answer']);
    
    return intval($userAnswer) === $correctAnswer;
}

/**
 * 格式化字节数
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * 生成表单样式CSS
 */
function generateFormCSS($config) {
    $css = '';
    
    if (!empty($config['primaryColor'])) {
        $css .= ':root { --uform-primary: ' . $config['primaryColor'] . '; }';
    }
    
    if (!empty($config['formWidth'])) {
        $css .= '.uform { max-width: ' . $config['formWidth'] . '; }';
    }
    
    if (!empty($config['colors'])) {
        foreach ($config['colors'] as $key => $color) {
            $css .= ':root { --uform-' . $key . ': ' . $color . '; }';
        }
    }
    
    if (!empty($config['customCSS'])) {
        $css .= $config['customCSS'];
    }
    
    return $css;
}

/**
 * 处理条件逻辑
 */
function evaluateConditions($conditions, $submissionData) {
    if (empty($conditions) || !is_array($conditions)) {
        return true;
    }
    
    foreach ($conditions as $condition) {
        $fieldValue = $submissionData[$condition['field']] ?? '';
        $operator = $condition['operator'];
        $compareValue = $condition['value'];
        
        $result = false;
        
        switch ($operator) {
            case 'equals':
                $result = $fieldValue === $compareValue;
                break;
            case 'not_equals':
                $result = $fieldValue !== $compareValue;
                break;
            case 'contains':
                $result = strpos($fieldValue, $compareValue) !== false;
                break;
            case 'not_contains':
                $result = strpos($fieldValue, $compareValue) === false;
                break;
            case 'empty':
                $result = empty($fieldValue);
                break;
            case 'not_empty':
                $result = !empty($fieldValue);
                break;
            case 'greater':
                $result = is_numeric($fieldValue) && is_numeric($compareValue) && $fieldValue > $compareValue;
                break;
            case 'less':
                $result = is_numeric($fieldValue) && is_numeric($compareValue) && $fieldValue < $compareValue;
                break;
        }
        
        // 如果任何条件不满足，返回false（AND逻辑）
        if (!$result) {
            return false;
        }
    }
    
    return true;
}

/**
 * 清理HTML内容
 */
function sanitizeHTML($html) {
    // 允许的HTML标签
    $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><span><div><h1><h2><h3><h4><h5><h6>';
    
    // 清理HTML
    $html = strip_tags($html, $allowedTags);
    
    // 移除危险属性
    $html = preg_replace('/(<[^>]+)(on\w+)([^>]*>)/i', '$1$3', $html);
    $html = preg_replace('/(<[^>]+)(javascript:)([^>]*>)/i', '$1$3', $html);
    
    return $html;
}

/**
 * 生成安全令牌
 */
function generateSecurityToken($formId) {
    $token = hash('sha256', $formId . time() . mt_rand());
    $_SESSION['uform_token_' . $formId] = $token;
    return $token;
}

/**
 * 验证安全令牌
 */
function validateSecurityToken($formId, $token) {
    $sessionToken = $_SESSION['uform_token_' . $formId] ?? '';
    unset($_SESSION['uform_token_' . $formId]);
    
    return $token && $sessionToken && hash_equals($sessionToken, $token);
}

/**
 * 检查频率限制
 */
function checkRateLimit($formId, $ip, $limit = 3, $window = 60) {
    $db = Typecho_Db::get();
    
    $count = $db->fetchObject(
        $db->select('COUNT(*) as count')
           ->from('table.uforms_submissions')
           ->where('form_id = ? AND ip = ? AND created_time > ?', $formId, $ip, time() - $window)
    )->count;
    
    return $count < $limit;
}

/**
 * 记录垃圾内容
 */
function logSpamAttempt($formId, $ip, $reason, $data) {
    $db = Typecho_Db::get();
    
    $logData = array(
        'form_id' => $formId,
        'ip' => $ip,
        'reason' => $reason,
        'data' => json_encode($data),
        'created_time' => time()
    );
    
    $db->query($db->insert('table.uforms_spam_log')->rows($logData));
}

/**
 * 创建系统通知
 */
function createSystemNotification($formId, $type, $title, $message, $data = null) {
    $db = Typecho_Db::get();
    
    $notificationData = array(
        'form_id' => $formId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'data' => $data ? json_encode($data) : null,
        'created_time' => time()
    );
    
    $db->query($db->insert('table.uforms_system_notifications')->rows($notificationData));
}

/**
 * 解析邮件模板变量
 */
function parseEmailTemplate($template, $variables) {
    foreach ($variables as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    
    return $template;
}

/**
 * 获取表单统计数据
 */
function getFormStatistics($formId, $days = 30) {
    $db = Typecho_Db::get();
    $startTime = time() - ($days * 24 * 60 * 60);
    
    $stats = array(
        'total_submissions' => 0,
        'daily_average' => 0,
        'completion_rate' => 0,
        'device_breakdown' => array(),
        'field_analytics' => array()
    );
    
    // 总提交数
    $stats['total_submissions'] = $db->fetchObject(
        $db->select('COUNT(*) as count')
           ->from('table.uforms_submissions')
           ->where('form_id = ? AND created_time > ?', $formId, $startTime)
    )->count;
    
    // 日均提交数
    $stats['daily_average'] = round($stats['total_submissions'] / $days, 2);
    
    return $stats;
}
?>
