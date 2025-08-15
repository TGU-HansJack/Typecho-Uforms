<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Uforms 前端功能函数库
 */

/**
 * 前端表单渲染类
 */
class UformsFrontend
{
    private static $loadedForms = array();
    private static $scriptsEnqueued = false;
    private static $stylesEnqueued = false;
    
    /**
     * 渲染表单
     */
    public static function renderForm($formId = null, $formName = null, $template = 'default', $options = array()) {
        // 获取表单数据
        if ($formId) {
            $form = UformsHelper::getForm($formId);
        } elseif ($formName) {
            $form = UformsHelper::getFormByName($formName);
        } else {
            return '<div class="uform-error">表单ID或名称不能为空</div>';
        }
        
        if (!$form) {
            return '<div class="uform-error">表单不存在</div>';
        }
        
        if ($form['status'] !== 'published') {
            return '<div class="uform-error">表单未发布</div>';
        }
        
        // 获取字段
        $fields = UformsHelper::getFormFields($form['id']);
        
        // 记录已加载的表单
        self::$loadedForms[] = $form['id'];
        
        // 确保资源文件已加载
        self::enqueueAssets();
        
        // 生成表单HTML
        $html = self::generateFormHTML($form, $fields, $template, $options);
        
        return $html;
    }
    
    /**
     * 生成表单HTML
     */
    private static function generateFormHTML($form, $fields, $template = 'default', $options = array()) {
        $formId = $form['id'];
        $formName = $form['name'];
        $formConfig = $form['config'] ?? array();
        $formSettings = $form['settings'] ?? array();
        
        // 合并选项
        $defaultOptions = array(
            'show_title' => true,
            'show_description' => true,
            'ajax_submit' => $formSettings['ajaxSubmit'] ?? true,
            'show_required_note' => true,
            'css_class' => '',
            'submit_button_text' => $formSettings['submitText'] ?? '提交'
        );
        $options = array_merge($defaultOptions, $options);
        
        // 开始构建HTML
        $html = '';
        
        // 表单容器开始
        $cssClasses = array(
            'uform',
            'uform-' . $template,
            'uform-' . $formName,
            'uform-id-' . $formId
        );
        
        if (!empty($options['css_class'])) {
            $cssClasses[] = $options['css_class'];
        }
        
        if ($options['ajax_submit']) {
            $cssClasses[] = 'uform-ajax';
        }
        
        $html .= '<div class="' . implode(' ', $cssClasses) . '" data-form-id="' . $formId . '" data-form-name="' . $formName . '">';
        
        // 表单头部
        if ($options['show_title'] || $options['show_description']) {
            $html .= '<div class="uform-header">';
            
            if ($options['show_title'] && !empty($form['title'])) {
                $html .= '<h2 class="uform-title">' . htmlspecialchars($form['title']) . '</h2>';
            }
            
            if ($options['show_description'] && !empty($form['description'])) {
                $html .= '<div class="uform-description">' . nl2br(htmlspecialchars($form['description'])) . '</div>';
            }
            
            $html .= '</div>';
        }
        
        // 消息容器
        $html .= '<div class="uform-messages" style="display: none;"></div>';
        
        // 表单开始
        $formAction = Helper::options()->siteUrl . 'uforms/api/submit';
        $html .= '<form class="uform-form" method="post" action="' . $formAction . '" enctype="multipart/form-data">';
        
        // 隐藏字段
        $html .= '<input type="hidden" name="form_id" value="' . $formId . '">';
        $html .= '<input type="hidden" name="form_name" value="' . $formName . '">';
        $html .= '<input type="hidden" name="uforms_timestamp" value="' . time() . '">';
        
        // 蜜罐字段（反垃圾）
        if (!empty($formSettings['enableHoneypot'])) {
            $html .= '<input type="text" name="uforms_honeypot" value="" style="display: none !important;" tabindex="-1" autocomplete="off">';
        }
        
        // 必填提示
        if ($options['show_required_note'] && self::hasRequiredFields($fields)) {
            $html .= '<div class="uform-required-note">标有 <span class="required-mark">*</span> 的字段为必填项</div>';
        }
        
        // 渲染字段
        foreach ($fields as $field) {
            $html .= self::renderField($field, $formConfig);
        }
        
        // 验证码
        if (!empty($formSettings['enableCaptcha'])) {
            $html .= self::renderCaptchaField();
        }
        
        // 提交按钮
        $html .= '<div class="uform-actions">';
        $html .= '<button type="submit" class="uform-submit btn btn-primary">';
        $html .= '<span class="submit-text">' . htmlspecialchars($options['submit_button_text']) . '</span>';
        $html .= '<span class="submit-loading" style="display: none;">提交中...</span>';
        $html .= '</button>';
        $html .= '</div>';
        
        $html .= '</form>';
        
        // 成功消息容器
        $html .= '<div class="uform-success" style="display: none;">';
        $successMessage = $formSettings['successMessage'] ?? '表单提交成功！感谢您的参与。';
        $html .= '<div class="success-message">' . nl2br(htmlspecialchars($successMessage)) . '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // 添加表单特定的样式
        if (!empty($formConfig['customCSS'])) {
            $html .= '<style>' . $formConfig['customCSS'] . '</style>';
        }
        
        // 添加表单配置到JavaScript
        $html .= self::generateFormScript($form, $options);
        
        return $html;
    }
    
    /**
     * 渲染单个字段
     */
    private static function renderField($field, $formConfig = array()) {
        $fieldType = $field['field_type'];
        $fieldName = $field['field_name'];
        $fieldLabel = $field['field_label'];
        $fieldConfig = $field['field_config'] ?? array();
        $isRequired = $field['is_required'];
        
        // 跳过布局字段的容器包装
        if (in_array($fieldType, array('heading', 'paragraph', 'divider', 'html'))) {
            return self::renderSpecialField($field);
        }
        
        // 字段容器
        $containerClasses = array(
            'uform-field',
            'uform-field-' . $fieldType,
            'field-' . $fieldName
        );
        
        if ($isRequired) {
            $containerClasses[] = 'required';
        }
        
        if (!empty($fieldConfig['cssClass'])) {
            $containerClasses[] = $fieldConfig['cssClass'];
        }
        
        // 字段宽度
        $width = $fieldConfig['width'] ?? 'full';
        if ($width !== 'full') {
            $containerClasses[] = 'width-' . $width;
        }
        
        $html = '<div class="' . implode(' ', $containerClasses) . '"';
        
        if (!empty($fieldConfig['cssId'])) {
            $html .= ' id="' . htmlspecialchars($fieldConfig['cssId']) . '"';
        }
        
        $html .= '>';
        
        // 字段标签
        if ($fieldType !== 'hidden') {
            $html .= '<label class="uform-label" for="field_' . $fieldName . '">';
            $html .= htmlspecialchars($fieldLabel);
            if ($isRequired) {
                $html .= ' <span class="required-mark">*</span>';
            }
            $html .= '</label>';
        }
        
        // 字段输入
        $html .= self::renderFieldInput($field);
        
        // 帮助文本
        if (!empty($fieldConfig['help'])) {
            $html .= '<div class="uform-help">' . htmlspecialchars($fieldConfig['help']) . '</div>';
        }
        
        // 错误消息容器
        $html .= '<div class="uform-field-error" style="display: none;"></div>';
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 渲染字段输入
     */
    private static function renderFieldInput($field) {
        $fieldType = $field['field_type'];
        $fieldName = $field['field_name'];
        $fieldConfig = $field['field_config'] ?? array();
        $isRequired = $field['is_required'];
        
        $attributes = array(
            'name' => $fieldName,
            'id' => 'field_' . $fieldName,
            'class' => 'uform-input uform-' . $fieldType
        );
        
        if ($isRequired) {
            $attributes['required'] = 'required';
        }
        
        if (!empty($fieldConfig['placeholder'])) {
            $attributes['placeholder'] = $fieldConfig['placeholder'];
        }
        
        $defaultValue = $fieldConfig['defaultValue'] ?? '';
        
        switch ($fieldType) {
            case 'text':
            case 'email':
            case 'url':
            case 'tel':
            case 'password':
                if (!empty($fieldConfig['maxLength'])) {
                    $attributes['maxlength'] = intval($fieldConfig['maxLength']);
                }
                if (!empty($fieldConfig['pattern'])) {
                    $attributes['pattern'] = $fieldConfig['pattern'];
                }
                $attributes['value'] = htmlspecialchars($defaultValue);
                return '<input type="' . $fieldType . '"' . self::buildAttributes($attributes) . '>';
                
            case 'number':
                if (isset($fieldConfig['min'])) {
                    $attributes['min'] = $fieldConfig['min'];
                }
                if (isset($fieldConfig['max'])) {
                    $attributes['max'] = $fieldConfig['max'];
                }
                if (!empty($fieldConfig['step'])) {
                    $attributes['step'] = $fieldConfig['step'];
                }
                $attributes['value'] = htmlspecialchars($defaultValue);
                return '<input type="number"' . self::buildAttributes($attributes) . '>';
                
            case 'range':
                $min = $fieldConfig['min'] ?? 0;
                $max = $fieldConfig['max'] ?? 100;
                $step = $fieldConfig['step'] ?? 1;
                $value = $defaultValue ?: $min;
                
                $attributes['min'] = $min;
                $attributes['max'] = $max;
                $attributes['step'] = $step;
                $attributes['value'] = $value;
                $attributes['class'] = 'uform-range';
                
                $html = '<div class="range-container">';
                $html .= '<input type="range"' . self::buildAttributes($attributes) . '>';
                if (!empty($fieldConfig['showValue'])) {
                    $html .= '<output class="range-output" for="' . $attributes['id'] . '">' . $value . '</output>';
                }
                $html .= '</div>';
                return $html;
                
            case 'textarea':
                $rows = $fieldConfig['rows'] ?? 4;
                $attributes['rows'] = $rows;
                $attributes['class'] = 'uform-textarea';
                
                if (!empty($fieldConfig['maxLength'])) {
                    $attributes['maxlength'] = intval($fieldConfig['maxLength']);
                }
                
                return '<textarea' . self::buildAttributes($attributes) . '>' . htmlspecialchars($defaultValue) . '</textarea>';
                
            case 'select':
                $attributes['class'] = 'uform-select';
                if (!empty($fieldConfig['multiple'])) {
                    $attributes['multiple'] = 'multiple';
                    $attributes['name'] = $fieldName . '[]';
                }
                
                $html = '<select' . self::buildAttributes($attributes) . '>';
                
                if (empty($fieldConfig['multiple']) && !$isRequired) {
                    $placeholder = $fieldConfig['placeholder'] ?? '请选择';
                    $html .= '<option value="">' . htmlspecialchars($placeholder) . '</option>';
                }
                
                if (!empty($fieldConfig['options'])) {
                    foreach ($fieldConfig['options'] as $option) {
                        $optionValue = $option['value'] ?? $option;
                        $optionLabel = $option['label'] ?? $option;
                        $selected = ($optionValue === $defaultValue) ? ' selected' : '';
                        $html .= '<option value="' . htmlspecialchars($optionValue) . '"' . $selected . '>';
                        $html .= htmlspecialchars($optionLabel);
                        $html .= '</option>';
                    }
                }
                
                $html .= '</select>';
                return $html;
                
            case 'radio':
                return self::renderRadioField($fieldName, $fieldConfig, $defaultValue);
                
            case 'checkbox':
                return self::renderCheckboxField($fieldName, $fieldConfig, $defaultValue);
                
            case 'file':
                $attributes['type'] = 'file';
                $attributes['class'] = 'uform-file';
                
                if (!empty($fieldConfig['accept'])) {
                    $attributes['accept'] = $fieldConfig['accept'];
                }
                
                if (!empty($fieldConfig['multiple'])) {
                    $attributes['multiple'] = 'multiple';
                    $attributes['name'] = $fieldName . '[]';
                }
                
                $html = '<input' . self::buildAttributes($attributes) . '>';
                
                // 文件上传提示
                if (!empty($fieldConfig['maxSize']) || !empty($fieldConfig['accept'])) {
                    $html .= '<div class="file-help">';
                    if (!empty($fieldConfig['maxSize'])) {
                        $html .= '最大文件大小: ' . self::formatFileSize($fieldConfig['maxSize'] * 1024 * 1024) . ' ';
                    }
                    if (!empty($fieldConfig['accept'])) {
                        $html .= '支持格式: ' . htmlspecialchars($fieldConfig['accept']);
                    }
                    $html .= '</div>';
                }
                
                return $html;
                
            case 'date':
                $attributes['type'] = 'date';
                $attributes['value'] = htmlspecialchars($defaultValue);
                if (!empty($fieldConfig['minDate'])) {
                    $attributes['min'] = $fieldConfig['minDate'];
                }
                if (!empty($fieldConfig['maxDate'])) {
                    $attributes['max'] = $fieldConfig['maxDate'];
                }
                return '<input' . self::buildAttributes($attributes) . '>';
                
            case 'time':
                $attributes['type'] = 'time';
                $attributes['value'] = htmlspecialchars($defaultValue);
                return '<input' . self::buildAttributes($attributes) . '>';
                
            case 'datetime':
                $attributes['type'] = 'datetime-local';
                $attributes['value'] = htmlspecialchars($defaultValue);
                if (!empty($fieldConfig['minDate'])) {
                    $attributes['min'] = $fieldConfig['minDate'];
                }
                if (!empty($fieldConfig['maxDate'])) {
                    $attributes['max'] = $fieldConfig['maxDate'];
                }
                return '<input' . self::buildAttributes($attributes) . '>';
                
            case 'color':
                $attributes['type'] = 'color';
                $attributes['value'] = $defaultValue ?: '#000000';
                return '<input' . self::buildAttributes($attributes) . '>';
                
            case 'hidden':
                return '<input type="hidden" name="' . $fieldName . '" value="' . htmlspecialchars($fieldConfig['value'] ?? '') . '">';
                
            case 'rating':
                return self::renderRatingField($fieldName, $fieldConfig, $defaultValue);
                
            case 'signature':
                return self::renderSignatureField($fieldName, $fieldConfig);
                
            case 'tags':
                return self::renderTagsField($fieldName, $fieldConfig, $defaultValue);
                
            case 'calendar':
                return self::renderCalendarField($fieldName, $fieldConfig);
                
            case 'cascade':
                return self::renderCascadeField($fieldName, $fieldConfig);
                
            // 系统字段
            case 'user_name':
            case 'user_email':
            case 'page_url':
            case 'page_title':
            case 'timestamp':
                $value = self::getSystemFieldValue($fieldType, $fieldConfig);
                $attributes['value'] = htmlspecialchars($value);
                $attributes['readonly'] = 'readonly';
                $attributes['class'] .= ' system-field';
                return '<input type="text"' . self::buildAttributes($attributes) . '>';
                
            default:
                return '<div class="unsupported-field">不支持的字段类型: ' . htmlspecialchars($fieldType) . '</div>';
        }
    }
    
    /**
     * 渲染特殊字段（标题、段落等）
     */
    private static function renderSpecialField($field) {
        $fieldType = $field['field_type'];
        $fieldConfig = $field['field_config'] ?? array();
        
        switch ($fieldType) {
            case 'heading':
                $level = $fieldConfig['level'] ?? 'h3';
                $text = $fieldConfig['text'] ?? '标题文本';
                $align = $fieldConfig['align'] ?? 'left';
                $color = $fieldConfig['color'] ?? '#333333';
                
                return '<' . $level . ' class="uform-heading" style="text-align: ' . $align . '; color: ' . $color . ';">' 
                       . htmlspecialchars($text) . '</' . $level . '>';
                
            case 'paragraph':
                $text = $fieldConfig['text'] ?? '段落文本';
                $align = $fieldConfig['align'] ?? 'left';
                $color = $fieldConfig['color'] ?? '#666666';
                
                return '<p class="uform-paragraph" style="text-align: ' . $align . '; color: ' . $color . ';">' 
                       . nl2br(htmlspecialchars($text)) . '</p>';
                
            case 'divider':
                $style = $fieldConfig['style'] ?? 'solid';
                $color = $fieldConfig['color'] ?? '#dddddd';
                $thickness = $fieldConfig['thickness'] ?? 1;
                $marginTop = $fieldConfig['marginTop'] ?? 20;
                $marginBottom = $fieldConfig['marginBottom'] ?? 20;
                
                return '<hr class="uform-divider" style="border: none; border-top: ' . $thickness . 'px ' . $style . ' ' . $color . '; margin: ' . $marginTop . 'px 0 ' . $marginBottom . 'px 0;">';
                
            case 'html':
                $content = $fieldConfig['content'] ?? '';
                return '<div class="uform-html">' . $content . '</div>';
                
            default:
                return '';
        }
    }
    
    /**
     * 渲染单选字段
     */
    private static function renderRadioField($fieldName, $config, $defaultValue = '') {
        $html = '<div class="uform-radio-group">';
        
        if (!empty($config['options'])) {
            foreach ($config['options'] as $index => $option) {
                $optionValue = $option['value'] ?? $option;
                $optionLabel = $option['label'] ?? $option;
                $checked = ($optionValue === $defaultValue) ? ' checked' : '';
                
                $html .= '<label class="uform-radio-label">';
                $html .= '<input type="radio" name="' . $fieldName . '" value="' . htmlspecialchars($optionValue) . '"' . $checked . '>';
                $html .= '<span class="radio-text">' . htmlspecialchars($optionLabel) . '</span>';
                $html .= '</label>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * 渲染复选字段
     */
    private static function renderCheckboxField($fieldName, $config, $defaultValue = array()) {
        $html = '<div class="uform-checkbox-group">';
        
        if (!is_array($defaultValue)) {
            $defaultValue = array();
        }
        
        if (!empty($config['options'])) {
            foreach ($config['options'] as $index => $option) {
                $optionValue = $option['value'] ?? $option;
                $optionLabel = $option['label'] ?? $option;
                $checked = in_array($optionValue, $defaultValue) ? ' checked' : '';
                
                $html .= '<label class="uform-checkbox-label">';
                $html .= '<input type="checkbox" name="' . $fieldName . '[]" value="' . htmlspecialchars($optionValue) . '"' . $checked . '>';
                $html .= '<span class="checkbox-text">' . htmlspecialchars($optionLabel) . '</span>';
                $html .= '</label>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * 渲染评分字段
     */
    private static function renderRatingField($fieldName, $config, $defaultValue = '') {
        $max = $config['max'] ?? 5;
        $icon = $config['icon'] ?? 'star';
        $allowHalf = !empty($config['allowHalf']);
        
        $html = '<div class="uform-rating-group" data-max="' . $max . '" data-allow-half="' . ($allowHalf ? '1' : '0') . '">';
        
        for ($i = 1; $i <= $max; $i++) {
            $html .= '<span class="rating-star" data-value="' . $i . '">★</span>';
        }
        
        $html .= '<input type="hidden" name="' . $fieldName . '" value="' . htmlspecialchars($defaultValue) . '">';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 渲染签名字段
     */
    private static function renderSignatureField($fieldName, $config) {
        $width = $config['canvasWidth'] ?? 400;
        $height = $config['canvasHeight'] ?? 200;
        
        $html = '<div class="uform-signature-container">';
        $html .= '<canvas class="signature-canvas" width="' . $width . '" height="' . $height . '"></canvas>';
        $html .= '<div class="signature-controls">';
        $html .= '<button type="button" class="signature-clear">清除</button>';
        $html .= '</div>';
        $html .= '<input type="hidden" name="' . $fieldName . '" class="signature-data">';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 渲染标签字段
     */
    private static function renderTagsField($fieldName, $config, $defaultValue = '') {
        $maxTags = $config['maxTags'] ?? null;
        $allowCustom = !empty($config['allowCustom']);
        $suggestions = $config['suggestions'] ?? array();
        $delimiter = $config['delimiter'] ?? ',';
        
        $html = '<div class="uform-tags-container" data-max-tags="' . ($maxTags ?: '') . '" data-allow-custom="' . ($allowCustom ? '1' : '0') . '" data-delimiter="' . htmlspecialchars($delimiter) . '">';
        $html .= '<div class="tags-input-container">';
        $html .= '<input type="text" class="tags-input" placeholder="输入标签...">';
        $html .= '</div>';
        $html .= '<div class="tags-display"></div>';
        $html .= '<input type="hidden" name="' . $fieldName . '" value="' . htmlspecialchars($defaultValue) . '">';
        
        if (!empty($suggestions)) {
            $html .= '<div class="tags-suggestions" style="display: none;">';
            foreach ($suggestions as $suggestion) {
                $html .= '<span class="tag-suggestion">' . htmlspecialchars($suggestion) . '</span>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 渲染日历字段
     */
    private static function renderCalendarField($fieldName, $config) {
        $html = '<div class="uform-calendar-container">';
        $html .= '<div class="calendar-widget" id="calendar_' . $fieldName . '"></div>';
        $html .= '<input type="hidden" name="' . $fieldName . '" class="calendar-value">';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 渲染级联字段
     */
    private static function renderCascadeField($fieldName, $config) {
        $levels = $config['levels'] ?? array();
        
        $html = '<div class="uform-cascade-container">';
        
        foreach ($levels as $index => $level) {
            $html .= '<select class="cascade-select" data-level="' . $index . '" data-placeholder="' . htmlspecialchars($level['placeholder']) . '">';
            $html .= '<option value="">' . htmlspecialchars($level['placeholder']) . '</option>';
            $html .= '</select>';
        }
        
        $html .= '<input type="hidden" name="' . $fieldName . '" class="cascade-value">';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 渲染验证码字段
     */
    private static function renderCaptchaField() {
        $html = '<div class="uform-field uform-field-captcha">';
        $html .= '<label class="uform-label">验证码 <span class="required-mark">*</span></label>';
        $html .= '<div class="captcha-container">';
        $html .= '<img src="' . Helper::options()->siteUrl . 'uforms/api/captcha" class="captcha-image" alt="验证码">';
        $html .= '<input type="text" name="captcha" class="uform-input captcha-input" placeholder="请输入验证码" required>';
        $html .= '<button type="button" class="captcha-refresh" title="刷新验证码">刷新</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 获取系统字段值
     */
    private static function getSystemFieldValue($fieldType, $config) {
        $user = Typecho_Widget::widget('Widget_User');
        
        switch ($fieldType) {
            case 'user_name':
                return $user->hasLogin() ? $user->screenName : '';
                
            case 'user_email':
                return $user->hasLogin() ? $user->mail : '';
                
            case 'page_url':
                return $_SERVER['REQUEST_URI'] ?? '';
                
            case 'page_title':
                return Helper::options()->title ?? '';
                
            case 'timestamp':
                $format = $config['format'] ?? 'Y-m-d H:i:s';
                return date($format);
                
            default:
                return '';
        }
    }
    
    /**
     * 构建HTML属性字符串
     */
    private static function buildAttributes($attributes) {
        $html = '';
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== false) {
                if ($value === true) {
                    $html .= ' ' . $key;
                } else {
                    $html .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
                }
            }
        }
        return $html;
    }
    
    /**
     * 检查是否有必填字段
     */
    private static function hasRequiredFields($fields) {
        foreach ($fields as $field) {
            if ($field['is_required']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 格式化文件大小
     */
    private static function formatFileSize($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * 加载资源文件
     */
    private static function enqueueAssets() {
        if (self::$scriptsEnqueued) {
            return;
        }
        
        $pluginUrl = Helper::options()->pluginUrl . '/Uforms';
        
        // 添加样式到页面头部
        if (!self::$stylesEnqueued) {
            self::addToHead('<link rel="stylesheet" href="' . $pluginUrl . '/assets/css/uforms.css">');
            self::addToHead('<link rel="stylesheet" href="' . $pluginUrl . '/assets/css/frontend.css">');
            self::$stylesEnqueued = true;
        }
        
        // 添加脚本到页面底部
        self::addToFooter('<script src="' . $pluginUrl . '/assets/js/uforms.js"></script>');
        self::addToFooter('<script src="' . $pluginUrl . '/assets/js/frontend.js"></script>');
        
        self::$scriptsEnqueued = true;
    }
    
    /**
     * 生成表单脚本
     */
    private static function generateFormScript($form, $options) {
        $config = array(
            'formId' => $form['id'],
            'formName' => $form['name'],
            'ajaxSubmit' => $options['ajax_submit'],
            'ajaxUrl' => Helper::options()->siteUrl . 'uforms/api/',
            'validation' => true,
            'messages' => array(
                'required' => '此字段为必填项',
                'email' => '请输入有效的邮箱地址',
                'url' => '请输入有效的网址',
                'number' => '请输入有效的数字',
                'minLength' => '输入长度不能少于 {0} 个字符',
                'maxLength' => '输入长度不能超过 {0} 个字符',
                'submitError' => '提交失败，请重试'
            )
        );
        
        $script = '<script>';
        $script .= 'document.addEventListener("DOMContentLoaded", function() {';
        $script .= 'if (typeof UformsInit !== "undefined") {';
        $script .= 'UformsInit(' . json_encode($config) . ');';
        $script .= '}';
        $script .= '});';
        $script .= '</script>';
        
        return $script;
    }
    
    /**
     * 添加内容到页面头部
     */
    private static function addToHead($content) {
        if (!defined('__UFORMS_HEAD_CONTENT__')) {
            define('__UFORMS_HEAD_CONTENT__', array());
        }
        
        global $__UFORMS_HEAD_CONTENT__;
        if (!is_array($__UFORMS_HEAD_CONTENT__)) {
            $__UFORMS_HEAD_CONTENT__ = array();
        }
        $__UFORMS_HEAD_CONTENT__[] = $content;
    }
    
    /**
     * 添加内容到页面底部
     */
    private static function addToFooter($content) {
        if (!defined('__UFORMS_FOOTER_CONTENT__')) {
            define('__UFORMS_FOOTER_CONTENT__', array());
        }
        
        global $__UFORMS_FOOTER_CONTENT__;
        if (!is_array($__UFORMS_FOOTER_CONTENT__)) {
            $__UFORMS_FOOTER_CONTENT__ = array();
        }
        $__UFORMS_FOOTER_CONTENT__ = array();
        $__UFORMS_FOOTER_CONTENT__[] = $content;
    }
    
    /**
     * 输出头部内容
     */
    public static function outputHeadContent() {
        global $__UFORMS_HEAD_CONTENT__;
        if (is_array($__UFORMS_HEAD_CONTENT__)) {
            foreach ($__UFORMS_HEAD_CONTENT__ as $content) {
                echo $content . "\n";
            }
        }
    }
    
    /**
     * 输出底部内容
     */
    public static function outputFooterContent() {
        global $__UFORMS_FOOTER_CONTENT__;
        if (is_array($__UFORMS_FOOTER_CONTENT__)) {
            foreach ($__UFORMS_FOOTER_CONTENT__ as $content) {
                echo $content . "\n";
            }
        }
    }
}

/**
 * 简化的表单渲染函数
 */
function uforms_render($formId = null, $formName = null, $options = array()) {
    return UformsFrontend::renderForm($formId, $formName, 'default', $options);
}

/**
 * 短代码解析函数
 */
function uforms_shortcode($attributes, $content = '') {
    $defaults = array(
        'id' => null,
        'name' => null,
        'template' => 'default',
        'show_title' => true,
        'show_description' => true,
        'ajax' => true,
        'class' => ''
    );
    
    $attributes = array_merge($defaults, $attributes);
    
    $options = array(
        'show_title' => $attributes['show_title'] !== 'false',
        'show_description' => $attributes['show_description'] !== 'false',
        'ajax_submit' => $attributes['ajax'] !== 'false',
        'css_class' => $attributes['class']
    );
    
    return UformsFrontend::renderForm($attributes['id'], $attributes['name'], $attributes['template'], $options);
}
