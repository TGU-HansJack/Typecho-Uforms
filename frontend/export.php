<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'UformsHelper.php';

// 检查用户权限
if (!$user->pass('administrator', true)) {
    throw new Typecho_Exception('权限不足', 403);
}

$type = $request->get('type');
$format = $request->get('format', 'csv');

try {
    switch ($type) {
        case 'submissions':
            exportSubmissions();
            break;
            
        case 'calendar':
            exportCalendar();
            break;
            
        case 'analytics':
            exportAnalytics();
            break;
            
        default:
            throw new Exception('未知的导出类型');
    }
} catch (Exception $e) {
    echo '<div class="message error">导出失败: ' . $e->getMessage() . '</div>';
}

function exportSubmissions() {
    global $db, $request;
    
    $form_id = $request->get('form_id');
    $date_from = $request->get('date_from');
    $date_to = $request->get('date_to');
    $status = $request->get('status');
    $format = $request->get('format', 'csv');
    
    if (!$form_id) {
        throw new Exception('表单ID不能为空');
    }
    
    $form = UformsHelper::getForm($form_id);
    if (!$form) {
        throw new Exception('表单不存在');
    }
    
    // 构建查询
    $select = $db->select('*')->from('table.uforms_submissions')
                 ->where('form_id = ?', $form_id);
    
    if ($date_from) {
        $select->where('created_time >= ?', strtotime($date_from));
    }
    
    if ($date_to) {
        $select->where('created_time <= ?', strtotime($date_to . ' 23:59:59'));
    }
    
    if ($status) {
        $select->where('status = ?', $status);
    }
    
    $submissions = $db->fetchAll($select->order('created_time DESC'));
    
    // 获取字段
    $fields = UformsHelper::getFormFields($form_id);
    
    if ($format === 'csv') {
        exportSubmissionsToCSV($submissions, $fields, $form);
    } elseif ($format === 'excel') {
        exportSubmissionsToExcel($submissions, $fields, $form);
    } elseif ($format === 'json') {
        exportSubmissionsToJSON($submissions, $fields, $form);
    } else {
        throw new Exception('不支持的导出格式');
    }
}

function exportSubmissionsToCSV($submissions, $fields, $form) {
    $filename = $form['name'] . '_submissions_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // 输出BOM以支持中文
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // 写入表头
    $headers = array('ID', '提交时间', 'IP地址', '状态');
    foreach ($fields as $field) {
        $headers[] = $field['field_label'];
    }
    $headers[] = '备注';
    
    fputcsv($output, $headers);
    
    // 写入数据
    foreach ($submissions as $submission) {
        $data = json_decode($submission['data'], true) ?: array();
        
        $row = array(
            $submission['id'],
            date('Y-m-d H:i:s', $submission['created_time']),
            $submission['ip'],
            getStatusLabel($submission['status'])
        );
        
        foreach ($fields as $field) {
            $value = $data[$field['field_name']] ?? '';
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $row[] = $value;
        }
        
        $row[] = $submission['notes'] ?? '';
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function exportSubmissionsToExcel($submissions, $fields, $form) {
    require_once __TYPECHO_ROOT_DIR__ . '/usr/plugins/Uforms/lib/PhpSpreadsheet/autoload.php';
    
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;
    use PhpOffice\PhpSpreadsheet\Style\Font;
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // 设置文档属性
    $spreadsheet->getProperties()
                ->setCreator('Uforms Plugin')
                ->setTitle($form['title'] . ' - 提交数据')
                ->setSubject('表单提交数据导出')
                ->setDescription('通过 Uforms 插件导出的表单提交数据');
    
    // 设置表头
    $headers = array('ID', '提交时间', 'IP地址', '状态');
    foreach ($fields as $field) {
        $headers[] = $field['field_label'];
    }
    $headers[] = '备注';
    
    $col = 1;
    foreach ($headers as $header) {
        $sheet->setCellValueByColumnAndRow($col, 1, $header);
        $col++;
    }
    
    // 设置表头样式
    $headerStyle = array(
        'font' => array(
            'bold' => true,
            'size' => 12
        ),
        'alignment' => array(
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        )
    );
    
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);
    
    // 写入数据
    $row = 2;
    foreach ($submissions as $submission) {
        $data = json_decode($submission['data'], true) ?: array();
        
        $sheet->setCellValueByColumnAndRow(1, $row, $submission['id']);
        $sheet->setCellValueByColumnAndRow(2, $row, date('Y-m-d H:i:s', $submission['created_time']));
        $sheet->setCellValueByColumnAndRow(3, $row, $submission['ip']);
        $sheet->setCellValueByColumnAndRow(4, $row, getStatusLabel($submission['status']));
        
        $col = 5;
        foreach ($fields as $field) {
            $value = $data[$field['field_name']] ?? '';
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $sheet->setCellValueByColumnAndRow($col, $row, $value);
            $col++;
        }
        
        $sheet->setCellValueByColumnAndRow($col, $row, $submission['notes'] ?? '');
        $row++;
    }
    
    // 自动调整列宽
    foreach (range('A', $sheet->getHighestColumn()) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // 输出文件
    $filename = $form['name'] . '_submissions_' . date('Y-m-d') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function exportSubmissionsToJSON($submissions, $fields, $form) {
    $data = array(
        'form' => array(
            'id' => $form['id'],
            'name' => $form['name'],
            'title' => $form['title'],
            'description' => $form['description']
        ),
        'fields' => $fields,
        'submissions' => array(),
        'exported_at' => date('Y-m-d H:i:s'),
        'total_count' => count($submissions)
    );
    
    foreach ($submissions as $submission) {
        $submission_data = array(
            'id' => $submission['id'],
            'created_time' => date('Y-m-d H:i:s', $submission['created_time']),
            'ip' => $submission['ip'],
            'status' => $submission['status'],
            'data' => json_decode($submission['data'], true),
            'notes' => $submission['notes'],
            'user_agent' => $submission['user_agent']
        );
        
        $data['submissions'][] = $submission_data;
    }
    
    $filename = $form['name'] . '_submissions_' . date('Y-m-d') . '.json';
    
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function exportCalendar() {
    global $db, $request;
    
    $form_id = $request->get('form_id');
    $start = $request->get('start');
    $end = $request->get('end');
    $format = $request->get('format', 'ics');
    
    // 构建查询
    $select = $db->select('*')->from('table.uforms_calendar');
    
    if ($form_id) {
        $select->where('form_id = ?', $form_id);
    }
    
    if ($start && $end) {
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        $select->where('start_time <= ? AND end_time >= ?', $end_time, $start_time);
    }
    
    $events = $db->fetchAll($select->order('start_time ASC'));
    
    if ($format === 'ics') {
        exportCalendarToICS($events);
    } elseif ($format === 'csv') {
        exportCalendarToCSV($events);
    } else {
        throw new Exception('不支持的日历导出格式');
    }
}

function exportCalendarToICS($events) {
    $filename = 'uforms_calendar_' . date('Y-m-d') . '.ics';
    
    header('Content-Type: text/calendar; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//Uforms//Uforms Plugin//EN\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    
    foreach ($events as $event) {
        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . $event['id'] . "@uforms\r\n";
        echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        echo "DTSTART:" . gmdate('Ymd\THis\Z', $event['start_time']) . "\r\n";
        
        if ($event['end_time']) {
            echo "DTEND:" . gmdate('Ymd\THis\Z', $event['end_time']) . "\r\n";
        }
        
        echo "SUMMARY:" . str_replace(array(',', ';', '\\', "\n"), array('\\,', '\\;', '\\\\', '\\n'), $event['title']) . "\r\n";
        
        if ($event['description']) {
            echo "DESCRIPTION:" . str_replace(array(',', ';', '\\', "\n"), array('\\,', '\\;', '\\\\', '\\n'), $event['description']) . "\r\n";
        }
        
        echo "STATUS:" . strtoupper($event['status']) . "\r\n";
        echo "END:VEVENT\r\n";
    }
    
    echo "END:VCALENDAR\r\n";
    exit;
}

function exportCalendarToCSV($events) {
    $filename = 'uforms_calendar_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // 输出BOM以支持中文
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // 写入表头
    $headers = array('ID', '标题', '开始时间', '结束时间', '全天', '状态', '描述', '表单', '创建时间');
    fputcsv($output, $headers);
    
    // 写入数据
    foreach ($events as $event) {
        $row = array(
            $event['id'],
            $event['title'],
            date('Y-m-d H:i:s', $event['start_time']),
            $event['end_time'] ? date('Y-m-d H:i:s', $event['end_time']) : '',
            $event['all_day'] ? '是' : '否',
            $event['status'],
            $event['description'],
            $event['form_title'] ?? '',
            date('Y-m-d H:i:s', $event['created_time'])
        );
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function exportAnalytics() {
    global $db, $request;
    
    $form_id = $request->get('form_id');
    $date_from = $request->get('date_from');
    $date_to = $request->get('date_to');
    $format = $request->get('format', 'json');
    
    // 获取分析数据
    $analytics_data = array(
        'summary' => array(),
        'submissions_by_day' => array(),
        'field_analysis' => array(),
        'device_stats' => array(),
        'conversion_funnel' => array()
    );
    
    // 构建基础查询条件
    $where_conditions = array();
    $where_values = array();
    
    if ($form_id) {
        $where_conditions[] = 'form_id = ?';
        $where_values[] = $form_id;
    }
    
    if ($date_from) {
        $where_conditions[] = 'created_time >= ?';
        $where_values[] = strtotime($date_from);
    }
    
    if ($date_to) {
        $where_conditions[] = 'created_time <= ?';
        $where_values[] = strtotime($date_to . ' 23:59:59');
    }
    
    $where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // 获取汇总数据
    $total_submissions = $db->fetchObject(
        $db->select('COUNT(*) as count')->from('table.uforms_submissions') . $where_clause,
        ...$where_values
    )->count;
    
    $analytics_data['summary'] = array(
        'total_submissions' => $total_submissions,
        'date_range' => array(
            'from' => $date_from ?: 'all',
            'to' => $date_to ?: date('Y-m-d')
        ),
        'generated_at' => date('Y-m-d H:i:s')
    );
    
    // 获取每日提交统计
    $daily_stats = $db->fetchAll("
        SELECT 
            DATE(FROM_UNIXTIME(created_time)) as date,
            COUNT(*) as count
        FROM " . $db->getPrefix() . "uforms_submissions" . $where_clause . "
        GROUP BY DATE(FROM_UNIXTIME(created_time))
        ORDER BY date DESC
        LIMIT 30
    ", ...$where_values);
    
    $analytics_data['submissions_by_day'] = $daily_stats;
    
    // 导出数据
    if ($format === 'json') {
        exportAnalyticsToJSON($analytics_data);
    } elseif ($format === 'csv') {
        exportAnalyticsToCSV($analytics_data);
    } else {
        throw new Exception('不支持的分析数据导出格式');
    }
}

function exportAnalyticsToJSON($analytics_data) {
    $filename = 'uforms_analytics_' . date('Y-m-d') . '.json';
    
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    echo json_encode($analytics_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function exportAnalyticsToCSV($analytics_data) {
    $filename = 'uforms_analytics_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // 输出BOM以支持中文
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // 写入汇总信息
    fputcsv($output, array('数据类型', '值'));
    fputcsv($output, array('总提交数', $analytics_data['summary']['total_submissions']));
    fputcsv($output, array('统计日期范围', $analytics_data['summary']['date_range']['from'] . ' 至 ' . $analytics_data['summary']['date_range']['to']));
    fputcsv($output, array('生成时间', $analytics_data['summary']['generated_at']));
    fputcsv($output, array()); // 空行
    
    // 写入每日统计
    fputcsv($output, array('日期', '提交数'));
    foreach ($analytics_data['submissions_by_day'] as $day_stat) {
        fputcsv($output, array($day_stat['date'], $day_stat['count']));
    }
    
    fclose($output);
    exit;
}

function getStatusLabel($status) {
    $labels = array(
        'new' => '未读',
        'read' => '已读',
        'spam' => '垃圾',
        'deleted' => '已删除'
    );
    return $labels[$status] ?? $status;
}
?>
