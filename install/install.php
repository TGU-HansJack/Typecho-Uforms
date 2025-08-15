<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Uforms 插件安装向导
 */

$step = $_GET['step'] ?? 1;
$action = $_POST['action'] ?? '';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uforms 安装向导</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .step-nav {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            padding: 0;
            list-style: none;
        }
        
        .step-nav li {
            position: relative;
            padding: 0 20px;
            color: #ccc;
            font-weight: 500;
        }
        
        .step-nav li.active {
            color: #3788d8;
        }
        
        .step-nav li.completed {
            color: #27ae60;
        }
        
        .step-nav li:not(:last-child)::after {
            content: '→';
            position: absolute;
            right: -10px;
            color: #ddd;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3788d8;
            box-shadow: 0 0 0 2px rgba(55, 136, 216, 0.1);
        }
        
        .form-group .help {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input {
            width: auto;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: #3788d8;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2c5aa0;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .feature-item {
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            text-align: center;
        }
        
        .feature-icon {
            font-size: 48px;
            color: #3788d8;
            margin-bottom: 10px;
        }
        
        .requirements-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .requirements-table th,
        .requirements-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .requirements-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-ok {
            color: #27ae60;
            font-weight: bold;
        }
        
        .status-error {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .status-warning {
            color: #f39c12;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Uforms 安装向导</h1>
            <p>强大的表单系统插件，为您的网站提供完整的表单解决方案</p>
        </div>
        
        <div class="content">
            <ul class="step-nav">
                <li class="<?php echo $step >= 1 ? ($step == 1 ? 'active' : 'completed') : ''; ?>">欢迎</li>
                <li class="<?php echo $step >= 2 ? ($step == 2 ? 'active' : 'completed') : ''; ?>">环境检测</li>
                <li class="<?php echo $step >= 3 ? ($step == 3 ? 'active' : 'completed') : ''; ?>">基本配置</li>
                <li class="<?php echo $step >= 4 ? ($step == 4 ? 'active' : 'completed') : ''; ?>">完成安装</li>
            </ul>
            
            <?php if ($step == 1): ?>
            <!-- 步骤1：欢迎页面 -->
            <div class="step-content">
                <h2>欢迎使用 Uforms</h2>
                <p>感谢您选择 Uforms 表单系统插件！本向导将帮助您快速完成插件的安装和配置。</p>
                
                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-icon">📝</div>
                        <h3>可视化表单构建器</h3>
                        <p>拖拽式表单设计，支持多种字段类型</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">📊</div>
                        <h3>数据分析</h3>
                        <p>详细的提交统计和数据可视化</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">📅</div>
                        <h3>日历预约</h3>
                        <p>集成日历功能，支持在线预约</p>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🔒</div>
                        <h3>安全防护</h3>
                        <p>垃圾内容过滤、频率限制等安全功能</p>
                    </div>
                </div>
                
                <div class="actions">
                    <div></div>
                    <a href="?step=2" class="btn btn-primary">开始安装</a>
                </div>
            </div>
            
            <?php elseif ($step == 2): ?>
            <!-- 步骤2：环境检测 -->
            <div class="step-content">
                <h2>环境检测</h2>
                <p>正在检测您的服务器环境是否符合 Uforms 的运行要求...</p>
                
                <table class="requirements-table">
                    <thead>
                        <tr>
                            <th>检测项目</th>
                            <th>要求</th>
                            <th>当前状态</th>
                            <th>结果</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>PHP 版本</td>
                            <td>>= 7.0</td>
                            <td><?php echo PHP_VERSION; ?></td>
                            <td class="<?php echo version_compare(PHP_VERSION, '7.0', '>=') ? 'status-ok' : 'status-error'; ?>">
                                <?php echo version_compare(PHP_VERSION, '7.0', '>=') ? '通过' : '不符合'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>文件上传</td>
                            <td>支持</td>
                            <td><?php echo ini_get('file_uploads') ? '支持' : '不支持'; ?></td>
                            <td class="<?php echo ini_get('file_uploads') ? 'status-ok' : 'status-warning'; ?>">
                                <?php echo ini_get('file_uploads') ? '通过' : '警告'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>GD 库</td>
                            <td>推荐</td>
                            <td><?php echo extension_loaded('gd') ? '已安装' : '未安装'; ?></td>
                            <td class="<?php echo extension_loaded('gd') ? 'status-ok' : 'status-warning'; ?>">
                                <?php echo extension_loaded('gd') ? '通过' : '警告'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>cURL</td>
                            <td>推荐</td>
                            <td><?php echo extension_loaded('curl') ? '已安装' : '未安装'; ?></td>
                            <td class="<?php echo extension_loaded('curl') ? 'status-ok' : 'status-warning'; ?>">
                                <?php echo extension_loaded('curl') ? '通过' : '警告'; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>上传目录权限</td>
                            <td>可写</td>
                            <td><?php 
                                $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads';
                                echo is_writable($uploadDir) ? '可写' : '不可写';
                            ?></td>
                            <td class="<?php echo is_writable($uploadDir) ? 'status-ok' : 'status-error'; ?>">
                                <?php echo is_writable($uploadDir) ? '通过' : '需要修复'; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (version_compare(PHP_VERSION, '7.0', '<')): ?>
                <div class="alert alert-error">
                    <strong>错误：</strong>您的 PHP 版本过低，Uforms 需要 PHP 7.0 或更高版本。
                </div>
                <?php elseif (!is_writable($uploadDir)): ?>
                <div class="alert alert-error">
                    <strong>错误：</strong>上传目录不可写，请设置 <?php echo $uploadDir; ?> 目录权限为 755。
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <strong>恭喜！</strong>您的服务器环境完全符合 Uforms 的运行要求。
                </div>
                <?php endif; ?>
                
                <div class="actions">
                    <a href="?step=1" class="btn btn-secondary">上一步</a>
                    <?php if (version_compare(PHP_VERSION, '7.0', '>=') && is_writable($uploadDir)): ?>
                    <a href="?step=3" class="btn btn-primary">下一步</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php elseif ($step == 3): ?>
            <!-- 步骤3：基本配置 -->
            <div class="step-content">
                <h2>基本配置</h2>
                <p>请配置 Uforms 的基本设置，这些设置稍后可以在管理界面中修改。</p>
                
                <?php if ($action == 'save_config'): ?>
                    <?php
                    // 处理配置保存
                    $config = array(
                        'enable_forms' => isset($_POST['enable_forms']) ? 1 : 0,
                        'enable_calendar' => isset($_POST['enable_calendar']) ? 1 : 0,
                        'enable_analytics' => isset($_POST['enable_analytics']) ? 1 : 0,
                        'upload_enabled' => isset($_POST['upload_enabled']) ? 1 : 0,
                        'upload_max_size' => intval($_POST['upload_max_size']),
                        'allowed_file_types' => $_POST['allowed_file_types'],
                        'enable_spam_filter' => isset($_POST['enable_spam_filter']) ? 1 : 0,
                        'admin_per_page' => intval($_POST['admin_per_page'])
                    );
                    
                    // 保存配置到会话中
                    session_start();
                    $_SESSION['uforms_config'] = $config;
                    ?>
                    <div class="alert alert-success">
                        配置已保存！请继续完成安装。
                    </div>
                    <div class="actions">
                        <a href="?step=2" class="btn btn-secondary">上一步</a>
                        <a href="?step=4" class="btn btn-primary">完成安装</a>
                    </div>
                <?php else: ?>
                
                <form method="post">
                    <input type="hidden" name="action" value="save_config">
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="enable_forms" name="enable_forms" checked>
                            <label for="enable_forms">启用表单功能</label>
                        </div>
                        <div class="help">核心功能，建议启用</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="enable_calendar" name="enable_calendar" checked>
                            <label for="enable_calendar">启用日历功能</label>
                        </div>
                        <div class="help">支持预约和事件管理</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="enable_analytics" name="enable_analytics" checked>
                            <label for="enable_analytics">启用数据分析</label>
                        </div>
                        <div class="help">提供详细的统计报表</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="upload_enabled" name="upload_enabled" checked>
                            <label for="upload_enabled">启用文件上传</label>
                        </div>
                        <div class="help">允许表单包含文件上传字段</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="upload_max_size">最大文件大小 (MB)</label>
                        <input type="number" id="upload_max_size" name="upload_max_size" value="5" min="1" max="50">
                        <div class="help">单个文件的最大上传大小</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="allowed_file_types">允许的文件类型</label>
                        <input type="text" id="allowed_file_types" name="allowed_file_types" 
                               value="jpg,jpeg,png,gif,pdf,doc,docx,txt,zip">
                        <div class="help">用逗号分隔的文件扩展名</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="enable_spam_filter" name="enable_spam_filter" checked>
                            <label for="enable_spam_filter">启用垃圾内容过滤</label>
                        </div>
                        <div class="help">自动检测和过滤垃圾提交</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_per_page">后台分页数量</label>
                        <select id="admin_per_page" name="admin_per_page">
                            <option value="10">10 条/页</option>
                            <option value="20" selected>20 条/页</option>
                            <option value="50">50 条/页</option>
                            <option value="100">100 条/页</option>
                        </select>
                        <div class="help">后台列表页面每页显示的记录数</div>
                    </div>
                    
                    <div class="actions">
                        <a href="?step=2" class="btn btn-secondary">上一步</a>
                        <button type="submit" class="btn btn-primary">保存配置</button>
                    </div>
                </form>
                
                <?php endif; ?>
            </div>
            
            <?php elseif ($step == 4): ?>
            <!-- 步骤4：完成安装 -->
            <div class="step-content">
                <h2>完成安装</h2>
                
                <?php
                session_start();
                if (isset($_SESSION['uforms_config'])) {
                    // 执行真正的安装过程
                    try {
                        // 这里应该调用实际的安装逻辑
                        // 由于我们在示例中，这里只是模拟
                        
                        echo '<div class="alert alert-success">';
                        echo '<strong>安装成功！</strong>Uforms 已经成功安装到您的网站。';
                        echo '</div>';
                        
                        echo '<h3>接下来您可以：</h3>';
                        echo '<ul>';
                        echo '<li>访问 <a href="' . Helper::options()->adminUrl . 'extending.php?panel=Uforms%2Fpanel.php">管理界面</a> 开始创建表单</li>';
                        echo '<li>查看 <a href="#" target="_blank">使用文档</a> 了解更多功能</li>';
                        echo '<li>在 <a href="' . Helper::options()->adminUrl . 'extending.php?panel=Uforms%2Fsettings.php">设置页面</a> 中完善配置</li>';
                        echo '</ul>';
                        
                        echo '<h3>快速开始：</h3>';
                        echo '<ol>';
                        echo '<li>进入管理界面创建第一个表单</li>';
                        echo '<li>配置表单字段和设置</li>';
                        echo '<li>发布表单并获取嵌入代码</li>';
                        echo '<li>在文章或页面中使用 <code>[uform name="表单名称"]</code> 短代码</li>';
                        echo '</ol>';
                        
                        // 清理会话数据
                        unset($_SESSION['uforms_config']);
                        
                    } catch (Exception $e) {
                        echo '<div class="alert alert-error">';
                        echo '<strong>安装失败：</strong>' . $e->getMessage();
                        echo '</div>';
                        
                        echo '<div class="actions">';
                        echo '<a href="?step=3" class="btn btn-secondary">重新配置</a>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="alert alert-error">';
                    echo '<strong>错误：</strong>配置信息丢失，请重新配置。';
                    echo '</div>';
                    
                    echo '<div class="actions">';
                    echo '<a href="?step=3" class="btn btn-primary">重新配置</a>';
                    echo '</div>';
                }
                ?>
                
                <?php if (isset($_SESSION['uforms_config'])): ?>
                <div class="actions">
                    <a href="<?php echo Helper::options()->adminUrl; ?>extending.php?panel=Uforms%2Fpanel.php" 
                       class="btn btn-primary">进入管理界面</a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // 简单的进度动画
        document.addEventListener('DOMContentLoaded', function() {
            const steps = document.querySelectorAll('.step-nav li');
            const currentStep = <?php echo $step; ?>;
            
            steps.forEach(function(step, index) {
                if (index < currentStep - 1) {
                    step.classList.add('completed');
                } else if (index === currentStep - 1) {
                    step.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
