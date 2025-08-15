<?php
// 获取包含日历字段的表单
$calendar_forms = $db->fetchAll(
    $db->select('DISTINCT f.id, f.title, f.name')
       ->from('table.uforms_forms f')
       ->join('table.uforms_fields fd', 'f.id = fd.form_id')
       ->where('fd.field_type IN ? AND f.status = ?', 
              array('date', 'datetime', 'calendar'), 'published')
);

$selected_form = $request->get('calendar_form', $form_id);
?>

<div class="calendar-view">
    <!-- 日历工具栏 -->
    <div class="calendar-toolbar">
        <div class="toolbar-left">
            <?php if (!empty($calendar_forms)): ?>
            <div class="form-selector">
                <label>选择表单：</label>
                <select id="calendar-form-select">
                    <option value="">所有表单</option>
                    <?php foreach ($calendar_forms as $form): ?>
                    <option value="<?php echo $form['id']; ?>" 
                            <?php echo $selected_form == $form['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($form['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="view-switcher">
                <button id="month-view" class="view-btn active">月</button>
                <button id="week-view" class="view-btn">周</button>
                <button id="day-view" class="view-btn">日</button>
                <button id="list-view" class="view-btn">列表</button>
            </div>
        </div>
        
        <div class="toolbar-center">
            <button id="today-btn" class="btn">今天</button>
            <div class="nav-buttons">
                <button id="prev-btn" class="btn-nav">‹</button>
                <button id="next-btn" class="btn-nav">›</button>
            </div>
            <h3 id="calendar-title"></h3>
        </div>
        
        <div class="toolbar-right">
            <button id="add-event" class="btn btn-primary">
                <i class="icon-plus"></i> 添加事件
            </button>
            <button id="export-calendar" class="btn">
                <i class="icon-export"></i> 导出
            </button>
        </div>
    </div>
    
    <!-- 日历容器 -->
    <div id="calendar-container">
        <div id="calendar"></div>
    </div>
    
    <!-- 日历图例 -->
    <div class="calendar-legend">
        <h4>图例</h4>
        <div class="legend-items">
            <div class="legend-item">
                <span class="legend-color" style="background-color: #3788d8;"></span>
                <span class="legend-label">可预约</span>
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background-color: #f39c12;"></span>
                <span class="legend-label">已预约</span>
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background-color: #e74c3c;"></span>
                <span class="legend-label">已满/禁用</span>
            </div>
            <div class="legend-item">
                <span class="legend-color" style="background-color: #27ae60;"></span>
                <span class="legend-label">表单提交</span>
            </div>
        </div>
    </div>
</div>

<!-- 事件详情模态框 -->
<div id="event-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="event-modal-title">事件详情</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="event-details">
                <div class="event-info">
                    <div class="info-item">
                        <label>标题：</label>
                        <span id="event-title"></span>
                    </div>
                    <div class="info-item">
                        <label>开始时间：</label>
                        <span id="event-start"></span>
                    </div>
                    <div class="info-item">
                        <label>结束时间：</label>
                        <span id="event-end"></span>
                    </div>
                    <div class="info-item">
                        <label>状态：</label>
                        <span id="event-status" class="status-badge"></span>
                    </div>
                    <div class="info-item" id="form-info" style="display: none;">
                        <label>关联表单：</label>
                        <span id="event-form"></span>
                    </div>
                </div>
                
                <div class="event-data" id="event-submission-data" style="display: none;">
                    <h4>提交数据</h4>
                    <div id="submission-data-content"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button id="edit-event" class="btn" style="display: none;">编辑</button>
            <button id="delete-event" class="btn btn-danger" style="display: none;">删除</button>
            <button class="btn btn-default modal-close">关闭</button>
        </div>
    </div>
</div>

<!-- 添加/编辑事件模态框 -->
<div id="add-event-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="add-event-title">添加事件</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="event-form">
                <div class="form-group">
                    <label>事件标题*</label>
                    <input type="text" id="new-event-title" required>
                </div>
                
                <div class="form-group">
                    <label>关联表单</label>
                    <select id="new-event-form">
                        <option value="">无关联</option>
                        <?php foreach ($calendar_forms as $form): ?>
                        <option value="<?php echo $form['id']; ?>">
                            <?php echo htmlspecialchars($form['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group half">
                        <label>开始时间*</label>
                        <input type="datetime-local" id="new-event-start" required>
                    </div>
                    <div class="form-group half">
                        <label>结束时间</label>
                        <input type="datetime-local" id="new-event-end">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="new-event-all-day">
                        全天事件
                    </label>
                </div>
                
                <div class="form-group">
                    <label>状态</label>
                    <select id="new-event-status">
                        <option value="available">可预约</option>
                        <option value="booked">已预约</option>
                        <option value="blocked">已禁用</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>颜色</label>
                    <input type="color" id="new-event-color" value="#3788d8">
                </div>
                
                <div class="form-group">
                    <label>描述</label>
                    <textarea id="new-event-description" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button id="save-event" class="btn btn-primary">保存</button>
            <button class="btn btn-default modal-close">取消</button>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let calendar;
    let currentView = 'dayGridMonth';
    
    // 初始化日历
    initCalendar();
    
    // 视图切换
    $('.view-btn').on('click', function() {
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        
        const viewType = $(this).attr('id').replace('-view', '');
        const viewMap = {
            'month': 'dayGridMonth',
            'week': 'timeGridWeek',
            'day': 'timeGridDay',
            'list': 'listWeek'
        };
        
        currentView = viewMap[viewType];
        calendar.changeView(currentView);
    });
    
    // 导航按钮
    $('#today-btn').on('click', function() {
        calendar.today();
        updateTitle();
    });
    
    $('#prev-btn').on('click', function() {
        calendar.prev();
        updateTitle();
    });
    
    $('#next-btn').on('click', function() {
        calendar.next();
        updateTitle();
    });
    
    // 表单选择器
    $('#calendar-form-select').on('change', function() {
        const formId = $(this).val();
        loadCalendarEvents(formId);
        
        // 更新URL
        const url = new URL(window.location);
        if (formId) {
            url.searchParams.set('calendar_form', formId);
        } else {
            url.searchParams.delete('calendar_form');
        }
        window.history.replaceState({}, '', url);
    });
    
    // 添加事件
    $('#add-event').on('click', function() {
        $('#add-event-modal').show();
        $('#add-event-title').text('添加事件');
        $('#event-form')[0].reset();
    });
    
    // 保存事件
    $('#save-event').on('click', function() {
        const eventData = {
            title: $('#new-event-title').val(),
            form_id: $('#new-event-form').val() || null,
            start: $('#new-event-start').val(),
            end: $('#new-event-end').val(),
            all_day: $('#new-event-all-day').is(':checked'),
            status: $('#new-event-status').val(),
            color: $('#new-event-color').val(),
            description: $('#new-event-description').val()
        };
        
        if (!eventData.title || !eventData.start) {
            alert('请填写必填字段');
            return;
        }
        
        saveCalendarEvent(eventData);
    });
    
    // 模态框关闭
    $('.modal-close').on('click', function() {
        $(this).closest('.modal').hide();
    });
    
    function initCalendar() {
        const calendarEl = document.getElementById('calendar');
        
        calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: currentView,
            locale: 'zh-cn',
            headerToolbar: false,
            height: 'auto',
            dayMaxEvents: true,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            eventDisplay: 'block',
            eventClick: function(info) {
                showEventDetails(info.event);
            },
            dateClick: function(info) {
                // 快速添加事件
                $('#new-event-start').val(info.dateStr + 'T09:00');
                $('#add-event-modal').show();
            },
            datesSet: function() {
                updateTitle();
            },
            eventDidMount: function(info) {
                // 根据事件类型设置样式
                const event = info.event;
                const eventType = event.extendedProps.type;
                
                if (eventType === 'submission') {
                    info.el.classList.add('submission-event');
                } else if (eventType === 'calendar') {
                    info.el.classList.add('calendar-event');
                }
                
                // 添加状态标识
                const status = event.extendedProps.status;
                if (status) {
                    info.el.classList.add('status-' + status);
                }
            }
        });
        
        calendar.render();
        
        // 加载事件数据
        const selectedForm = $('#calendar-form-select').val();
        loadCalendarEvents(selectedForm);
        
        updateTitle();
    }
    
    function loadCalendarEvents(formId) {
        $.ajax({
            url: '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=Uforms%2Fajax.php',
            method: 'GET',
            data: {
                action: 'get_calendar_events',
                form_id: formId || '',
                start: calendar.view.activeStart.toISOString(),
                end: calendar.view.activeEnd.toISOString()
            },
            success: function(response) {
                if (response.success) {
                    // 清除现有事件
                    calendar.removeAllEvents();
                    
                    // 添加新事件
                    response.events.forEach(event => {
                        calendar.addEvent({
                            id: event.id,
                            title: event.title,
                            start: event.start,
                            end: event.end,
                            allDay: event.all_day,
                            color: event.color,
                            extendedProps: {
                                type: event.type,
                                status: event.status,
                                form_id: event.form_id,
                                form_title: event.form_title,
                                submission_id: event.submission_id,
                                data: event.data
                            }
                        });
                    });
                }
            },
            error: function() {
                console.error('加载日历事件失败');
            }
        });
    }
    
    function showEventDetails(event) {
        const props = event.extendedProps;
        
        $('#event-title').text(event.title);
        $('#event-start').text(formatDateTime(event.start));
        $('#event-end').text(event.end ? formatDateTime(event.end) : '无');
        
        // 设置状态
        const statusLabels = {
            'available': '可预约',
            'booked': '已预约',
            'blocked': '已禁用'
        };
        $('#event-status').removeClass().addClass('status-badge status-' + props.status)
                         .text(statusLabels[props.status] || props.status);
        
        // 表单信息
        if (props.form_title) {
            $('#event-form').text(props.form_title);
            $('#form-info').show();
        } else {
            $('#form-info').hide();
        }
        
        // 提交数据
        if (props.data && props.type === 'submission') {
            const data = JSON.parse(props.data);
            let dataHtml = '<div class="data-grid">';
            
            for (const [key, value] of Object.entries(data)) {
                const displayValue = Array.isArray(value) ? value.join(', ') : value;
                dataHtml += `
                    <div class="data-item">
                        <div class="data-label">${escapeHtml(key)}</div>
                        <div class="data-value">${escapeHtml(displayValue)}</div>
                    </div>
                `;
            }
            dataHtml += '</div>';
            
            $('#submission-data-content').html(dataHtml);
            $('#event-submission-data').show();
        } else {
            $('#event-submission-data').hide();
        }
        
        // 显示编辑删除按钮（仅对管理员添加的事件）
        if (props.type === 'calendar') {
            $('#edit-event, #delete-event').show();
            $('#edit-event').data('event-id', event.id);
            $('#delete-event').data('event-id', event.id);
        } else {
            $('#edit-event, #delete-event').hide();
        }
        
        $('#event-modal').show();
    }
    
    function saveCalendarEvent(eventData) {
        $.ajax({
            url: '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=Uforms%2Fajax.php',
            method: 'POST',
            data: {
                action: 'save_calendar_event',
                ...eventData
            },
            success: function(response) {
                if (response.success) {
                    $('#add-event-modal').hide();
                    
                    // 重新加载事件
                    const selectedForm = $('#calendar-form-select').val();
                    loadCalendarEvents(selectedForm);
                    
                    alert('事件保存成功');
                } else {
                    alert('事件保存失败: ' + (response.message || '未知错误'));
                }
            },
            error: function() {
                alert('事件保存失败');
            }
        });
    }
    
    function updateTitle() {
        const title = calendar.view.title;
        $('#calendar-title').text(title);
    }
    
    function formatDateTime(date) {
        return new Date(date).toLocaleString('zh-CN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 导出日历
    $('#export-calendar').on('click', function() {
        const formId = $('#calendar-form-select').val();
        const start = calendar.view.activeStart.toISOString().split('T')[0];
        const end = calendar.view.activeEnd.toISOString().split('T')[0];
        
        let url = '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=Uforms%2Fexport.php';
        url += '?type=calendar';
        url += '&start=' + start;
        url += '&end=' + end;
        
        if (formId) {
            url += '&form_id=' + formId;
        }
        
        window.open(url, '_blank');
    });
    
    // 删除事件
    $('#delete-event').on('click', function() {
        const eventId = $(this).data('event-id');
        
        if (confirm('确定要删除这个事件吗？')) {
            $.ajax({
                url: '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=Uforms%2Fajax.php',
                method: 'POST',
                data: {
                    action: 'delete_calendar_event',
                    event_id: eventId
                },
                success: function(response) {
                    if (response.success) {
                        $('#event-modal').hide();
                        
                        // 重新加载事件
                        const selectedForm = $('#calendar-form-select').val();
                        loadCalendarEvents(selectedForm);
                        
                        alert('事件删除成功');
                    } else {
                        alert('事件删除失败');
                    }
                },
                error: function() {
                    alert('事件删除失败');
                }
            });
        }
    });
});
</script>
