// 表单构建器核心类
class UformsBuilder {
    constructor() {
        this.selectedField = null;
        this.formData = window.uformsConfig.existingConfig || {};
        this.formSettings = window.uformsConfig.existingSettings || {};
        this.fieldsData = new Map();
        this.fieldCounter = 0;
        this.isDirty = false;
        this.autoSaveTimer = null;
        
        this.init();
    }
    
    init() {
        this.initFieldCategories();
        this.bindEvents();
        this.initSortable();
        this.initPresetOptions();
        this.loadExistingForm();
        this.setupAutoSave();
        this.initColorPickers();
        this.initRangeSliders();
    }
    
    initFieldCategories() {
        // 初始化字段分类的展开/折叠状态
        $('.field-category h4').each(function(index) {
            const category = $(this).closest('.field-category');
            const isCollapsed = localStorage.getItem(`uforms_category_${index}`) === 'true';
            
            if (isCollapsed) {
                category.addClass('collapsed');
            }
        });
        
        // 绑定分类点击事件
        $('.field-category h4').on('click', function() {
            const category = $(this).closest('.field-category');
            const index = $('.field-category').index(category);
            
            if (category.hasClass('collapsed')) {
                category.removeClass('collapsed').addClass('slide-in');
                localStorage.setItem(`uforms_category_${index}`, 'false');
                setTimeout(() => {
                    category.removeClass('slide-in');
                }, 300);
            } else {
                category.addClass('collapsed slide-out');
                localStorage.setItem(`uforms_category_${index}`, 'true');
                setTimeout(() => {
                    category.removeClass('slide-out');
                }, 300);
            }
        });
    }
    
    bindEvents() {
        // 字段库事件
        $('.field-item').on('click', (e) => {
            this.addFieldFromLibrary(e.currentTarget);
        });
        
        // 画布字段事件
        $(document).on('click', '.canvas-field', (e) => {
            e.stopPropagation();
            this.selectField(e.currentTarget);
        });
        
        $(document).on('click', '.field-action', (e) => {
            e.stopPropagation();
            this.handleFieldAction(e);
        });
        
        // 属性面板事件
        $('.tab-button').on('click', (e) => {
            this.switchTab(e.target.dataset.tab);
        });
        
        // 字段属性变更事件
        $('#field-label').on('input', () => this.updateFieldProperty('label'));
        $('#field-name').on('input', () => this.updateFieldProperty('name'));
        $('#field-placeholder').on('input', () => this.updateFieldProperty('placeholder'));
        $('#field-default').on('input', () => this.updateFieldProperty('default'));
        $('#field-help').on('input', () => this.updateFieldProperty('help'));
        $('#field-required').on('change', () => this.updateFieldProperty('required'));
        $('#field-css-class').on('input', () => this.updateFieldProperty('cssClass'));
        $('#field-css-id').on('input', () => this.updateFieldProperty('cssId'));
        $('#field-width').on('change', () => this.updateFieldProperty('width'));
        
        // 验证规则事件
        $('#field-min-length').on('input', () => this.updateFieldProperty('minLength'));
        $('#field-max-length').on('input', () => this.updateFieldProperty('maxLength'));
        $('#field-pattern').on('input', () => this.updateFieldProperty('pattern'));
        $('#field-error-message').on('input', () => this.updateFieldProperty('errorMessage'));
        
        // 条件逻辑事件
        $('#field-conditional').on('change', () => this.toggleConditionalLogic());
        $('#add-condition-rule').on('click', () => this.addConditionRule());
        $(document).on('click', '.rule-remove', (e) => this.removeConditionRule(e));
        
        // 选项管理事件
        $('#add-option').on('click', () => this.addOption());
        $('#bulk-add-options').on('click', () => this.showBulkOptionsModal());
        $(document).on('click', '.option-remove', (e) => this.removeOption(e));
        $(document).on('input', '.option-label, .option-value', () => this.updateOptions());
        
        // 画布工具栏事件
        $('.preview-btn').on('click', (e) => {
            this.switchPreviewMode(e.target.dataset.view);
        });
        
        $('#clear-form').on('click', () => this.clearForm());
        $('#preview-form, #preview-form-btn').on('click', () => this.previewForm());
        $('#toggle-grid').on('click', () => this.toggleGrid());
        
        // 表单设置事件
        $('#form-name').on('input', () => this.updateFormSetting('name'));
        $('#form-title').on('input', () => this.updateFormSetting('title'));
        $('#form-description').on('input', () => this.updateFormSetting('description'));
        $('#submit-text').on('input', () => this.updateFormSetting('submitText'));
        $('#success-message').on('input', () => this.updateFormSetting('successMessage'));
        $('#success-action').on('change', () => this.handleSuccessActionChange());
        $('#ajax-submit').on('change', () => this.updateFormSetting('ajaxSubmit'));
        
        // 安全设置事件
        $('#enable-captcha').on('change', () => this.updateFormSetting('enableCaptcha'));
        $('#enable-honeypot').on('change', () => this.updateFormSetting('enableHoneypot'));
        $('#submit-limit').on('input', () => this.updateFormSetting('submitLimit'));
        
        // 邮件通知事件
        $('#admin-notification').on('change', () => this.toggleAdminNotification());
        $('#user-notification').on('change', () => this.toggleUserNotification());
        
        // 样式设置事件
        $('#form-theme').on('change', () => this.updateFormStyle('theme'));
        $('#primary-color, #primary-color-text').on('input', () => this.updateFormStyle('primaryColor'));
        $('#label-position').on('change', () => this.updateFormStyle('labelPosition'));
        
        // 底部操作事件
        $('#save-draft').on('click', () => this.saveForm('draft'));
        $('#publish-form').on('click', () => this.saveForm('published'));
        $('#get-code').on('click', () => this.showCodeModal());
        $('#save-template').on('click', () => this.saveAsTemplate());
        
        // 模态框事件
        $('.modal-close').on('click', (e) => {
            $(e.target).closest('.modal').hide();
        });
        
        // 批量选项事件
        $('.bulk-tab').on('click', (e) => this.switchBulkTab(e.target.dataset.tab));
        $('.preset-btn').on('click', (e) => this.loadPresetOptions(e.target.dataset.preset));
        $('#apply-bulk-options').on('click', () => this.applyBulkOptions());
        
        // 预览设备切换
        $('.preview-device').on('click', (e) => {
            this.switchPreviewDevice(e.target.dataset.device);
        });
        
        // 代码复制事件
        $('#copy-link, #copy-iframe, #copy-shortcode, #copy-api').on('click', (e) => {
            this.copyCode(e.target.id);
        });
        
        // iframe选项更新
        $('#update-iframe').on('click', () => this.updateIframeCode());
        
        // 键盘快捷键
        $(document).on('keydown', (e) => this.handleKeyboardShortcuts(e));
        
        // 页面离开提醒
        $(window).on('beforeunload', () => {
            if (this.isDirty) {
                return '您有未保存的更改，确定要离开吗？';
            }
        });
    }
    
    initSortable() {
        if (typeof Sortable !== 'undefined') {
            // 画布排序
            this.canvasSortable = Sortable.create(document.getElementById('canvas-drop-zone'), {
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                handle: '.field-drag-handle',
                onStart: () => {
                    $('#canvas-drop-zone').addClass('sorting');
                },
                onEnd: () => {
                    $('#canvas-drop-zone').removeClass('sorting');
                    this.updateFieldOrder();
                    this.markDirty();
                }
            });
            
            // 选项排序
            $(document).on('DOMNodeInserted', '.options-list', () => {
                if (this.optionsSortable) {
                    this.optionsSortable.destroy();
                }
                
                const optionsList = document.querySelector('.options-list');
                if (optionsList) {
                    this.optionsSortable = Sortable.create(optionsList, {
                        animation: 150,
                        handle: '.option-drag-handle',
                        onEnd: () => {
                            this.updateOptions();
                        }
                    });
                }
            });
            
            // 条件规则排序
            $(document).on('DOMNodeInserted', '.rule-builder', () => {
                if (this.rulesSortable) {
                    this.rulesSortable.destroy();
                }
                
                const ruleBuilder = document.querySelector('.rule-builder');
                if (ruleBuilder) {
                    this.rulesSortable = Sortable.create(ruleBuilder, {
                        animation: 150,
                        handle: '.rule-drag-handle',
                        onEnd: () => {
                            // 更新规则顺序
                        }
                    });
                }
            });
            
            // 确保所有已存在的列表都初始化排序功能
            const canvasDropZone = document.getElementById('canvas-drop-zone');
            if (canvasDropZone) {
                this.canvasSortable = Sortable.create(canvasDropZone, {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    handle: '.field-drag-handle',
                    onStart: () => {
                        $('#canvas-drop-zone').addClass('sorting');
                    },
                    onEnd: () => {
                        $('#canvas-drop-zone').removeClass('sorting');
                        this.updateFieldOrder();
                        this.markDirty();
                    }
                });
            }
            
            const optionsList = document.querySelector('.options-list');
            if (optionsList) {
                this.optionsSortable = Sortable.create(optionsList, {
                    animation: 150,
                    handle: '.option-drag-handle',
                    onEnd: () => {
                        this.updateOptions();
                    }
                });
            }
            
            const ruleBuilder = document.querySelector('.rule-builder');
            if (ruleBuilder) {
                this.rulesSortable = Sortable.create(ruleBuilder, {
                    animation: 150,
                    handle: '.rule-drag-handle',
                    onEnd: () => {
                        // 更新规则顺序
                    }
                });
            }
        } else {
            console.warn('SortableJS library not found');
        }
    }
    
    initPresetOptions() {
        this.presetOptions = {
            yesno: ['是', '否'],
            gender: ['男', '女', '其他'],
            rating: ['非常不满意', '不满意', '一般', '满意', '非常满意'],
            education: ['小学', '初中', '高中', '大专', '本科', '硕士', '博士'],
            cities: ['北京', '上海', '广州', '深圳', '杭州', '南京', '成都', '武汉', '西安', '重庆'],
            provinces: [
                '北京市', '天津市', '河北省', '山西省', '内蒙古自治区',
                '辽宁省', '吉林省', '黑龙江省', '上海市', '江苏省',
                '浙江省', '安徽省', '福建省', '江西省', '山东省',
                '河南省', '湖北省', '湖南省', '广东省', '广西壮族自治区',
                '海南省', '重庆市', '四川省', '贵州省', '云南省',
                '西藏自治区', '陕西省', '甘肃省', '青海省', '宁夏回族自治区',
                '新疆维吾尔自治区', '台湾省', '香港特别行政区', '澳门特别行政区'
            ],
            countries: ['中国', '美国', '日本', '英国', '法国', '德国', '加拿大', '澳大利亚', '韩国', '新加坡'],
            numbers: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']
        };
    }
    
    addFieldFromLibrary(fieldItem) {
        const fieldType = fieldItem.dataset.type;
        const fieldConfig = this.getDefaultFieldConfig(fieldType);
        const fieldId = this.generateFieldId();
        
        const fieldElement = this.createFieldElement(fieldId, fieldType, fieldConfig);
        
        // 如果画布为空，移除提示
        if ($('#form-canvas .canvas-drop-zone').length > 0) {
            $('#form-canvas').empty();
        }
        
        $('#form-canvas').append(fieldElement);
        
        // 保存字段数据
        this.fieldsData.set(fieldId, {
            id: fieldId,
            type: fieldType,
            config: fieldConfig
        });
        
        // 选中新添加的字段
        this.selectField(fieldElement[0]);
        
        // 滚动到字段位置
        fieldElement[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        this.markDirty();
        
        // 添加动画效果
        fieldElement.addClass('field-added');
        setTimeout(() => {
            fieldElement.removeClass('field-added');
        }, 600);
    }
    
    getDefaultFieldConfig(type) {
        const configs = {
            text: {
                label: '单行文本',
                name: 'text_field_' + (this.fieldCounter + 1),
                placeholder: '请输入文本',
                required: false,
                width: 'full'
            },
            textarea: {
                label: '多行文本',
                name: 'textarea_field_' + (this.fieldCounter + 1),
                placeholder: '请输入内容',
                required: false,
                width: 'full',
                rows: 4
            },
            email: {
                label: '邮箱地址',
                name: 'email_field_' + (this.fieldCounter + 1),
                placeholder: '请输入邮箱',
                required: false,
                width: 'full'
            },
            select: {
                label: '下拉选择',
                name: 'select_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                options: [
                    { label: '选项1', value: 'option1' },
                    { label: '选项2', value: 'option2' }
                ]
            },
            radio: {
                label: '单选按钮',
                name: 'radio_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                options: [
                    { label: '选项1', value: 'option1' },
                    { label: '选项2', value: 'option2' }
                ]
            },
            checkbox: {
                label: '复选框',
                name: 'checkbox_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                options: [
                    { label: '选项1', value: 'option1' },
                    { label: '选项2', value: 'option2' }
                ]
            },
            file: {
                label: '文件上传',
                name: 'file_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                accept: 'image/*,.pdf,.doc,.docx',
                maxSize: 10,
                multiple: false
            },
            date: {
                label: '日期选择',
                name: 'date_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full'
            },
            number: {
                label: '数字输入',
                name: 'number_field_' + (this.fieldCounter + 1),
                placeholder: '请输入数字',
                required: false,
                width: 'full',
                min: null,
                max: null,
                step: 1
            },
            range: {
                label: '数值滑块',
                name: 'range_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                min: 0,
                max: 100,
                step: 1,
                defaultValue: 50
            },
            rating: {
                label: '评分',
                name: 'rating_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                max: 5,
                icon: 'star'
            },
            color: {
                label: '颜色选择',
                name: 'color_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                defaultValue: '#000000'
            },
            heading: {
                label: '标题文本',
                name: 'heading_' + (this.fieldCounter + 1),
                text: '章节标题',
                level: 'h3',
                width: 'full'
            },
            paragraph: {
                label: '段落文本',
                name: 'paragraph_' + (this.fieldCounter + 1),
                text: '这里是段落文本内容，可以用于说明或描述。',
                width: 'full'
            },
            divider: {
                label: '分割线',
                name: 'divider_' + (this.fieldCounter + 1),
                style: 'solid',
                width: 'full'
            },
            hidden: {
                label: '隐藏字段',
                name: 'hidden_field_' + (this.fieldCounter + 1),
                value: '',
                width: 'full'
            }
        };
        
        return configs[type] || {};
    }
    
    generateFieldId() {
        return 'field_' + (++this.fieldCounter) + '_' + Date.now();
    }
    
    createFieldElement(fieldId, fieldType, config) {
        const fieldElement = $(`
            <div class="canvas-field" id="${fieldId}" data-type="${fieldType}" data-field-id="${fieldId}">
                <div class="field-header">
                    <span class="field-drag-handle" title="拖拽排序">
                        <i class="icon-drag"></i>
                    </span>
                    <span class="field-label">${config.label}</span>
                    <div class="field-meta">
                        <span class="field-type">${this.getFieldTypeLabel(fieldType)}</span>
                        ${config.required ? '<span class="field-required">必填</span>' : ''}
                    </div>
                    <div class="field-actions">
                        <button class="field-action field-edit ui icon button" title="编辑字段">
                            <i class="edit icon"></i>
                        </button>
                        <button class="field-action field-copy ui icon button" title="复制字段">
                            <i class="copy icon"></i>
                        </button>
                        <button class="field-action field-delete ui icon button" title="删除字段">
                            <i class="trash icon"></i>
                        </button>
                    </div>
                </div>
                <div class="field-body">
                    ${this.renderFieldPreview(fieldType, config)}
                </div>
                <div class="field-properties-preview">
                    ${this.renderFieldPropertiesPreview(config)}
                </div>
            </div>
        `);
        
        return fieldElement;
    }
    
    renderFieldPreview(fieldType, config) {
        switch (fieldType) {
            case 'text':
            case 'email':
            case 'url':
            case 'tel':
            case 'password':
                return `<input type="${fieldType}" placeholder="${config.placeholder || ''}" disabled />`;
                
            case 'textarea':
                return `<textarea rows="${config.rows || 4}" placeholder="${config.placeholder || ''}" disabled></textarea>`;
                
            case 'number':
                return `<input type="number" placeholder="${config.placeholder || ''}" min="${config.min || ''}" max="${config.max || ''}" step="${config.step || 1}" disabled />`;
                
            case 'select':
                let selectOptions = config.options ? config.options.map(opt => 
                    `<option value="${opt.value}">${opt.label}</option>`
                ).join('') : '';
                return `<select disabled>
                    <option value="">请选择</option>
                    ${selectOptions}
                </select>`;
                
            case 'radio':
                let radioOptions = config.options ? config.options.map((opt, i) => 
                    `<label class="radio-option">
                        <input type="radio" name="${config.name}" value="${opt.value}" disabled />
                        ${opt.label}
                    </label>`
                ).join('') : '';
                return `<div class="radio-group">${radioOptions}</div>`;
                
            case 'checkbox':
                let checkboxOptions = config.options ? config.options.map((opt, i) => 
                    `<label class="checkbox-option">
                        <input type="checkbox" name="${config.name}" value="${opt.value}" disabled />
                        ${opt.label}
                    </label>`
                ).join('') : '';
                return `<div class="checkbox-group">${checkboxOptions}</div>`;
                
            case 'file':
                return `<input type="file" ${config.multiple ? 'multiple' : ''} accept="${config.accept || '*'}" disabled />
                        <div class="file-info">最大大小: ${config.maxSize || 10}MB</div>`;
                
            case 'date':
                return `<input type="date" disabled />`;
                
            case 'time':
                return `<input type="time" disabled />`;
                
            case 'datetime':
                return `<input type="datetime-local" disabled />`;
                
            case 'range':
                return `<input type="range" min="${config.min || 0}" max="${config.max || 100}" value="${config.defaultValue || 50}" disabled />
                        <div class="range-output">${config.defaultValue || 50}</div>`;
                
            case 'color':
                return `<input type="color" value="${config.defaultValue || '#000000'}" disabled />`;
                
            case 'rating':
                let stars = '';
                for (let i = 1; i <= (config.max || 5); i++) {
                    stars += `<span class="rating-star" data-rating="${i}">★</span>`;
                }
                return `<div class="rating-group">${stars}</div>`;
                
            case 'heading':
                const level = config.level || 'h3';
                return `<${level} class="form-heading">${config.text || '标题文本'}</${level}>`;
                
            case 'paragraph':
                return `<p class="form-paragraph">${config.text || '段落文本'}</p>`;
                
            case 'divider':
                return `<hr class="form-divider" />`;
                
            case 'hidden':
                return `<div class="hidden-field-info">隐藏字段: ${config.name}</div>`;
                
            default:
                return `<div class="field-placeholder">未知字段类型: ${fieldType}</div>`;
        }
    }
    
    renderFieldPropertiesPreview(config) {
        const properties = [];
        
        if (config.placeholder) {
            properties.push(`<span class="prop-item">占位符: ${config.placeholder}</span>`);
        }
        
        if (config.help) {
            properties.push(`<span class="prop-item">帮助: ${config.help}</span>`);
        }
        
        if (config.cssClass) {
            properties.push(`<span class="prop-item">CSS类: ${config.cssClass}</span>`);
        }
        
        if (config.minLength || config.maxLength) {
            properties.push(`<span class="prop-item">长度: ${config.minLength || 0} - ${config.maxLength || '无限'}</span>`);
        }
        
        return properties.length > 0 ? properties.join('') : '';
    }
    
    getFieldTypeLabel(type) {
        const labels = {
            text: '文本',
            textarea: '多行文本',
            email: '邮箱',
            url: '网址',
            tel: '电话',
            number: '数字',
            password: '密码',
            select: '下拉选择',
            radio: '单选',
            checkbox: '多选',
            file: '文件',
            date: '日期',
            time: '时间',
            datetime: '日期时间',
            range: '滑块',
            color: '颜色',
            rating: '评分',
            heading: '标题',
            paragraph: '段落',
            divider: '分割线',
            hidden: '隐藏'
        };
        
        return labels[type] || type;
    }
    
    selectField(fieldElement) {
        // 移除其他字段的选中状态
        $('.canvas-field').removeClass('selected');
        
        // 选中当前字段
        $(fieldElement).addClass('selected');
        
        this.selectedField = fieldElement;
        const fieldId = fieldElement.id;
        const fieldData = this.fieldsData.get(fieldId);
        
        if (fieldData) {
            this.showFieldProperties(fieldData);
        }
    }
    
    showFieldProperties(fieldData) {
        // 切换到字段设置标签
        this.switchTab('field');
        
        // 隐藏无选择提示，显示属性设置
        $('.no-selection').hide();
        $('.field-properties').show();
        
        // 填充基本属性
        $('#field-label').val(fieldData.config.label || '');
        $('#field-name').val(fieldData.config.name || '');
        $('#field-placeholder').val(fieldData.config.placeholder || '');
        $('#field-default').val(fieldData.config.defaultValue || fieldData.config.value || '');
        $('#field-help').val(fieldData.config.help || '');
        $('#field-required').prop('checked', fieldData.config.required || false);
        $('#field-css-class').val(fieldData.config.cssClass || '');
        $('#field-css-id').val(fieldData.config.cssId || '');
        $('#field-width').val(fieldData.config.width || 'full');
        
        // 显示/隐藏特定设置组
        this.toggleFieldSpecificSettings(fieldData.type);
        
        // 填充特定字段设置
        this.loadFieldSpecificSettings(fieldData);
    }
    
    toggleFieldSpecificSettings(fieldType) {
        // 隐藏所有特殊设置组
        $('.options-group, .file-group, .number-group, .datetime-group').hide();
        
        // 根据字段类型显示相应设置
        if (['select', 'radio', 'checkbox'].includes(fieldType)) {
            $('.options-group').show();
        }
        
        if (fieldType === 'file') {
            $('.file-group').show();
        }
        
        if (['number', 'range'].includes(fieldType)) {
            $('.number-group').show();
        }
        
        if (['date', 'time', 'datetime'].includes(fieldType)) {
            $('.datetime-group').show();
        }
    }
    
    loadFieldSpecificSettings(fieldData) {
        const { type, config } = fieldData;
        
        // 选项类字段设置
        if (['select', 'radio', 'checkbox'].includes(type) && config.options) {
            this.loadOptions(config.options);
        }
        
        // 文件上传设置
        if (type === 'file') {
            $('#file-types').val(config.accept || 'jpg,png,pdf');
            $('#file-max-size').val(config.maxSize || 10);
            $('#file-multiple').prop('checked', config.multiple || false);
            $('#file-max-count').val(config.maxCount || 5);
        }
        
        // 数字字段设置
        if (['number', 'range'].includes(type)) {
            $('#number-min').val(config.min || '');
            $('#number-max').val(config.max || '');
            $('#number-step').val(config.step || 1);
        }
        
        // 日期时间设置
        if (['date', 'time', 'datetime'].includes(type)) {
            $('#date-min').val(config.minDate || '');
            $('#date-max').val(config.maxDate || '');
            $('#date-format').val(config.dateFormat || 'YYYY-MM-DD');
        }
        
        // 验证规则
        $('#field-min-length').val(config.minLength || '');
        $('#field-max-length').val(config.maxLength || '');
        $('#field-pattern').val(config.pattern || '');
        $('#field-error-message').val(config.errorMessage || '');
        
        // 条件逻辑
        if (config.conditional) {
            $('#field-conditional').prop('checked', true);
            this.showConditionalRules();
            this.loadConditionalRules(config.conditional);
        } else {
            $('#field-conditional').prop('checked', false);
            this.hideConditionalRules();
        }
    }
    
    loadOptions(options) {
        const optionsList = $('#options-list');
        optionsList.empty();
        
        options.forEach((option, index) => {
            const optionItem = $(`
                <div class="option-item">
                    <span class="option-drag" title="拖拽排序">⋮⋮</span>
                    <input type="text" class="option-label" placeholder="选项标签" value="${option.label}" />
                    <input type="text" class="option-value" placeholder="选项值" value="${option.value}" />
                    <button type="button" class="option-remove" title="删除选项">×</button>
                </div>
            `);
            optionsList.append(optionItem);
        });
        
        // 确保至少有一个空选项用于添加
        if (options.length === 0) {
            this.addOption();
        }
    }
    
    updateFieldProperty(property) {
        if (!this.selectedField) return;
        
        const fieldId = this.selectedField.id;
        const fieldData = this.fieldsData.get(fieldId);
        if (!fieldData) return;
        
        // 获取新值
        let value;
        const element = document.getElementById(`field-${property.replace(/([A-Z])/g, '-$1').toLowerCase()}`);
        
        if (element) {
            if (element.type === 'checkbox') {
                value = element.checked;
            } else {
                value = element.value;
            }
        }
        
        // 更新字段数据
        fieldData.config[property] = value;
        
        // 特殊处理
        if (property === 'label') {
            // 更新画布中的标签显示
            $(this.selectedField).find('.field-label').text(value);
            
            // 如果name为空，自动生成
            if (!fieldData.config.name) {
                const autoName = this.generateFieldName(value);
                fieldData.config.name = autoName;
                $('#field-name').val(autoName);
            }
        }
        
        if (property === 'required') {
            // 更新必填显示
            const requiredSpan = $(this.selectedField).find('.field-required');
            if (value) {
                if (requiredSpan.length === 0) {
                    $(this.selectedField).find('.field-meta').append('<span class="field-required">必填</span>');
                }
            } else {
                requiredSpan.remove();
            }
        }
        
        // 更新预览
        this.updateFieldPreview(fieldId);
        
        // 更新属性预览
        this.updateFieldPropertiesPreview(fieldId);
        
        this.markDirty();
    }
    
    generateFieldName(label) {
        const name = label.replace(/[^\w\u4e00-\u9fa5]/g, '_')
                          .replace(/_{2,}/g, '_')
                          .replace(/^_|_$/g, '')
                          .toLowerCase();
        
        // 确保唯一性
        let finalName = name;
        let counter = 1;
        const existingNames = Array.from(this.fieldsData.values()).map(f => f.config.name);
        
        while (existingNames.includes(finalName)) {
            finalName = `${name}_${counter}`;
            counter++;
        }
        
        return finalName;
    }
    
    updateFieldPreview(fieldId) {
        const fieldData = this.fieldsData.get(fieldId);
        if (!fieldData) return;
        
        const fieldElement = document.getElementById(fieldId);
        const fieldBody = fieldElement.querySelector('.field-body');
        
        fieldBody.innerHTML = this.renderFieldPreview(fieldData.type, fieldData.config);
    }
    
    updateFieldPropertiesPreview(fieldId) {
        const fieldData = this.fieldsData.get(fieldId);
        if (!fieldData) return;
        
        const fieldElement = document.getElementById(fieldId);
        const propertiesPreview = fieldElement.querySelector('.field-properties-preview');
        
        propertiesPreview.innerHTML = this.renderFieldPropertiesPreview(fieldData.config);
    }
    
    handleFieldAction(e) {
        const action = e.target.closest('.field-action');
        const fieldElement = e.target.closest('.canvas-field');
        
        if (action.classList.contains('field-edit')) {
            this.selectField(fieldElement);
        } else if (action.classList.contains('field-copy')) {
            this.duplicateField(fieldElement);
        } else if (action.classList.contains('field-delete')) {
            this.deleteField(fieldElement);
        }
    }
    
    duplicateField(fieldElement) {
        const fieldId = fieldElement.id;
        const originalData = this.fieldsData.get(fieldId);
        
        if (!originalData) return;
        
        // 创建新字段数据
        const newFieldId = this.generateFieldId();
        const newConfig = JSON.parse(JSON.stringify(originalData.config));
        
        // 修改名称以避免重复
        newConfig.name = newConfig.name + '_copy';
        newConfig.label = newConfig.label + ' (副本)';
        
        // 创建新字段元素
        const newFieldElement = this.createFieldElement(newFieldId, originalData.type, newConfig);
        
        // 插入到原字段后面
        $(fieldElement).after(newFieldElement);
        
        // 保存新字段数据
        this.fieldsData.set(newFieldId, {
            id: newFieldId,
            type: originalData.type,
            config: newConfig
        });
        
        // 选中新字段
        this.selectField(newFieldElement[0]);
        
        this.markDirty();
        
        // 添加动画
        newFieldElement.addClass('field-added');
        setTimeout(() => {
            newFieldElement.removeClass('field-added');
        }, 600);
    }
    
    deleteField(fieldElement) {
        const fieldId = fieldElement.id;
        const fieldData = this.fieldsData.get(fieldId);
        
        if (!fieldData) return;
        
        if (confirm(`确定要删除字段 "${fieldData.config.label}" 吗？`)) {
            // 移除字段数据
            this.fieldsData.delete(fieldId);
            
            // 如果是当前选中的字段，清空属性面板
            if (this.selectedField === fieldElement) {
                this.selectedField = null;
                this.hideFieldProperties();
            }
            
            // 移除元素
            $(fieldElement).addClass('field-removing');
            setTimeout(() => {
                $(fieldElement).remove();
                
                // 如果没有字段了，显示提示
                if ($('#form-canvas .canvas-field').length === 0) {
                    this.showEmptyCanvas();
                }
            }, 300);
            
            this.markDirty();
        }
    }
    
    hideFieldProperties() {
        $('.field-properties').hide();
        $('.no-selection').show();
    }
    
    showEmptyCanvas() {
        $('#form-canvas').html(`
            <div class="canvas-drop-zone" id="canvas-drop-zone">
                <div class="drop-hint">
                    <div class="drop-icon">
                        <i class="icon-drag"></i>
                    </div>
                    <h3>从左侧拖拽字段到这里开始创建表单</h3>
                    <p>或者点击左侧字段图标快速添加到表单</p>
                    <div class="quick-start">
                        <button class="btn btn-primary" id="add-text-field">
                            <i class="icon-plus"></i> 添加文本字段
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        // 重新绑定快速添加事件
        $('#add-text-field').on('click', () => {
            const textField = document.querySelector('.field-item[data-type="text"]');
            this.addFieldFromLibrary(textField);
        });
    }
    
    switchTab(tab) {
        // 切换标签按钮状态
        $('.tab-button').removeClass('active');
        $(`.tab-button[data-tab="${tab}"]`).addClass('active');
        
        // 切换内容面板
        $('.tab-content').removeClass('active');
        $(`#${tab}-tab`).addClass('active');
    }
    

    // 继续 UformsBuilder 类的其余方法
    
    // 选项管理方法
    addOption() {
        const optionsList = $('#options-list');
        const optionItem = $(`
            <div class="option-item">
                <span class="option-drag" title="拖拽排序">⋮⋮</span>
                <input type="text" class="option-label" placeholder="选项标签" />
                <input type="text" class="option-value" placeholder="选项值" />
                <button type="button" class="option-remove" title="删除选项">×</button>
            </div>
        `);
        
        optionsList.append(optionItem);
        
        // 聚焦到新添加的标签输入框
        optionItem.find('.option-label').focus();
        
        // 重新初始化排序
        this.initOptionsSortable();
    }
    
    removeOption(e) {
        const optionItem = $(e.target).closest('.option-item');
        const optionsList = $('#options-list');
        
        // 确保至少保留一个选项
        if (optionsList.children('.option-item').length > 1) {
            optionItem.remove();
            this.updateOptions();
        } else {
            alert('至少需要保留一个选项');
        }
    }
    
    updateOptions() {
        if (!this.selectedField) return;
        
        const fieldId = this.selectedField.id;
        const fieldData = this.fieldsData.get(fieldId);
        if (!fieldData) return;
        
        const options = [];
        $('#options-list .option-item').each(function() {
            const label = $(this).find('.option-label').val().trim();
            const value = $(this).find('.option-value').val().trim();
            
            if (label) {
                options.push({
                    label: label,
                    value: value || label.toLowerCase().replace(/\s+/g, '_')
                });
            }
        });
        
        fieldData.config.options = options;
        this.updateFieldPreview(fieldId);
        this.markDirty();
    }
    
    initOptionsSortable() {
        const optionsList = document.querySelector('#options-list');
        if (optionsList && typeof Sortable !== 'undefined') {
            if (this.optionsSortable) {
                this.optionsSortable.destroy();
            }
            
            this.optionsSortable = Sortable.create(optionsList, {
                animation: 150,
                handle: '.option-drag',
                onEnd: () => this.updateOptions()
            });
        }
    }
    
    showBulkOptionsModal() {
        $('#bulk-options-modal').show();
        $('#bulk-options-text').focus();
    }
    
    switchBulkTab(tab) {
        $('.bulk-tab').removeClass('active');
        $(`.bulk-tab[data-tab="${tab}"]`).addClass('active');
        
        $('.bulk-tab-content').removeClass('active');
        $(`#${tab}-tab`).addClass('active');
    }
    
    loadPresetOptions(preset) {
        const options = this.presetOptions[preset];
        if (options) {
            const previewHtml = options.map(opt => `<div class="preset-option">${opt}</div>`).join('');
            $('#preset-preview').html(previewHtml);
            
            // 将预设选项填入文本框
            const textContent = options.join('\n');
            $('#bulk-options-text').val(textContent);
        }
    }
    
    applyBulkOptions() {
        const activeTab = $('.bulk-tab.active').data('tab');
        let optionsText = '';
        
        if (activeTab === 'text') {
            optionsText = $('#bulk-options-text').val();
        } else {
            // 预设选项已经填入了文本框
            optionsText = $('#bulk-options-text').val();
        }
        
        if (!optionsText.trim()) {
            alert('请输入选项内容');
            return;
        }
        
        // 解析选项
        const lines = optionsText.split('\n');
        const options = [];
        
        lines.forEach(line => {
            line = line.trim();
            if (line) {
                const parts = line.split('|');
                const label = parts[0].trim();
                const value = parts.length > 1 ? parts[1].trim() : label.toLowerCase().replace(/\s+/g, '_');
                
                if (label) {
                    options.push({ label, value });
                }
            }
        });
        
        if (options.length === 0) {
            alert('没有有效的选项');
            return;
        }
        
        // 清空现有选项并添加新选项
        $('#options-list').empty();
        
        options.forEach(option => {
            const optionItem = $(`
                <div class="option-item">
                    <span class="option-drag" title="拖拽排序">⋮⋮</span>
                    <input type="text" class="option-label" placeholder="选项标签" value="${option.label}" />
                    <input type="text" class="option-value" placeholder="选项值" value="${option.value}" />
                    <button type="button" class="option-remove" title="删除选项">×</button>
                </div>
            `);
            $('#options-list').append(optionItem);
        });
        
        this.updateOptions();
        this.initOptionsSortable();
        
        $('#bulk-options-modal').hide();
    }
    
    // 条件逻辑方法
    toggleConditionalLogic() {
        const enabled = $('#field-conditional').is(':checked');
        
        if (enabled) {
            this.showConditionalRules();
        } else {
            this.hideConditionalRules();
        }
        
        if (this.selectedField) {
            const fieldId = this.selectedField.id;
            const fieldData = this.fieldsData.get(fieldId);
            if (fieldData) {
                if (enabled) {
                    fieldData.config.conditional = { enabled: true, rules: [] };
                } else {
                    delete fieldData.config.conditional;
                }
                this.markDirty();
            }
        }
    }
    
    showConditionalRules() {
        $('#conditional-rules').show();
        this.updateConditionFieldOptions();
    }
    
    hideConditionalRules() {
        $('#conditional-rules').hide();
    }
    
    updateConditionFieldOptions() {
        const conditionField = $('#condition-field');
        conditionField.html('<option value="">选择字段</option>');
        
        // 获取当前字段之前的字段作为条件字段选项
        const currentFieldIndex = Array.from($('#form-canvas .canvas-field')).indexOf(this.selectedField);
        
        $('#form-canvas .canvas-field').each((index, element) => {
            if (index < currentFieldIndex) {
                const fieldId = element.id;
                const fieldData = this.fieldsData.get(fieldId);
                if (fieldData) {
                    const option = $(`<option value="${fieldId}">${fieldData.config.label}</option>`);
                    conditionField.append(option);
                }
            }
        });
    }
    
    addConditionRule() {
        const rulesContainer = $('.rule-builder');
        const ruleItem = $(`
            <div class="rule-item">
                <select class="rule-select condition-field">
                    <option value="">选择字段</option>
                </select>
                <select class="rule-select condition-operator">
                    <option value="equals">等于</option>
                    <option value="not_equals">不等于</option>
                    <option value="contains">包含</option>
                    <option value="not_contains">不包含</option>
                    <option value="empty">为空</option>
                    <option value="not_empty">不为空</option>
                    <option value="greater">大于</option>
                    <option value="less">小于</option>
                </select>
                <input type="text" class="rule-input condition-value" placeholder="比较值" />
                <button type="button" class="rule-remove" title="删除规则">×</button>
            </div>
        `);
        
        rulesContainer.append(ruleItem);
        
        // 填充字段选项
        const conditionField = ruleItem.find('.condition-field');
        $('#condition-field option').each(function() {
            conditionField.append($(this).clone());
        });
    }
    
    removeConditionRule(e) {
        $(e.target).closest('.rule-item').remove();
    }
    
    // 表单设置方法
    updateFormSetting(setting) {
        let value;
        const element = document.getElementById(setting.replace(/([A-Z])/g, '-$1').toLowerCase());
        
        if (element) {
            if (element.type === 'checkbox') {
                value = element.checked;
            } else {
                value = element.value;
            }
        }
        
        this.formSettings[setting] = value;
        this.markDirty();
    }
    
    handleSuccessActionChange() {
        const action = $('#success-action').val();
        
        // 隐藏所有特定设置
        $('#redirect-url-setting, #success-block-setting').hide();
        
        // 显示对应设置
        if (action === 'redirect') {
            $('#redirect-url-setting').show();
        } else if (action === 'block') {
            $('#success-block-setting').show();
        }
        
        this.updateFormSetting('successAction');
    }
    
    toggleAdminNotification() {
        const enabled = $('#admin-notification').is(':checked');
        
        if (enabled) {
            $('#admin-notification-settings').show();
        } else {
            $('#admin-notification-settings').hide();
        }
        
        this.updateFormSetting('adminNotification');
    }
    
    toggleUserNotification() {
        const enabled = $('#user-notification').is(':checked');
        
        if (enabled) {
            $('#user-notification-settings').show();
            this.updateUserEmailFieldOptions();
        } else {
            $('#user-notification-settings').hide();
        }
        
        this.updateFormSetting('userNotification');
    }
    
    updateUserEmailFieldOptions() {
        const select = $('#user-email-field');
        select.html('<option value="">选择邮箱字段</option>');
        
        // 找到所有邮箱类型的字段
        this.fieldsData.forEach((fieldData, fieldId) => {
            if (fieldData.type === 'email') {
                const option = $(`<option value="${fieldId}">${fieldData.config.label}</option>`);
                select.append(option);
            }
        });
    }
    
    // 样式设置方法
    updateFormStyle(property) {
        let value;
        const element = document.getElementById(property.replace(/([A-Z])/g, '-$1').toLowerCase());
        
        if (element) {
            value = element.value;
        }
        
        // 特殊处理颜色选择器
        if (property === 'primaryColor') {
            const colorInput = $('#primary-color');
            const textInput = $('#primary-color-text');
            
            if (element.id === 'primary-color') {
                textInput.val(value);
            } else {
                colorInput.val(value);
            }
            
            // 应用颜色到预览
            this.applyColorPreview(value);
        }
        
        if (!this.formData.style) {
            this.formData.style = {};
        }
        
        this.formData.style[property] = value;
        this.markDirty();
    }
    
    applyColorPreview(color) {
        // 在画布中应用颜色预览
        const style = `
            .canvas-field.selected { border-color: ${color} !important; }
            .btn-primary { background-color: ${color} !important; }
            .field-required { color: ${color} !important; }
        `;
        
        let styleElement = document.getElementById('uforms-preview-style');
        if (!styleElement) {
            styleElement = document.createElement('style');
            styleElement.id = 'uforms-preview-style';
            document.head.appendChild(styleElement);
        }
        
        styleElement.textContent = style;
    }
    
    initColorPickers() {
        // 同步颜色输入框
        $('#primary-color').on('input', function() {
            $('#primary-color-text').val(this.value);
        });
        
        $('#primary-color-text').on('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                $('#primary-color').val(this.value);
            }
        });
        
        // 初始化其他颜色选择器
        $('.color-grid input[type="color"]').on('input', function() {
            const property = this.id.replace(/-color$/, '');
            // 更新对应的样式预览
            this.updateColorPreview(property, this.value);
        }.bind(this));
    }
    
    updateColorPreview(property, color) {
        // 更新颜色预览逻辑
        const colorMap = {
            'bg': '--uform-bg-color',
            'text': '--uform-text-color',
            'border': '--uform-border-color',
            'error': '--uform-error-color',
            'success': '--uform-success-color',
            'warning': '--uform-warning-color'
        };
        
        const cssVar = colorMap[property];
        if (cssVar) {
            document.documentElement.style.setProperty(cssVar, color);
        }
    }
    
    initRangeSliders() {
        // 初始化所有范围滑块
        const self = this; // 保存当前类实例的引用
        $('input[type="range"]').each(function() {
            const slider = $(this);
            const valueDisplay = slider.siblings('.range-value');
            
            slider.on('input', function() {
                const value = this.value;
                let unit = 'px';
                
                // 根据slider的ID确定单位
                if (this.id.includes('spacing') || this.id.includes('padding') || this.id.includes('radius')) {
                    unit = 'px';
                }
                
                valueDisplay.text(value + unit);
                
                // 更新对应的样式
                const property = this.id.replace(/[-_]/g, '');
                self.updateRangePreview(property, value); // 使用保存的引用调用函数
            });
            
            // 初始化显示
            slider.trigger('input');
        });
    }
    
    updateRangePreview(property, value) {
        // 实时更新样式预览
        const styleMap = {
            'fieldspacing': '--uform-field-spacing',
            'formpadding': '--uform-form-padding',
            'inputborderradius': '--uform-input-border-radius',
            'inputborderwidth': '--uform-input-border-width',
            'inputheight': '--uform-input-height'
        };
        
        const cssVar = styleMap[property];
        if (cssVar) {
            let unit = 'px';
            document.documentElement.style.setProperty(cssVar, value + unit);
        }
    }
    
    // 预览功能
    switchPreviewMode(mode) {
        $('.preview-btn').removeClass('active');
        $(`.preview-btn[data-view="${mode}"]`).addClass('active');
        
        const canvas = $('#form-canvas');
        
        // 移除所有预览模式class
        canvas.removeClass('preview-desktop preview-tablet preview-mobile');
        
        // 添加对应的预览模式class
        canvas.addClass(`preview-${mode}`);
        
        // 更新缩放比例显示
        const scales = {
            desktop: '100%',
            tablet: '768px',
            mobile: '375px'
        };
        
        $('#canvas-scale').text(scales[mode]);
    }
    
    toggleGrid() {
        $('#form-canvas').toggleClass('show-grid');
        $('#toggle-grid').toggleClass('active');
    }
    
    clearForm() {
        if (confirm('确定要清空整个表单吗？此操作不可撤销。')) {
            // 清空所有字段数据
            this.fieldsData.clear();
            this.selectedField = null;
            this.fieldCounter = 0;
            
            // 清空画布
            this.showEmptyCanvas();
            
            // 隐藏属性面板
            this.hideFieldProperties();
            
            this.markDirty();
        }
    }
    
    previewForm() {
        // 保存当前表单数据
        const formData = this.collectFormData();
        
        // 生成预览HTML
        this.generatePreviewHTML(formData).then(html => {
            // 创建预览窗口
            const previewWindow = window.open('', 'uform-preview', 'width=800,height=600');
            previewWindow.document.write(html);
            previewWindow.document.close();
        });
    }
    
    generatePreviewHTML(formData) {
        return new Promise((resolve) => {
            // 构建预览HTML
            const html = `
                <!DOCTYPE html>
                <html lang="zh-CN">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>${formData.title || '表单预览'}</title>
                    <link rel="stylesheet" href="${window.uformsConfig.pluginUrl}/assets/css/uforms.css">
                    <style>
                        body { margin: 0; padding: 20px; background: #f5f5f5; }
                        .preview-notice { 
                            background: #e3f2fd; 
                            border: 1px solid #2196f3; 
                            color: #1976d2; 
                            padding: 10px; 
                            margin-bottom: 20px; 
                            border-radius: 4px; 
                            text-align: center; 
                        }
                        ${formData.customCSS || ''}
                    </style>
                </head>
                <body>
                    <div class="preview-notice">
                        <strong>这是表单预览</strong> - 所有字段均已禁用，无法提交
                    </div>
                    ${this.generateFormHTML(formData, true)}
                </body>
                </html>
            `;
            
            resolve(html);
        });
    }
    
    generateFormHTML(formData, isPreview = false) {
        let html = `<div class="uform uform-${formData.theme || 'default'}" data-form-name="${formData.name}">`;
        
        // 表单标题和描述
        if (formData.title) {
            html += `<div class="uform-header">`;
            html += `<h2 class="uform-title">${formData.title}</h2>`;
            if (formData.description) {
                html += `<div class="uform-description">${formData.description}</div>`;
            }
            html += `</div>`;
        }
        
        // 表单内容
        html += `<form class="uform-form" ${isPreview ? '' : 'method="post"'}>`;
        
        // 渲染字段
        const sortedFields = Array.from(this.fieldsData.values()).sort((a, b) => {
            const aIndex = Array.from($('#form-canvas .canvas-field')).findIndex(el => el.id === a.id);
            const bIndex = Array.from($('#form-canvas .canvas-field')).findIndex(el => el.id === b.id);
            return aIndex - bIndex;
        });
        
        sortedFields.forEach(fieldData => {
            html += this.renderFormField(fieldData, isPreview);
        });
        
        // 提交按钮
        if (!isPreview) {
            html += `<div class="uform-actions">`;
            html += `<button type="submit" class="uform-submit btn btn-primary">${formData.submitText || '提交'}</button>`;
            html += `</div>`;
        } else {
            html += `<div class="uform-actions">`;
            html += `<button type="button" class="uform-submit btn btn-primary" disabled>${formData.submitText || '提交'}</button>`;
            html += `</div>`;
        }
        
        html += `</form></div>`;
        
        return html;
    }
    
    renderFormField(fieldData, isPreview = false) {
        const { type, config } = fieldData;
        const disabled = isPreview ? 'disabled' : '';
        const required = config.required ? 'required' : '';
        const cssClass = config.cssClass ? ` ${config.cssClass}` : '';
        const cssId = config.cssId ? ` id="${config.cssId}"` : '';
        
        let html = `<div class="uform-field uform-field-${type} width-${config.width || 'full'}${cssClass}"${cssId}>`;
        
        // 字段标签
        if (config.label && !['heading', 'paragraph', 'divider', 'hidden'].includes(type)) {
            html += `<label class="uform-label">`;
            html += config.label;
            if (config.required) {
                html += ` <span class="required-mark">*</span>`;
            }
            html += `</label>`;
        }
        
        // 字段输入控件
        html += this.renderFieldInput(fieldData, disabled, required);
        
        // 帮助文本
        if (config.help) {
            html += `<div class="uform-help">${config.help}</div>`;
        }
        
        html += `</div>`;
        
        return html;
    }
    
    renderFieldInput(fieldData, disabled, required) {
        const { type, config } = fieldData;
        const placeholder = config.placeholder ? ` placeholder="${config.placeholder}"` : '';
        const defaultValue = config.defaultValue || config.value || '';
        
        switch (type) {
            case 'text':
            case 'email':
            case 'url':
            case 'tel':
            case 'password':
                return `<input type="${type}" name="${config.name}" value="${defaultValue}"${placeholder} ${required} ${disabled} class="uform-input">`;
                
            case 'textarea':
                return `<textarea name="${config.name}" rows="${config.rows || 4}"${placeholder} ${required} ${disabled} class="uform-textarea">${defaultValue}</textarea>`;
                
            case 'number':
                const min = config.min !== null && config.min !== undefined ? ` min="${config.min}"` : '';
                const max = config.max !== null && config.max !== undefined ? ` max="${config.max}"` : '';
                const step = config.step ? ` step="${config.step}"` : '';
                return `<input type="number" name="${config.name}" value="${defaultValue}"${placeholder}${min}${max}${step} ${required} ${disabled} class="uform-input">`;
                
            case 'select':
                let selectHtml = `<select name="${config.name}" ${required} ${disabled} class="uform-select">`;
                if (!config.required) {
                    selectHtml += `<option value="">${config.placeholder || '请选择'}</option>`;
                }
                if (config.options) {
                    config.options.forEach(option => {
                        const selected = option.value === defaultValue ? ' selected' : '';
                        selectHtml += `<option value="${option.value}"${selected}>${option.label}</option>`;
                    });
                }
                selectHtml += `</select>`;
                return selectHtml;
                
            case 'radio':
                let radioHtml = `<div class="uform-radio-group">`;
                if (config.options) {
                    config.options.forEach((option, index) => {
                        const checked = option.value === defaultValue ? ' checked' : '';
                        radioHtml += `<label class="uform-radio-label">`;
                        radioHtml += `<input type="radio" name="${config.name}" value="${option.value}"${checked} ${required} ${disabled}>`;
                        radioHtml += `<span class="radio-text">${option.label}</span>`;
                        radioHtml += `</label>`;
                    });
                }
                radioHtml += `</div>`;
                return radioHtml;
                
            case 'checkbox':
                let checkboxHtml = `<div class="uform-checkbox-group">`;
                if (config.options) {
                    config.options.forEach((option, index) => {
                        checkboxHtml += `<label class="uform-checkbox-label">`;
                        checkboxHtml += `<input type="checkbox" name="${config.name}[]" value="${option.value}" ${disabled}>`;
                        checkboxHtml += `<span class="checkbox-text">${option.label}</span>`;
                        checkboxHtml += `</label>`;
                    });
                }
                checkboxHtml += `</div>`;
                return checkboxHtml;
                
            case 'file':
                const multiple = config.multiple ? ' multiple' : '';
                const accept = config.accept ? ` accept="${config.accept}"` : '';
                return `<input type="file" name="${config.name}" ${required} ${disabled}${multiple}${accept} class="uform-file">`;
                
            case 'date':
                return `<input type="date" name="${config.name}" value="${defaultValue}" ${required} ${disabled} class="uform-input">`;
                
            case 'time':
                return `<input type="time" name="${config.name}" value="${defaultValue}" ${required} ${disabled} class="uform-input">`;
                
            case 'datetime':
                return `<input type="datetime-local" name="${config.name}" value="${defaultValue}" ${required} ${disabled} class="uform-input">`;
                
            case 'range':
                const rangeMin = config.min || 0;
                const rangeMax = config.max || 100;
                const rangeStep = config.step || 1;
                const rangeDefault = config.defaultValue || rangeMin;
                return `<input type="range" name="${config.name}" min="${rangeMin}" max="${rangeMax}" step="${rangeStep}" value="${rangeDefault}" ${disabled} class="uform-range">`;
                
            case 'color':
                return `<input type="color" name="${config.name}" value="${config.defaultValue || '#000000'}" ${disabled} class="uform-color">`;
                
            case 'heading':
                const level = config.level || 'h3';
                return `<${level} class="uform-heading">${config.text || '标题'}</${level}>`;
                
            case 'paragraph':
                return `<div class="uform-paragraph">${config.text || '段落文本'}</div>`;
                
            case 'divider':
                return `<hr class="uform-divider">`;
                
            case 'hidden':
                return `<input type="hidden" name="${config.name}" value="${config.value || ''}">`;
                
            default:
                return `<div class="uform-unknown">未知字段类型: ${type}</div>`;
        }
    }
    
    // 保存功能
    saveForm(status = 'draft') {
        const formData = this.collectFormData();
        formData.status = status;
        
        // 验证必填字段
        if (!formData.name || !formData.title) {
            alert('表单名称和标题不能为空');
            this.switchTab('form');
            return;
        }
        
        if (this.fieldsData.size === 0) {
            alert('表单至少需要包含一个字段');
            return;
        }
        
        // 显示保存状态
        this.setSaveStatus('saving', '正在保存...');
        
        // 发送保存请求
        $.ajax({
            url: window.uformsConfig.ajaxUrl,
            method: 'POST',
            data: {
                action: 'save_form',
                form_id: window.uformsConfig.formId,
                form_name: formData.name,
                form_title: formData.title,
                form_description: formData.description,
                form_status: formData.status,
                form_config: JSON.stringify(formData.config),
                form_settings: JSON.stringify(formData.settings),
                fields_config: JSON.stringify(formData.fields)
            },
            success: (response) => {
                if (response.success) {
                    this.setSaveStatus('success', status === 'published' ? '发布成功！' : '保存成功！');
                    this.isDirty = false;
                    
                    // 更新表单ID
                    if (response.form_id && !window.uformsConfig.formId) {
                        window.uformsConfig.formId = response.form_id;
                    }
                    
                    // 如果是发布，显示获取代码按钮
                    if (status === 'published') {
                        $('#get-code').show();
                    }
                    
                    // 3秒后恢复正常状态
                    setTimeout(() => {
                        this.setSaveStatus('saved', '已保存');
                    }, 3000);
                } else {
                    this.setSaveStatus('error', response.message || '保存失败');
                    alert('保存失败：' + (response.message || '未知错误'));
                }
            },
            error: () => {
                this.setSaveStatus('error', '保存失败');
                alert('保存失败，请检查网络连接');
            }
        });
    }
    
    collectFormData() {
        const formSettings = {
            name: $('#form-name').val(),
            title: $('#form-title').val(),
            description: $('#form-description').val(),
            submitText: $('#submit-text').val(),
            successMessage: $('#success-message').val(),
            successAction: $('#success-action').val(),
            redirectUrl: $('#redirect-url').val(),
            successBlock: $('#success-block').val(),
            ajaxSubmit: $('#ajax-submit').is(':checked'),
            preventDuplicate: $('#prevent-duplicate').is(':checked'),
            enableCaptcha: $('#enable-captcha').is(':checked'),
            enableHoneypot: $('#enable-honeypot').is(':checked'),
            submitLimit: $('#submit-limit').val(),
            maxSubmissions: $('#max-submissions').val(),
            adminNotification: {
                enabled: $('#admin-notification').is(':checked'),
                recipients: $('#admin-email').val(),
                subject: $('#admin-subject').val(),
                message: $('#admin-message').val()
            },
            userNotification: {
                enabled: $('#user-notification').is(':checked'),
                emailField: $('#user-email-field').val(),
                subject: $('#user-subject').val(),
                message: $('#user-message').val()
            },
            webhook: {
                enabled: $('#enable-webhook').is(':checked'),
                url: $('#webhook-url').val(),
                secret: $('#webhook-secret').val()
            }
        };
        
        const formConfig = {
            theme: $('#form-theme').val(),
            primaryColor: $('#primary-color').val(),
            formWidth: $('#form-width').val(),
            formMaxWidth: $('#form-max-width').val(),
            labelPosition: $('#label-position').val(),
            fieldSpacing: $('#field-spacing').val(),
            formPadding: $('#form-padding').val(),
            inputBorderRadius: $('#input-border-radius').val(),
            inputBorderWidth: $('#input-border-width').val(),
            inputHeight: $('#input-height').val(),
            colors: {
                background: $('#bg-color').val(),
                text: $('#text-color').val(),
                border: $('#border-color').val(),
                error: $('#error-color').val(),
                success: $('#success-color').val(),
                warning: $('#warning-color').val()
            },
            customCSS: $('#custom-css').val()
        };
        
        // 收集字段数据
        const fields = [];
        $('#form-canvas .canvas-field').each((index, element) => {
            const fieldId = element.id;
            const fieldData = this.fieldsData.get(fieldId);
            if (fieldData) {
                fields.push({
                    ...fieldData.config,
                    type: fieldData.type,
                    sortOrder: index
                });
            }
        });
        
        return {
            ...formSettings,
            config: formConfig,
            settings: formSettings,
            fields: fields
        };
    }
    
    setSaveStatus(status, message) {
        const statusElement = $('#save-status');
        const iconMap = {
            saving: 'icon-loading',
            success: 'icon-check',
            error: 'icon-error',
            saved: 'icon-check'
        };
        
        statusElement.removeClass('saving success error saved')
                   .addClass(status)
                   .html(`<i class="${iconMap[status]}"></i> ${message}`);
    }
    
    setupAutoSave() {
        // 监听表单变化，3秒后自动保存草稿
        $(document).on('input change', '.property-item input, .property-item textarea, .property-item select', () => {
            if (!this.isDirty) return;
            
            clearTimeout(this.autoSaveTimer);
            this.autoSaveTimer = setTimeout(() => {
                if (this.isDirty) {
                    this.autoSaveDraft();
                }
            }, 3000);
        });
    }
    
    autoSaveDraft() {
        if (!window.uformsConfig.formId) return; // 新表单不自动保存
        
        this.setSaveStatus('saving', '自动保存中...');
        
        const formData = this.collectFormData();
        formData.status = 'draft';
        
        $.ajax({
            url: window.uformsConfig.ajaxUrl,
            method: 'POST',
            data: {
                action: 'save_form',
                form_id: window.uformsConfig.formId,
                ...formData
            },
            success: (response) => {
                if (response.success) {
                    this.setSaveStatus('saved', '已自动保存');
                    this.isDirty = false;
                }
            },
            error: () => {
                // 自动保存失败时不显示错误，避免打扰用户
                this.setSaveStatus('saved', '已保存');
            }
        });
    }
    
    markDirty() {
        this.isDirty = true;
        this.setSaveStatus('unsaved', '有未保存的更改');
    }
    
    // 更多功能方法继续...
    
    updateFieldOrder() {
        // 更新字段排序后的处理逻辑
        this.markDirty();
    }
    
    loadExistingForm() {
        // 加载现有表单数据
        if (window.uformsConfig.existingFields && window.uformsConfig.existingFields.length > 0) {
            // 清空画布
            $('#form-canvas').empty();
            
            window.uformsConfig.existingFields.forEach((field, index) => {
                const fieldConfig = JSON.parse(field.field_config || '{}');
                const fieldId = this.generateFieldId();
                
                // 合并字段配置
                const mergedConfig = {
                    ...fieldConfig,
                    label: field.field_label,
                    name: field.field_name,
                    required: field.is_required == 1
                };
                
                // 创建字段元素
                const fieldElement = this.createFieldElement(fieldId, field.field_type, mergedConfig);
                $('#form-canvas').append(fieldElement);
                
                // 保存字段数据
                this.fieldsData.set(fieldId, {
                    id: fieldId,
                    type: field.field_type,
                    config: mergedConfig
                });
            });
        }
        
        // 加载表单设置
        if (window.uformsConfig.existingSettings) {
            const settings = window.uformsConfig.existingSettings;
            
            // 填充表单设置
            Object.keys(settings).forEach(key => {
                const element = document.getElementById(key.replace(/([A-Z])/g, '-$1').toLowerCase());
                if (element) {
                    if (element.type === 'checkbox') {
                        element.checked = settings[key];
                    } else {
                        element.value = settings[key];
                    }
                }
            });
        }
    }
    
    // 键盘快捷键
    handleKeyboardShortcuts(e) {
        // Ctrl+S 保存
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            this.saveForm('draft');
        }
        
        // Ctrl+Shift+S 发布
        if (e.ctrlKey && e.shiftKey && e.key === 'S') {
            e.preventDefault();
            this.saveForm('published');
        }
        
        // Delete 删除选中字段
        if (e.key === 'Delete' && this.selectedField) {
            this.deleteField(this.selectedField);
        }
        
        // Ctrl+D 复制字段
        if (e.ctrlKey && e.key === 'd' && this.selectedField) {
            e.preventDefault();
            this.duplicateField(this.selectedField);
        }
        
        // Esc 取消选择
        if (e.key === 'Escape') {
            if (this.selectedField) {
                $('.canvas-field').removeClass('selected');
                this.selectedField = null;
                this.hideFieldProperties();
            }
        }
    }
    
    // 获取代码功能
    showCodeModal() {
        if (!window.uformsConfig.formId) {
            alert('请先保存并发布表单');
            return;
        }
        
        const formUrl = `${window.uformsConfig.siteUrl}uforms/form/${window.uformsConfig.formId}`;
        const iframeCode = `<iframe src="${formUrl}" width="100%" height="600px" frameborder="0"></iframe>`;
        const shortcode = `[uforms id="${window.uformsConfig.formId}"]`;
        const apiUrl = `${window.uformsConfig.siteUrl}uforms/api/submit`;
        
        $('#form-link').val(formUrl);
        $('#iframe-code').val(iframeCode);
        $('#shortcode').val(shortcode);
        $('#api-url').val(apiUrl);
        
        $('#code-modal').show();
    }
    
    updateIframeCode() {
        const width = $('#iframe-width').val() || '100%';
        const height = $('#iframe-height').val() || '600px';
        const formUrl = $('#form-link').val();
        
        const iframeCode = `<iframe src="${formUrl}" width="${width}" height="${height}" frameborder="0"></iframe>`;
        $('#iframe-code').val(iframeCode);
    }
    
    copyCode(buttonId) {
        let targetId;
        
        switch (buttonId) {
            case 'copy-link':
                targetId = 'form-link';
                break;
            case 'copy-iframe':
                targetId = 'iframe-code';
                break;
            case 'copy-shortcode':
                targetId = 'shortcode';
                break;
            case 'copy-api':
                targetId = 'api-url';
                break;
        }
        
        if (targetId) {
            const element = document.getElementById(targetId);
            element.select();
            element.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                
                // 更新按钮文本
                const button = document.getElementById(buttonId);
                const originalText = button.textContent;
                button.textContent = '已复制';
                button.classList.add('copied');
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            } catch (err) {
                alert('复制失败，请手动选择并复制');
            }
        }
    }
    
    saveAsTemplate() {
        const formData = this.collectFormData();
        const templateName = prompt('请输入模板名称：');
        
        if (!templateName) return;
        
        $.ajax({
            url: window.uformsConfig.ajaxUrl,
            method: 'POST',
            data: {
                action: 'save_template',
                template_name: templateName,
                template_data: JSON.stringify(formData)
            },
            success: (response) => {
                if (response.success) {
                    alert('模板保存成功！');
                } else {
                    alert('模板保存失败：' + response.message);
                }
            },
            error: () => {
                alert('模板保存失败，请重试');
            }
        });
    }
    
    switchPreviewDevice(device) {
        $('.preview-device').removeClass('active');
        $(`.preview-device[data-device="${device}"]`).addClass('active');
        
        const iframe = $('#preview-iframe');
        const container = $('#preview-container');
        
        // 移除现有设备类
        container.removeClass('device-desktop device-tablet device-mobile');
        
        // 添加新设备类
        container.addClass(`device-${device}`);
        
        // 设置iframe尺寸
        const sizes = {
            desktop: { width: '100%', height: '600px' },
            tablet: { width: '768px', height: '600px' },
            mobile: { width: '375px', height: '600px' }
        };
        
        const size = sizes[device];
        iframe.css(size);
    }
}

// 初始化构建器
$(document).ready(function() {
    // 检查必要的依赖
    if (typeof Sortable === 'undefined') {
        console.warn('Sortable.js 未加载，拖拽功能将不可用');
    }
    
    // 初始化表单构建器
    window.uformsBuilder = new UformsBuilder();
    
    // 绑定全局事件
    $(document).on('click', function(e) {
        // 点击空白区域取消字段选择
        if (!$(e.target).closest('.canvas-field, .properties-panel').length) {
            $('.canvas-field').removeClass('selected');
            if (window.uformsBuilder) {
                window.uformsBuilder.selectedField = null;
                window.uformsBuilder.hideFieldProperties();
            }
        }
    });
});



