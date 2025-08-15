(function() {
    'use strict';
    
    // 等待DOM加载完成
    document.addEventListener('DOMContentLoaded', function() {
        initForms();
    });
    
    function initForms() {
        const forms = document.querySelectorAll('.formbuilder-form form');
        forms.forEach(function(form) {
            initForm(form);
        });
    }
    
    function initForm(form) {
        // 初始化验证码
        initCaptcha(form);
        
        // 绑定提交事件
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(form);
        });
        
        // 绑定实时验证
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(function(input) {
            input.addEventListener('blur', function() {
                validateField(input);
            });
            
            input.addEventListener('input', function() {
                clearFieldError(input);
            });
        });
    }
    
    function initCaptcha(form) {
        const captchaImg = form.querySelector('.formbuilder-captcha-image');
        const refreshBtn = form.querySelector('.formbuilder-captcha-refresh');
        
        if (captchaImg) {
            // 刷新验证码
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    refreshCaptcha(captchaImg);
                });
            }
            
            // 点击图片刷新
            captchaImg.addEventListener('click', function() {
                refreshCaptcha(captchaImg);
            });
        }
    }
    
    function refreshCaptcha(img) {
        const src = img.src.split('?')[0];
        img.src = src + '?t=' + Date.now();
    }
    
    function submitForm(form) {
        // 显示加载状态
        form.closest('.formbuilder-form').classList.add('loading');
        
        // 清除之前的错误信息
        clearFormErrors(form);
        
        // 验证表单
        if (!validateForm(form)) {
            form.closest('.formbuilder-form').classList.remove('loading');
            return;
        }
        
        // 收集表单数据
        const formData = new FormData(form);
        
        // 发送请求
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            handleSubmitResponse(form, data);
        })
        .catch(function(error) {
            console.error('Error:', error);
            showFormMessage(form, '网络错误，请稍后重试', 'error');
        })
        .finally(function() {
            form.closest('.formbuilder-form').classList.remove('loading');
        });
    }
    
    function validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(function(input) {
            if (!validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    function validateField(field) {
        const value = field.value.trim();
        const isRequired = field.hasAttribute('required') || field.classList.contains('required');
        const type = field.type || 'text';
        let errorMessage = '';
        
        // 必填验证
        if (isRequired && !value) {
            errorMessage = '此字段为必填项';
        }
        // 邮箱验证
        else if (type === 'email' && value && !isValidEmail(value)) {
            errorMessage = '请输入有效的邮箱地址';
        }
        // 电话验证
        else if (type === 'tel' && value && !isValidPhone(value)) {
            errorMessage = '请输入有效的电话号码';
        }
        // 数字验证
        else if (type === 'number' && value && !isValidNumber(value)) {
            errorMessage = '请输入有效的数字';
        }
        // 文件验证
        else if (type === 'file' && field.files.length > 0) {
            const file = field.files[0];
            const maxSize = parseInt(field.getAttribute('data-max-size')) || 5 * 1024 * 1024; // 5MB
            const allowedTypes = field.getAttribute('data-allowed-types');
            
            if (file.size > maxSize) {
                errorMessage = '文件大小超过限制';
            } else if (allowedTypes && !allowedTypes.split(',').some(type => file.type.includes(type.trim()))) {
                errorMessage = '文件类型不支持';
            }
        }
        
        if (errorMessage) {
            showFieldError(field, errorMessage);
            return false;
        } else {
            clearFieldError(field);
            return true;
        }
    }
    
    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    function isValidPhone(phone) {
        const regex = /^[\d\s\-\+\(\)]+$/;
        return regex.test(phone) && phone.replace(/\D/g, '').length >= 10;
    }
    
    function isValidNumber(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    }
    
    function showFieldError(field, message) {
        field.classList.add('error');
        
        // 移除现有错误信息
        const existingError = field.parentNode.querySelector('.formbuilder-field-error');
        if (existingError) {
            existingError.remove();
        }
        
        // 添加错误信息
        const errorDiv = document.createElement('span');
        errorDiv.className = 'formbuilder-field-error';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }
    
    function clearFieldError(field) {
        field.classList.remove('error');
        const errorDiv = field.parentNode.querySelector('.formbuilder-field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    function clearFormErrors(form) {
        const errorInputs = form.querySelectorAll('.error');
        errorInputs.forEach(function(input) {
            clearFieldError(input);
        });
        
        // 清除全局消息
        const messages = form.parentNode.querySelectorAll('.formbuilder-message');
        messages.forEach(function(message) {
            message.remove();
        });
    }
    
    function handleSubmitResponse(form, data) {
        if (data.success) {
            showFormMessage(form, data.message || '提交成功！', 'success');
            form.reset();
            
            // 刷新验证码
            const captchaImg = form.querySelector('.formbuilder-captcha-image');
            if (captchaImg) {
                refreshCaptcha(captchaImg);
            }
            
            // 如果有重定向URL，延迟跳转
            if (data.redirect) {
                setTimeout(function() {
                    window.location.href = data.redirect;
                }, 2000);
            }
        } else {
            showFormMessage(form, data.message || '提交失败', 'error');
            
            // 显示字段错误
            if (data.errors) {
                for (const fieldName in data.errors) {
                    const field = form.querySelector(`[name="${fieldName}"]`);
                    if (field) {
                        showFieldError(field, data.errors[fieldName]);
                    }
                }
            }
            
            // 刷新验证码
            const captchaImg = form.querySelector('.formbuilder-captcha-image');
            if (captchaImg) {
                refreshCaptcha(captchaImg);
            }
        }
    }
    
    function showFormMessage(form, message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'formbuilder-message ' + type;
        messageDiv.textContent = message;
        
        form.parentNode.insertBefore(messageDiv, form);
        
        // 滚动到消息位置
        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // 5秒后自动移除成功消息
        if (type === 'success') {
            setTimeout(function() {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 5000);
        }
    }
    
    // 工具函数：防抖
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = function() {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // 工具函数：节流
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }
    
})();
