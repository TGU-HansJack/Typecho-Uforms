<?php
// 初始化变量
$request = Typecho_Request::getInstance();
$date_range = $request->get('date_range', '7days');
$form_id = $request->get('form_id');

// 获取时间范围
$start_time = 0;
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
    $where_conditions[] = 's.created_time >= ?';  // 使用表别名
    $where_values[] = $start_time;
}

if ($form_id) {
    $where_conditions[] = 's.form_id = ?';  // 使用表别名
    $where_values[] = $form_id;
}

$where_clause = !empty($where_conditions) ? ' WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取统计数据
$select1 = $db->select('COUNT(*) as count')->from('table.uforms_submissions s');
if (!empty($where_conditions)) {
    $select1->where(implode(' AND ', $where_conditions), ...$where_values);
}
$total_submissions = $db->fetchObject($select1)->count;

$select2 = $db->select('COUNT(*) as count')->from('table.uforms_submissions s');
if (!empty($where_conditions)) {
    $select2->where(implode(' AND ', $where_conditions), ...$where_values);
}
$select2->where('s.status = ?', 'new');  // 使用表别名
$new_submissions = $db->fetchObject($select2)->count;

// 获取每日提交统计
$daily_stats = array();
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', time() - ($i * 24 * 60 * 60));
    $day_start = strtotime($date . ' 00:00:00');
    $day_end = strtotime($date . ' 23:59:59');
    
    $day_conditions = $where_conditions;
    $day_values = $where_values;
    $day_conditions[] = 's.created_time >= ? AND s.created_time <= ?';  // 使用表别名
    $day_values[] = $day_start;
    $day_values[] = $day_end;
    
    $select = $db->select('COUNT(*) as count')->from('table.uforms_submissions s');
    if (!empty($day_conditions)) {
        $select->where(implode(' AND ', $day_conditions), ...$day_values);
    }
    
    $count = $db->fetchObject($select)->count;
    
    $daily_stats[] = array(
        'date' => $date,
        'count' => $count
    );
}

// 获取表单提交统计
$form_select = $db->select('f.title', 'COUNT(s.id) as submission_count')
   ->from('table.uforms_forms f')
   ->join('table.uforms_submissions s', 'f.id = s.form_id', Typecho_Db::LEFT_JOIN)
   ->group('f.id')
   ->order('submission_count', Typecho_Db::SORT_DESC)
   ->limit(10);

if (!empty($where_conditions)) {
    $form_select->where(implode(' AND ', $where_conditions), ...$where_values);
}
$form_stats = $db->fetchAll($form_select);

// 获取最近提交
$recent_select = $db->select('s.*', 'f.title as form_title')
   ->from('table.uforms_submissions s')
   ->join('table.uforms_forms f', 's.form_id = f.id', Typecho_Db::LEFT_JOIN)
   ->order('s.created_time', Typecho_Db::SORT_DESC)
   ->limit(10);

if (!empty($where_conditions)) {
    $recent_select->where(implode(' AND ', $where_conditions), ...$where_values);
}
$recent_submissions = $db->fetchAll($recent_select);

// 计算增长率
$prev_period_start = 0;
$prev_submissions = 0;
if ($start_time > 0) {
    $prev_period_start = $start_time - (time() - $start_time);
    $prev_select = $db->select('COUNT(*) as count')->from('table.uforms_submissions s');
    $prev_conditions = array();
    $prev_values = array();
    
    $prev_conditions[] = 's.created_time >= ? AND s.created_time < ?';
    $prev_values[] = $prev_period_start;
    $prev_values[] = $start_time;
    
    if ($form_id) {
        $prev_conditions[] = 's.form_id = ?';
        $prev_values[] = $form_id;
    }
    
    $prev_select->where(implode(' AND ', $prev_conditions), ...$prev_values);
    $prev_submissions = $db->fetchObject($prev_select)->count;
}

$growth = $prev_submissions > 0 ? (($total_submissions - $prev_submissions) / $prev_submissions * 100) : 0;
$growth_class = $growth >= 0 ? 'positive' : 'negative';
$growth_icon = $growth >= 0 ? 'arrow up icon' : 'arrow down icon';

// 获取活跃表单数量
$active_forms_select = $db->select('COUNT(DISTINCT form_id) as count')
    ->from('table.uforms_submissions s');
    
if (!empty($where_conditions)) {
    $active_forms_select->where(implode(' AND ', $where_conditions), ...$where_values);
}
$active_forms = $db->fetchObject($active_forms_select)->count;
?>

<div class="overview-dashboard">
    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-header">
                <h3>总提交数</h3>
                <i class="inbox icon"></i>
            </div>
            <div class="stat-body">
                <div class="stat-number"><?php echo number_format($total_submissions); ?></div>
                <div class="stat-growth">
                    <span class="growth <?php echo $growth_class; ?>">
                        <i class="<?php echo $growth_icon; ?>"></i>
                        <?php echo abs(round($growth, 1)); ?>%
                    </span>
                </div>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-header">
                <h3>未读提交</h3>
                <i class="envelope open outline icon"></i>
            </div>
            <div class="stat-body">
                <div class="stat-number"><?php echo number_format($new_submissions); ?></div>
                <div class="stat-percentage">
                    <?php 
                    $unread_percentage = $total_submissions > 0 ? ($new_submissions / $total_submissions * 100) : 0;
                    echo round($unread_percentage, 1) . '%';
                    ?>
                </div>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-header">
                <h3>平均每日</h3>
                <i class="chart line icon"></i>
            </div>
            <div class="stat-body">
                <?php
                $days = $date_range === 'all' ? max(1, (time() - strtotime('2020-01-01')) / (24 * 60 * 60)) : 
                        ($date_range === '7days' ? 7 : 
                        ($date_range === '30days' ? 30 : 
                        ($date_range === '3months' ? 90 : 365)));
                $avg_daily = $total_submissions / $days;
                ?>
                <div class="stat-number"><?php echo round($avg_daily, 1); ?></div>
                <div class="stat-label">提交/天</div>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-header">
                <h3>活跃表单</h3>
                <i class="wpforms icon"></i>
            </div>
            <div class="stat-body">
                <?php
                $active_forms_select = $db->select('COUNT(DISTINCT form_id) as count')
                    ->from('table.uforms_submissions s');
                    
                if (!empty($where_conditions)) {
                    $active_forms_select->where(implode(' AND ', $where_conditions), ...$where_values);
                }
                $active_forms = $db->fetchObject($active_forms_select)->count;
                ?>
                <div class="stat-number"><?php echo $active_forms; ?></div>
                <div class="stat-label">个表单</div>
            </div>
        </div>
    </div>
    
    <!-- 图表区域 -->
    <div class="charts-grid">
        <!-- 提交趋势图 -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>提交趋势</h3>
                <div class="chart-actions">
                    <button class="ui button btn-chart-type" data-type="line">线图</button>
                    <button class="ui primary button btn-chart-type active" data-type="bar">柱图</button>
                </div>
            </div>
            <div class="chart-body">
                <div id="submissions-chart" style="height: 300px;"></div>
            </div>
        </div>
        
        <!-- 表单分布图 -->
        <?php if (!$form_id && !empty($form_stats)): ?>
        <div class="chart-card">
            <div class="chart-header">
                <h3>表单提交分布</h3>
            </div>
            <div class="chart-body">
                <div id="forms-pie-chart" style="height: 300px;"></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 状态分布图 -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>提交状态分布</h3>
            </div>
            <div class="chart-body">
                <div id="status-chart" style="height: 300px;"></div>
            </div>
        </div>
    </div>
    
    <!-- 最近活动 -->
    <div class="activity-section">
        <div class="section-header">
            <h3>最近提交</h3>
            <a href="?view=view&type=submissions" class="ui button">查看全部</a>
        </div>
        
        <?php if (empty($recent_submissions)): ?>
        <div class="empty-state">
            <i class="inbox icon"></i>
            <p>暂无提交数据</p>
        </div>
        <?php else: ?>
        <div class="activity-list">
            <?php foreach ($recent_submissions as $submission): ?>
            <?php $data = json_decode($submission['data'], true); ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="inbox icon"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">
                        <strong><?php echo htmlspecialchars($submission['form_title']); ?></strong>
                        <span class="ui label activity-status status-<?php echo $submission['status']; ?>">
                            <?php
                            $status_labels = array(
                                'new' => '新提交',
                                'read' => '已读',
                                'spam' => '垃圾',
                                'deleted' => '已删除'
                            );
                            echo $status_labels[$submission['status']] ?? $submission['status'];
                            ?>
                        </span>
                    </div>
                    <div class="activity-meta">
                        <span class="activity-time"><?php echo Typecho_I18n::dateWord($submission['created_time'], time()); ?></span>
                        <span class="activity-ip"><?php echo $submission['ip']; ?></span>
                    </div>
                    <?php if (!empty($data)): ?>
                    <div class="activity-preview">
                        <?php
                        $preview_fields = array_slice($data, 0, 2);
                        foreach ($preview_fields as $key => $value) {
                            if (is_array($value)) {
                                $value = implode(', ', $value);
                            }
                            echo '<span class="field-preview">' . htmlspecialchars($key) . ': ' . htmlspecialchars(mb_substr($value, 0, 30)) . '</span>';
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="activity-actions">
                    <a href="?view=submission&id=<?php echo $submission['id']; ?>" class="ui icon button" title="查看详情">
                        <i class="eye icon"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // 初始化图表
    initSubmissionsChart();
    <?php if (!$form_id && !empty($form_stats)): ?>
    initFormsPieChart();
    <?php endif; ?>
    initStatusChart();
    
    // 图表类型切换
    $('.btn-chart-type').on('click', function() {
        $('.btn-chart-type').removeClass('active');
        $(this).addClass('active');
        
        const type = $(this).data('type');
        updateSubmissionsChart(type);
    });
});

function initSubmissionsChart() {
    const chart = echarts.init(document.getElementById('submissions-chart'));
    
    const data = <?php echo json_encode($daily_stats); ?>;
    const dates = data.map(item => item.date);
    const counts = data.map(item => item.count);
    
    const option = {
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'cross'
            }
        },
        xAxis: {
            type: 'category',
            data: dates,
            axisLabel: {
                formatter: function(value) {
                    return echarts.format.formatTime('MM-dd', value);
                }
            }
        },
        yAxis: {
            type: 'value'
        },
        series: [{
            data: counts,
            type: 'bar',
            itemStyle: {
                color: '#007cba'
            },
            emphasis: {
                itemStyle: {
                    color: '#005a87'
                }
            }
        }]
    };
    
    chart.setOption(option);
    window.submissionsChart = chart;
}

function updateSubmissionsChart(type) {
    const chart = window.submissionsChart;
    const option = chart.getOption();
    
    option.series[0].type = type;
    if (type === 'line') {
        option.series[0].smooth = true;
    }
    
    chart.setOption(option);
}

<?php if (!$form_id && !empty($form_stats)): ?>
function initFormsPieChart() {
    const chart = echarts.init(document.getElementById('forms-pie-chart'));
    
    const data = <?php echo json_encode(array_map(function($stat) {
        return array('name' => $stat['title'], 'value' => $stat['submission_count']);
    }, $form_stats)); ?>;
    
    const option = {
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c} ({d}%)'
        },
        legend: {
            orient: 'vertical',
            left: 'left',
            data: data.map(item => item.name)
        },
        series: [{
            name: '提交数',
            type: 'pie',
            radius: '50%',
            data: data,
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowOffsetX: 0,
                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                }
            }
        }]
    };
    
    chart.setOption(option);
}
<?php endif; ?>

function initStatusChart() {
    const chart = echarts.init(document.getElementById('status-chart'));
    
    // 获取状态统计数据
    <?php
    $status_stats = array();
    $statuses = array('new', 'read', 'spam');
    
    foreach ($statuses as $status) {
        $count_where = array_merge($where_conditions, array('s.status = ?'));
        $count_values = array_merge($where_values, array($status));
        
        $count_select = $db->select('COUNT(*) as count')
                           ->from('table.uforms_submissions s');
        
        if (!empty($count_where)) {
            $count_select->where(implode(' AND ', $count_where), ...$count_values);
        }
        
        $count = $db->fetchObject($count_select)->count;
        
        $status_stats[] = array(
            'name' => $status === 'new' ? '新提交' : ($status === 'read' ? '已读' : '垃圾'),
            'value' => $count
        );
    }
    ?>
    
    const data = <?php echo json_encode($status_stats); ?>;
    
    const option = {
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c} ({d}%)'
        },
        series: [{
            name: '状态分布',
            type: 'pie',
            radius: ['30%', '60%'],
            data: data,
            itemStyle: {
                color: function(params) {
                    const colors = ['#f39c12', '#27ae60', '#e74c3c'];
                    return colors[params.dataIndex];
                }
            }
        }]
    };
    
    chart.setOption(option);
}

// 响应式处理
window.addEventListener('resize', function() {
    if (window.submissionsChart) {
        window.submissionsChart.resize();
    }
});
</script>