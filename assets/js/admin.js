/**
 * Uforms 管理后台JavaScript
 */
(function($) {
    'use strict';
    
    window.UformsAdmin = {
        init: function() {
            this.bindEvents();
            this.initComponents();
            this.loadData();
        },
        
        bindEvents: function() {
            // 表单构建器事件
            $(document).on('click', '.field-item', this.selectFieldType.bind(this));
            $(document).on('click', '.form-field', this.selectFormField.bind(this));
            $(document).on('click', '.field-control', this.handleFieldControl.bind(this));
            
            // 搜索和过滤事件
            $('.search-box input').on('keyup', this.debounce(this.handleSearch.bind(this), 300));
            $('.filter-select').on('change', this.handleFilter.bind(this));
            
            // 批量操作事件
            $('#select-all').on('change', this.toggleSelectAll.bind(this));
            $('.item-checkbox').on('change', this.updateBatchActions.bind(this));
            $('.batch-action').on('click', this.handleBatchAction.bind(this));
            
            // 模态框事件
            $(document).on('click', '.modal-trigger', this.openModal.bind(this));
            $(document).on('click', '.modal-close', this.closeModal.bind(this));
            $(document).on('click', '.modal', this.handleModalClick.bind(this));
            
            // 表单事件
            $('.ajax-form').on('submit', this.handleAjaxForm.bind(this));
            $('.auto-save').on('input change', this.debounce(this.autoSave.bind(this), 1000));
            
            // 工具提示
            $('[data-tooltip]').each(this.initTooltip);
        },
        
        initComponents: function() {
            // 初始化sortable
            if (typeof $.fn.sortable !== 'undefined') {
                $('.sortable').sortable({
                    handle: '.drag-handle',
                    placeholder: 'sort-placeholder',
                    update: this.handleSort.bind(this)
                });
            }
            
            // 初始化日期选择器
            if (typeof $.fn.datepicker !== 'undefined') {
                $('.datepicker').datepicker({
                    format: 'yyyy-mm-dd',
                    autoclose: true,
                    todayHighlight: true
                });
            }
            
            // 初始化颜色选择器
            if (typeof $.fn.spectrum !== 'undefined') {
                $('.colorpicker').spectrum({
                    preferredFormat: 'hex',
                    showInput: true,
                    showPalette: true
                });
            }
            
            // 初始化代码编辑器
            this.initCodeEditor();
            
            // 初始化图表
            this.initCharts();
        },
        
        loadData: function() {
            // 加载统计数据
            this.loadStats();
            
            // 加载最新提交
            this.loadRecentSubmissions();
            
            // 加载通知
            this.loadNotifications();
        },
        
        // 表单构建器
        selectFieldType: function(e) {
            e.preventDefault();
            const $item = $(e.currentTarget);
            const fieldType = $item.data('type');
            
            $('.field-item').removeClass('selected');
            $item.addClass('selected');
            
            this.showFieldProperties(fieldType);
        },
        
        selectFormField: function(e) {
            e.preventDefault();
            const $field = $(e.currentTarget);
            
            $('.form-field').removeClass('selected');
            $field.addClass('selected');
            
            const fieldData = this.getFieldData($field);
            this.showFieldProperties(fieldData.type, fieldData);
        },
        
        showFieldProperties: function(fieldType, fieldData = {}) {
            const $properties = $('.field-properties');
            
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_field_properties',
                    field_type: fieldType,
                    field_data: JSON.stringify(fieldData)
                },
                success: function(response) {
                    if (response.success) {
                        $properties.html(response.html);
                        this.bindPropertyEvents();
                    }
                }.bind(this)
            });
        },
        
        handleFieldControl: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(e.currentTarget);
            const action = $btn.data('action');
            const $field = $btn.closest('.form-field');
            
            switch (action) {
                case 'edit':
                    this.editField($field);
                    break;
                case 'clone':
                    this.cloneField($field);
                    break;
                case 'delete':
                    this.deleteField($field);
                    break;
                case 'move-up':
                    this.moveField($field, 'up');
                    break;
                case 'move-down':
                    this.moveField($field, 'down');
                    break;
            }
        },
        
        editField: function($field) {
            $field.trigger('click');
        },
        
        cloneField: function($field) {
            const $clone = $field.clone();
            $clone.find('input, textarea, select').each(function() {
                const name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name + '_copy');
                }
            });
            $field.after($clone);
            this.updateFieldOrder();
        },
        
        deleteField: function($field) {
            if (confirm('确定要删除这个字段吗？')) {
                $field.remove();
                this.updateFieldOrder();
                $('.field-properties').html('<p class="empty-state">选择一个字段来编辑其属性</p>');
            }
        },
        
        moveField: function($field, direction) {
            if (direction === 'up') {
                $field.prev('.form-field').before($field);
            } else {
                $field.next('.form-field').after($field);
            }
            this.updateFieldOrder();
        },
        
        updateFieldOrder: function() {
            $('.form-canvas .form-field').each(function(index) {
                $(this).data('order', index);
                $(this).find('[name*="[sort_order]"]').val(index);
            });
        },
        
        // 搜索和过滤
        handleSearch: function(e) {
            const query = $(e.target).val().toLowerCase();
            this.filterItems(query);
        },
        
        handleFilter: function(e) {
            const filter = $(e.target).val();
            const filterType = $(e.target).data('filter');
            
            this.applyFilter(filterType, filter);
        },
        
        filterItems: function(query) {
            $('.data-table tbody tr').each(function() {
                const $row = $(this);
                const text = $row.text().toLowerCase();
                
                if (text.indexOf(query) !== -1) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },
        
        applyFilter: function(type, value) {
            const $rows = $('.data-table tbody tr');
            
            if (!value || value === 'all') {
                $rows.show();
                return;
            }
            
            $rows.each(function() {
                const $row = $(this);
                const cellValue = $row.find('[data-filter="' + type + '"]').text().trim();
                
                if (cellValue === value) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },
        
        // 批量操作
        toggleSelectAll: function(e) {
            const checked = $(e.target).is(':checked');
            $('.item-checkbox').prop('checked', checked);
            this.updateBatchActions();
        },
        
        updateBatchActions: function() {
            const checkedCount = $('.item-checkbox:checked').length;
            const $batchActions = $('.batch-actions');
            
            if (checkedCount > 0) {
                $batchActions.show();
                $batchActions.find('.count').text(checkedCount);
            } else {
                $batchActions.hide();
            }
        },
        
        handleBatchAction: function(e) {
            e.preventDefault();
            
            const action = $(e.currentTarget).data('action');
            const items = $('.item-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (items.length === 0) {
                this.showMessage('请选择要操作的项目', 'warning');
                return;
            }
            
            if (!confirm('确定要对选中的 ' + items.length + ' 个项目执行此操作吗？')) {
                return;
            }
            
            this.performBatchAction(action, items);
        },
        
        performBatchAction: function(action, items) {
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'batch_' + action,
                    items: items
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        this.showMessage(response.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage('操作失败，请重试', 'error');
                }.bind(this)
            });
        },
        
        // 模态框
        openModal: function(e) {
            e.preventDefault();
            
            const $trigger = $(e.currentTarget);
            const modalId = $trigger.data('modal') || $trigger.attr('href');
            const $modal = $(modalId);
            
            if ($modal.length) {
                $modal.addClass('show');
                
                // 加载动态内容
                const url = $trigger.data('url');
                if (url) {
                    this.loadModalContent($modal, url);
                }
            }
        },
        
        closeModal: function(e) {
            e.preventDefault();
            $(e.currentTarget).closest('.modal').removeClass('show');
        },
        
        handleModalClick: function(e) {
            if (e.target === e.currentTarget) {
                $(e.currentTarget).removeClass('show');
            }
        },
        
        loadModalContent: function($modal, url) {
            const $body = $modal.find('.modal-body');
            
            $body.html('<div class="loading">加载中...</div>');
            
            $.ajax({
                url: url,
                success: function(response) {
                    $body.html(response);
                    this.bindModalEvents($modal);
                }.bind(this),
                error: function() {
                    $body.html('<div class="error">加载失败</div>');
                }
            });
        },
        
        bindModalEvents: function($modal) {
            // 绑定模态框内的表单事件
            $modal.find('.ajax-form').on('submit', this.handleModalForm.bind(this));
        },
        
        handleModalForm: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const formData = new FormData($form[0]);
            
            $.ajax({
                url: $form.attr('action') || ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.message, 'success');
                        $form.closest('.modal').removeClass('show');
                        
                        if (response.reload) {
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        this.showMessage(response.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage('操作失败，请重试', 'error');
                }.bind(this)
            });
        },
        
        // Ajax表单处理
        handleAjaxForm: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $submitBtn = $form.find('[type="submit"]');
            const originalText = $submitBtn.text();
            
            // 禁用提交按钮
            $submitBtn.prop('disabled', true).text('处理中...');
            
            const formData = new FormData($form[0]);
            
            $.ajax({
                url: $form.attr('action') || ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        this.showMessage(response.message, 'success');
                        
                        if (response.redirect) {
                            setTimeout(function() {
                                location.href = response.redirect;
                            }, 1000);
                        } else if (response.reload) {
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        this.showMessage(response.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showMessage('操作失败，请重试', 'error');
                }.bind(this),
                complete: function() {
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        },
        
        // 自动保存
        autoSave: function(e) {
            const $field = $(e.currentTarget);
            const data = {
                action: 'auto_save',
                field: $field.attr('name'),
                value: $field.val(),
                id: $field.data('id')
            };
            
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $field.addClass('saved');
                        setTimeout(function() {
                            $field.removeClass('saved');
                        }, 2000);
                    }
                }
            });
        },
        
        // 排序处理
        handleSort: function(event, ui) {
            const $list = $(event.target);
            const order = $list.sortable('toArray', {attribute: 'data-id'});
            
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'update_order',
                    type: $list.data('type'),
                    order: order
                },
                success: function(response) {
                    if (response.success) {
                        this.showMessage('排序已保存', 'success', 2000);
                    }
                }.bind(this)
            });
        },
        
        // 统计数据加载
        loadStats: function() {
            const $statsContainer = $('.stats-container');
            if (!$statsContainer.length) return;
            
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {action: 'get_stats'},
                success: function(response) {
                    if (response.success) {
                        this.updateStats(response.data);
                    }
                }.bind(this)
            });
        },
        
        updateStats: function(stats) {
            $('.stat-total-forms').text(stats.total_forms || 0);
            $('.stat-published-forms').text(stats.published_forms || 0);
            $('.stat-total-submissions').text(stats.total_submissions || 0);
            $('.stat-new-submissions').text(stats.new_submissions || 0);
        },
        
        // 最新提交加载
        loadRecentSubmissions: function() {
            const $container = $('.recent-submissions');
            if (!$container.length) return;
            
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {action: 'get_recent_submissions', limit: 5},
                success: function(response) {
                    if (response.success) {
                        this.updateRecentSubmissions(response.data);
                    }
                }.bind(this)
            });
        },
        
        updateRecentSubmissions: function(submissions) {
            const $container = $('.recent-submissions tbody');
            $container.empty();
            
            if (submissions.length === 0) {
                $container.append('<tr><td colspan="4" class="text-center">暂无提交</td></tr>');
                return;
            }
            
            submissions.forEach(function(submission) {
                const row = `
                    <tr>
                        <td><a href="?view=submissions&id=${submission.id}">#${submission.id}</a></td>
                        <td>${submission.form_title}</td>
                        <td><span class="status-badge status-${submission.status}">${submission.status_label}</span></td>
                        <td>${submission.created_time}</td>
                    </tr>
                `;
                $container.append(row);
            });
        },
        
        // 通知加载
        loadNotifications: function() {
            const $badge = $('.notification-badge');
            if (!$badge.length) return;
            
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {action: 'get_unread_notifications'},
                success: function(response) {
                    if (response.success && response.count > 0) {
                        $badge.text(response.count).show();
                    } else {
                        $badge.hide();
                    }
                }
            });
        },
        
        // 代码编辑器初始化
        initCodeEditor: function() {
            if (typeof CodeMirror === 'undefined') return;
            
            $('.code-editor').each(function() {
                const $textarea = $(this);
                const mode = $textarea.data('mode') || 'htmlmixed';
                
                const editor = CodeMirror.fromTextArea(this, {
                    lineNumbers: true,
                    mode: mode,
                    theme: 'default',
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    indentUnit: 2,
                    tabSize: 2
                });
                
                $textarea.data('editor', editor);
            });
        },
        
        // 图表初始化
        initCharts: function() {
            if (typeof echarts === 'undefined') return;
            
            // 初始化提交趋势图
            this.initSubmissionChart();
            
            // 初始化设备统计图
            this.initDeviceChart();
        },
        
        initSubmissionChart: function() {
            const $container = $('#submission-chart');
            if (!$container.length) return;
            
            const chart = echarts.init($container[0]);
            
            // 加载数据
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {action: 'get_submission_trend'},
                success: function(response) {
                    if (response.success) {
                        const option = {
                            title: {
                                text: '提交趋势',
                                left: 'center'
                            },
                            tooltip: {
                                trigger: 'axis'
                            },
                            xAxis: {
                                type: 'category',
                                data: response.data.dates
                            },
                            yAxis: {
                                type: 'value'
                            },
                            series: [{
                                data: response.data.counts,
                                type: 'line',
                                smooth: true,
                                itemStyle: {
                                    color: '#3788d8'
                                }
                            }]
                        };
                        chart.setOption(option);
                    }
                }
            });
        },
        
        // 工具提示初始化
        initTooltip: function() {
            const $element = $(this);
            const content = $element.data('tooltip');
            const position = $element.data('position') || 'top';
            
            $element.hover(
                function() {
                    const tooltip = $('<div class="tooltip">')
                        .addClass('tooltip-' + position)
                        .text(content);
                    
                    $('body').append(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    const tooltipRect = tooltip[0].getBoundingClientRect();
                    
                    let left = rect.left + rect.width / 2 - tooltipRect.width / 2;
                    let top = rect.top - tooltipRect.height - 8;
                    
                    if (position === 'bottom') {
                        top = rect.bottom + 8;
                    }
                    
                    tooltip.css({
                        left: left + 'px',
                        top: top + 'px'
                    });
                },
                function() {
                    $('.tooltip').remove();
                }
            );
        },
        
        // 消息显示
        showMessage: function(message, type = 'info', duration = 5000) {
            const $message = $('<div class="message message-' + type + '">')
                .text(message)
                .hide();
            
            $('.uforms-admin').prepend($message);
            $message.fadeIn();
            
            if (duration > 0) {
                setTimeout(function() {
                    $message.fadeOut(function() {
                        $message.remove();
                    });
                }, duration);
            }
        },
        
        // 防抖函数
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        // 获取字段数据
        getFieldData: function($field) {
            const data = {
                type: $field.data('type'),
                name: $field.find('[name*="[name]"]').val(),
                label: $field.find('[name*="[label]"]').val(),
                required: $field.find('[name*="[required]"]').is(':checked'),
                config: {}
            };
            
            $field.find('[name*="[config]"]').each(function() {
                const name = $(this).attr('name');
                const key = name.match(/\[config\]\[([^\]]+)\]/);
                if (key) {
                    data.config[key[1]] = $(this).val();
                }
            });
            
            return data;
        },
        
        // 绑定属性编辑事件
        bindPropertyEvents: function() {
            // 字段名称自动生成
            $('.field-properties input[name*="[label]"]').on('input', function() {
                const label = $(this).val();
                const name = label.replace(/[^a-zA-Z0-9\u4e00-\u9fa5]/g, '_').toLowerCase();
                $('.field-properties input[name*="[name]"]').val(name);
            });
            
            // 选项管理
            $(document).on('click', '.add-option', function() {
                const $container = $(this).prev('.options-list');
                const $option = $('<div class="option-item">' +
                    '<input type="text" name="options[]" placeholder="选项内容">' +
                    '<button type="button" class="remove-option">删除</button>' +
                    '</div>');
                $container.append($option);
            });
            
            $(document).on('click', '.remove-option', function() {
                $(this).parent().remove();
            });
        }
    };
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        if ($('.uforms-admin').length) {
            UformsAdmin.init();
        }
    });
    
})(jQuery);
