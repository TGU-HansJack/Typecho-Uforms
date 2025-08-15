<?php
// 获取分析数据的时间范围
$start_time = 0;
$end_time = time();

switch ($date_range) {
    case '7days':
        $start_time = time() - (7 * 24 * 60 * 60);
        break;
    case '30days':
        $start_time = time() - (30 * 24 * 60 * 60);
        break;
    case '3months':
        $start_time = time() - (90 * 24 * 60 * 60);
        break;
    case '1year':
        $start_time = time() - (365 * 24 * 60 * 60);
        break;
}

// 构建查询条件
$where_conditions = array();
$where_values = array();

if ($start_time > 0) {
    $where_conditions[] = 'created_time >= ?';
    $where_values[] = $start_time;
}

if ($form_id) {
    $where_conditions[] = 'form_id = ?';
    $where_values[] = $form_id;
}

$where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取转化率数据
$conversion_data = array();
if ($form_id) {
    // 获取表单访问统计（如果有的话）
    $views = $db->fetchObject(
        $db->select('COUNT(*) as count')->from('table.uforms_stats')
        . " WHERE form_id = ? AND action = 'view'" . ($start_time > 0 ? " AND created_time >= ?" : ""),
        $form_id, ...($start_time > 0 ? array($start_time) : array())
    )->count;
    
    $submissions = $db->fetchObject(
        $db->select('COUNT(*) as count')->from('table.uforms_submissions')
        . " WHERE form_id = ?" . ($start_time > 0 ? " AND created_time >= ?" : ""),
        $form_id, ...($start_time > 0 ? array($start_time) : array())
    )->count;
    
    $conversion_rate = $views > 0 ? ($submissions / $views * 100) : 0;
    
    $conversion_data = array(
        'views' => $views,
        'submissions' => $submissions,
        'conversion_rate' => round($conversion_rate, 2)
    );
}

// 获取字段分析数据
$field_analytics = array();
if ($form_id) {
    $form_fields = UformsHelper::getFormFields($form_id);
    $submissions = $db->fetchAll(
        $db->select('data')->from('table.uforms_submissions')
        . " WHERE form_id = ?" . ($start_time > 0 ? " AND created_time >= ?" : ""),
        $form_id, ...($start_time > 0 ? array($start_time) : array())
    );
    
    foreach ($form_fields as $field) {
        $field_name = $field['field_name'];
        $field_config = json_decode($field['field_config'], true);
        
        $field_data = array(
            'name' => $field_name,
            'label' => $field['field_label'],
            'type' => $field['field_type'],
            'required' => $field['is_required'],
            'completion_rate' => 0,
            'values' => array(),
            'stats' => array()
        );
        
        $completed_count = 0;
        $all_values = array();
        
        foreach ($submissions as $submission) {
            $data = json_decode($submission['data'], true);
            
            if (isset($data[$field_name]) && !empty($data[$field_name])) {
                $completed_count++;
                $value = $data[$field_name];
                
                if (is_array($value)) {
                    $all_values = array_merge($all_values, $value);
                } else {
                    $all_values[] = $value;
                }
            }
        }
        
        $total_submissions = count($submissions);
        $field_data['completion_rate'] = $total_submissions > 0 ? 
                                       ($completed_count / $total_submissions * 100) : 0;
        
        // 分析字段值
        if ($field['field_type'] === 'select' || $field['field_type'] === 'radio' || $field['field_type'] === 'checkbox') {
            $value_counts = array_count_values($all_values);
            arsort($value_counts);
            $field_data['values'] = $value_counts;
        } elseif ($field['field_type'] === 'number' || $field['field_type'] === 'range') {
            $numeric_values = array_filter($all_values, 'is_numeric');
            if (!empty($numeric_values)) {
                $field_data['stats'] = array(
                    'min' => min($numeric_values),
                    'max' => max($numeric_values),
                    'avg' => round(array_sum($numeric_values) / count($numeric_values), 2),
                    'count' => count($numeric_values)
                );
            }
        } elseif ($field['field_type'] === 'text' || $field['field_type'] === 'textarea') {
            $lengths = array_map('strlen', $all_values);
            if (!empty($lengths)) {
                $field_data['stats'] = array(
                    'min_length' => min($lengths),
                    'max_length' => max($lengths),
                    'avg_length' => round(array_sum($lengths) / count($lengths), 2),
                    'total_words' => array_sum(array_map('str_word_count', $all_values))
                );
            }
        }
        
        $field_analytics[] = $field_data;
    }
}

// 获取设备和浏览器统计
$device_stats = $db->fetchAll("
    SELECT 
        CASE 
            WHEN user_agent LIKE '%Mobile%' THEN 'Mobile'
            WHEN user_agent LIKE '%Tablet%' THEN 'Tablet'
            ELSE 'Desktop'
        END as device_type,
        COUNT(*) as count
    FROM " . $db->getPrefix() . "uforms_submissions s" . $where_clause . "
    GROUP BY device_type
", ...$where_values);

// 获取地理位置统计（基于IP）
$location_stats = $db->fetchAll("
    SELECT 
        SUBSTRING_INDEX(ip, '.', 2) as ip_range,
        COUNT(*) as count
    FROM " . $db->getPrefix() . "uforms_submissions s" . $where_clause . "
    GROUP BY ip_range
    ORDER BY count DESC
    LIMIT 10
", ...$where_values);

// 获取时间分布统计
$hourly_stats = array_fill(0, 24, 0);
$daily_stats = array();

$submissions_raw = $db->fetchAll(
    $db->select('created_time')->from('table.uforms_submissions')
    . $where_clause,
    ...$where_values
);

foreach ($submissions_raw as $submission) {
    $hour = (int)date('G', $submission['created_time']);
    $day = date('w', $submission['created_time']); // 0 = Sunday
    
    $hourly_stats[$hour]++;
    
    if (!isset($daily_stats[$day])) {
        $daily_stats[$day] = 0;
    }
    $daily_stats[$day]++;
}

// 获取漏斗分析数据
$funnel_data = array();
if ($form_id) {
    $form_fields = UformsHelper::getFormFields($form_id);
    $total_starts = $conversion_data['views'] ?? 0;
    
    if ($total_starts > 0) {
        $funnel_data[] = array(
            'step' => '表单访问',
            'count' => $total_starts,
            'rate' => 100
        );
        
        foreach ($form_fields as $index => $field) {
            if ($field['is_required']) {
                $completed = 0;
                foreach ($submissions as $submission) {
                    $data = json_decode($submission['data'], true);
                    if (isset($data[$field['field_name']]) && !empty($data[$field['field_name']])) {
                        $completed++;
                    }
                }
                
                $rate = ($completed / $total_starts) * 100;
                $funnel_data[] = array(
                    'step' => $field['field_label'],
                    'count' => $completed,
                    'rate' => round($rate, 2)
                );
            }
        }
        
        $funnel_data[] = array(
            'step' => '表单提交',
            'count' => $conversion_data['submissions'],
            'rate' => $conversion_data['conversion_rate']
        );
    }
}
?>

<div class="analytics-dashboard">
    <!-- 关键指标 -->
    <div class="kpi-section">
        <h3>关键指标</h3>
        <div class="kpi-grid">
            <?php if ($form_id && !empty($conversion_data)): ?>
            <div class="kpi-card">
                <div class="kpi-value"><?php echo number_format($conversion_data['views']); ?></div>
                <div class="kpi-label">页面访问</div>
                <div class="kpi-trend">
                    <i class="icon-eye"></i>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo number_format($conversion_data['submissions']); ?></div>
                <div class="kpi-label">表单提交</div>
                <div class="kpi-trend">
                    <i class="icon-submit"></i>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo $conversion_data['conversion_rate']; ?>%</div>
                <div class="kpi-label">转化率</div>
                <div class="kpi-trend <?php echo $conversion_data['conversion_rate'] > 10 ? 'positive' : 'neutral'; ?>">
                    <i class="icon-percentage"></i>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="kpi-card">
                <div class="kpi-value"><?php echo number_format(array_sum($hourly_stats)); ?></div>
                <div class="kpi-label">总提交数</div>
                <div class="kpi-trend">
                    <i class="icon-total"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 图表分析 -->
    <div class="charts-section">
        <div class="charts-grid">
            <!-- 时间分布图 -->
            <div class="chart-card">
                <div class="chart-header">
                    <h4>提交时间分布</h4>
                    <div class="chart-tabs">
                        <button class="chart-tab active" data-chart="hourly">按小时</button>
                        <button class="chart-tab" data-chart="daily">按星期</button>
                    </div>
                </div>
                <div class="chart-body">
                    <div id="time-distribution-chart" style="height: 300px;"></div>
                </div>
            </div>
            
            <!-- 设备分布图 -->
            <div class="chart-card">
                <div class="chart-header">
                    <h4>设备类型分布</h4>
                </div>
                <div class="chart-body">
                    <div id="device-chart" style="height: 300px;"></div>
                </div>
            </div>
            
            <?php if ($form_id && !empty($funnel_data)): ?>
            <!-- 转化漏斗图 -->
            <div class="chart-card full-width">
                <div class="chart-header">
                    <h4>转化漏斗</h4>
                </div>
                <div class="chart-body">
                    <div id="funnel-chart" style="height: 400px;"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 字段分析 -->
    <?php if (!empty($field_analytics)): ?>
    <div class="field-analysis-section">
        <h3>字段分析</h3>
        <div class="fields-grid">
            <?php foreach ($field_analytics as $field): ?>
            <div class="field-card">
                <div class="field-header">
                    <h4><?php echo htmlspecialchars($field['label']); ?></h4>
                    <span class="field-type"><?php echo $field['type']; ?></span>
                    <?php if ($field['required']): ?>
                    <span class="required-badge">必填</span>
                    <?php endif; ?>
                </div>
                
                <div class="field-metrics">
                    <div class="metric">
                        <div class="metric-value"><?php echo round($field['completion_rate'], 1); ?>%</div>
                        <div class="metric-label">完成率</div>
                    </div>
                </div>
                
                <?php if (!empty($field['values'])): ?>
                <div class="field-values">
                    <h5>选项分布</h5>
                    <div class="values-chart">
                        <?php 
                        $total_values = array_sum($field['values']);
                        foreach (array_slice($field['values'], 0, 5) as $value => $count): 
                            $percentage = ($count / $total_values) * 100;
                        ?>
                        <div class="value-bar">
                            <div class="value-label"><?php echo htmlspecialchars($value); ?></div>
                            <div class="value-progress">
                                <div class="progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                                <span class="progress-text"><?php echo $count; ?> (<?php echo round($percentage, 1); ?>%)</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($field['stats'])): ?>
                <div class="field-stats">
                    <h5>统计数据</h5>
                    <div class="stats-grid">
                        <?php foreach ($field['stats'] as $key => $value): ?>
                        <div class="stat-item">
                            <div class="stat-label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?></div>
                            <div class="stat-value"><?php echo $value; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- 地理分布 -->
    <?php if (!empty($location_stats)): ?>
    <div class="location-section">
        <h3>地理分布</h3>
        <div class="location-table">
            <table>
                <thead>
                    <tr>
                        <th>IP段</th>
                        <th>提交数</th>
                        <th>百分比</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_locations = array_sum(array_column($location_stats, 'count'));
                    foreach ($location_stats as $location): 
                        $percentage = ($location['count'] / $total_locations) * 100;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($location['ip_range']); ?>.x.x</td>
                        <td><?php echo $location['count']; ?></td>
                        <td>
                            <div class="percentage-bar">
                                <div class="bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                                <span><?php echo round($percentage, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // 初始化图表
    initTimeDistributionChart();
    initDeviceChart();
    <?php if ($form_id && !empty($funnel_data)): ?>
    initFunnelChart();
    <?php endif; ?>
    
    // 图表标签切换
    $('.chart-tab').on('click', function() {
        const chartType = $(this).data('chart');
        const parent = $(this).closest('.chart-card');
        
        parent.find('.chart-tab').removeClass('active');
        $(this).addClass('active');
        
        if (parent.find('#time-distribution-chart').length) {
            updateTimeDistributionChart(chartType);
        }
    });
});

function initTimeDistributionChart() {
    const chart = echarts.init(document.getElementById('time-distribution-chart'));
    
    const hourlyData = <?php echo json_encode($hourly_stats); ?>;
    const dailyData = <?php echo json_encode($daily_stats); ?>;
    
    window.timeChart = chart;
    window.timeChartData = {
        hourly: hourlyData,
        daily: dailyData
    };
    
    updateTimeDistributionChart('hourly');
}

function updateTimeDistributionChart(type) {
    const chart = window.timeChart;
    let data, xAxisData, title;
    
    if (type === 'hourly') {
        data = window.timeChartData.hourly;
        xAxisData = Array.from({length: 24}, (_, i) => i + ':00');
        title = '每小时提交分布';
    } else {
        const dailyLabels = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
        data = dailyLabels.map((_, index) => window.timeChartData.daily[index] || 0);
        xAxisData = dailyLabels;
        title = '每周提交分布';
    }
    
    const option = {
        title: {
            text: title,
            left: 'center',
            textStyle: {
                fontSize: 14
            }
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'cross'
            }
        },
        xAxis: {
            type: 'category',
            data: xAxisData
        },
        yAxis: {
            type: 'value'
        },
        series: [{
            data: data,
            type: 'bar',
            itemStyle: {
                color: '#3788d8'
            },
            emphasis: {
                itemStyle: {
                    color: '#2c5aa0'
                }
            }
        }]
    };
    
    chart.setOption(option);
}

function initDeviceChart() {
    const chart = echarts.init(document.getElementById('device-chart'));
    
    const deviceData = <?php echo json_encode(array_map(function($item) {
        return array('name' => $item['device_type'], 'value' => $item['count']);
    }, $device_stats)); ?>;
    
    const option = {
        title: {
            text: '设备类型分布',
            left: 'center',
            textStyle: {
                fontSize: 14
            }
        },
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c} ({d}%)'
        },
        legend: {
            orient: 'vertical',
            left: 'left',
            data: deviceData.map(item => item.name)
        },
        series: [{
            name: '设备类型',
            type: 'pie',
            radius: '50%',
            data: deviceData,
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowOffsetX: 0,
                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                }
            },
            itemStyle: {
                color: function(params) {
                    const colors = ['#3788d8', '#f39c12', '#27ae60'];
                    return colors[params.dataIndex % colors.length];
                }
            }
        }]
    };
    
    chart.setOption(option);
}

<?php if ($form_id && !empty($funnel_data)): ?>
function initFunnelChart() {
    const chart = echarts.init(document.getElementById('funnel-chart'));
    
    const funnelData = <?php echo json_encode($funnel_data); ?>;
    
    const option = {
        title: {
            text: '转化漏斗分析',
            left: 'center',
            textStyle: {
                fontSize: 16
            }
        },
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c} ({d}%)'
        },
        series: [{
            name: '转化漏斗',
            type: 'funnel',
            left: '10%',
            top: 60,
            bottom: 60,
            width: '80%',
            min: 0,
            max: Math.max(...funnelData.map(item => item.count)),
            minSize: '0%',
            maxSize: '100%',
            sort: 'descending',
            gap: 2,
            label: {
                show: true,
                position: 'inside'
            },
            labelLine: {
                length: 10,
                lineStyle: {
                    width: 1,
                    type: 'solid'
                }
            },
            itemStyle: {
                borderColor: '#fff',
                borderWidth: 1
            },
            emphasis: {
                label: {
                    fontSize: 20
                }
            },
            data: funnelData.map((item, index) => ({
                name: item.step,
                value: item.count,
                itemStyle: {
                    color: `hsl(${200 + index * 20}, 70%, 50%)`
                }
            }))
        }]
    };
    
    chart.setOption(option);
}
<?php endif; ?>

// 响应式处理
window.addEventListener('resize', function() {
    if (window.timeChart) window.timeChart.resize();
    const deviceChart = echarts.getInstanceByDom(document.getElementById('device-chart'));
    if (deviceChart) deviceChart.resize();
    <?php if ($form_id && !empty($funnel_data)): ?>
    const funnelChart = echarts.getInstanceByDom(document.getElementById('funnel-chart'));
    if (funnelChart) funnelChart.resize();
    <?php endif; ?>
});
</script>
