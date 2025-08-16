// 表单构建器核心类 - 完整增强版本
class UformsBuilder {
    constructor() {
        this.selectedField = null;
        this.formData = window.uformsConfig.existingConfig || {};
        this.formSettings = window.uformsConfig.existingSettings || {};
        this.fieldsData = new Map();
        this.fieldCounter = 0;
        this.isDirty = false;
        this.autoSaveTimer = null;
        this.dragDropHandler = null;
        this.fieldCategories = new Map();
        
        this.init();
    }
    
    init() {
        this.initFieldCategories();
        this.bindEvents();
        this.initSortable();
        this.initDragDrop();
        this.initPresetOptions();
        this.loadExistingForm();
        this.setupAutoSave();
        this.initColorPickers();
        this.initRangeSliders();
        this.initTooltips();
        this.enableStylePreview();
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
        this.bindPropertyEvents();
        
        // 画布工具栏事件
        this.bindCanvasToolbarEvents();
        
        // 表单设置事件
        this.bindFormSettingsEvents();
        
        // 样式设置事件
        this.bindStyleEvents();
        
        // 底部操作事件
        this.bindActionEvents();
        
        // 模态框事件
        this.bindModalEvents();
        
        // 键盘快捷键
        $(document).on('keydown', (e) => this.handleKeyboardShortcuts(e));
        
        // 页面离开提醒
        $(window).on('beforeunload', () => {
            if (this.isDirty) {
                return '您有未保存的更改，确定要离开吗？';
            }
        });
    }
    
    bindPropertyEvents() {
        // 基本属性
        $('#field-label').on('input', () => this.updateFieldProperty('label'));
        $('#field-name').on('input', () => this.updateFieldProperty('name'));
        $('#field-placeholder').on('input', () => this.updateFieldProperty('placeholder'));
        $('#field-default').on('input', () => this.updateFieldProperty('default'));
        $('#field-help').on('input', () => this.updateFieldProperty('help'));
        $('#field-required').on('change', () => this.updateFieldProperty('required'));
        $('#field-css-class').on('input', () => this.updateFieldProperty('cssClass'));
        $('#field-css-id').on('input', () => this.updateFieldProperty('cssId'));
        $('#field-width').on('change', () => this.updateFieldProperty('width'));
        
        // 自定义宽度
        $('#field-width').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#custom-width-input').show();
            } else {
                $('#custom-width-input').hide();
            }
        });
        
        $('#field-custom-width, #field-width-unit').on('input change', () => {
            this.updateFieldProperty('customWidth');
        });
        
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
        
        // 文件上传设置
        $('#file-types').on('input', () => this.updateFieldProperty('fileTypes'));
        $('#file-max-size').on('input', () => this.updateFieldProperty('fileMaxSize'));
        $('#file-multiple').on('change', () => this.updateFieldProperty('fileMultiple'));
        $('#file-max-count').on('input', () => this.updateFieldProperty('fileMaxCount'));
        
        // 数字字段设置
        $('#number-min').on('input', () => this.updateFieldProperty('numberMin'));
        $('#number-max').on('input', () => this.updateFieldProperty('numberMax'));
        $('#number-step').on('input', () => this.updateFieldProperty('numberStep'));
        
        // 日期时间设置
        $('#date-min').on('input', () => this.updateFieldProperty('dateMin'));
        $('#date-max').on('input', () => this.updateFieldProperty('dateMax'));
        $('#date-format').on('change', () => this.updateFieldProperty('dateFormat'));
    }
    
    bindCanvasToolbarEvents() {
        $('.preview-btn').on('click', (e) => {
            this.switchPreviewMode(e.target.dataset.view);
        });
        
        $('#clear-form').on('click', () => this.clearForm());
        $('#preview-form, #preview-form-btn').on('click', () => this.previewForm());
        $('#toggle-grid').on('click', () => this.toggleGrid());
        
        // 快速添加按钮
        $(document).on('click', '#add-text-field', () => {
            const textField = document.querySelector('.field-item[data-type="text"]');
            this.addFieldFromLibrary(textField);
        });
    }
    
    bindFormSettingsEvents() {
        $('#form-name').on('input', () => this.updateFormSetting('name'));
        $('#form-title').on('input', () => this.updateFormSetting('title'));
        $('#form-description').on('input', () => this.updateFormSetting('description'));
        $('#submit-text').on('input', () => this.updateFormSetting('submitText'));
        $('#success-message').on('input', () => this.updateFormSetting('successMessage'));
        $('#success-action').on('change', () => this.handleSuccessActionChange());
        $('#redirect-url').on('input', () => this.updateFormSetting('redirectUrl'));
        $('#success-block').on('input', () => this.updateFormSetting('successBlock'));
        $('#ajax-submit').on('change', () => this.updateFormSetting('ajaxSubmit'));
        $('#prevent-duplicate').on('change', () => this.updateFormSetting('preventDuplicate'));
        
        // 安全设置事件
        $('#enable-captcha').on('change', () => this.updateFormSetting('enableCaptcha'));
        $('#enable-honeypot').on('change', () => this.updateFormSetting('enableHoneypot'));
        $('#submit-limit').on('input', () => this.updateFormSetting('submitLimit'));
        $('#max-submissions').on('input', () => this.updateFormSetting('maxSubmissions'));
        
        // 邮件通知事件
        $('#admin-notification').on('change', () => this.toggleAdminNotification());
        $('#admin-email').on('input', () => this.updateFormSetting('adminEmail'));
        $('#admin-subject').on('input', () => this.updateFormSetting('adminSubject'));
        $('#admin-message').on('input', () => this.updateFormSetting('adminMessage'));
        
        $('#user-notification').on('change', () => this.toggleUserNotification());
        $('#user-email-field').on('change', () => this.updateFormSetting('userEmailField'));
        $('#user-subject').on('input', () => this.updateFormSetting('userSubject'));
        $('#user-message').on('input', () => this.updateFormSetting('userMessage'));
        
        // Webhook设置
        $('#enable-webhook').on('change', () => this.toggleWebhook());
        $('#webhook-url').on('input', () => this.updateFormSetting('webhookUrl'));
        $('#webhook-secret').on('input', () => this.updateFormSetting('webhookSecret'));
    }
    
    bindStyleEvents() {
        $('#form-theme').on('change', () => this.updateFormStyle('theme'));
        $('#primary-color, #primary-color-text').on('input', () => this.updateFormStyle('primaryColor'));
        $('#form-width').on('input', () => this.updateFormStyle('formWidth'));
        $('#form-max-width').on('input', () => this.updateFormStyle('formMaxWidth'));
        $('#label-position').on('change', () => this.updateFormStyle('labelPosition'));
        
        // 布局设置
        $('#field-spacing').on('input', () => this.updateFormStyle('fieldSpacing'));
        $('#form-padding').on('input', () => this.updateFormStyle('formPadding'));
        $('#input-border-radius').on('input', () => this.updateFormStyle('inputBorderRadius'));
        $('#input-border-width').on('input', () => this.updateFormStyle('inputBorderWidth'));
        $('#input-height').on('input', () => this.updateFormStyle('inputHeight'));
        
        // 颜色设置
        $('.color-grid input[type="color"]').on('input', function() {
            const property = this.id.replace('-color', '');
            this.updateFormStyle(property + 'Color');
        }.bind(this));
        
        // 自定义CSS
        $('#custom-css').on('input', () => this.updateFormStyle('customCSS'));
        $('#preview-css').on('click', () => this.previewCustomCSS());
        $('#reset-css').on('click', () => this.resetCustomCSS());
    }
    
    bindActionEvents() {
        $('#save-draft').on('click', () => this.saveForm('draft'));
        $('#save-template').on('click', () => this.saveAsTemplate());
        $('#publish-form').on('click', () => this.saveForm('published'));
        $('#get-code').on('click', () => this.showCodeModal());
    }
    
    bindModalEvents() {
        $('.modal-close').on('click', (e) => {
            $(e.target).closest('.modal').hide();
        });
        
        // 点击模态框背景关闭
        $('.modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
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
    }
    
    initSortable() {
        if (typeof Sortable !== 'undefined') {
            // 画布排序
            this.canvasSortable = Sortable.create(document.getElementById('form-canvas'), {
                group: 'form-fields',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                handle: '.field-drag-handle',
                filter: '.canvas-drop-zone',
                onStart: (evt) => {
                    $('#form-canvas').addClass('sorting');
                    this.highlightDropZones();
                },
                onEnd: (evt) => {
                    $('#form-canvas').removeClass('sorting');
                    this.clearDropZoneHighlight();
                    this.updateFieldOrder();
                    this.markDirty();
                },
                onAdd: (evt) => {
                    // 处理从字段库拖拽添加的字段
                    this.handleFieldDrop(evt);
                },
                onMove: (evt) => {
                    // 禁止拖拽到某些区域
                    return !evt.related.classList.contains('no-drop');
                }
            });
            
            // 字段库拖拽
            $('.field-items').each(function() {
                Sortable.create(this, {
                    group: {
                        name: 'form-fields',
                        pull: 'clone',
                        put: false
                    },
                    sort: false,
                    animation: 150,
                    onStart: () => {
                        this.highlightDropZones();
                    }.bind(this),
                    onEnd: () => {
                        this.clearDropZoneHighlight();
                    }.bind(this)
                });
            }.bind(this));
            
            // 初始化嵌套容器排序
            this.initNestedSortable();
        }
    }
    
    initNestedSortable() {
        // 为列容器初始化排序
        $(document).on('DOMNodeInserted', '.column', function() {
            if (!$(this).data('sortable-initialized')) {
                Sortable.create(this, {
                    group: 'form-fields',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: () => {
                        this.updateFieldOrder();
                        this.markDirty();
                    }
                });
                $(this).data('sortable-initialized', true);
            }
        }.bind(this));
        
        // 为重复器初始化排序
        $(document).on('DOMNodeInserted', '.repeater-body', function() {
            if (!$(this).data('sortable-initialized')) {
                Sortable.create(this, {
                    group: 'form-fields',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: () => {
                        this.updateFieldOrder();
                        this.markDirty();
                    }
                });
                $(this).data('sortable-initialized', true);
            }
        }.bind(this));
    }
    
    initDragDrop() {
        // 初始化HTML5拖拽API作为fallback
        $('.field-item').each(function() {
            this.draggable = true;
            
            $(this).on('dragstart', function(e) {
                const fieldType = this.dataset.type;
                e.originalEvent.dataTransfer.setData('application/json', JSON.stringify({
                    type: 'field',
                    fieldType: fieldType
                }));
                e.originalEvent.dataTransfer.effectAllowed = 'copy';
                
                $(this).addClass('dragging');
                this.highlightDropZones();
            }.bind(this));
            
            $(this).on('dragend', function() {
                $(this).removeClass('dragging');
                this.clearDropZoneHighlight();
            }.bind(this));
        }.bind(this));
        
        // 画布拖拽接收
        $('#form-canvas').on('dragover', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'copy';
            $(this).addClass('drag-over');
        });
        
        $('#form-canvas').on('dragleave', function() {
            $(this).removeClass('drag-over');
        });
        
        $('#form-canvas').on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
            
            try {
                const data = JSON.parse(e.originalEvent.dataTransfer.getData('application/json'));
                if (data.type === 'field') {
                    this.addFieldFromDrop(data.fieldType, e);
                }
            } catch (err) {
                console.warn('Invalid drag data:', err);
            }
        }.bind(this));
    }
    
    highlightDropZones() {
        $('.canvas-drop-zone, .column, .repeater-body').addClass('drop-zone-highlight');
    }
    
    clearDropZoneHighlight() {
        $('.drop-zone-highlight').removeClass('drop-zone-highlight');
    }
    
    handleFieldDrop(evt) {
        const fieldType = evt.item.dataset.type;
        if (fieldType) {
            // 移除克隆的元素
            evt.item.remove();
            
            // 添加真正的字段
            const insertIndex = evt.newIndex;
            this.addFieldAtPosition(fieldType, insertIndex);
        }
    }
    
    addFieldFromDrop(fieldType, dropEvent) {
        const fieldConfig = this.getDefaultFieldConfig(fieldType);
        const fieldId = this.generateFieldId();
        const fieldElement = this.createFieldElement(fieldId, fieldType, fieldConfig);
        
        // 确定插入位置
        const dropY = dropEvent.originalEvent.clientY;
        const canvasFields = $('#form-canvas .canvas-field');
        let insertAfter = null;
        
        canvasFields.each(function() {
            const rect = this.getBoundingClientRect();
            if (dropY > rect.top + rect.height / 2) {
                insertAfter = this;
            }
        });
        
        // 如果画布为空，移除提示
        if ($('#form-canvas .canvas-drop-zone').length > 0) {
            $('#form-canvas').empty();
        }
        
        // 插入字段
        if (insertAfter) {
            $(insertAfter).after(fieldElement);
        } else {
            $('#form-canvas').prepend(fieldElement);
        }
        
        // 保存字段数据
        this.fieldsData.set(fieldId, {
            id: fieldId,
            type: fieldType,
            config: fieldConfig
        });
        
        // 选中新字段
        this.selectField(fieldElement[0]);
        this.markDirty();
        
        // 添加动画
        fieldElement.addClass('field-added');
        setTimeout(() => {
            fieldElement.removeClass('field-added');
        }, 600);
    }
    
    addFieldAtPosition(fieldType, position) {
        const fieldConfig = this.getDefaultFieldConfig(fieldType);
        const fieldId = this.generateFieldId();
        const fieldElement = this.createFieldElement(fieldId, fieldType, fieldConfig);
        
        // 如果画布为空，移除提示
        if ($('#form-canvas .canvas-drop-zone').length > 0) {
            $('#form-canvas').empty();
        }
        
        const canvasFields = $('#form-canvas .canvas-field');
        if (position >= canvasFields.length) {
            $('#form-canvas').append(fieldElement);
        } else {
            $(canvasFields[position]).before(fieldElement);
        }
        
        // 保存字段数据
        this.fieldsData.set(fieldId, {
            id: fieldId,
            type: fieldType,
            config: fieldConfig
        });
        
        this.selectField(fieldElement[0]);
        this.markDirty();
        
        // 添加动画
        fieldElement.addClass('field-added');
        setTimeout(() => {
            fieldElement.removeClass('field-added');
        }, 600);
    }
    
    initPresetOptions() {
        this.presetOptions = {
            yesno: [
                { label: '是', value: 'yes' },
                { label: '否', value: 'no' }
            ],
            gender: [
                { label: '男', value: 'male' },
                { label: '女', value: 'female' },
                { label: '其他', value: 'other' }
            ],
            rating: [
                { label: '非常不满意', value: '1' },
                { label: '不满意', value: '2' },
                { label: '一般', value: '3' },
                { label: '满意', value: '4' },
                { label: '非常满意', value: '5' }
            ],
            education: [
                { label: '小学', value: 'primary' },
                { label: '初中', value: 'junior' },
                { label: '高中', value: 'senior' },
                { label: '大专', value: 'college' },
                { label: '本科', value: 'bachelor' },
                { label: '硕士', value: 'master' },
                { label: '博士', value: 'doctor' }
            ],
            cities: [
                { label: '北京', value: 'beijing' },
                { label: '上海', value: 'shanghai' },
                { label: '广州', value: 'guangzhou' },
                { label: '深圳', value: 'shenzhen' },
                { label: '杭州', value: 'hangzhou' },
                { label: '南京', value: 'nanjing' },
                { label: '成都', value: 'chengdu' },
                { label: '武汉', value: 'wuhan' },
                { label: '西安', value: 'xian' },
                { label: '重庆', value: 'chongqing' }
            ],
            provinces: [
                { label: '北京市', value: 'beijing' },
                { label: '天津市', value: 'tianjin' },
                { label: '河北省', value: 'hebei' },
                { label: '山西省', value: 'shanxi' },
                { label: '内蒙古自治区', value: 'neimenggu' },
                { label: '辽宁省', value: 'liaoning' },
                { label: '吉林省', value: 'jilin' },
                { label: '黑龙江省', value: 'heilongjiang' },
                { label: '上海市', value: 'shanghai' },
                { label: '江苏省', value: 'jiangsu' },
                { label: '浙江省', value: 'zhejiang' },
                { label: '安徽省', value: 'anhui' },
                { label: '福建省', value: 'fujian' },
                { label: '江西省', value: 'jiangxi' },
                { label: '山东省', value: 'shandong' },
                { label: '河南省', value: 'henan' },
                { label: '湖北省', value: 'hubei' },
                { label: '湖南省', value: 'hunan' },
                { label: '广东省', value: 'guangdong' },
                { label: '广西壮族自治区', value: 'guangxi' },
                { label: '海南省', value: 'hainan' },
                { label: '重庆市', value: 'chongqing' },
                { label: '四川省', value: 'sichuan' },
                { label: '贵州省', value: 'guizhou' },
                { label: '云南省', value: 'yunnan' },
                { label: '西藏自治区', value: 'xizang' },
                { label: '陕西省', value: 'shaanxi' },
                { label: '甘肃省', value: 'gansu' },
                { label: '青海省', value: 'qinghai' },
                { label: '宁夏回族自治区', value: 'ningxia' },
                { label: '新疆维吾尔自治区', value: 'xinjiang' },
                { label: '台湾省', value: 'taiwan' },
                { label: '香港特别行政区', value: 'hongkong' },
                { label: '澳门特别行政区', value: 'macao' }
            ],
            countries: [
                { label: '中国', value: 'china' },
                { label: '美国', value: 'usa' },
                { label: '日本', value: 'japan' },
                { label: '英国', value: 'uk' },
                { label: '法国', value: 'france' },
                { label: '德国', value: 'germany' },
                { label: '加拿大', value: 'canada' },
                { label: '澳大利亚', value: 'australia' },
                { label: '韩国', value: 'korea' },
                { label: '新加坡', value: 'singapore' }
            ],
            numbers: [
                { label: '1', value: '1' },
                { label: '2', value: '2' },
                { label: '3', value: '3' },
                { label: '4', value: '4' },
                { label: '5', value: '5' },
                { label: '6', value: '6' },
                { label: '7', value: '7' },
                { label: '8', value: '8' },
                { label: '9', value: '9' },
                { label: '10', value: '10' }
            ]
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
                width: 'full',
                minLength: null,
                maxLength: null,
                pattern: '',
                errorMessage: ''
            },
            textarea: {
                label: '多行文本',
                name: 'textarea_field_' + (this.fieldCounter + 1),
                placeholder: '请输入内容',
                required: false,
                width: 'full',
                rows: 4,
                minLength: null,
                maxLength: null
            },
            email: {
                label: '邮箱地址',
                name: 'email_field_' + (this.fieldCounter + 1),
                placeholder: '请输入邮箱',
                required: false,
                width: 'full',
                pattern: '^[^@]+@[^@]+\\.[^@]+$',
                errorMessage: '请输入有效的邮箱地址'
            },
            url: {
                label: '网址',
                name: 'url_field_' + (this.fieldCounter + 1),
                placeholder: '请输入网址',
                required: false,
                width: 'full',
                pattern: '^https?://.+',
                errorMessage: '请输入有效的网址'
            },
            tel: {
                label: '电话号码',
                name: 'tel_field_' + (this.fieldCounter + 1),
                placeholder: '请输入电话号码',
                required: false,
                width: 'full',
                pattern: '^[0-9-+\\s()]+$',
                errorMessage: '请输入有效的电话号码'
            },
            password: {
                label: '密码',
                name: 'password_field_' + (this.fieldCounter + 1),
                placeholder: '请输入密码',
                required: false,
                width: 'full',
                minLength: 6,
                maxLength: null
            },
            select: {
                label: '下拉选择',
                name: 'select_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                options: [
                    { label: '选项1', value: 'option1' },
                    { label: '选项2', value: 'option2' }
                ],
                multiple: false,
                allowOther: false
            },
            radio: {
                label: '单选按钮',
                name: 'radio_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                options: [
                    { label: '选项1', value: 'option1' },
                    { label: '选项2', value: 'option2' }
                ],
                allowOther: false
            },
            checkbox: {
                label: '复选框',
                name: 'checkbox_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                options: [
                    { label: '选项1', value: 'option1' },
                    { label: '选项2', value: 'option2' }
                ],
                allowOther: false,
                minSelect: null,
                maxSelect: null
            },
            file: {
                label: '文件上传',
                name: 'file_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                accept: 'image/*,.pdf,.doc,.docx',
                maxSize: 10,
                multiple: false,
                maxCount: 5
            },
            date: {
                label: '日期选择',
                name: 'date_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                minDate: null,
                maxDate: null,
                dateFormat: 'YYYY-MM-DD'
            },
            time: {
                label: '时间选择',
                name: 'time_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                format: '24',
                step: 1
            },
            datetime: {
                label: '日期时间',
                name: 'datetime_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                minDate: null,
                maxDate: null,
                dateFormat: 'YYYY-MM-DD HH:mm'
            },
            number: {
                label: '数字输入',
                name: 'number_field_' + (this.fieldCounter + 1),
                placeholder: '请输入数字',
                required: false,
                width: 'full',
                min: null,
                max: null,
                step: 1,
                decimalPlaces: null
            },
            range: {
                label: '数值滑块',
                name: 'range_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                min: 0,
                max: 100,
                step: 1,
                defaultValue: 50,
                showValue: true
            },
            rating: {
                label: '评分',
                name: 'rating_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                max: 5,
                icon: 'star',
                allowHalf: false,
                showText: false
            },
            color: {
                label: '颜色选择',
                name: 'color_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                defaultValue: '#000000',
                format: 'hex'
            },
            signature: {
                label: '签名板',
                name: 'signature_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                canvasWidth: 400,
                canvasHeight: 200,
                penColor: '#000000',
                backgroundColor: '#ffffff'
            },
            heading: {
                label: '标题文本',
                name: 'heading_' + (this.fieldCounter + 1),
                text: '章节标题',
                level: 'h3',
                width: 'full',
                align: 'left',
                color: '#333333'
            },
            paragraph: {
                label: '段落文本',
                name: 'paragraph_' + (this.fieldCounter + 1),
                text: '这里是段落文本内容，可以用于说明或描述。',
                width: 'full',
                align: 'left',
                color: '#666666'
            },
            divider: {
                label: '分割线',
                name: 'divider_' + (this.fieldCounter + 1),
                style: 'solid',
                width: 'full',
                thickness: 1,
                color: '#dddddd',
                marginTop: 20,
                marginBottom: 20
            },
            html: {
                label: 'HTML代码',
                name: 'html_' + (this.fieldCounter + 1),
                content: '<p>在这里输入自定义HTML代码</p>',
                width: 'full'
            },
            hidden: {
                label: '隐藏字段',
                name: 'hidden_field_' + (this.fieldCounter + 1),
                value: '',
                width: 'full'
            },
            // 布局字段
            columns: {
                label: '多列布局',
                name: 'columns_' + (this.fieldCounter + 1),
                columnCount: 2,
                columnWidths: ['50%', '50%'],
                gap: 15,
                width: 'full'
            },
            // 特殊字段
            calendar: {
                label: '日历预约',
                name: 'calendar_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                availableDays: [1, 2, 3, 4, 5], // 周一到周五
                timeSlots: ['09:00', '10:00', '11:00', '14:00', '15:00', '16:00'],
                duration: 60, // 分钟
                advance: 1 // 提前预约天数
            },
            cascade: {
                label: '级联选择',
                name: 'cascade_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                levels: [
                    { label: '省份', placeholder: '请选择省份' },
                    { label: '城市', placeholder: '请选择城市' },
                    { label: '区域', placeholder: '请选择区域' }
                ],
                data: {}
            },
            tags: {
                label: '标签选择',
                name: 'tags_field_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                maxTags: null,
                allowCustom: true,
                suggestions: [],
                delimiter: ','
            },
            repeater: {
                label: '重复器',
                name: 'repeater_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                minItems: 1,
                maxItems: 10,
                addButtonText: '添加项目',
                removeButtonText: '删除',
                fields: []
            },
            // 系统字段
            user_name: {
                label: '用户姓名',
                name: 'user_name_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                readonly: true,
                source: 'current_user.displayName'
            },
            user_email: {
                label: '用户邮箱',
                name: 'user_email_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                readonly: true,
                source: 'current_user.mail'
            },
            page_url: {
                label: '页面URL',
                name: 'page_url_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                readonly: true,
                source: 'page.url'
            },
            page_title: {
                label: '页面标题',
                name: 'page_title_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                readonly: true,
                source: 'page.title'
            },
            timestamp: {
                label: '时间戳',
                name: 'timestamp_' + (this.fieldCounter + 1),
                required: false,
                width: 'full',
                readonly: true,
                source: 'current_time',
                format: 'Y-m-d H:i:s'
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
                        <button class="field-action field-edit" title="编辑字段">
                            <i class="icon-edit"></i>
                        </button>
                        <button class="field-action field-copy" title="复制字段">
                            <i class="icon-copy"></i>
                        </button>
                        <button class="field-action field-delete" title="删除字段">
                            <i class="icon-trash"></i>
                        </button>
                        <button class="field-action field-move" title="移动字段">
                            <i class="icon-move"></i>
                        </button>
                    </div>
                </div>
                <div class="field-body">
                    ${this.renderFieldPreview(fieldType, config)}
                </div>
                <div class="field-properties-preview">
                    ${this.renderFieldPropertiesPreview(config)}
                </div>
                <div class="field-status valid" title="字段配置有效"></div>
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
                return `<input type="${fieldType}" placeholder="${config.placeholder || ''}" value="${config.defaultValue || ''}" disabled />`;
                
            case 'textarea':
                return `<textarea rows="${config.rows || 4}" placeholder="${config.placeholder || ''}" disabled>${config.defaultValue || ''}</textarea>`;
                
            case 'number':
                return `<input type="number" placeholder="${config.placeholder || ''}" 
                              min="${config.min || ''}" max="${config.max || ''}" 
                              step="${config.step || 1}" value="${config.defaultValue || ''}" disabled />`;
                
            case 'range':
                return `<input type="range" min="${config.min || 0}" max="${config.max || 100}" 
                              value="${config.defaultValue || 50}" step="${config.step || 1}" disabled />
                        ${config.showValue ? `<div class="range-output">${config.defaultValue || 50}</div>` : ''}`;
                
            case 'select':
                let selectOptions = config.options ? config.options.map(opt => 
                    `<option value="${opt.value}">${opt.label}</option>`
                ).join('') : '';
                return `<select ${config.multiple ? 'multiple' : ''} disabled>
                    ${!config.multiple ? '<option value="">请选择</option>' : ''}
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
                return `<input type="file" ${config.multiple ? 'multiple' : ''} 
                              accept="${config.accept || '*'}" disabled />
                        <div class="file-info">
                            最大大小: ${config.maxSize || 10}MB
                            ${config.multiple ? `, 最多${config.maxCount || 5}个文件` : ''}
                        </div>`;
                
            case 'date':
                return `<input type="date" value="${config.defaultValue || ''}" 
                              min="${config.minDate || ''}" max="${config.maxDate || ''}" disabled />`;
                
            case 'time':
                return `<input type="time" value="${config.defaultValue || ''}" disabled />`;
                
            case 'datetime':
                return `<input type="datetime-local" value="${config.defaultValue || ''}" 
                              min="${config.minDate || ''}" max="${config.maxDate || ''}" disabled />`;
                
            case 'color':
                return `<input type="color" value="${config.defaultValue || '#000000'}" disabled />`;
                
            case 'rating':
                let stars = '';
                for (let i = 1; i <= (config.max || 5); i++) {
                    stars += `<span class="rating-star" data-rating="${i}">★</span>`;
                }
                return `<div class="rating-group">${stars}</div>`;
                
            case 'signature':
                return `<div class="signature-preview" style="width: 200px; height: 100px; border: 1px solid #ddd; 
                              display: flex; align-items: center; justify-content: center; color: #999;">
                    签名区域 (${config.canvasWidth}×${config.canvasHeight})
                </div>`;
                
            case 'heading':
                const level = config.level || 'h3';
                return `<${level} class="form-heading" style="text-align: ${config.align || 'left'}; 
                              color: ${config.color || '#333'};">
                    ${config.text || '标题文本'}
                </${level}>`;
                
            case 'paragraph':
                return `<p class="form-paragraph" style="text-align: ${config.align || 'left'}; 
                              color: ${config.color || '#666'};">
                    ${config.text || '段落文本'}
                </p>`;
                
            case 'divider':
                return `<hr class="form-divider" style="border-style: ${config.style || 'solid'}; 
                              border-width: ${config.thickness || 1}px 0 0 0; 
                              border-color: ${config.color || '#ddd'}; 
                              margin: ${config.marginTop || 20}px 0 ${config.marginBottom || 20}px 0;" />`;
                
            case 'html':
                return `<div class="html-content">${config.content || '<p>HTML内容</p>'}</div>`;
                
            case 'columns':
                const columnCount = config.columnCount || 2;
                const columnWidths = config.columnWidths || Array(columnCount).fill(`${100/columnCount}%`);
                let columnsHtml = '<div class="column-container">';
                for (let i = 0; i < columnCount; i++) {
                    columnsHtml += `<div class="column" style="width: ${columnWidths[i]};">
                        <div class="column-header">列 ${i + 1}</div>
                        <div class="column-drop-zone">拖拽字段到这里</div>
                    </div>`;
                }
                columnsHtml += '</div>';
                return columnsHtml;
                
            case 'repeater':
                return `<div class="repeater-container">
                    <div class="repeater-header">
                        <span class="repeater-title">${config.label}</span>
                        <div class="repeater-actions">
                            <button class="repeater-add" title="添加项目">+</button>
                        </div>
                    </div>
                    <div class="repeater-body">
                        <div class="repeater-item">
                            <div class="repeater-item-header">
                                <span class="repeater-item-title">项目 #1</span>
                                <button class="repeater-item-remove">×</button>
                            </div>
                            <div class="repeater-item-content">拖拽字段到这里构建重复项模板</div>
                        </div>
                    </div>
                </div>`;
                
            case 'calendar':
                return `<div class="calendar-preview">
                    <div style="border: 1px solid #ddd; border-radius: 4px; padding: 15px; text-align: center;">
                        <div style="font-weight: 500; margin-bottom: 10px;">📅 日历预约</div>
                        <div style="font-size: 12px; color: #666;">
                            可预约时间: ${config.timeSlots ? config.timeSlots.join(', ') : '9:00-17:00'}
                        </div>
                    </div>
                </div>`;
                
            case 'cascade':
                const levels = config.levels || [{ label: '一级', placeholder: '请选择' }];
                let cascadeHtml = '<div class="cascade-group">';
                levels.forEach((level, i) => {
                    cascadeHtml += `<select disabled style="margin-right: 10px;">
                        <option>${level.placeholder || '请选择'}</option>
                    </select>`;
                });
                cascadeHtml += '</div>';
                return cascadeHtml;
                
            case 'tags':
                return `<div class="tags-input" style="border: 1px solid #ddd; padding: 8px; min-height: 40px; border-radius: 4px;">
                    <span class="tag-item" style="background: #e3f2fd; padding: 2px 6px; border-radius: 3px; margin: 2px; font-size: 12px;">标签1</span>
                    <span class="tag-item" style="background: #e3f2fd; padding: 2px 6px; border-radius: 3px; margin: 2px; font-size: 12px;">标签2</span>
                </div>`;
                
            case 'hidden':
                return `<div class="hidden-field-info">隐藏字段: ${config.name} = "${config.value || ''}"</div>`;
                
            // 系统字段预览
            case 'user_name':
            case 'user_email':
            case 'page_url':
            case 'page_title':
            case 'timestamp':
                return `<input type="text" value="[系统自动获取: ${config.source}]" disabled style="font-style: italic; color: #999;" />`;
                
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
        
        if (config.pattern) {
            properties.push(`<span class="prop-item">验证规则: ${config.pattern}</span>`);
        }
        
        if (config.conditional) {
            properties.push(`<span class="prop-item">条件逻辑: 启用</span>`);
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
            signature: '签名',
            heading: '标题',
            paragraph: '段落',
            divider: '分割线',
            html: 'HTML',
            hidden: '隐藏',
            columns: '多列',
            calendar: '日历',
            cascade: '级联',
            tags: '标签',
            repeater: '重复器',
            user_name: '用户名',
            user_email: '用户邮箱',
            page_url: '页面URL',
            page_title: '页面标题',
            timestamp: '时间戳'
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
        
        // 处理自定义宽度
        if (fieldData.config.width === 'custom') {
            $('#custom-width-input').show();
            $('#field-custom-width').val(fieldData.config.customWidth || '');
            $('#field-width-unit').val(fieldData.config.widthUnit || 'px');
        } else {
            $('#custom-width-input').hide();
        }
        
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
            $('#allow-other').prop('checked', config.allowOther || false);
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
    
    // 重新初始化排序
    this.initOptionsSortable();
}

updateFieldProperty(property) {
    if (!this.selectedField) return;
    
    const fieldId = this.selectedField.id;
    const fieldData = this.fieldsData.get(fieldId);
    if (!fieldData) return;
    
    // 获取新值
    let value;
    let elementId = `field-${property.replace(/([A-Z])/g, '-$1').toLowerCase()}`;
    const element = document.getElementById(elementId);
    
    if (element) {
        if (element.type === 'checkbox') {
            value = element.checked;
        } else {
            value = element.value;
        }
    }
    
    // 特殊处理自定义宽度
    if (property === 'customWidth') {
        const width = $('#field-custom-width').val();
        const unit = $('#field-width-unit').val();
        fieldData.config.customWidth = width;
        fieldData.config.widthUnit = unit;
    } else {
        // 更新字段数据
        fieldData.config[property] = value;
    }
    
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
    
    if (property === 'width' && value === 'custom') {
        $('#custom-width-input').show();
    } else if (property === 'width') {
        $('#custom-width-input').hide();
    }
    
    // 更新预览
    this.updateFieldPreview(fieldId);
    
    // 更新属性预览
    this.updateFieldPropertiesPreview(fieldId);
    
    // 验证字段配置
    this.validateFieldConfig(fieldId);
    
    this.markDirty();
}

generateFieldName(label) {
    const name = label.replace(/[^\w\u4e00-\u9fa5]/g, '_')
                      .replace(/_{2,}/g, '_')
                      .replace(/^_|_$/g, '')
                      .toLowerCase();
    
    // 确保唯一性
    let finalName = name || 'field';
    let counter = 1;
    const existingNames = Array.from(this.fieldsData.values()).map(f => f.config.name);
    
    while (existingNames.includes(finalName)) {
        finalName = `${name || 'field'}_${counter}`;
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

validateFieldConfig(fieldId) {
    const fieldData = this.fieldsData.get(fieldId);
    if (!fieldData) return;
    
    const fieldElement = document.getElementById(fieldId);
    const statusElement = fieldElement.querySelector('.field-status');
    
    let isValid = true;
    let issues = [];
    
    // 验证必填项
    if (!fieldData.config.label) {
        isValid = false;
        issues.push('缺少字段标签');
    }
    
    if (!fieldData.config.name) {
        isValid = false;
        issues.push('缺少字段名称');
    }
    
    // 验证选项类字段的选项
    if (['select', 'radio', 'checkbox'].includes(fieldData.type)) {
        if (!fieldData.config.options || fieldData.config.options.length === 0) {
            isValid = false;
            issues.push('缺少选项');
        }
    }
    
    // 验证数字字段的范围
    if (['number', 'range'].includes(fieldData.type)) {
        const min = parseFloat(fieldData.config.min);
        const max = parseFloat(fieldData.config.max);
        if (!isNaN(min) && !isNaN(max) && min >= max) {
            isValid = false;
            issues.push('最小值不能大于等于最大值');
        }
    }
    
    // 更新状态指示器
    statusElement.className = 'field-status ' + (isValid ? 'valid' : 'invalid');
    statusElement.title = isValid ? '字段配置有效' : '字段配置有误: ' + issues.join(', ');
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
    } else if (action.classList.contains('field-move')) {
        this.showFieldMoveModal(fieldElement);
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
    newConfig.name = this.generateFieldName(newConfig.name + '_copy');
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

showFieldMoveModal(fieldElement) {
    const fieldId = fieldElement.id;
    const fieldData = this.fieldsData.get(fieldId);
    if (!fieldData) return;
    
    // 创建移动模态框
    const modal = $(`
        <div class="modal" id="move-field-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>移动字段: ${fieldData.config.label}</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="move-options">
                        <button class="btn move-to-top">移动到顶部</button>
                        <button class="btn move-up">向上移动</button>
                        <button class="btn move-down">向下移动</button>
                        <button class="btn move-to-bottom">移动到底部</button>
                    </div>
                    <div class="move-position">
                        <label>移动到指定位置:</label>
                        <input type="number" id="move-position-input" min="1" max="${$('#form-canvas .canvas-field').length}" value="1">
                        <button class="btn btn-primary" id="move-to-position">确定移动</button>
                    </div>
                </div>
            </div>
        </div>
    `);
    
    $('body').append(modal);
    modal.show();
    
    // 绑定移动事件
    modal.find('.move-to-top').on('click', () => {
        this.moveFieldTo(fieldElement, 0);
        modal.remove();
    });
    
    modal.find('.move-up').on('click', () => {
        const currentIndex = $('#form-canvas .canvas-field').index(fieldElement);
        if (currentIndex > 0) {
            this.moveFieldTo(fieldElement, currentIndex - 1);
        }
        modal.remove();
    });
    
    modal.find('.move-down').on('click', () => {
        const currentIndex = $('#form-canvas .canvas-field').index(fieldElement);
        const maxIndex = $('#form-canvas .canvas-field').length - 1;
        if (currentIndex < maxIndex) {
            this.moveFieldTo(fieldElement, currentIndex + 1);
        }
        modal.remove();
    });
    
    modal.find('.move-to-bottom').on('click', () => {
        this.moveFieldTo(fieldElement, -1);
        modal.remove();
    });
    
    modal.find('#move-to-position').on('click', () => {
        const position = parseInt(modal.find('#move-position-input').val()) - 1;
        this.moveFieldTo(fieldElement, position);
        modal.remove();
    });
    
    modal.find('.modal-close').on('click', () => {
        modal.remove();
    });
}

moveFieldTo(fieldElement, position) {
    const $field = $(fieldElement);
    const $canvas = $('#form-canvas');
    const $fields = $canvas.find('.canvas-field');
    
    $field.detach();
    
    if (position === -1 || position >= $fields.length) {
        // 移动到底部
        $canvas.append($field);
    } else if (position === 0) {
        // 移动到顶部
        $canvas.prepend($field);
    } else {
        // 移动到指定位置
        $fields.eq(position - 1).after($field);
    }
    
    this.updateFieldOrder();
    this.markDirty();
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
        this.showTooltip(e.target, '至少需要保留一个选项');
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
    this.validateFieldConfig(fieldId);
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
        const previewHtml = options.map(opt => 
            `<div class="preset-option">${opt.label}</div>`
        ).join('');
        $('#preset-preview').html(previewHtml);
        
        // 将预设选项填入文本框
        const textContent = options.map(opt => 
            opt.value !== opt.label ? `${opt.label}|${opt.value}` : opt.label
        ).join('\n');
        $('#bulk-options-text').val(textContent);
    }
}

applyBulkOptions() {
    const activeTab = $('.bulk-tab.active').data('tab');
    let optionsText = $('#bulk-options-text').val();
    
    if (!optionsText.trim()) {
        this.showMessage('请输入选项内容', 'warning');
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
        this.showMessage('没有有效的选项', 'warning');
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
    this.showMessage('选项批量添加成功', 'success');
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

loadConditionalRules(conditional) {
    if (conditional.rules && conditional.rules.length > 0) {
        // 清空现有规则
        $('.rule-builder .rule-item').remove();
        
        // 添加保存的规则
        conditional.rules.forEach(rule => {
            this.addConditionRule();
            const ruleItem = $('.rule-builder .rule-item').last();
            ruleItem.find('.condition-field').val(rule.field);
            ruleItem.find('.condition-operator').val(rule.operator);
            ruleItem.find('.condition-value').val(rule.value);
        });
    }
}

// 表单设置方法
updateFormSetting(setting) {
    let value;
    let elementId = setting.replace(/([A-Z])/g, '-$1').toLowerCase();
    const element = document.getElementById(elementId);
    
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

toggleWebhook() {
    const enabled = $('#enable-webhook').is(':checked');
    
    if (enabled) {
        $('#webhook-settings').show();
    } else {
        $('#webhook-settings').hide();
    }
    
    this.updateFormSetting('enableWebhook');
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
    let elementId = property.replace(/([A-Z])/g, '-$1').toLowerCase();
    const element = document.getElementById(elementId);
    
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
    
    // 特殊处理主题
    if (property === 'theme') {
        this.applyThemePreview(value);
    }
    
    if (!this.formData.style) {
        this.formData.style = {};
    }
    
    this.formData.style[property] = value;
    this.markDirty();
    
    // 实时预览样式变化
    this.applyStylePreview(property, value);
}

applyColorPreview(color) {
    // 在画布中应用颜色预览
    document.documentElement.style.setProperty('--preview-primary-color', color);
    
    const style = `
        .canvas-field.selected { border-color: ${color} !important; }
        .btn-primary { background-color: ${color} !important; }
        .field-required { background-color: ${color} !important; }
        .field-type { background-color: ${color} !important; }
    `;
    
    this.updatePreviewStyle(style);
}

applyThemePreview(theme) {
    // 移除现有主题类
    $('#form-canvas').removeClass((index, className) => {
        return (className.match(/(^|\s)theme-\S+/g) || []).join(' ');
    });
    
    // 添加新主题类
    $('#form-canvas').addClass(`theme-${theme}`);
    
    // 更新主题预览
    this.updateThemePreview(theme);
}

updateThemePreview(theme) {
    const preview = $('#theme-preview');
    const previewBox = preview.find('.preview-box');
    
    const themes = {
        default: { bg: '#ffffff', border: '#dddddd', text: '#333333' },
        minimal: { bg: '#ffffff', border: '#eeeeee', text: '#333333' },
        modern: { bg: '#ffffff', border: '#ddd6fe', text: '#333333' },
        classic: { bg: '#ffffff', border: '#bdc3c7', text: '#2c3e50' },
        bootstrap: { bg: '#ffffff', border: '#ced4da', text: '#333333' },
        material: { bg: '#ffffff', border: '#e0e0e0', text: '#333333' }
    };
    
    const themeConfig = themes[theme] || themes.default;
    previewBox.css({
        'background-color': themeConfig.bg,
        'border-color': themeConfig.border,
        'color': themeConfig.text
    });
}

applyStylePreview(property, value) {
    const propertyMap = {
        'fieldSpacing': '--preview-field-spacing',
        'formPadding': '--preview-form-padding',
        'inputBorderRadius': '--preview-input-border-radius',
        'inputBorderWidth': '--preview-input-border-width',
        'inputHeight': '--preview-input-height',
        'backgroundColor': '--preview-bg-color',
        'textColor': '--preview-text-color',
        'borderColor': '--preview-border-color',
        'errorColor': '--preview-error-color',
        'successColor': '--preview-success-color',
        'warningColor': '--preview-warning-color'
    };
    
    const cssVar = propertyMap[property];
    if (cssVar) {
        let cssValue = value;
        
        // 为尺寸属性添加单位
        if (['fieldSpacing', 'formPadding', 'inputBorderRadius', 'inputBorderWidth', 'inputHeight'].includes(property)) {
            cssValue = value + 'px';
        }
        
        document.documentElement.style.setProperty(cssVar, cssValue);
    }
}

updatePreviewStyle(style) {
    let styleElement = document.getElementById('uforms-preview-style');
    if (!styleElement) {
        styleElement = document.createElement('style');
        styleElement.id = 'uforms-preview-style';
        document.head.appendChild(styleElement);
    }
    
    styleElement.textContent = style;
}

previewCustomCSS() {
    const customCSS = $('#custom-css').val();
    this.updatePreviewStyle(customCSS);
    this.showMessage('CSS预览已应用', 'info');
}

resetCustomCSS() {
    $('#custom-css').val('');
    this.updatePreviewStyle('');
    this.showMessage('CSS已重置', 'info');
    this.markDirty();
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
        this.updateColorPreview(property, this.value);
    }.bind(this));
}

updateColorPreview(property, color) {
    const colorMap = {
        'bg': '--preview-bg-color',
        'text': '--preview-text-color',
        'border': '--preview-border-color',
        'error': '--preview-error-color',
        'success': '--preview-success-color',
        'warning': '--preview-warning-color'
    };
    
    const cssVar = colorMap[property];
    if (cssVar) {
        document.documentElement.style.setProperty(cssVar, color);
    }
}

initRangeSliders() {
    $('input[type="range"]').each(function() {
        const slider = $(this);
        const valueDisplay = slider.siblings('.range-value');
        
        slider.on('input', function() {
            const value = this.value;
            let unit = 'px';
            
            if (this.id.includes('spacing') || this.id.includes('padding') || this.id.includes('radius')) {
                unit = 'px';
            }
            
            valueDisplay.text(value + unit);
            
            const property = this.id.replace(/[-_]/g, '');
            this.updateRangePreview(property, value);
        }.bind(this));
        
        slider.trigger('input');
    });
}

updateRangePreview(property, value) {
    const styleMap = {
        'fieldspacing': '--preview-field-spacing',
        'formpadding': '--preview-form-padding',
        'inputborderradius': '--preview-input-border-radius',
        'inputborderwidth': '--preview-input-border-width',
        'inputheight': '--preview-input-height'
    };
    
    const cssVar = styleMap[property];
    if (cssVar) {
        document.documentElement.style.setProperty(cssVar, value + 'px');
    }
}

initTooltips() {
    // 初始化工具提示
    $(document).on('mouseenter', '[title]', function() {
        const element = $(this);
        const title = element.attr('title');
        
        if (title && !element.data('tooltip-initialized')) {
            element.data('original-title', title);
            element.removeAttr('title');
            
            const tooltip = $(`<div class="tooltip">${title}</div>`);
            $('body').append(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.css({
                top: rect.bottom + 5,
                left: rect.left + rect.width / 2 - tooltip.outerWidth() / 2
            });
            
            element.data('tooltip', tooltip);
            element.data('tooltip-initialized', true);
        }
    });
    
    $(document).on('mouseleave', '[data-tooltip-initialized]', function() {
        const tooltip = $(this).data('tooltip');
        if (tooltip) {
            tooltip.remove();
            $(this).removeData('tooltip');
            $(this).removeData('tooltip-initialized');
            $(this).attr('title', $(this).data('original-title'));
        }
    });
}

showTooltip(element, message) {
    const tooltip = $(`<div class="tooltip">${message}</div>`);
    $('body').append(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.css({
        top: rect.bottom + 5,
        left: rect.left + rect.width / 2 - tooltip.outerWidth() / 2
    });
    
    setTimeout(() => {
        tooltip.remove();
    }, 2000);
}

enableStylePreview() {
    $('#form-canvas').addClass('style-preview-enabled');
}

// 预览功能
switchPreviewMode(mode) {
    $('.preview-btn').removeClass('active');
    $(`.preview-btn[data-view="${mode}"]`).addClass('active');
    
    const canvas = $('#form-canvas');
    
    canvas.removeClass('preview-desktop preview-tablet preview-mobile');
    canvas.addClass(`preview-${mode}`);
    
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
        this.fieldsData.clear();
        this.selectedField = null;
        this.fieldCounter = 0;
        
        this.showEmptyCanvas();
        this.hideFieldProperties();
        
        this.markDirty();
    }
}

previewForm() {
    const formData = this.collectFormData();
    
    this.generatePreviewHTML(formData).then(html => {
        const previewWindow = window.open('', 'uform-preview', 'width=800,height=600,scrollbars=yes');
        previewWindow.document.write(html);
        previewWindow.document.close();
    });
}

generatePreviewHTML(formData) {
    return new Promise((resolve) => {
        const html = `
            <!DOCTYPE html>
            <html lang="zh-CN">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>${formData.title || '表单预览'}</title>
                <link rel="stylesheet" href="${window.uformsConfig.pluginUrl}/assets/css/uforms.css">
                <style>
                    body { 
                        margin: 0; 
                        padding: 20px; 
                        background: #f5f5f5; 
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    }
                    .preview-notice { 
                        background: #e3f2fd; 
                        border: 1px solid #2196f3; 
                        color: #1976d2; 
                        padding: 12px 16px; 
                        margin-bottom: 20px; 
                        border-radius: 4px; 
                        text-align: center;
                        font-size: 14px;
                    }
                    .preview-notice i {
                        margin-right: 6px;
                    }
                    ${this.generatePreviewCSS(formData)}
                    ${formData.customCSS || ''}
                </style>
            </head>
            <body>
                <div class="preview-notice">
                    <i>ℹ</i> <strong>这是表单预览</strong> - 所有字段均已禁用，无法提交
                </div>
                ${this.generateFormHTML(formData, true)}
            </body>
            </html>
        `;
        
        resolve(html);
    });
}

generatePreviewCSS(formData) {
    const style = formData.style || {};
    
    return `
        :root {
            --form-primary-color: ${style.primaryColor || '#3788d8'};
            --form-bg-color: ${style.backgroundColor || '#ffffff'};
            --form-text-color: ${style.textColor || '#333333'};
            --form-border-color: ${style.borderColor || '#dddddd'};
            --form-error-color: ${style.errorColor || '#e74c3c'};
            --form-success-color: ${style.successColor || '#27ae60'};
            --form-warning-color: ${style.warningColor || '#f39c12'};
            --form-field-spacing: ${style.fieldSpacing || 20}px;
            --form-padding: ${style.formPadding || 20}px;
            --form-input-border-radius: ${style.inputBorderRadius || 4}px;
            --form-input-border-width: ${style.inputBorderWidth || 1}px;
            --form-input-height: ${style.inputHeight || 40}px;
        }
        
        .uform {
            max-width: ${style.formMaxWidth || '800px'};
            width: ${style.formWidth || '100%'};
            margin: 0 auto;
            background: var(--form-bg-color);
            padding: var(--form-padding);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .uform-field {
            margin-bottom: var(--form-field-spacing);
        }
        
        .uform-input,
        .uform-textarea,
        .uform-select {
            height: var(--form-input-height);
            border-radius: var(--form-input-border-radius);
            border-width: var(--form-input-border-width);
            border-color: var(--form-border-color);
            color: var(--form-text-color);
        }
        
        .uform-submit {
            background-color: var(--form-primary-color);
            border-color: var(--form-primary-color);
        }
        
        .required-mark {
            color: var(--form-error-color);
        }
    `;
}

generateFormHTML(formData, isPreview = false) {
    let html = `<div class="uform uform-${formData.theme || 'default'}" data-form-name="${formData.name}">`;
    
    if (formData.title) {
        html += `<div class="uform-header">`;
        html += `<h2 class="uform-title">${formData.title}</h2>`;
        if (formData.description) {
            html += `<div class="uform-description">${formData.description}</div>`;
        }
        html += `</div>`;
    }
    
    html += `<form class="uform-form" ${isPreview ? '' : 'method="post"'}>`;
    
    const sortedFields = Array.from(this.fieldsData.values()).sort((a, b) => {
        const aIndex = Array.from($('#form-canvas .canvas-field')).findIndex(el => el.id === a.id);
        const bIndex = Array.from($('#form-canvas .canvas-field')).findIndex(el => el.id === b.id);
        return aIndex - bIndex;
    });
    
    sortedFields.forEach(fieldData => {
        html += this.renderFormField(fieldData, isPreview);
    });
    
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
    
    if (config.label && !['heading', 'paragraph', 'divider', 'hidden', 'html'].includes(type)) {
        html += `<label class="uform-label">`;
        html += config.label;
        if (config.required) {
            html += ` <span class="required-mark">*</span>`;
        }
        html += `</label>`;
    }
    
    html += this.renderFieldInput(fieldData, disabled, required);
    
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
            
        case 'html':
            return `<div class="uform-html">${config.content || ''}</div>`;
            
        case 'hidden':
            return `<input type="hidden" name="${config.name}" value="${config.value || ''}">`;
            
        default:
            return `<div class="uform-unknown">未知字段类型: ${type}</div>`;
    }
}

// 保存功能 - 完整版
saveForm(status = 'draft') {
    const formData = this.collectFormData();
    formData.status = status;
    
    // 验证必填字段
    if (!formData.name || !formData.title) {
        this.showMessage('表单名称和标题不能为空', 'error');
        this.switchTab('form');
        return;
    }
    
    // 验证表单名称格式
    if (!/^[a-zA-Z0-9_-]+$/.test(formData.name)) {
        this.showMessage('表单名称只能包含字母、数字、下划线和短横线', 'error');
        this.switchTab('form');
        $('#form-name').focus();
        return;
    }
    
    if (this.fieldsData.size === 0) {
        this.showMessage('表单至少需要包含一个字段', 'error');
        return;
    }
    
    // 验证所有字段配置
    let hasInvalidFields = false;
    this.fieldsData.forEach((fieldData, fieldId) => {
        this.validateFieldConfig(fieldId);
        if (document.querySelector(`#${fieldId} .field-status.invalid`)) {
            hasInvalidFields = true;
        }
    });
    
    if (hasInvalidFields) {
        this.showMessage('存在配置错误的字段，请检查后重试', 'error');
        return;
    }
    
    // 显示保存状态
    this.setSaveStatus('saving', '正在保存...');
    
    // 准备保存数据
    const saveData = {
        action: 'save_form',
        form_id: window.uformsConfig.formId || null,
        form_name: formData.name,
        form_title: formData.title,
        form_description: formData.description || '',
        form_status: formData.status,
        form_config: JSON.stringify(formData.config || {}),
        form_settings: JSON.stringify(formData.settings || {}),
        fields_config: JSON.stringify(formData.fields || [])
    };
    
    // 发送保存请求
    $.ajax({
        url: window.uformsConfig.ajaxUrl,
        method: 'POST',
        dataType: 'json',
        data: saveData,
        timeout: 30000,
        success: (response) => {
            if (response && response.success) {
                this.setSaveStatus('success', status === 'published' ? '发布成功！' : '保存成功！');
                this.isDirty = false;
                
                // 更新表单ID
                if (response.form_id && !window.uformsConfig.formId) {
                    window.uformsConfig.formId = response.form_id;
                    
                    // 更新浏览器地址
                    if (history.pushState) {
                        const newUrl = window.location.pathname + '?view=create&id=' + response.form_id;
                        history.pushState({}, '', newUrl);
                    }
                }
                
                // 如果是发布，显示获取代码按钮
                if (status === 'published') {
                    $('#get-code').show();
                }
                
                // 显示成功消息
                this.showMessage(
                    status === 'published' ? '表单发布成功！' : '表单保存成功！', 
                    'success'
                );
                
                // 3秒后恢复正常状态
                setTimeout(() => {
                    this.setSaveStatus('saved', '已保存');
                }, 3000);
            } else {
                this.setSaveStatus('error', '保存失败');
                this.showMessage('保存失败：' + (response?.message || '未知错误'), 'error');
            }
        },
        error: (xhr, status, error) => {
            this.setSaveStatus('error', '保存失败');
            
            let errorMessage = '保存失败，请检查网络连接';
            
            if (xhr.status === 0) {
                errorMessage = '网络连接失败，请检查网络设置';
            } else if (xhr.status === 404) {
                errorMessage = '服务器地址不存在';
            } else if (xhr.status === 500) {
                errorMessage = '服务器内部错误';
            } else if (status === 'timeout') {
                errorMessage = '请求超时，请稍后重试';
            } else if (xhr.responseJSON?.message) {
                errorMessage = xhr.responseJSON.message;
            }
            
            this.showMessage(errorMessage, 'error');
            console.error('保存表单失败:', { xhr, status, error });
        }
    });
}

collectFormData() {
    const formSettings = {
        name: $('#form-name').val(),
        title: $('#form-title').val(),
        description: $('#form-description').val(),
        submitText: $('#submit-text').val() || '提交',
        successMessage: $('#success-message').val() || '表单提交成功！',
        successAction: $('#success-action').val() || 'message',
        redirectUrl: $('#redirect-url').val() || '',
        successBlock: $('#success-block').val() || '',
        ajaxSubmit: $('#ajax-submit').is(':checked'),
        preventDuplicate: $('#prevent-duplicate').is(':checked'),
        enableCaptcha: $('#enable-captcha').is(':checked'),
        enableHoneypot: $('#enable-honeypot').is(':checked'),
        submitLimit: parseInt($('#submit-limit').val()) || 60,
        maxSubmissions: parseInt($('#max-submissions').val()) || 0,
        adminNotification: {
            enabled: $('#admin-notification').is(':checked'),
            recipients: $('#admin-email').val() || '',
            subject: $('#admin-subject').val() || '新的表单提交 - {form_title}',
            message: $('#admin-message').val() || '您收到一个新的表单提交：\n\n{all_fields}\n\n提交时间：{submit_time}'
        },
        userNotification: {
            enabled: $('#user-notification').is(':checked'),
            emailField: $('#user-email-field').val() || '',
            subject: $('#user-subject').val() || '表单提交确认 - {form_title}',
            message: $('#user-message').val() || '感谢您的提交！\n\n我们已收到您的表单信息。'
        },
        webhook: {
            enabled: $('#enable-webhook').is(':checked'),
            url: $('#webhook-url').val() || '',
            secret: $('#webhook-secret').val() || ''
        }
    };
    
    const formConfig = {
        theme: $('#form-theme').val() || 'default',
        primaryColor: $('#primary-color').val() || '#3788d8',
        formWidth: $('#form-width').val() || '100%',
        formMaxWidth: $('#form-max-width').val() || '800px',
        labelPosition: $('#label-position').val() || 'top',
        fieldSpacing: parseInt($('#field-spacing').val()) || 20,
        formPadding: parseInt($('#form-padding').val()) || 20,
        inputBorderRadius: parseInt($('#input-border-radius').val()) || 4,
        inputBorderWidth: parseInt($('#input-border-width').val()) || 1,
        inputHeight: parseInt($('#input-height').val()) || 40,
        colors: {
            background: $('#bg-color').val() || '#ffffff',
            text: $('#text-color').val() || '#333333',
            border: $('#border-color').val() || '#dddddd',
            error: $('#error-color').val() || '#e74c3c',
            success: $('#success-color').val() || '#27ae60',
            warning: $('#warning-color').val() || '#f39c12'
        },
        customCSS: $('#custom-css').val() || ''
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
    // 监听表单变化，5秒后自动保存草稿
    $(document).on('input change', '.property-item input, .property-item textarea, .property-item select', () => {
        if (!this.isDirty) return;
        
        clearTimeout(this.autoSaveTimer);
        this.autoSaveTimer = setTimeout(() => {
            if (this.isDirty && window.uformsConfig.formId) {
                this.autoSaveDraft();
            }
        }, 5000);
    });
}

autoSaveDraft() {
    if (!window.uformsConfig.formId) return;
    
    this.setSaveStatus('saving', '自动保存中...');
    
    const formData = this.collectFormData();
    formData.status = 'draft';
    
    $.ajax({
        url: window.uformsConfig.ajaxUrl,
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'save_form',
            form_id: window.uformsConfig.formId,
            form_name: formData.name,
            form_title: formData.title,
            form_description: formData.description,
            form_status: 'draft',
            form_config: JSON.stringify(formData.config),
            form_settings: JSON.stringify(formData.settings),
            fields_config: JSON.stringify(formData.fields)
        },
        success: (response) => {
            if (response && response.success) {
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

updateFieldOrder() {
    this.markDirty();
}

loadExistingForm() {
    // 加载现有表单数据
    if (window.uformsConfig.existingFields && window.uformsConfig.existingFields.length > 0) {
        $('#form-canvas').empty();
        
        window.uformsConfig.existingFields.forEach((field, index) => {
            const fieldConfig = JSON.parse(field.field_config || '{}');
            const fieldId = this.generateFieldId();
            
            const mergedConfig = {
                ...fieldConfig,
                label: field.field_label,
                name: field.field_name,
                required: field.is_required == 1
            };
            
            const fieldElement = this.createFieldElement(fieldId, field.field_type, mergedConfig);
            $('#form-canvas').append(fieldElement);
            
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
        
        Object.keys(settings).forEach(key => {
            const elementId = key.replace(/([A-Z])/g, '-$1').toLowerCase();
            const element = document.getElementById(elementId);
            
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
    if (e.key === 'Delete' && this.selectedField && !$(e.target).is('input, textarea')) {
        e.preventDefault();
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
        
        // 关闭模态框
        $('.modal').hide();
    }
    
    // Ctrl+Z 撤销
    if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
        e.preventDefault();
        // TODO: 实现撤销功能
    }
    
    // Ctrl+Y 或 Ctrl+Shift+Z 重做
    if (e.ctrlKey && (e.key === 'y' || (e.shiftKey && e.key === 'Z'))) {
        e.preventDefault();
        // TODO: 实现重做功能
    }
}

// 其他辅助方法
showMessage(message, type = 'info') {
    const messageElement = $(`
        <div class="message ${type}">
            <i class="icon-${type === 'error' ? 'error' : type === 'success' ? 'check' : type === 'warning' ? 'warning' : 'info'}"></i>
            ${message}
        </div>
    `);
    
    // 移除现有消息
    $('.message').remove();
    
    // 添加新消息
    $('.uforms-creator').prepend(messageElement);
    
    // 3秒后自动移除
    setTimeout(() => {
        messageElement.fadeOut(300, function() {
            $(this).remove();
        });
    }, 3000);
}

// 获取代码功能
showCodeModal() {
    if (!window.uformsConfig.formId) {
        this.showMessage('请先保存并发布表单', 'warning');
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
            
            const button = document.getElementById(buttonId);
            const originalText = button.textContent;
            button.textContent = '已复制';
            button.classList.add('copied');
            
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('copied');
            }, 2000);
            
            this.showMessage('代码已复制到剪贴板', 'success');
        } catch (err) {
            this.showMessage('复制失败，请手动选择并复制', 'error');
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
                this.showMessage('模板保存成功！', 'success');
            } else {
                this.showMessage('模板保存失败：' + response.message, 'error');
            }
        },
        error: () => {
            this.showMessage('模板保存失败，请重试', 'error');
        }
    });
}

switchPreviewDevice(device) {
    $('.preview-device').removeClass('active');
    $(`.preview-device[data-device="${device}"]`).addClass('active');
    
    const iframe = $('#preview-iframe');
    const container = $('#preview-container');
    
    container.removeClass('device-desktop device-tablet device-mobile');
    container.addClass(`device-${device}`);
    
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
        
        // 关闭所有下拉菜单
        $('.dropdown-menu').removeClass('show');
    });
    
    // 字段分类滑动效果
    $('.field-category h4').on('click', function(e) {
        e.preventDefault();
        const category = $(this).closest('.field-category');
        const items = category.find('.field-items');
        
        if (category.hasClass('collapsed')) {
            // 展开
            category.removeClass('collapsed');
            items.slideDown(300);
        } else {
            // 折叠
            category.addClass('collapsed');
            items.slideUp(300);
        }
    });
});
