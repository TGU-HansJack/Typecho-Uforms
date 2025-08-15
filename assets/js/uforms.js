/**
 * Uforms 前端JavaScript库
 */
(function() {
    'use strict';
    
    window.UformsAjax = {
        init: function(formName) {
            this.formName = formName;
            this.bindEvents();
            this.initValidation();
            this.initProgress();
        },
        
        bindEvents: function() {
            const form = document.querySelector('.uform-' + this.formName + ' .uform-form');
            if (!form) return;
            
            form.addEventListener('submit', this.handleSubmit.bind(this));
            
            // 实时验证
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('blur', this.validateField.bind(this));
                input.addEventListener('input', this.updateProgress.bind(this));
            });
            
            // 文件上传预览
            const fileInputs = form.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', this.handleFilePreview.bind(this));
            });
            
            // 范围输入更新
            const rangeInputs = form.querySelectorAll('input[type="range"]');
            rangeInputs.forEach(input => {
                input.addEventListener('input', this.updateRangeOutput.bind(this));
            });
        },
        
        handleSubmit: function(e) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            const submitButton = form.querySelector('.uform-submit');
            
            // 验证表单
            if (!this.validateForm(form)) {
                return;
            }
            
            // 显示加载状态
            this.setLoadingState(form, true);
            submitButton.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess(form, data.message);
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    } else {
                        form.reset();
                        this.updateProgress();
                    }
                } else {
                    this.showErrors(form, data.errors || [data.message]);
                }
            })
            .catch(error => {
                console.error('Form submission error:', error);
                this.showErrors(form, ['提交失败，请重试']);
            })
            .finally(() => {
                this.setLoadingState(form, false);
                submitButton.disabled = false;
            });
        },
        
        validateField: function(e) {
            const field = e.target;
            const fieldContainer = field.closest('.uform-field');
            
            this.clearFieldError(fieldContainer);
            
            // 基本验证
            if (field.hasAttribute('required') && !field.value.trim()) {
                this.showFieldError(fieldContainer, '此字段为必填项');
                return false;
            }
            
            // 类型验证
            const fieldType = field.type;
            const value = field.value.trim();
            
            if (value && !this.validateFieldType(fieldType, value)) {
                this.showFieldError(fieldContainer, this.getValidationMessage(fieldType));
                return false;
            }
            
            // 异步验证
            if (field.dataset.asyncValidation) {
                this.performAsyncValidation(field);
            }
            
            this.showFieldSuccess(fieldContainer);
            return true;
        },
        
        validateFieldType: function(type, value) {
            const patterns = {
                email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                tel: /^[\d\s\-\+\(\)]+$/,
                url: /^https?:\/\/.+/
            };
            
            return !patterns[type] || patterns[type].test(value);
        },
        
        getValidationMessage: function(type) {
            const messages = {
                email: '请输入有效的邮箱地址',
                tel: '请输入有效的电话号码',
                url: '请输入有效的网址'
            };
            
            return messages[type] || '输入格式不正确';
        },
        
        validateForm: function(form) {
            const fields = form.querySelectorAll('input, textarea, select');
            let isValid = true;
            
            fields.forEach(field => {
                if (!this.validateField({target: field})) {
                    isValid = false;
                }
            });
            
            return isValid;
        },
        
        showFieldError: function(fieldContainer, message) {
            fieldContainer.classList.add('error');
            fieldContainer.classList.remove('success');
            
            let errorElement = fieldContainer.querySelector('.field-error');
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'field-error';
                fieldContainer.appendChild(errorElement);
            }
            
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        },
        
        showFieldSuccess: function(fieldContainer) {
            fieldContainer.classList.add('success');
            fieldContainer.classList.remove('error');
            this.clearFieldError(fieldContainer);
        },
        
        clearFieldError: function(fieldContainer) {
            fieldContainer.classList.remove('error');
            const errorElement = fieldContainer.querySelector('.field-error');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        },
        
        showSuccess: function(form, message) {
            this.clearMessages(form);
            
            const successElement = document.createElement('div');
            successElement.className = 'uform-success';
            successElement.textContent = message;
            
            form.parentNode.insertBefore(successElement, form);
            
            // 滚动到成功消息
            successElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
        
        showErrors: function(form, errors) {
            this.clearMessages(form);
            
            if (!Array.isArray(errors)) {
                errors = [errors];
            }
            
            const errorContainer = document.createElement('div');
            errorContainer.className = 'uform-errors';
            
            const errorList = document.createElement('ul');
            errors.forEach(error => {
                const errorItem = document.createElement('li');
                errorItem.textContent = error;
                errorList.appendChild(errorItem);
            });
            
            errorContainer.appendChild(errorList);
            form.parentNode.insertBefore(errorContainer, form);
            
            // 滚动到错误消息
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
        
        clearMessages: function(form) {
            const messages = form.parentNode.querySelectorAll('.uform-success, .uform-errors');
            messages.forEach(message => message.remove());
        },
        
        setLoadingState: function(form, isLoading) {
            const container = form.closest('.uform');
            if (isLoading) {
                container.classList.add('uform-loading');
            } else {
                container.classList.remove('uform-loading');
            }
        },
        
        initProgress: function() {
            const form = document.querySelector('.uform-' + this.formName);
            const progressBar = form && form.querySelector('.progress-bar');
            
            if (progressBar) {
                this.updateProgress();
            }
        },
        
        updateProgress: function() {
            const form = document.querySelector('.uform-' + this.formName + ' .uform-form');
            const progressBar = form && form.querySelector('.progress-bar');
            
            if (!progressBar) return;
            
            const fields = form.querySelectorAll('input, textarea, select');
            const requiredFields = Array.from(fields).filter(field => field.hasAttribute('required'));
            
            if (requiredFields.length === 0) return;
            
            const completedFields = requiredFields.filter(field => {
                if (field.type === 'checkbox' || field.type === 'radio') {
                    return form.querySelector(`input[name="${field.name}"]:checked`);
                }
                return field.value.trim() !== '';
            });
            
            const progress = (completedFields.length / requiredFields.length) * 100;
            progressBar.style.width = progress + '%';
        },
        
        handleFilePreview: function(e) {
            const input = e.target;
            const fieldContainer = input.closest('.uform-field');
            
            // 清除旧的预览
            const oldPreview = fieldContainer.querySelector('.file-preview');
            if (oldPreview) {
                oldPreview.remove();
            }
            
            if (!input.files || input.files.length === 0) return;
            
            const previewContainer = document.createElement('div');
            previewContainer.className = 'file-preview';
            
            Array.from(input.files).forEach(file => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                
                const fileName = document.createElement('span');
                fileName.className = 'file-name';
                fileName.textContent = file.name;
                
                const fileSize = document.createElement('span');
                fileSize.className = 'file-size';
                fileSize.textContent = this.formatFileSize(file.size);
                
                fileItem.appendChild(fileName);
                fileItem.appendChild(fileSize);
                
                // 图片预览
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'file-thumbnail';
                        img.style.maxWidth = '100px';
                        img.style.maxHeight = '100px';
                        fileItem.insertBefore(img, fileName);
                    };
                    reader.readAsDataURL(file);
                }
                
                previewContainer.appendChild(fileItem);
            });
            
            fieldContainer.appendChild(previewContainer);
        },
        
        updateRangeOutput: function(e) {
            const input = e.target;
            const output = input.parentNode.querySelector('.range-output');
            if (output) {
                output.textContent = input.value;
            }
        },
        
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },
        
        performAsyncValidation: function(field) {
            const fieldContainer = field.closest('.uform-field');
            
            // 显示验证中状态
            fieldContainer.classList.add('validating');
            
            fetch('/admin/extending.php?panel=Uforms%2Fajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'validate_field',
                    field_type: field.type,
                    field_value: field.value,
                    field_config: field.dataset.config || '{}'
                })
            })
            .then(response => response.json())
            .then(data => {
                fieldContainer.classList.remove('validating');
                
                if (data.success) {
                    this.showFieldSuccess(fieldContainer);
                } else {
                    this.showFieldError(fieldContainer, data.errors.join(', '));
                }
            })
            .catch(error => {
                console.error('Async validation error:', error);
                fieldContainer.classList.remove('validating');
            });
        },
        
        initValidation: function() {
            // 添加实时验证样式
            const style = document.createElement('style');
            style.textContent = `
                .field-error {
                    color: #e74c3c;
                    font-size: 12px;
                    margin-top: 5px;
                    display: none;
                }
                
                .uform-field.validating::after {
                    content: '验证中...';
                    position: absolute;
                    right: 10px;
                    top: 50%;
                    transform: translateY(-50%);
                    font-size: 12px;
                    color: #999;
                }
                
                .file-preview {
                    margin-top: 10px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 10px;
                    background-color: #f9f9f9;
                }
                
                .file-item {
                    display: flex;
                    align-items: center;
                    margin-bottom: 5px;
                }
                
                .file-item:last-child {
                    margin-bottom: 0;
                }
                
                .file-thumbnail {
                    margin-right: 10px;
                    border-radius: 4px;
                }
                
                .file-name {
                    flex: 1;
                    font-weight: 500;
                }
                
                .file-size {
                    font-size: 12px;
                    color: #666;
                    margin-left: 10px;
                }
            `;
            document.head.appendChild(style);
        }
    };
    
    // 自动初始化
    document.addEventListener('DOMContentLoaded', function() {
        // 初始化所有表单
        const forms = document.querySelectorAll('.uform[data-form-id]');
        forms.forEach(form => {
            const formName = form.className.match(/uform-(\w+)/);
            if (formName) {
                UformsAjax.init(formName[1]);
            }
        });
        
        // 初始化其他功能
        initFormAnimations();
        initAccessibility();
    });
    
    function initFormAnimations() {
        // 字段动画
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        });
        
        document.querySelectorAll('.uform-field').forEach(field => {
            observer.observe(field);
        });
    }
    
    function initAccessibility() {
        // 键盘导航
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });
        
        document.addEventListener('click', function() {
            document.body.classList.remove('keyboard-navigation');
        });
        
        // ARIA 标签
        document.querySelectorAll('.uform-field.required input, .uform-field.required textarea, .uform-field.required select').forEach(field => {
            field.setAttribute('aria-required', 'true');
        });
        
        document.querySelectorAll('.field-error').forEach(error => {
            const field = error.closest('.uform-field').querySelector('input, textarea, select');
            if (field) {
                field.setAttribute('aria-describedby', error.id || 'error-' + Math.random().toString(36).substr(2, 9));
            }
        });
    }
})();
