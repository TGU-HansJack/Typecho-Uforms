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
            
            console.log('Uforms: Form submission started');
            
            // 验证表单
            if (!this.validateForm(form)) {
                console.log('Uforms: Form validation failed');
                return;
            }
            
            // 显示加载状态
            this.setLoadingState(form, true);
            submitButton.disabled = true;
            
            console.log('Uforms: Sending request to: ' + window.location.href);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('Uforms: Response received', response);
                console.log('Uforms: Response status:', response.status);
                console.log('Uforms: Response headers:', [...response.headers.entries()]);
                
                // 检查响应类型
                const contentType = response.headers.get('content-type');
                console.log('Uforms: Content type:', contentType);
                
                if (contentType && contentType.indexOf('application/json') !== -1) {
                    console.log('Uforms: Parsing JSON response');
                    return response.json();
                } else {
                    // 如果不是JSON响应，尝试解析为文本
                    console.log('Uforms: Parsing text response');
                    return response.text().then(text => {
                        console.log('Uforms: Text response:', text);
                        
                        // 如果响应包含成功类
                        if (text.indexOf('uform-success') !== -1 || 
                            text.indexOf('class="uform-success"') !== -1) {
                            return {success: true, message: '提交成功'};
                        } 
                        // 如果响应包含错误类
                        else if (text.indexOf('uform-error') !== -1 || 
                                 text.indexOf('class="uform-errors"') !== -1) {
                            // 尝试提取错误信息
                            const errorMatch = text.match(/<div[^>]*class="uform-[^"]*"[^>]*>(.*?)<\/div>/);
                            const errorMessage = errorMatch ? errorMatch[1] : '提交失败';
                            return {success: false, message: errorMessage};
                        } 
                        // 未知响应，尝试重新加载页面
                        else {
                            window.location.reload();
                            return {success: true, message: '提交成功'};
                        }
                    });
                }
            })
            .then(data => {
                console.log('Uforms: Processed data:', data);
                
                if (data.success) {
                    this.showSuccess(form, data.message);
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    } else if (data.redirect === "") {
                        // 如果重定向URL为空字符串，则重新加载页面
                        window.location.reload();
                    } else {
                        // 只有在成功时才重置表单
                        // 延迟重置表单，让用户看到成功消息
                        setTimeout(() => {
                            form.reset();
                            this.updateProgress();
                        }, 3000);
                    }
                } else {
                    this.showErrors(form, [data.message]);
                }
            })
            .catch(error => {
                console.error('Uforms: Form submission error:', error);
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
            const successContainer = document.createElement('div');
            successContainer.className = 'uform-success';
            successContainer.textContent = message;
            
            // 移除已存在的成功信息
            const existingSuccess = form.parentNode.querySelector('.uform-success');
            if (existingSuccess) {
                existingSuccess.remove();
            }
            
            // 移除错误信息
            const existingErrors = form.parentNode.querySelector('.uform-errors');
            if (existingErrors) {
                existingErrors.remove();
            }
            
            form.parentNode.insertBefore(successContainer, form);
            
            // 滚动到成功信息位置
            successContainer.scrollIntoView({behavior: 'smooth', block: 'center'});
        },
        
        showErrors: function(form, errors) {
            const errorContainer = document.createElement('div');
            errorContainer.className = 'uform-errors';
            errorContainer.innerHTML = '<ul><li>' + errors.join('</li><li>') + '</li></ul>';
            
            // 移除已存在的错误信息
            const existingErrors = form.parentNode.querySelector('.uform-errors');
            if (existingErrors) {
                existingErrors.remove();
            }
            
            form.parentNode.insertBefore(errorContainer, form);
            
            // 滚动到错误信息位置
            errorContainer.scrollIntoView({behavior: 'smooth', block: 'center'});
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
            const progressBar = document.querySelector('.form-progress');
            if (progressBar) {
                this.updateProgress();
            }
        },
        
        updateProgress: function() {
            const form = document.querySelector('.uform-form');
            if (!form) return;
            
            const inputs = form.querySelectorAll('input, textarea, select');
            const total = inputs.length;
            let filled = 0;
            
            inputs.forEach(input => {
                if (input.value && input.type !== 'submit' && input.type !== 'button') {
                    filled++;
                }
            });
            
            const percentage = total > 0 ? Math.round((filled / total) * 100) : 0;
            
            const progressBar = document.querySelector('.form-progress-bar');
            if (progressBar) {
                progressBar.style.width = percentage + '%';
                progressBar.textContent = percentage + '%';
            }
        },
        
        handleFilePreview: function(e) {
            const fileInput = e.target;
            const file = fileInput.files[0];
            if (!file) return;
            
            const previewContainer = fileInput.parentNode.querySelector('.file-preview');
            if (!previewContainer) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = 'file-preview-item';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="预览">
                    <span class="file-name">${file.name}</span>
                `;
                previewContainer.innerHTML = '';
                previewContainer.appendChild(preview);
            };
            reader.readAsDataURL(file);
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
