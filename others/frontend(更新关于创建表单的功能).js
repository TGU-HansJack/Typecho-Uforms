/**
 * Uforms 前端JavaScript - 完整版本
 */

(function() {
    'use strict';
    
    // 全局配置
    window.UformsConfig = window.UformsConfig || {};
    
    /**
     * Uforms 前端类
     */
    class UformsFrontend {
        constructor() {
            this.forms = new Map();
            this.validators = new Map();
            this.initialized = false;
            
            this.defaultMessages = {
                required: '此字段为必填项',
                email: '请输入有效的邮箱地址',
                url: '请输入有效的网址',
                number: '请输入有效的数字',
                tel: '请输入有效的电话号码',
                minLength: '输入长度不能少于 {0} 个字符',
                maxLength: '输入长度不能超过 {0} 个字符',
                min: '数值不能小于 {0}',
                max: '数值不能大于 {0}',
                pattern: '输入格式不正确',
                fileSize: '文件大小不能超过 {0}',
                fileType: '不支持的文件类型',
                submitError: '提交失败，请重试',
                networkError: '网络连接失败，请检查网络设置',
                serverError: '服务器错误，请稍后重试'
            };
        }
        
        /**
         * 初始化
         */
        init(config = {}) {
            if (this.initialized) return;
            
            this.config = Object.assign({
                ajaxUrl: '/uforms/api/',
                validation: true,
                autoValidate: true,
                scrollToError: true,
                showLoading: true,
                messages: {}
            }, config);
            
            this.messages = Object.assign({}, this.defaultMessages, this.config.messages);
            
            this.bindEvents();
            this.initializeForms();
            this.initialized = true;
        }
        
        /**
         * 绑定全局事件
         */
        bindEvents() {
            // 表单提交事件
            document.addEventListener('submit', (e) => {
                if (e.target.classList.contains('uform-form')) {
                    this.handleFormSubmit(e);
                }
            });
            
            // 实时验证
            if (this.config.autoValidate) {
                document.addEventListener('blur', (e) => {
                    if (e.target.classList.contains('uform-input') || 
                        e.target.classList.contains('uform-textarea') || 
                        e.target.classList.contains('uform-select')) {
                        this.validateField(e.target);
                    }
                }, true);
                
                document.addEventListener('change', (e) => {
                    if (e.target.type === 'radio' || e.target.type === 'checkbox' || e.target.tagName === 'SELECT') {
                        if (e.target.form && e.target.form.classList.contains('uform-form')) {
                            this.validateField(e.target);
                        }
                    }
                });
            }
            
            // 文件上传事件
            document.addEventListener('change', (e) => {
                if (e.target.type === 'file' && e.target.classList.contains('uform-file')) {
                    this.handleFileChange(e.target);
                }
            });
            
            // 范围滑块事件
            document.addEventListener('input', (e) => {
                if (e.target.type === 'range') {
                    this.updateRangeOutput(e.target);
                }
            });
            
            // 验证码刷新
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('captcha-refresh')) {
                    e.preventDefault();
                    this.refreshCaptcha(e.target);
                }
            });
            
            // 评分字段
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('rating-star')) {
                    this.handleRatingClick(e.target);
                }
            });
            
            // 签名板初始化
            this.initializeSignaturePads();
            
            // 标签输入
            this.initializeTagsInputs();
            
            // 级联选择
            this.initializeCascadeSelects();
        }
        
        /**
         * 初始化表单
         */
        initializeForms() {
            document.querySelectorAll('.uform').forEach(form => {
                this.initializeForm(form);
            });
        }
        
        /**
         * 初始化单个表单
         */
        initializeForm(formElement) {
            const formId = formElement.dataset.formId;
            const formName = formElement.dataset.formName;
            
            if (!formId && !formName) return;
            
            const formInfo = {
                element: formElement,
                form: formElement.querySelector('.uform-form'),
                id: formId,
                name: formName,
                isAjax: formElement.classList.contains('uform-ajax'),
                fields: new Map()
            };
            
            // 收集字段信息
            formElement.querySelectorAll('.uform-field').forEach(fieldElement => {
                const input = this.getFieldInput(fieldElement);
                if (input) {
                    const fieldInfo = {
                        element: fieldElement,
                        input: input,
                        name: input.name || input.id,
                        type: this.getFieldType(input),
                        required: input.hasAttribute('required'),
                        rules: this.extractValidationRules(input)
                    };
                    
                    formInfo.fields.set(fieldInfo.name, fieldInfo);
                }
            });
            
            this.forms.set(formId || formName, formInfo);
            
            // 初始化特殊字段
            this.initializeSpecialFields(formElement);
        }
        
        /**
         * 处理表单提交
         */
        async handleFormSubmit(e) {
            e.preventDefault();
            
            const form = e.target;
            const formContainer = form.closest('.uform');
            const formKey = formContainer.dataset.formId || formContainer.dataset.formName;
            const formInfo = this.forms.get(formKey);
            
            if (!formInfo) return;
            
            try {
                // 清除之前的错误信息
                this.clearErrors(formContainer);
                
                // 验证表单
                if (this.config.validation) {
                    const validationResult = this.validateForm(formInfo);
                    if (!validationResult.isValid) {
                        this.showValidationErrors(formContainer, validationResult.errors);
                        return;
                    }
                }
                
                // 显示加载状态
                this.setSubmitLoading(form, true);
                
                if (formInfo.isAjax) {
                    // AJAX提交
                    await this.submitFormAjax(form, formInfo);
                } else {
                    // 普通提交
                    form.submit();
                }
                
            } catch (error) {
                console.error('Form submit error:', error);
                this.showError(formContainer, this.messages.submitError);
            } finally {
                this.setSubmitLoading(form, false);
            }
        }
        
        /**
         * AJAX表单提交
         */
        async submitFormAjax(form, formInfo) {
            const formData = new FormData(form);
            const formContainer = formInfo.element;
            
            try {
                const response = await fetch(this.config.ajaxUrl + 'submit', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.handleSubmitSuccess(formContainer, result);
                } else {
                    this.handleSubmitError(formContainer, result);
                }
                
            } catch (error) {
                console.error('AJAX submit error:', error);
                
                if (error.name === 'TypeError' && error.message.includes('fetch')) {
                    this.showError(formContainer, this.messages.networkError);
                } else {
                    this.showError(formContainer, this.messages.serverError);
                }
            }
        }
        
        /**
         * 处理提交成功
         */
        handleSubmitSuccess(formContainer, result) {
            const form = formContainer.querySelector('.uform-form');
            const successContainer = formContainer.querySelector('.uform-success');
            
            switch (result.action) {
                case 'redirect':
                    if (result.redirect_url) {
                        window.location.href = result.redirect_url;
                        return;
                    }
                    break;
                    
                case 'refresh':
                    window.location.reload();
                    return;
                    
                case 'block':
                    if (result.success_block) {
                        this.showCustomSuccess(formContainer, result.success_block);
                        return;
                    }
                    break;
            }
            
            // 默认显示成功消息
            this.showSuccess(formContainer, result.message);
            
            // 隐藏表单，显示成功信息
            form.style.display = 'none';
            if (successContainer) {
                successContainer.style.display = 'block';
            }
            
            // 触发自定义事件
            this.triggerEvent(formContainer, 'uforms:success', result);
        }
        
        /**
         * 处理提交错误
         */
        handleSubmitError(formContainer, result) {
            if (result.errors && typeof result.errors === 'object') {
                // 显示字段级错误
                this.showValidationErrors(formContainer, result.errors);
            } else {
                // 显示通用错误
                this.showError(formContainer, result.message || this.messages.submitError);
            }
            
            this.triggerEvent(formContainer, 'uforms:error', result);
        }
        
        /**
         * 验证表单
         */
        validateForm(formInfo) {
            const errors = {};
            let hasErrors = false;
            
            formInfo.fields.forEach((field, fieldName) => {
                const fieldErrors = this.validateField(field.input, field.rules);
                if (fieldErrors.length > 0) {
                    errors[fieldName] = fieldErrors;
                    hasErrors = true;
                }
            });
            
            return {
                isValid: !hasErrors,
                errors: errors
            };
        }
        
        /**
         * 验证单个字段
         */
        validateField(input, rules = null) {
            if (!input) return [];
            
            const errors = [];
            const value = this.getFieldValue(input);
            const fieldRules = rules || this.extractValidationRules(input);
            
            // 必填验证
            if (fieldRules.required && this.isEmpty(value)) {
                errors.push(this.messages.required);
                this.showFieldError(input, this.messages.required);
                return errors;
            }
            
            // 如果值为空且非必填，跳过其他验证
            if (this.isEmpty(value)) {
                this.clearFieldError(input);
                return errors;
            }
            
            // 类型验证
            if (fieldRules.type) {
                const typeError = this.validateFieldType(value, fieldRules.type);
                if (typeError) {
                    errors.push(typeError);
                }
            }
            
            // 长度验证
            if (fieldRules.minLength && value.length < fieldRules.minLength) {
                errors.push(this.messages.minLength.replace('{0}', fieldRules.minLength));
            }
            
            if (fieldRules.maxLength && value.length > fieldRules.maxLength) {
                errors.push(this.messages.maxLength.replace('{0}', fieldRules.maxLength));
            }
            
            // 数值范围验证
            if (fieldRules.min !== undefined && parseFloat(value) < fieldRules.min) {
                errors.push(this.messages.min.replace('{0}', fieldRules.min));
            }
            
            if (fieldRules.max !== undefined && parseFloat(value) > fieldRules.max) {
                errors.push(this.messages.max.replace('{0}', fieldRules.max));
            }
            
            // 正则表达式验证
            if (fieldRules.pattern && !new RegExp(fieldRules.pattern).test(value)) {
                errors.push(fieldRules.patternMessage || this.messages.pattern);
            }
            
            // 文件验证
            if (input.type === 'file' && input.files.length > 0) {
                const fileErrors = this.validateFiles(input.files, fieldRules);
                errors.push(...fileErrors);
            }
            
            // 显示或清除错误
            if (errors.length > 0) {
                this.showFieldError(input, errors[0]);
            } else {
                this.clearFieldError(input);
            }
            
            return errors;
        }
        
        /**
         * 类型验证
         */
        validateFieldType(value, type) {
            switch (type) {
                case 'email':
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    return emailRegex.test(value) ? null : this.messages.email;
                    
                case 'url':
                    try {
                        new URL(value);
                        return null;
                    } catch {
                        return this.messages.url;
                    }
                    
                case 'number':
                    return isNaN(parseFloat(value)) ? this.messages.number : null;
                    
                case 'tel':
                    const telRegex = /^[0-9\-\+\s\(\)]+$/;
                    return telRegex.test(value) ? null : this.messages.tel;
                    
                default:
                    return null;
            }
        }
        
        /**
         * 文件验证
         */
        validateFiles(files, rules) {
            const errors = [];
            
            for (let file of files) {
                // 文件大小验证
                if (rules.maxSize && file.size > rules.maxSize * 1024 * 1024) {
                    errors.push(this.messages.fileSize.replace('{0}', this.formatFileSize(rules.maxSize * 1024 * 1024)));
                }
                
                // 文件类型验证
                if (rules.allowedTypes && rules.allowedTypes.length > 0) {
                    const fileExtension = file.name.split('.').pop().toLowerCase();
                    if (!rules.allowedTypes.includes(fileExtension)) {
                        errors.push(this.messages.fileType);
                    }
                }
            }
            
            return errors;
        }
        
        /**
         * 提取验证规则
         */
        extractValidationRules(input) {
            const rules = {
                required: input.hasAttribute('required'),
                type: input.type,
                minLength: input.getAttribute('minlength'),
                maxLength: input.getAttribute('maxlength'),
                min: input.getAttribute('min'),
                max: input.getAttribute('max'),
                pattern: input.getAttribute('pattern'),
                patternMessage: input.getAttribute('data-pattern-message')
            };
            
            // 文件上传规则
            if (input.type === 'file') {
                const accept = input.getAttribute('accept');
                if (accept) {
                    rules.allowedTypes = accept.split(',').map(type => {
                        if (type.startsWith('.')) {
                            return type.substring(1).toLowerCase();
                        }
                        return type.split('/')[1] || type;
                    });
                }
                
                const maxSize = input.getAttribute('data-max-size');
                if (maxSize) {
                    rules.maxSize = parseInt(maxSize);
                }
            }
            
            return rules;
        }
        
        /**
         * 获取字段值
         */
        getFieldValue(input) {
            switch (input.type) {
                case 'checkbox':
                    if (input.name.endsWith('[]')) {
                        // 多选框组
                        const checkboxes = document.querySelectorAll(`input[name="${input.name}"]`);
                        const values = [];
                        checkboxes.forEach(cb => {
                            if (cb.checked) values.push(cb.value);
                        });
                        return values;
                    } else {
                        return input.checked ? input.value : '';
                    }
                    
                case 'radio':
                    const radioGroup = document.querySelectorAll(`input[name="${input.name}"]`);
                    for (let radio of radioGroup) {
                        if (radio.checked) return radio.value;
                    }
                    return '';
                    
                case 'file':
                    return input.files.length > 0 ? input.files : null;
                    
                default:
                    return input.value || '';
            }
        }
        
        /**
         * 检查值是否为空
         */
        isEmpty(value) {
            if (value === null || value === undefined || value === '') return true;
            if (Array.isArray(value)) return value.length === 0;
            if (typeof value === 'string') return value.trim() === '';
            return false;
        }
        
        /**
         * 获取字段输入元素
         */
        getFieldInput(fieldElement) {
            return fieldElement.querySelector('input, textarea, select');
        }
        
        /**
         * 获取字段类型
         */
        getFieldType(input) {
            if (input.tagName === 'SELECT') return 'select';
            if (input.tagName === 'TEXTAREA') return 'textarea';
            return input.type || 'text';
        }
        
        /**
         * 显示字段错误
         */
        showFieldError(input, message) {
            const fieldElement = input.closest('.uform-field');
            if (!fieldElement) return;
            
            fieldElement.classList.add('has-error');
            
            let errorElement = fieldElement.querySelector('.uform-field-error');
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }
        
        /**
         * 清除字段错误
         */
        clearFieldError(input) {
            const fieldElement = input.closest('.uform-field');
            if (!fieldElement) return;
            
            fieldElement.classList.remove('has-error');
            
            const errorElement = fieldElement.querySelector('.uform-field-error');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        }
        
        /**
         * 显示验证错误
         */
        showValidationErrors(formContainer, errors) {
            Object.keys(errors).forEach(fieldName => {
                const fieldElement = formContainer.querySelector(`[name="${fieldName}"], [name="${fieldName}[]"]`);
                if (fieldElement) {
                    this.showFieldError(fieldElement, errors[fieldName][0] || errors[fieldName]);
                }
            });
            
            // 滚动到第一个错误字段
            if (this.config.scrollToError) {
                const firstError = formContainer.querySelector('.has-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        }
        
        /**
         * 清除所有错误
         */
        clearErrors(formContainer) {
            formContainer.querySelectorAll('.has-error').forEach(element => {
                element.classList.remove('has-error');
            });
            
            formContainer.querySelectorAll('.uform-field-error').forEach(element => {
                element.style.display = 'none';
            });
            
            this.hideMessages(formContainer);
        }
        
        /**
         * 显示成功消息
         */
        showSuccess(formContainer, message) {
            this.showMessage(formContainer, message, 'success');
        }
        
        /**
         * 显示错误消息
         */
        showError(formContainer, message) {
            this.showMessage(formContainer, message, 'error');
        }
        
        /**
         * 显示消息
         */
        showMessage(formContainer, message, type = 'info') {
            const messagesContainer = formContainer.querySelector('.uform-messages');
            if (!messagesContainer) return;
            
            messagesContainer.innerHTML = `<div class="uform-message uform-message-${type}">${message}</div>`;
            messagesContainer.style.display = 'block';
            
            // 滚动到消息
            if (this.config.scrollToError || type === 'success') {
                messagesContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // 自动隐藏成功消息
            if (type === 'success') {
                setTimeout(() => {
                    this.hideMessages(formContainer);
                }, 5000);
            }
        }
        
        /**
         * 显示自定义成功内容
         */
        showCustomSuccess(formContainer, content) {
            const form = formContainer.querySelector('.uform-form');
            const successDiv = document.createElement('div');
            successDiv.className = 'uform-custom-success';
            successDiv.innerHTML = content;
            
            form.style.display = 'none';
            formContainer.appendChild(successDiv);
        }
        
        /**
         * 隐藏消息
         */
        hideMessages(formContainer) {
            const messagesContainer = formContainer.querySelector('.uform-messages');
            if (messagesContainer) {
                messagesContainer.style.display = 'none';
            }
        }
        
        /**
         * 设置提交按钮加载状态
         */
        setSubmitLoading(form, loading) {
            const submitButton = form.querySelector('.uform-submit');
            const submitText = submitButton.querySelector('.submit-text');
            const submitLoading = submitButton.querySelector('.submit-loading');
            
            if (loading) {
                submitButton.disabled = true;
                if (submitText) submitText.style.display = 'none';
                if (submitLoading) submitLoading.style.display = 'inline';
            } else {
                submitButton.disabled = false;
                if (submitText) submitText.style.display = 'inline';
                if (submitLoading) submitLoading.style.display = 'none';
            }
        }
        
        /**
         * 处理文件选择变化
         */
        handleFileChange(input) {
            const fieldElement = input.closest('.uform-field');
            const rules = this.extractValidationRules(input);
            
            if (input.files.length > 0) {
                const errors = this.validateFiles(input.files, rules);
                if (errors.length > 0) {
                    this.showFieldError(input, errors[0]);
                    input.value = ''; // 清除无效文件
                } else {
                    this.clearFieldError(input);
                }
            }
        }
        
        /**
         * 更新范围滑块输出
         */
        updateRangeOutput(rangeInput) {
            const output = rangeInput.parentNode.querySelector('.range-output');
            if (output) {
                output.textContent = rangeInput.value;
            }
        }
        
        /**
         * 刷新验证码
         */
        refreshCaptcha(button) {
            const img = button.parentNode.querySelector('.captcha-image');
            if (img) {
                img.src = img.src + '?t=' + Date.now();
            }
        }
        
        /**
         * 处理评分点击
         */
        handleRatingClick(star) {
            const ratingGroup = star.parentNode;
            const hiddenInput = ratingGroup.querySelector('input[type="hidden"]');
            const value = star.dataset.value;
            
            // 更新视觉状态
            ratingGroup.querySelectorAll('.rating-star').forEach((s, index) => {
                if (index < value) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
            
            // 更新隐藏输入值
            if (hiddenInput) {
                hiddenInput.value = value;
            }
        }
        
        /**
         * 初始化签名板
         */
        initializeSignaturePads() {
            document.querySelectorAll('.signature-canvas').forEach(canvas => {
                this.initSignaturePad(canvas);
            });
        }
        
        /**
         * 初始化单个签名板
         */
        initSignaturePad(canvas) {
            const container = canvas.parentNode;
            const hiddenInput = container.querySelector('.signature-data');
            const clearButton = container.querySelector('.signature-clear');
            
            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            let lastX = 0;
            let lastY = 0;
            
            // 鼠标事件
            canvas.addEventListener('mousedown', (e) => {
                isDrawing = true;
                [lastX, lastY] = this.getMousePos(canvas, e);
            });
            
            canvas.addEventListener('mousemove', (e) => {
                if (!isDrawing) return;
                const [currentX, currentY] = this.getMousePos(canvas, e);
                
                ctx.beginPath();
                ctx.moveTo(lastX, lastY);
                ctx.lineTo(currentX, currentY);
                ctx.strokeStyle = '#000';
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.stroke();
                
                [lastX, lastY] = [currentX, currentY];
                
                // 更新隐藏输入
                if (hiddenInput) {
                    hiddenInput.value = canvas.toDataURL();
                }
            });
            
            canvas.addEventListener('mouseup', () => {
                isDrawing = false;
            });
            
            // 触摸事件
            canvas.addEventListener('touchstart', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousedown', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvas.dispatchEvent(mouseEvent);
            });
            
            canvas.addEventListener('touchmove', (e) => {
                e.preventDefault();
                const touch = e.touches[0];
                const mouseEvent = new MouseEvent('mousemove', {
                    clientX: touch.clientX,
                    clientY: touch.clientY
                });
                canvas.dispatchEvent(mouseEvent);
            });
            
            canvas.addEventListener('touchend', (e) => {
                e.preventDefault();
                const mouseEvent = new MouseEvent('mouseup', {});
                canvas.dispatchEvent(mouseEvent);
            });
            
            // 清除按钮
            if (clearButton) {
                clearButton.addEventListener('click', () => {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    if (hiddenInput) {
                        hiddenInput.value = '';
                    }
                });
            }
        }
        
        /**
         * 获取鼠标位置
         */
        getMousePos(canvas, e) {
            const rect = canvas.getBoundingClientRect();
            return [
                e.clientX - rect.left,
                e.clientY - rect.top
            ];
        }
        
        /**
         * 初始化标签输入
         */
        initializeTagsInputs() {
            document.querySelectorAll('.uform-tags-container').forEach(container => {
                this.initTagsInput(container);
            });
        }
        
        /**
         * 初始化单个标签输入
         */
        initTagsInput(container) {
            const input = container.querySelector('.tags-input');
            const display = container.querySelector('.tags-display');
            const hiddenInput = container.querySelector('input[type="hidden"]');
            const suggestions = container.querySelector('.tags-suggestions');
            
            const maxTags = parseInt(container.dataset.maxTags) || null;
            const allowCustom = container.dataset.allowCustom === '1';
            const delimiter = container.dataset.delimiter || ',';
            
            let tags = [];
            
            // 加载现有标签
            if (hiddenInput.value) {
                tags = hiddenInput.value.split(delimiter).map(tag => tag.trim()).filter(tag => tag);
                this.updateTagsDisplay(display, tags);
            }
            
            // 输入事件
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === delimiter) {
                    e.preventDefault();
                    this.addTag(input.value.trim());
                } else if (e.key === 'Backspace' && input.value === '' && tags.length > 0) {
                    this.removeTag(tags.length - 1);
                }
            });
            
            // 建议点击
            if (suggestions) {
                suggestions.addEventListener('click', (e) => {
                    if (e.target.classList.contains('tag-suggestion')) {
                        this.addTag(e.target.textContent);
                    }
                });
            }
            
            const addTag = (tagText) => {
                if (!tagText || tags.includes(tagText)) return;
                if (maxTags && tags.length >= maxTags) return;
                
                tags.push(tagText);
                this.updateTagsDisplay(display, tags);
                this.updateHiddenInput(hiddenInput, tags, delimiter);
                input.value = '';
            };
            
            const removeTag = (index) => {
                tags.splice(index, 1);
                this.updateTagsDisplay(display, tags);
                this.updateHiddenInput(hiddenInput, tags, delimiter);
            };
            
            container.addTag = addTag;
            container.removeTag = removeTag;
        }
        
        /**
         * 更新标签显示
         */
        updateTagsDisplay(display, tags) {
            display.innerHTML = '';
            tags.forEach((tag, index) => {
                const tagElement = document.createElement('span');
                tagElement.className = 'tag-item';
                tagElement.innerHTML = `${tag} <button type="button" class="tag-remove" data-index="${index}">×</button>`;
                display.appendChild(tagElement);
            });
            
            // 绑定删除事件
            display.addEventListener('click', (e) => {
                if (e.target.classList.contains('tag-remove')) {
                    const container = display.closest('.uform-tags-container');
                    const index = parseInt(e.target.dataset.index);
                    container.removeTag(index);
                }
            });
        }
        
        /**
         * 更新隐藏输入
         */
        updateHiddenInput(hiddenInput, tags, delimiter) {
            hiddenInput.value = tags.join(delimiter);
        }
        
        /**
         * 初始化级联选择
         */
        initializeCascadeSelects() {
            document.querySelectorAll('.uform-cascade-container').forEach(container => {
                this.initCascadeSelect(container);
            });
        }
        
        /**
         * 初始化单个级联选择
         */
        initCascadeSelect(container) {
            const selects = container.querySelectorAll('.cascade-select');
            const hiddenInput = container.querySelector('.cascade-value');
            
            // 绑定变化事件
            selects.forEach((select, level) => {
                select.addEventListener('change', () => {
                    this.handleCascadeChange(container, level);
                });
            });
        }
        
        /**
         * 处理级联选择变化
         */
        handleCascadeChange(container, changedLevel) {
            const selects = container.querySelectorAll('.cascade-select');
            const hiddenInput = container.querySelector('.cascade-value');
            
            // 清空后续级别
            for (let i = changedLevel + 1; i < selects.length; i++) {
                selects[i].innerHTML = `<option value="">${selects[i].dataset.placeholder}</option>`;
                selects[i].disabled = true;
            }
            
            // 加载下一级数据（这里需要根据实际数据源实现）
            const currentValue = selects[changedLevel].value;
            if (currentValue && changedLevel < selects.length - 1) {
                this.loadCascadeOptions(container, changedLevel + 1, currentValue);
            }
            
            // 更新隐藏输入
            const values = Array.from(selects).map(select => select.value).filter(value => value);
            hiddenInput.value = values.join(',');
        }
        
        /**
         * 加载级联选项（需要根据实际需求实现）
         */
        async loadCascadeOptions(container, level, parentValue) {
            // 这里应该根据实际数据源实现
            // 例如通过AJAX请求获取数据
            console.log('Loading cascade options for level', level, 'with parent', parentValue);
        }
        
        /**
         * 初始化特殊字段
         */
        initializeSpecialFields(formElement) {
            // 可以在这里初始化其他特殊字段
            // 如日历选择器、富文本编辑器等
        }
        
        /**
         * 格式化文件大小
         */
        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        /**
         * 触发自定义事件
         */
        triggerEvent(element, eventName, detail = {}) {
            const event = new CustomEvent(eventName, {
                detail: detail,
                bubbles: true,
                cancelable: true
            });
            element.dispatchEvent(event);
        }
    }
    
    // 全局实例
    window.UformsFrontend = new UformsFrontend();
    
    // 初始化函数
    window.UformsInit = function(config) {
        window.UformsFrontend.init(config);
    };
    
    // 自动初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.UformsFrontend.init(window.UformsConfig);
        });
    } else {
        window.UformsFrontend.init(window.UformsConfig);
    }
    
})();
