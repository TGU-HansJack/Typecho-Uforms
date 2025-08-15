<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<div class="main">
    <div class="body container">
<?php
$form_id = $request->get('id');
$form = null;
$fields = array();

if ($form_id) {
    $form = UformsHelper::getForm($form_id);
    $fields = UformsHelper::getFormFields($form_id);
}

// 处理保存操作
if ($request->isPost()) {
    $action = $request->get('action');
    
    if ($action === 'save_form') {
        try {
            // 验证数据
            $form_name = trim($request->get('form_name'));
            $form_title = trim($request->get('form_title'));
            
            if (empty($form_name) || empty($form_title)) {
                throw new Exception('表单名称和标题不能为空');
            }
            
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $form_name)) {
                throw new Exception('表单名称只能包含字母、数字、下划线和短横线');
            }
            
            // 检查表单名称是否已存在
            $existing = $db->fetchRow(
                $db->select()->from('table.uforms_forms')
                   ->where('name = ?', $form_name)
                   ->where('id != ?', $form_id ?: 0)
            );
            
            if ($existing) {
                throw new Exception('表单名称已存在，请使用其他名称');
            }
            
            $form_data = array(
                'name' => $form_name,
                'title' => $form_title,
                'description' => $request->get('form_description', ''),
                'config' => json_encode($request->get('form_config', array())),
                'settings' => json_encode($request->get('form_settings', array())),
                'status' => $request->get('form_status', 'draft'),
                'modified_time' => time()
            );
            
            if ($form_id) {
                // 更新表单
                $db->query($db->update('table.uforms_forms')->rows($form_data)->where('id = ?', $form_id));
                $updated_form_id = $form_id;
            } else {
                // 创建新表单
                $form_data['author_id'] = $user->uid;
                $form_data['created_time'] = time();
                $form_data['view_count'] = 0;
                $form_data['submit_count'] = 0;
                $updated_form_id = $db->query($db->insert('table.uforms_forms')->rows($form_data));
            }
            
            // 保存字段配置
            $fields_config = json_decode($request->get('fields_config', '[]'), true);
            
            // 删除原有字段
            $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $updated_form_id));
            
            // 插入新字段
            if (is_array($fields_config)) {
                foreach ($fields_config as $index => $field_config) {
                    if (!isset($field_config['name']) || !isset($field_config['type'])) {
                        continue;
                    }
                    
                    $field_data = array(
                        'form_id' => $updated_form_id,
                        'field_type' => $field_config['type'],
                        'field_name' => $field_config['name'],
                        'field_label' => $field_config['label'] ?? '',
                        'field_config' => json_encode($field_config),
                        'sort_order' => $field_config['sortOrder'] ?? $index,
                        'is_required' => !empty($field_config['required']) ? 1 : 0,
                        'created_time' => time()
                    );
                    
                    $db->query($db->insert('table.uforms_fields')->rows($field_data));
                }
            }
            
            // 返回成功响应
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => true,
                    'message' => '表单保存成功',
                    'form_id' => $updated_form_id
                ));
                exit;
            } else {
                $request->throwNotice('表单保存成功！');
            }
            
        } catch (Exception $e) {
            // 错误处理
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => false,
                    'message' => $e->getMessage()
                ));
                exit;
            } else {
                $request->throwNotice('保存失败：' . $e->getMessage());
            }
        }
        
        // 重新获取数据
        if (!$request->isAjax()) {
            $form = UformsHelper::getForm($updated_form_id ?? $form_id);
            $fields = UformsHelper::getFormFields($updated_form_id ?? $form_id);
        }
    }
}

// 处理 AJAX 请求
if ($request->isAjax()) {
    $ajax_action = $request->get('action');
    
    switch ($ajax_action) {
        case 'save_template':
            try {
                $template_name = trim($request->get('template_name'));
                $template_data = $request->get('template_data');
                
                if (empty($template_name)) {
                    throw new Exception('模板名称不能为空');
                }
                
                // 保存模板到数据库或文件
                $template_data_array = array(
                    'name' => $template_name,
                    'data' => $template_data,
                    'author_id' => $user->uid,
                    'created_time' => time()
                );
                
                // 这里可以扩展模板功能
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => true,
                    'message' => '模板保存成功'
                ));
                
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => false,
                    'message' => $e->getMessage()
                ));
            }
            exit;
            
        case 'preview_form':
            try {
                $form_data = json_decode($request->get('form_data'), true);
                
                // 生成预览HTML
                ob_start();
                include dirname(__FILE__) . '/../templates/form-preview.php';
                $preview_html = ob_get_clean();
                
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => true,
                    'html' => $preview_html
                ));
                
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => false,
                    'message' => $e->getMessage()
                ));
            }
            exit;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(array(
                'success' => false,
                'message' => '未知的操作'
            ));
            exit;
    }
}
?>

<!-- 表单构建器头部 -->
<div class="uforms-header">
    <h1>
        <?php if ($form_id): ?>
            编辑表单: <?php echo htmlspecialchars($form['title'] ?? ''); ?>
        <?php else: ?>
            创建新表单
        <?php endif; ?>
    </h1>
</div>

<!-- 导航栏 -->
<div class="uforms-nav">
    <a href="?panel=Uforms%2Fadmin%2Findex.php" class="">
        <i class="icon-home"></i>概览
    </a>
    <a href="?panel=Uforms%2Fadmin%2Fmanage.php" class="">
        <i class="icon-list"></i>管理表单
    </a>
    <a href="?panel=Uforms%2Fadmin%2Fcreate.php" class="active">
        <i class="icon-plus"></i>创建表单
    </a>
    <a href="?panel=Uforms%2Fadmin%2Fnotifications.php" class="">
        <i class="icon-bell"></i>通知中心
    </a>
    <a href="?panel=Uforms%2Fadmin%2Fview.php" class="">
        <i class="icon-chart"></i>数据分析
    </a>
</div>

<div class="uforms-creator">
    <!-- 表单构建器 -->
    <div class="form-builder">
        <!-- 左侧字段库 -->
        <div class="fields-panel">
            <h3><i class="icon-widget"></i> 字段库</h3>
            
            <div class="fields-panel-content">
                <!-- 基础字段 -->
                <div class="field-category">
                    <h4><i class="icon-basic"></i> 基础字段</h4>
                    <div class="field-items">
                        <div class="field-item" data-type="text" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-text"></i>
                                <span>单行文本</span>
                            </div>
                            <div class="field-description">用于输入短文本内容</div>
                        </div>
                        <div class="field-item" data-type="textarea" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-textarea"></i>
                                <span>多行文本</span>
                            </div>
                            <div class="field-description">用于输入长文本内容</div>
                        </div>
                        <div class="field-item" data-type="email" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-email"></i>
                                <span>邮箱</span>
                            </div>
                            <div class="field-description">自动验证邮箱格式</div>
                        </div>
                        <div class="field-item" data-type="url" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-url"></i>
                                <span>网址</span>
                            </div>
                            <div class="field-description">自动验证网址格式</div>
                        </div>
                        <div class="field-item" data-type="tel" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-phone"></i>
                                <span>电话</span>
                            </div>
                            <div class="field-description">输入电话号码</div>
                        </div>
                        <div class="field-item" data-type="number" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-number"></i>
                                <span>数字</span>
                            </div>
                            <div class="field-description">只能输入数字</div>
                        </div>
                        <div class="field-item" data-type="password" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-password"></i>
                                <span>密码</span>
                            </div>
                            <div class="field-description">密码输入框</div>
                        </div>
                        <div class="field-item" data-type="select" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-select"></i>
                                <span>下拉选择</span>
                            </div>
                            <div class="field-description">从选项中选择一项</div>
                        </div>
                        <div class="field-item" data-type="radio" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-radio"></i>
                                <span>单选按钮</span>
                            </div>
                            <div class="field-description">从选项中选择一项</div>
                        </div>
                        <div class="field-item" data-type="checkbox" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-checkbox"></i>
                                <span>复选框</span>
                            </div>
                            <div class="field-description">可选择多个选项</div>
                        </div>
                        <div class="field-item" data-type="file" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-file"></i>
                                <span>文件上传</span>
                            </div>
                            <div class="field-description">上传文件或图片</div>
                        </div>
                    </div>
                </div>
                
                <!-- 高级字段 -->
                <div class="field-category">
                    <h4><i class="icon-advanced"></i> 高级字段</h4>
                    <div class="field-items">
                        <div class="field-item" data-type="date" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-date"></i>
                                <span>日期选择</span>
                            </div>
                            <div class="field-description">选择日期</div>
                        </div>
                        <div class="field-item" data-type="time" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-time"></i>
                                <span>时间选择</span>
                            </div>
                            <div class="field-description">选择时间</div>
                        </div>
                        <div class="field-item" data-type="datetime" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-datetime"></i>
                                <span>日期时间</span>
                            </div>
                            <div class="field-description">选择日期和时间</div>
                        </div>
                        <div class="field-item" data-type="range" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-range"></i>
                                <span>滑块</span>
                            </div>
                            <div class="field-description">拖拽选择数值</div>
                        </div>
                        <div class="field-item" data-type="rating" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-star"></i>
                                <span>评分</span>
                            </div>
                            <div class="field-description">星级评分选择</div>
                        </div>
                        <div class="field-item" data-type="color" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-color"></i>
                                <span>颜色选择</span>
                            </div>
                            <div class="field-description">选择颜色值</div>
                        </div>
                        <div class="field-item" data-type="signature" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-signature"></i>
                                <span>签名板</span>
                            </div>
                            <div class="field-description">手写签名输入</div>
                        </div>
                        <div class="field-item" data-type="hidden" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-hidden"></i>
                                <span>隐藏字段</span>
                            </div>
                            <div class="field-description">不显示的数据字段</div>
                        </div>
                    </div>
                </div>
                
                <!-- 布局字段 -->
                <div class="field-category">
                    <h4><i class="icon-layout"></i> 布局字段</h4>
                    <div class="field-items">
                        <div class="field-item" data-type="heading" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-heading"></i>
                                <span>标题</span>
                            </div>
                            <div class="field-description">添加章节标题</div>
                        </div>
                        <div class="field-item" data-type="paragraph" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-paragraph"></i>
                                <span>段落</span>
                            </div>
                            <div class="field-description">添加说明文字</div>
                        </div>
                        <div class="field-item" data-type="divider" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-divider"></i>
                                <span>分割线</span>
                            </div>
                            <div class="field-description">分隔不同区域</div>
                        </div>
                        <div class="field-item" data-type="columns" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-columns"></i>
                                <span>多列布局</span>
                            </div>
                            <div class="field-description">创建多列容器</div>
                        </div>
                        <div class="field-item" data-type="html" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-code"></i>
                                <span>HTML代码</span>
                            </div>
                            <div class="field-description">自定义HTML内容</div>
                        </div>
                    </div>
                </div>
                
                <!-- 特殊字段 -->
                <div class="field-category">
                    <h4><i class="icon-special"></i> 特殊字段</h4>
                    <div class="field-items">
                        <div class="field-item" data-type="calendar" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-calendar"></i>
                                <span>日历预约</span>
                            </div>
                            <div class="field-description">日历预约选择</div>
                        </div>
                        <div class="field-item" data-type="cascade" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-cascade"></i>
                                <span>级联选择</span>
                            </div>
                            <div class="field-description">多级关联选择</div>
                        </div>
                        <div class="field-item" data-type="tags" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-tags"></i>
                                <span>标签选择</span>
                            </div>
                            <div class="field-description">多标签输入</div>
                        </div>
                        <div class="field-item" data-type="repeater" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-repeat"></i>
                                <span>重复器</span>
                            </div>
                            <div class="field-description">可重复添加的字段组</div>
                        </div>
                    </div>
                </div>

                <!-- 系统字段（Typecho集成） -->
                <div class="field-category">
                    <h4><i class="icon-system"></i> 系统字段</h4>
                    <div class="field-items">
                        <div class="field-item" data-type="user_name" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-user"></i>
                                <span>用户姓名</span>
                            </div>
                            <div class="field-description">获取当前用户姓名</div>
                        </div>
                        <div class="field-item" data-type="user_email" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-user-email"></i>
                                <span>用户邮箱</span>
                            </div>
                            <div class="field-description">获取当前用户邮箱</div>
                        </div>
                        <div class="field-item" data-type="page_url" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-link"></i>
                                <span>页面URL</span>
                            </div>
                            <div class="field-description">当前页面地址</div>
                        </div>
                        <div class="field-item" data-type="page_title" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-page"></i>
                                <span>页面标题</span>
                            </div>
                            <div class="field-description">当前页面标题</div>
                        </div>
                        <div class="field-item" data-type="timestamp" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-time"></i>
                                <span>时间戳</span>
                            </div>
                            <div class="field-description">当前时间戳</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 中间画布 -->
        <div class="form-canvas">
            <div class="canvas-toolbar">
                <div class="toolbar-left">
                    <button id="preview-desktop" class="preview-btn active" data-view="desktop">
                        <i class="icon-desktop"></i>桌面
                    </button>
                    <button id="preview-tablet" class="preview-btn" data-view="tablet">
                        <i class="icon-tablet"></i>平板
                    </button>
                    <button id="preview-mobile" class="preview-btn" data-view="mobile">
                        <i class="icon-mobile"></i>手机
                    </button>
                </div>
                <div class="toolbar-center">
                    <span id="canvas-scale">100%</span>
                </div>
                <div class="toolbar-right">
                    <button id="clear-form" title="清空表单">
                        <i class="icon-trash"></i>清空
                    </button>
                    <button id="preview-form" title="预览表单">
                        <i class="icon-eye"></i>预览
                    </button>
                    <button id="toggle-grid" title="显示网格">
                        <i class="icon-grid"></i>网格
                    </button>
                </div>
            </div>
            
            <div class="canvas-content" id="form-canvas">
                <?php if (empty($fields)): ?>
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
                <?php else: ?>
                <!-- 现有字段会通过 JavaScript 加载 -->
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 右侧属性面板 -->
        <div class="properties-panel">
            <div class="panel-tabs">
                <button class="tab-button active" data-tab="field">
                    <i class="icon-field"></i>字段设置
                </button>
                <button class="tab-button" data-tab="form">
                    <i class="icon-form"></i>表单设置
                </button>
                <button class="tab-button" data-tab="style">
                    <i class="icon-style"></i>样式设置
                </button>
            </div>
            
            <!-- 字段设置面板 -->
            <div class="tab-content active" id="field-tab">
                <div class="no-selection">
                    <div class="no-selection-icon">
                        <i class="icon-select-field"></i>
                    </div>
                    <h4>选择一个字段</h4>
                    <p>点击表单中的字段来配置其属性和行为</p>
                </div>
                
                <div class="field-properties" style="display: none;">
                    <!-- 基本设置 -->
                    <div class="property-group">
                        <h4><i class="icon-basic"></i> 基本设置</h4>
                        <div class="property-item">
                            <label for="field-label">字段标签 <span class="required">*</span></label>
                            <input type="text" id="field-label" placeholder="输入字段标签" />
                            <div class="field-tip">显示在字段上方的标签文字</div>
                        </div>
                        <div class="property-item">
                            <label for="field-name">字段名称</label>
                            <input type="text" id="field-name" placeholder="字段的唯一标识" />
                            <div class="field-tip">用于表单提交的字段名，留空自动生成</div>
                        </div>
                        <div class="property-item">
                            <label for="field-placeholder">占位符文本</label>
                            <input type="text" id="field-placeholder" placeholder="提示用户输入的文字" />
                            <div class="field-tip">显示在输入框内的提示文字</div>
                        </div>
                        <div class="property-item">
                            <label for="field-default">默认值</label>
                            <input type="text" id="field-default" placeholder="字段的默认值" />
                            <div class="field-tip">字段的初始值</div>
                        </div>
                        <div class="property-item">
                            <label for="field-help">帮助文本</label>
                            <textarea id="field-help" placeholder="字段的详细说明" rows="3"></textarea>
                            <div class="field-tip">显示在字段下方的帮助说明</div>
                        </div>
                        <div class="property-item">
                            <label class="checkbox-label">
                                <input type="checkbox" id="field-required" />
                                <span class="checkbox-mark"></span>
                                设为必填字段
                            </label>
                        </div>
                    </div>
                    
                    <!-- 高级设置 -->
                    <div class="property-group">
                        <h4><i class="icon-advanced"></i> 高级设置</h4>
                        <div class="property-item">
                            <label for="field-css-class">CSS类名</label>
                            <input type="text" id="field-css-class" placeholder="自定义CSS类" />
                            <div class="field-tip">为字段添加自定义CSS类名</div>
                        </div>
                        <div class="property-item">
                            <label for="field-css-id">CSS ID</label>
                            <input type="text" id="field-css-id" placeholder="自定义CSS ID" />
                            <div class="field-tip">为字段设置唯一的CSS ID</div>
                        </div>
                        <div class="property-item">
                            <label for="field-width">字段宽度</label>
                            <select id="field-width">
                                <option value="full">100% - 全宽</option>
                                <option value="half">50% - 半宽</option>
                                <option value="third">33.33% - 三分之一</option>
                                <option value="quarter">25% - 四分之一</option>
                                <option value="auto">自动宽度</option>
                                <option value="custom">自定义</option>
                            </select>
                        </div>
                        <div class="property-item" id="custom-width-input" style="display: none;">
                            <label for="field-custom-width">自定义宽度</label>
                            <div class="input-group">
                                <input type="number" id="field-custom-width" placeholder="数值" />
                                <select id="field-width-unit">
                                    <option value="px">px</option>
                                    <option value="%">%</option>
                                    <option value="rem">rem</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 验证规则 -->
                    <div class="property-group">
                        <h4><i class="icon-validation"></i> 验证规则</h4>
                        <div class="property-item">
                            <label for="field-min-length">最小长度</label>
                            <input type="number" id="field-min-length" min="0" placeholder="0" />
                        </div>
                        <div class="property-item">
                            <label for="field-max-length">最大长度</label>
                            <input type="number" id="field-max-length" min="0" placeholder="无限制" />
                        </div>
                        <div class="property-item">
                            <label for="field-pattern">正则表达式</label>
                            <input type="text" id="field-pattern" placeholder="验证模式" />
                            <div class="field-tip">用于验证输入格式的正则表达式</div>
                        </div>
                        <div class="property-item">
                            <label for="field-error-message">错误提示</label>
                            <input type="text" id="field-error-message" placeholder="验证失败时的提示" />
                        </div>
                    </div>
                    
                    <!-- 条件逻辑 -->
                    <div class="property-group">
                        <h4><i class="icon-logic"></i> 条件逻辑</h4>
                        <div class="property-item">
                            <label class="checkbox-label">
                                <input type="checkbox" id="field-conditional" />
                                <span class="checkbox-mark"></span>
                                启用条件显示
                            </label>
                        </div>
                        <div class="conditional-rules" id="conditional-rules" style="display: none;">
                            <div class="rule-builder">
                                <div class="rule-header">
                                    <span>显示此字段当：</span>
                                </div>
                                <button type="button" id="add-condition-rule" class="btn btn-small">
                                    <i class="icon-plus"></i> 添加条件
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 选项设置（用于select/radio/checkbox） -->
                    <div class="property-group options-group" style="display: none;">
                        <h4><i class="icon-list"></i> 选项设置</h4>
                        <div class="options-list" id="options-list">
                            <!-- 选项项目会通过JavaScript添加 -->
                        </div>
                        <div class="options-actions">
                            <button type="button" id="add-option" class="btn btn-small">
                                <i class="icon-plus"></i> 添加选项
                            </button>
                            <button type="button" id="bulk-add-options" class="btn btn-small">
                                <i class="icon-bulk"></i> 批量添加
                            </button>
                        </div>
                        <div class="property-item">
                            <label class="checkbox-label">
                                <input type="checkbox" id="allow-other" />
                                <span class="checkbox-mark"></span>
                                允许用户输入其他选项
                            </label>
                        </div>
                    </div>
                    
                    <!-- 文件上传设置 -->
                    <div class="property-group file-group" style="display: none;">
                        <h4><i class="icon-file"></i> 文件上传设置</h4>
                        <div class="property-item">
                            <label for="file-types">允许的文件类型</label>
                            <input type="text" id="file-types" placeholder="jpg,png,pdf" value="jpg,png,pdf" />
                            <div class="field-tip">用逗号分隔多个类型</div>
                        </div>
                        <div class="property-item">
                            <label for="file-max-size">最大文件大小(MB)</label>
                            <input type="number" id="file-max-size" value="10" min="1" max="100" />
                        </div>
                        <div class="property-item">
                            <label class="checkbox-label">
                                <input type="checkbox" id="file-multiple" />
                                <span class="checkbox-mark"></span>
                                允许多文件上传
                            </label>
                        </div>
                        <div class="property-item">
                            <label for="file-max-count">最多上传文件数</label>
                            <input type="number" id="file-max-count" value="5" min="1" max="20" />
                        </div>
                    </div>

                    <!-- 数字字段设置 -->
                    <div class="property-group number-group" style="display: none;">
                        <h4><i class="icon-number"></i> 数字设置</h4>
                        <div class="property-item">
                            <label for="number-min">最小值</label>
                            <input type="number" id="number-min" placeholder="无限制" />
                        </div>
                        <div class="property-item">
                            <label for="number-max">最大值</label>
                            <input type="number" id="number-max" placeholder="无限制" />
                        </div>
                        <div class="property-item">
                            <label for="number-step">步长</label>
                            <input type="number" id="number-step" value="1" min="0.01" step="0.01" />
                        </div>
                    </div>

                    <!-- 日期时间设置 -->
                    <div class="property-group datetime-group" style="display: none;">
                        <h4><i class="icon-calendar"></i> 日期时间设置</h4>
                        <div class="property-item">
                            <label for="date-min">最早日期</label>
                            <input type="date" id="date-min" />
                        </div>
                        <div class="property-item">
                            <label for="date-max">最晚日期</label>
                            <input type="date" id="date-max" />
                        </div>
                        <div class="property-item">
                            <label for="date-format">日期格式</label>
                            <select id="date-format">
                                <option value="YYYY-MM-DD">2023-12-25</option>
                                <option value="DD/MM/YYYY">25/12/2023</option>
                                <option value="MM/DD/YYYY">12/25/2023</option>
                                <option value="DD-MM-YYYY">25-12-2023</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 表单设置面板 -->
            <div class="tab-content" id="form-tab">
                <div class="property-group">
                    <h4><i class="icon-info"></i> 基本信息</h4>
                    <div class="property-item">
                        <label for="form-name">表单名称 <span class="required">*</span></label>
                        <input type="text" id="form-name" value="<?php echo $form ? htmlspecialchars($form['name']) : ''; ?>" placeholder="表单的唯一标识" />
                        <div class="field-tip">用于生成表单URL，只能包含字母、数字和下划线</div>
                    </div>
                    <div class="property-item">
                        <label for="form-title">表单标题 <span class="required">*</span></label>
                        <input type="text" id="form-title" value="<?php echo $form ? htmlspecialchars($form['title']) : ''; ?>" placeholder="表单的显示标题" />
                    </div>
                    <div class="property-item">
                        <label for="form-description">表单描述</label>
                        <textarea id="form-description" rows="3" placeholder="表单的详细说明"><?php echo $form ? htmlspecialchars($form['description']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="icon-submit"></i> 提交设置</h4>
                    <div class="property-item">
                        <label for="submit-text">提交按钮文本</label>
                        <input type="text" id="submit-text" value="提交" />
                    </div>
                    <div class="property-item">
                        <label for="success-message">成功消息</label>
                        <textarea id="success-message" rows="2">表单提交成功！感谢您的参与。</textarea>
                    </div>
                    <div class="property-item">
                        <label for="success-action">成功后行为</label>
                        <select id="success-action">
                            <option value="message">显示消息</option>
                            <option value="redirect">跳转页面</option>
                            <option value="refresh">刷新页面</option>
                            <option value="block">显示内容区块</option>
                        </select>
                    </div>
                    <div class="property-item" id="redirect-url-setting" style="display: none;">
                        <label for="redirect-url">跳转地址</label>
                        <input type="url" id="redirect-url" placeholder="https://example.com/thanks" />
                    </div>
                    <div class="property-item" id="success-block-setting" style="display: none;">
                        <label for="success-block">成功内容</label>
                        <textarea id="success-block" rows="4" placeholder="成功后要显示的HTML内容"></textarea>
                    </div>
                    <div class="property-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="ajax-submit" checked />
                            <span class="checkbox-mark"></span>
                            使用AJAX提交（无刷新提交）
                        </label>
                    </div>
                    <div class="property-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="prevent-duplicate" />
                            <span class="checkbox-mark"></span>
                            防止重复提交
                        </label>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="icon-security"></i> 安全设置</h4>
                    <div class="property-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="enable-captcha" />
                            <span class="checkbox-mark"></span>
                            启用验证码
                        </label>
                    </div>
                    <div class="property-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="enable-honeypot" checked />
                            <span class="checkbox-mark"></span>
                            启用蜜罐防spam
                        </label>
                    </div>
                    <div class="property-item">
                        <label for="submit-limit">提交限制(秒)</label>
                        <input type="number" id="submit-limit" value="60" min="0" />
                        <div class="field-tip">同一IP再次提交的最小间隔时间</div>
                    </div>
                    <div class="property-item">
                        <label for="max-submissions">最大提交次数</label>
                        <input type="number" id="max-submissions" value="0" min="0" />
                        <div class="field-tip">表单最多接受的提交次数，0表示无限制</div>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="icon-email"></i> 邮件通知</h4>
                    <div class="property-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="admin-notification" />
                            <span class="checkbox-mark"></span>
                            发送管理员通知
                        </label>
                    </div>
                    <div class="admin-notification-settings" id="admin-notification-settings" style="display: none;">
                        <div class="property-item">
                            <label for="admin-email">收件人邮箱</label>
                            <input type="email" id="admin-email" placeholder="多个邮箱用逗号分隔" />
                        </div>
                        <div class="property-item">
                            <label for="admin-subject">邮件主题</label>
                            <input type="text" id="admin-subject" value="新的表单提交 - {form_title}" />
                        </div>
                        <div class="property-item">
                            <label for="admin-message">邮件内容</label>
                            <textarea id="admin-message" rows="6">您收到一个新的表单提交：

{all_fields}

提交时间：{submit_time}
IP地址：{ip_address}
用户代理：{user_agent}</textarea>
                            <div class="field-tip">
                                可用变量：{form_title}, {all_fields}, {submit_time}, {ip_address}, {user_agent}
                            </div>
                        </div>
                    </div>
                    
                    <div class="property-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="user-notification" />
                            <span class="checkbox-mark"></span>
                            发送用户确认邮件
                        </label>
                    </div>
                    <div class="user-notification-settings" id="user-notification-settings" style="display: none;">
                        <div class="property-item">
                            <label for="user-email-field">用户邮箱字段</label>
                            <select id="user-email-field">
                                <option value="">选择邮箱字段</option>
                            </select>
                        </div>
                        <div class="property-item">
                            <label for="user-subject">邮件主题</label>
                            <input type="text" id="user-subject" value="表单提交确认 - {form_title}" />
                        </div>
                        <div class="property-item">
                            <label for="user-message">邮件内容</label>
                            <textarea id="user-message" rows="6">感谢您的提交！

我们已收到您的表单信息，将尽快处理。

您的提交内容：
{all_fields}

如有疑问，请联系我们。</textarea>
                        </div>
                    </div>
                </div>

                <div class="property-group">
                    <h4><i class="icon-webhook"></i> 第三方集成</h4>
                    <div class="property-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="enable-webhook" />
                            <span class="checkbox-mark"></span>
                            启用Webhook
                        </label>
                    </div>
                    <div class="webhook-settings" id="webhook-settings" style="display: none;">
                        <div class="property-item">
                            <label for="webhook-url">Webhook URL</label>
                            <input type="url" id="webhook-url" placeholder="https://example.com/webhook" />
                        </div>
                        <div class="property-item">
                            <label for="webhook-secret">密钥</label>
                            <input type="text" id="webhook-secret" placeholder="用于验证的密钥" />
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 样式设置面板 -->
            <div class="tab-content" id="style-tab">
                <div class="property-group">
                    <h4><i class="icon-theme"></i> 表单样式</h4>
                    <div class="property-item">
                        <label for="form-theme">样式主题</label>
                        <select id="form-theme">
                            <option value="default">默认样式</option>
                            <option value="minimal">简约风格</option>
                            <option value="modern">现代风格</option>
                            <option value="classic">经典风格</option>
                            <option value="bootstrap">Bootstrap风格</option>
                            <option value="material">Material Design</option>
                        </select>
                        <div class="theme-preview" id="theme-preview">
                            <div class="preview-box">主题预览</div>
                        </div>
                    </div>
                    <div class="property-item">
                        <label for="primary-color">主色调</label>
                        <div class="color-picker-group">
                            <input type="color" id="primary-color" value="#3788d8" />
                            <input type="text" id="primary-color-text" value="#3788d8" />
                        </div>
                    </div>
                    <div class="property-item">
                        <label for="form-width">表单宽度</label>
                        <div class="input-group">
                            <input type="text" id="form-width" value="100%" placeholder="如: 100%, 800px" />
                        </div>
                    </div>
                    <div class="property-item">
                        <label for="form-max-width">最大宽度</label>
                        <input type="text" id="form-max-width" value="800px" placeholder="如: 800px, none" />
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="icon-layout"></i> 布局设置</h4>
                    <div class="property-item">
                        <label for="label-position">标签位置</label>
                        <select id="label-position">
                            <option value="top">顶部</option>
                            <option value="left">左侧</option>
                            <option value="inside">内部（浮动标签）</option>
                            <option value="hidden">隐藏</option>
                        </select>
                    </div>
                    <div class="property-item">
                        <label for="field-spacing">字段间距</label>
                        <input type="range" id="field-spacing" min="0" max="40" value="20" />
                        <span class="range-value">20px</span>
                    </div>
                    <div class="property-item">
                        <label for="form-padding">表单内边距</label>
                        <input type="range" id="form-padding" min="0" max="60" value="20" />
                        <span class="range-value">20px</span>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="icon-style"></i> 字段样式</h4>
                    <div class="property-item">
                        <label for="input-border-radius">输入框圆角</label>
                        <input type="range" id="input-border-radius" min="0" max="20" value="4" />
                        <span class="range-value">4px</span>
                    </div>
                    <div class="property-item">
                        <label for="input-border-width">边框粗细</label>
                        <input type="range" id="input-border-width" min="0" max="5" value="1" />
                        <span class="range-value">1px</span>
                    </div>
                    <div class="property-item">
                        <label for="input-height">输入框高度</label>
                        <input type="range" id="input-height" min="30" max="60" value="40" />
                        <span class="range-value">40px</span>
                    </div>
                </div>

                <div class="property-group">
                    <h4><i class="icon-color"></i> 颜色配置</h4>
                    <div class="color-grid">
                        <div class="color-item">
                            <label>背景色</label>
                            <input type="color" id="bg-color" value="#ffffff" />
                        </div>
                        <div class="color-item">
                            <label>文字色</label>
                            <input type="color" id="text-color" value="#333333" />
                        </div>
                        <div class="color-item">
                            <label>边框色</label>
                            <input type="color" id="border-color" value="#dddddd" />
                        </div>
                        <div class="color-item">
                            <label>错误色</label>
                            <input type="color" id="error-color" value="#e74c3c" />
                        </div>
                        <div class="color-item">
                            <label>成功色</label>
                            <input type="color" id="success-color" value="#27ae60" />
                        </div>
                        <div class="color-item">
                            <label>警告色</label>
                            <input type="color" id="warning-color" value="#f39c12" />
                        </div>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="icon-code"></i> 自定义CSS</h4>
                    <div class="property-item">
                        <label for="custom-css">CSS代码</label>
                        <textarea id="custom-css" rows="10" placeholder="输入自定义CSS代码" class="code-editor"></textarea>
                        <div class="css-tips">
                            <details>
                                <summary>常用CSS选择器</summary>
                                <ul>
                                    <li><code>.uform</code> - 表单容器</li>
                                    <li><code>.uform-field</code> - 字段容器</li>
                                    <li><code>.uform-label</code> - 字段标签</li>
                                    <li><code>.uform-input</code> - 输入框</li>
                                    <li><code>.uform-submit</code> - 提交按钮</li>
                                    <li><code>.uform-error</code> - 错误消息</li>
                                </ul>
                            </details>
                        </div>
                    </div>
                    <div class="property-item">
                        <button type="button" id="preview-css" class="btn btn-primary">
                            <i class="icon-eye"></i> 预览样式
                        </button>
                        <button type="button" id="reset-css" class="btn btn-default">
                            <i class="icon-refresh"></i> 重置
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 底部操作栏 -->
    <div class="form-actions">
        <div class="actions-left">
            <button id="save-draft" class="btn btn-default">
                <i class="icon-save"></i>保存草稿
            </button>
            <button id="save-template" class="btn btn-default">
                <i class="icon-template"></i>保存为模板
            </button>
        </div>
        <div class="actions-center">
            <span class="save-status" id="save-status">
                <i class="icon-check"></i> 已保存
            </span>
        </div>
        <div class="actions-right">
            <button id="preview-form-btn" class="btn btn-info">
                <i class="icon-eye"></i>预览表单
            </button>
            <button id="publish-form" class="btn btn-primary">
                <i class="icon-publish"></i>发布表单
            </button>
            <?php if ($form && $form['status'] === 'published'): ?>
            <button id="get-code" class="btn btn-success">
                <i class="icon-code"></i>获取代码
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 批量添加选项弹窗 -->
<div id="bulk-options-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>批量添加选项</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="bulk-options-tabs">
                <button class="bulk-tab active" data-tab="text">文本输入</button>
                <button class="bulk-tab" data-tab="preset">预设选项</button>
            </div>
            <div class="bulk-tab-content active" id="text-tab">
                <textarea id="bulk-options-text" rows="10" placeholder="每行一个选项，格式：&#10;选项1&#10;选项2|value2&#10;选项3"></textarea>
                <div class="bulk-tips">
                    <p>格式说明：</p>
                    <ul>
                        <li>每行一个选项</li>
                        <li>使用 | 分隔显示文本和值</li>
                        <li>例：北京|beijing</li>
                    </ul>
                </div>
            </div>
            <div class="bulk-tab-content" id="preset-tab">
                <div class="preset-categories">
                    <button class="preset-btn" data-preset="yesno">是/否</button>
                    <button class="preset-btn" data-preset="gender">性别</button>
                    <button class="preset-btn" data-preset="rating">满意度</button>
                    <button class="preset-btn" data-preset="education">学历</button>
                    <button class="preset-btn" data-preset="cities">主要城市</button>
                    <button class="preset-btn" data-preset="provinces">省份</button>
                    <button class="preset-btn" data-preset="countries">国家</button>
                    <button class="preset-btn" data-preset="numbers">数字 1-10</button>
                </div>
                <div class="preset-preview" id="preset-preview">
                    选择一个预设类别查看选项
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button id="apply-bulk-options" class="btn btn-primary">应用</button>
            <button class="btn btn-default modal-close">取消</button>
        </div>
    </div>
</div>

<!-- 表单预览弹窗 -->
<div id="preview-modal" class="modal large-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>表单预览</h3>
            <div class="preview-controls">
                <button id="preview-desktop-btn" class="preview-device active" data-device="desktop">
                    <i class="icon-desktop"></i>
                </button>
                <button id="preview-tablet-btn" class="preview-device" data-device="tablet">
                    <i class="icon-tablet"></i>
                </button>
                <button id="preview-mobile-btn" class="preview-device" data-device="mobile">
                    <i class="icon-mobile"></i>
                </button>
            </div>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="preview-container" id="preview-container">
                <iframe id="preview-iframe" src="about:blank" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- 代码获取弹窗 -->
<div id="code-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>获取表单代码</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="code-tabs">
                <button class="code-tab active" data-tab="link">直接链接</button>
                <button class="code-tab" data-tab="iframe">iframe嵌入</button>
                <button class="code-tab" data-tab="shortcode">短代码</button>
                <button class="code-tab" data-tab="api">API接口</button>
            </div>
            <div class="code-content">
                <div class="code-tab-content active" id="link-tab">
                    <label>表单访问链接：</label>
                    <div class="code-input-group">
                        <input type="text" id="form-link" readonly>
                        <button id="copy-link" class="btn">复制链接</button>
                    </div>
                    <div class="code-example">
                        <p>用户可以通过此链接直接访问表单</p>
                    </div>
                </div>
                <div class="code-tab-content" id="iframe-tab">
                    <label>iframe嵌入代码：</label>
                    <div class="code-input-group">
                        <textarea id="iframe-code" rows="4" readonly></textarea>
                        <button id="copy-iframe" class="btn">复制代码</button>
                    </div>
                    <div class="iframe-options">
                        <label>宽度: <input type="text" id="iframe-width" value="100%"></label>
                        <label>高度: <input type="text" id="iframe-height" value="600px"></label>
                        <button id="update-iframe" class="btn btn-small">更新代码</button>
                    </div>
                </div>
                <div class="code-tab-content" id="shortcode-tab">
                    <label>Typecho短代码：</label>
                    <div class="code-input-group">
                        <input type="text" id="shortcode" readonly>
                        <button id="copy-shortcode" class="btn">复制短代码</button>
                    </div>
                    <div class="code-example">
                        <p>在文章或页面中使用：</p>
                        <code>[uforms name="contact"]</code><br>
                        <code>[uforms id="123"]</code>
                    </div>
                </div>
                <div class="code-tab-content" id="api-tab">
                    <label>API提交地址：</label>
                    <div class="code-input-group">
                        <input type="text" id="api-url" readonly>
                        <button id="copy-api" class="btn">复制API</button>
                    </div>
                    <div class="api-docs">
                        <h5>API文档：</h5>
                        <p><strong>方法：</strong>POST</p>
                        <p><strong>内容类型：</strong>application/json</p>
                        <p><strong>请求示例：</strong></p>
                        <pre><code>{
  "field_name_1": "value1",
  "field_name_2": "value2"
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 全局变量
window.uformsConfig = {
    formId: <?php echo $form_id ? $form_id : 'null'; ?>,
    ajaxUrl: '<?php echo Helper::options()->adminUrl; ?>extending.php?panel=Uforms%2Fadmin%2Fcreate.php',
    pluginUrl: '<?php echo Helper::options()->pluginUrl; ?>/Uforms',
    siteUrl: '<?php echo Helper::options()->siteUrl; ?>',
    existingFields: <?php echo json_encode($fields); ?>,
    existingConfig: <?php echo $form ? json_encode(json_decode($form['config'], true)) : '{}'; ?>,
    existingSettings: <?php echo $form ? json_encode(json_decode($form['settings'], true)) : '{}'; ?>
};
</script>

<script src="<?php echo Helper::options()->pluginUrl; ?>/Uforms/assets/js/sortable.min.js"></script>
<script src="<?php echo Helper::options()->pluginUrl; ?>/Uforms/assets/js/uformsbuilder.js"></script>
<link rel="stylesheet" href="<?php echo Helper::options()->pluginUrl; ?>/Uforms/assets/css/create.css">

    </div>
</div>
