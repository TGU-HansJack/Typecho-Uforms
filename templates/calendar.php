<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>预约日历 - <?php echo Helper::options()->title; ?></title>
    <link rel="stylesheet" href="<?php echo Helper::options()->pluginUrl; ?>/Uforms/lib/fullcalendar/main.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .calendar-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .calendar-header {
            background: #3788d8;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .calendar-title {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .calendar-body {
            padding: 20px;
        }
        
        .calendar-legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
        
        .calendar {
            background: white;
            border-radius: 4px;
        }
        
        /* FullCalendar 自定义样式 */
        .fc-event {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .fc-event:hover {
            opacity: 0.8;
            transform: scale(1.02);
        }
        
        .fc-event-available {
            background-color: #27ae60 !important;
            border-color: #27ae60 !important;
        }
        
        .fc-event-booked {
            background-color: #e74c3c !important;
            border-color: #e74c3c !important;
        }
        
        .fc-event-blocked {
            background-color: #95a5a6 !important;
            border-color: #95a5a6 !important;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .calendar-header {
                padding: 15px;
            }
            
            .calendar-title {
                font-size: 20px;
            }
            
            .calendar-body {
                padding: 15px;
            }
            
            .calendar-legend {
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="calendar-container">
        <div class="calendar-header">
            <h1 class="calendar-title">预约日历</h1>
        </div>
        
        <div class="calendar-body">
            <div class="calendar-legend">
                <div class="legend-item">
                    <span class="legend-color" style="background-color: #27ae60;"></span>
                    <span>可预约</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background-color: #e74c3c;"></span>
                    <span>已预约</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color" style="background-color: #95a5a6;"></span>
                    <span>不可用</span>
                </div>
            </div>
            
            <div id="calendar" class="calendar"></div>
        </div>
    </div>
    
    <!-- 事件详情模态框 -->
    <div id="event-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; padding: 20px; max-width: 400px; width: 90%;">
            <h3 id="modal-title" style="margin: 0 0 15px 0;">事件详情</h3>
            <div id="modal-content"></div>
            <div style="text-align: right; margin-top: 20px;">
                <button onclick="closeModal()" style="padding: 8px 16px; background: #3788d8; color: white; border: none; border-radius: 4px; cursor: pointer;">关闭</button>
            </div>
        </div>
    </div>
    
    <script src="<?php echo Helper::options()->pluginUrl; ?>/Uforms/lib/fullcalendar/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'zh-cn',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 'auto',
                eventClick: function(info) {
                    showEventModal(info.event);
                },
                dateClick: function(info) {
                    // 处理日期点击，可以用于预约
                    if (info.date >= new Date()) {
                        handleDateClick(info);
                    }
                },
                events: function(fetchInfo, successCallback, failureCallback) {
                    // 加载事件数据
                    fetch('<?php echo Helper::options()->adminUrl; ?>extending.php?panel=<?php echo safe_urlencode('Uforms/ajax.php'); ?>?' +
                          'action=get_calendar_events&form_id=<?php echo $form_id; ?>&' +
                          'start=' + fetchInfo.start.toISOString() + 
                          '&end=' + fetchInfo.end.toISOString())
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            successCallback(data.events);
                        } else {
                            failureCallback(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('加载日历事件失败:', error);
                        failureCallback(error.message);
                    });
                }
            });
            
            calendar.render();
            
            window.calendar = calendar;
        });
        
        function showEventModal(event) {
            const modal = document.getElementById('event-modal');
            const title = document.getElementById('modal-title');
            const content = document.getElementById('modal-content');
            
            title.textContent = event.title;
            
            let html = '<div>';
            html += '<p><strong>开始时间:</strong> ' + event.start.toLocaleString() + '</p>';
            
            if (event.end) {
                html += '<p><strong>结束时间:</strong> ' + event.end.toLocaleString() + '</p>';
            }
            
            html += '<p><strong>状态:</strong> ' + getStatusLabel(event.extendedProps.status) + '</p>';
            
            if (event.extendedProps.description) {
                html += '<p><strong>说明:</strong> ' + event.extendedProps.description + '</p>';
            }
            
            html += '</div>';
            
            content.innerHTML = html;
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('event-modal').style.display = 'none';
        }
        
        function handleDateClick(info) {
            if (confirm('是否要预约 ' + info.dateStr + '？')) {
                // 跳转到预约表单或打开预约模态框
                window.location.href = '<?php echo Helper::options()->siteUrl; ?>uforms/form/<?php echo $form_id; ?>?date=' + info.dateStr;
            }
        }
        
        function getStatusLabel(status) {
            const labels = {
                'available': '可预约',
                'booked': '已预约',
                'blocked': '不可用'
            };
            return labels[status] || status;
        }
        
        // 点击模态框外部关闭
        document.getElementById('event-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
