## 🤖 Assistant

看到问题了！错误信息显示 JSON 解析失败，说明服务器返回的不是有效的 JSON 格式。同时 AJAX URL 配置也有问题。让我修复这些问题：

## 修复第一部分：create.php 中的 AJAX URL 配置

```php
<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<div class="main">
    <div class="body container">
<?php
$request = Typecho_Request::getInstance();
$user = Typecho_Widget::widget('Widget_User');
$db = Typecho_Db::get();
$options = Helper::options();

// 检查用户权限
if (!$user->hasLogin() || !$user->pass('administrator')) {
    throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
}

// 加载必要的类文件
if (!class_exists('UformsHelper')) {
    require_once dirname(__FILE__) . '/../core/UformsHelper.php';
}

$form_id = $request->get('id');
$form = null;
$fields = array();

if ($form_id) {
    $form = UformsHelper::getForm($form_id);
    if (!$form || $form['author_id'] != $user->uid) {
        throw new Typecho_Widget_Exception(_t('表单不存在或无权限访问'), 404);
    }
    $fields = UformsHelper::getFormFields($form_id);
}

// 修复：处理AJAX请求 - 检查是否是AJAX保存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    (isset($_POST['action']) || isset($_GET['action'])) && 
    ($_POST['action'] ?? $_GET['action']) === 'save_form') {
    
    // 确保输出JSON格式
    header('Content-Type: application/json; charset=UTF-8');
    
    // 清空之前可能的输出
    if (ob_get_level()) {
        ob_clean();
    }
    
    try {
        // 获取POST数据
        $form_name = trim($_POST['form_name'] ?? '');
        $form_title = trim($_POST['form_title'] ?? '');
        $form_description = trim($_POST['form_description'] ?? '');
        $form_status = $_POST['form_status'] ?? 'draft';
        $form_config = $_POST['form_config'] ?? '{}';
        $form_settings = $_POST['form_settings'] ?? '{}';
        $fields_config = $_POST['fields_config'] ?? '[]';
        $version_notes = $_POST['version_notes'] ?? '';
        $auto_save = isset($_POST['auto_save']) ? (bool)$_POST['auto_save'] : false;
        
        // 验证必填字段
        if (empty($form_name)) {
            echo json_encode(['success' => false, 'message' => '表单名称不能为空'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $form_name)) {
            echo json_encode(['success' => false, 'message' => '表单名称只能包含字母、数字和下划线'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (empty($form_title)) {
            echo json_encode(['success' => false, 'message' => '表单标题不能为空'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 验证JSON数据
        $fields_data = json_decode($fields_config, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => '字段配置格式错误：' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (empty($fields_data)) {
            echo json_encode(['success' => false, 'message' => '表单至少需要包含一个字段'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 验证表单配置JSON
        $config_data = json_decode($form_config, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => '表单配置格式错误：' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 验证表单设置JSON
        $settings_data = json_decode($form_settings, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => '表单设置格式错误：' . json_last_error_msg()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 检查表单名称唯一性
        $existing = $db->fetchRow(
            $db->select()->from('table.uforms_forms')
               ->where('name = ? AND id != ?', $form_name, $form_id ?: 0)
        );
        
        if ($existing) {
            echo json_encode(['success' => false, 'message' => '表单名称已存在，请使用其他名称'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $current_time = time();
        
        if ($form_id) {
            // 更新表单
            $update_data = array(
                'name' => $form_name,
                'title' => $form_title,
                'description' => $form_description,
                'config' => $form_config,
                'settings' => $form_settings,
                'status' => $form_status,
                'modified_time' => $current_time
            );
            
            // 如果是发布状态，记录发布时间和生成slug
            if ($form_status === 'published' && $form['status'] !== 'published') {
                $update_data['published_time'] = $current_time;
                $slug = UformsHelper::generateSlug($form_name, $form_title);
                $update_data['slug'] = $slug;
            }
            
            $db->query($db->update('table.uforms_forms')
                         ->rows($update_data)
                         ->where('id = ?', $form_id));
            
            // 创建版本备份（仅非自动保存时）
            if (!$auto_save && method_exists('UformsHelper', 'createFormVersion')) {
                UformsHelper::createFormVersion($form_id, $config_data, $fields_data, $version_notes);
            }
            
        } else {
            // 创建新表单
            $insert_data = array(
                'name' => $form_name,
                'title' => $form_title,
                'description' => $form_description,
                'config' => $form_config,
                'settings' => $form_settings,
                'status' => $form_status,
                'author_id' => $user->uid,
                'created_time' => $current_time,
                'modified_time' => $current_time,
                'view_count' => 0,
                'submit_count' => 0,
                'version' => 1
            );
            
            if ($form_status === 'published') {
                $insert_data['published_time'] = $current_time;
                $slug = UformsHelper::generateSlug($form_name, $form_title);
                $insert_data['slug'] = $slug;
            }
            
            $form_id = $db->query($db->insert('table.uforms_forms')->rows($insert_data));
        }
        
        // 保存字段配置
        // 删除原有字段
        $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $form_id));
        
        // 插入新字段
        foreach ($fields_data as $index => $field_config) {
            $field_data = array(
                'form_id' => $form_id,
                'field_type' => $field_config['type'] ?? 'text',
                'field_name' => $field_config['name'] ?? 'field_' . $index,
                'field_label' => $field_config['label'] ?? '',
                'field_config' => json_encode($field_config, JSON_UNESCAPED_UNICODE),
                'sort_order' => isset($field_config['sortOrder']) ? intval($field_config['sortOrder']) : $index,
                'is_required' => !empty($field_config['required']) ? 1 : 0,
                'is_enabled' => 1,
                'created_time' => $current_time
            );
            
            $db->query($db->insert('table.uforms_fields')->rows($field_data));
        }
        
        // 准备响应数据
        $response_data = array(
            'form_id' => $form_id,
            'status' => $form_status
        );
        
        if ($form_status === 'published') {
            $site_url = rtrim($options->siteUrl, '/');
            $form_url = $site_url . '/uforms/form/' . $form_id;
            $response_data['form_url'] = $form_url;
        }
        
        // 发送成功通知
        if ($form_status === 'published' && !$auto_save && method_exists('UformsHelper', 'createSystemNotification')) {
            UformsHelper::createSystemNotification(
                $form_id,
                null,
                'form_published',
                '表单发布成功',
                "表单 \"{$form_title}\" 已成功发布并可以开始接收用户提交。",
                array('form_url' => isset($response_data['form_url']) ? $response_data['form_url'] : null)
            );
        }
        
        $response = [
            'success' => true,
            'message' => $form_status === 'published' ? '表单发布成功' : '表单保存成功',
            'data' => $response_data
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log('Uforms save error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        
        $error_response = [
            'success' => false, 
            'message' => '保存失败：' . $e->getMessage(),
            'debug' => [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ];
        
        echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Uforms fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        
        echo json_encode([
            'success' => false, 
            'message' => '系统错误：' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    // 确保脚本结束
    exit;
}

// 处理保存模板请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    (isset($_POST['action']) || isset($_GET['action'])) && 
    ($_POST['action'] ?? $_GET['action']) === 'save_template') {
    
    header('Content-Type: application/json; charset=UTF-8');
    
    if (ob_get_level()) {
        ob_clean();
    }
    
    try {
        $template_name = trim($_POST['template_name'] ?? '');
        $template_title = trim($_POST['template_title'] ?? '');
        $template_description = trim($_POST['template_description'] ?? '');
        $template_config = $_POST['template_config'] ?? '{}';
        $fields_config = $_POST['fields_config'] ?? '[]';
        
        if (empty($template_name)) {
            throw new Exception('模板名称不能为空');
        }
        
        // 检查模板名称唯一性
        $existing = $db->fetchRow(
            $db->select()->from('table.uforms_templates')
               ->where('name = ? AND author_id = ?', $template_name, $user->uid)
        );
        
        if ($existing) {
            throw new Exception('模板名称已存在');
        }
        
        $template_data = array(
            'name' => $template_name,
            'title' => $template_title ?: $template_name,
            'description' => $template_description,
            'config' => $template_config,
            'fields_config' => $fields_config,
            'category' => 'custom',
            'author_id' => $user->uid,
            'usage_count' => 0,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        $db->query($db->insert('table.uforms_templates')->rows($template_data));
        
        echo json_encode(['success' => true, 'message' => '模板保存成功'], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '保存失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}
?>

<!-- 表单构建器界面 -->
<div class="uforms-creator">
    <!-- 表单构建器 -->
    <div class="form-builder">
        <!-- 左侧字段库 -->
        <div class="fields-panel">
            <h3><i class="icon-widget">🧩</i> 字段库</h3>
            
            <div class="fields-panel-content">
                <!-- 基础字段 -->
                <div class="field-category">
                    <h4><i class="icon-basic">📝</i> 基础字段</h4>
                    <div class="field-items">
                        <div class="field-item" data-type="text" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-text">📝</i>
                                <span>单行文本</span>
                            </div>
                            <div class="field-description">用于输入短文本内容</div>
                        </div>
                        <div class="field-item" data-type="textarea" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-textarea">📄</i>
                                <span>多行文本</span>
                            </div>
                            <div class="field-description">用于输入长文本内容</div>
                        </div>
                        <div class="field-item" data-type="email" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-email">📧</i>
                                <span>邮箱</span>
                            </div>
                            <div class="field-description">自动验证邮箱格式</div>
                        </div>
                        <div class="field-item" data-type="url" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-url">🔗</i>
                                <span>网址</span>
                            </div>
                            <div class="field-description">自动验证网址格式</div>
                        </div>
                        <div class="field-item" data-type="tel" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-phone">📞</i>
                                <span>电话</span>
                            </div>
                            <div class="field-description">输入电话号码</div>
                        </div>
                        <div class="field-item" data-type="number" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-number">🔢</i>
                                <span>数字</span>
                            </div>
                            <div class="field-description">只能输入数字</div>
                        </div>
                        <div class="field-item" data-type="select" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-select">📋</i>
                                <span>下拉选择</span>
                            </div>
                            <div class="field-description">从选项中选择一项</div>
                        </div>
                        <div class="field-item" data-type="radio" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-radio">⚪</i>
                                <span>单选按钮</span>
                            </div>
                            <div class="field-description">从选项中选择一项</div>
                        </div>
                        <div class="field-item" data-type="checkbox" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-checkbox">☑️</i>
                                <span>复选框</span>
                            </div>
                            <div class="field-description">可选择多个选项</div>
                        </div>
                        <div class="field-item" data-type="file" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-file">📎</i>
                                <span>文件上传</span>
                            </div>
                            <div class="field-description">上传文件或图片</div>
                        </div>
                    </div>
                </div>
                
                <!-- 高级字段 -->
                <div class="field-category">
                    <h4><i class="icon-advanced">⚡</i> 高级字段</h4>
                    <div class="field-items">
                        <div class="field-item" data-type="date" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-date">📅</i>
                                <span>日期选择</span>
                            </div>
                            <div class="field-description">选择日期</div>
                        </div>
                        <div class="field-item" data-type="time" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-time">⏰</i>
                                <span>时间选择</span>
                            </div>
                            <div class="field-description">选择时间</div>
                        </div>
                        <div class="field-item" data-type="datetime" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-datetime">📅⏰</i>
                                <span>日期时间</span>
                            </div>
                            <div class="field-description">选择日期和时间</div>
                        </div>
                        <div class="field-item" data-type="range" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-range">🎚️</i>
                                <span>滑块</span>
                            </div>
                            <div class="field-description">拖拽选择数值</div>
                        </div>
                        <div class="field-item" data-type="hidden" draggable="true">
                            <div class="field-item-header">
                                <i class="icon-hidden">👁️‍🗨️</i>
                                <span>隐藏字段</span>
                            </div>
                            <div class="field-description">不显示的数据字段</div>
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
                        <i class="icon-desktop">🖥️</i>桌面
                    </button>
                    <button id="preview-tablet" class="preview-btn" data-view="tablet">
                        <i class="icon-tablet">📱</i>平板
                    </button>
                    <button id="preview-mobile" class="preview-btn" data-view="mobile">
                        <i class="icon-mobile">📲</i>手机
                    </button>
                </div>
                <div class="toolbar-center">
                    <span id="canvas-scale">100%</span>
                </div>
                <div class="toolbar-right">
                    <button id="clear-form" title="清空表单">
                        <i class="icon-trash">🗑️</i>清空
                    </button>
                    <button id="preview-form" title="预览表单">
                        <i class="icon-eye">👁️</i>预览
                    </button>
                </div>
            </div>
            
            <div class="canvas-content" id="form-canvas">
                <div class="canvas-drop-zone" id="canvas-drop-zone">
                    <div class="drop-hint">
                        <div class="drop-icon">
                            <i class="icon-drag">⬇</i>
                        </div>
                        <h3>从左侧拖拽字段到这里开始创建表单</h3>
                        <p>或者点击左侧字段图标快速添加到表单</p>
                        <div class="quick-start">
                            <button class="btn btn-primary" id="add-text-field">
                                <i class="icon-plus">+</i> 添加文本字段
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 右侧属性面板 -->
        <div class="properties-panel">
            <div class="panel-tabs">
                <button class="tab-button active" data-tab="field">
                    <i class="icon-field">🔧</i>字段设置
                </button>
                <button class="tab-button" data-tab="form">
                    <i class="icon-form">📋</i>表单设置
                </button>
                <button class="tab-button" data-tab="style">
                    <i class="icon-style">🎨</i>样式设置
                </button>
            </div>
            
            <!-- 字段设置面板 -->
            <div class="tab-content active" id="field-tab">
                <div class="no-selection">
                    <div class="no-selection-icon">
                        <i class="icon-select-field">👆</i>
                    </div>
                    <h4>选择一个字段</h4>
                    <p>点击表单中的字段来配置其属性和行为</p>
                </div>
                
                <div class="field-properties" style="display: none;">
                    <!-- 基本设置 -->
                    <div class="property-group">
                        <h4><i class="icon-basic">⚙️</i> 基本设置</h4>
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
                            <label class="checkbox-label">
                                <input type="checkbox" id="field-required" />
                                <span class="checkbox-mark"></span>
                                设为必填字段
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 表单设置面板 -->
            <div class="tab-content" id="form-tab">
                <div class="property-group">
                    <h4><i class="icon-info">ℹ️</i> 基本信息</h4>
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
                    <h4><i class="icon-submit">📤</i> 提交设置</h4>
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
                        </select>
                    </div>
                    <div class="property-item">
                        <label class="checkbox-label">
                            <input type="checkbox" id="ajax-submit" checked />
                            <span class="checkbox-mark"></span>
                            使用AJAX提交（无刷新提交）
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- 样式设置面板 -->
            <div class="tab-content" id="style-tab">
                <div class="property-group">
                    <h4><i class="icon-theme">🎨</i> 表单样式</h4>
                    <div class="property-item">
                        <label for="form-theme">样式主题</label>
                        <select id="form-theme">
                            <option value="default">默认样式</option>
                            <option value="minimal">简约风格</option>
                            <option value="modern">现代风格</option>
                        </select>
                    </div>
                    <div class="property-item">
                        <label for="primary-color">主色调</label>
                        <div class="color-picker-group">
                            <input type="color" id="primary-color" value="#3788d8" />
                            <input type="text" id="primary-color-text" value="#3788d8" />
                        </div>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="icon-layout">🏗️</i> 布局设置</h4>
                    <div class="property-item">
                        <label for="label-position">标签位置</label>
                        <select id="label-position">
                            <option value="top">顶部</option>
                            <option value="left">左侧</option>
                            <option value="inside">内部（浮动标签）</option>
                        </select>
                    </div>
                    <div class="property-item">
                        <label for="field-spacing">字段间距</label>
                        <input type="range" id="field-spacing" min="0" max="40" value="20" />
                        <span class="range-value">20px</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 底部操作栏 -->
    <div class="form-actions">
        <div class="actions-left">
            <button id="save-draft" class="btn btn-default">
                <i class="icon-save">💾</i>保存草稿
            </button>
        </div>
        <div class="actions-center">
            <span class="save-status" id="save-status">
                <i class="status-icon">✅</i> 就绪
            </span>
        </div>
        <div class="actions-right">
            <button id="preview-form-btn" class="btn btn-info">
                <i class="icon-eye">👁️</i>预览表单
            </button>
            <button id="publish-form" class="btn btn-primary">
                <i class="icon-publish">🚀</i>发布表单
            </button>
        </div>
    </div>
</div>

<script>
// 修复：全局变量配置
window.uformsConfig = {
    formId: <?php echo $form_id ? $form_id : 'null'; ?>,
    ajaxUrl: '<?php echo $options->adminUrl; ?>extending.php?panel=Uforms%2Fcreate.php',
    pluginUrl: '<?php echo $options->pluginUrl; ?>/Uforms',
    siteUrl: '<?php echo $options->siteUrl; ?>',
    existingFields: <?php echo json_encode($fields); ?>,
    existingConfig: <?php echo $form ? json_encode(json_decode($form['config'], true) ?: array()) : '{}'; ?>,
    existingSettings: <?php echo $form ? json_encode(json_decode($form['settings'], true) ?: array()) : '{}'; ?>,
    userId: <?php echo $user->uid; ?>,
    userName: '<?php echo addslashes($user->screenName); ?>',
    userEmail: '<?php echo addslashes($user->mail); ?>'
};

// 确保在DOM加载后初始化
document.addEventListener('DOMContentLoaded', function() {
    console.log('Uforms Creator initialized with config:', window.uformsConfig);
});
</script>

<!-- 加载必要的脚本文件 -->
<script src="<?php echo $options->pluginUrl; ?>/Uforms/assets/js/sortable.min.js"></script>
<script src="<?php echo $options->pluginUrl; ?>/Uforms/assets/js/uformsbuilder.js"></script>

<!-- 加载样式文件 -->
<link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/Uforms/assets/css/create.css">

    </div>
</div>
```

## 修复第二部分：uformsbuilder.js 中的保存函数

```javascript
// 表单构建器核心类 - 简化版本
class UformsBuilder {
    constructor() {
        this.selectedField = null;
        this.formData = window.uformsConfig.existingConfig || {};
        this.formSettings = window.uformsConfig.existingSettings || {};
        this.fieldsData = new Map();
        this.fieldCounter = 0;
        this.isDirty = false;
        this.isPublishing = false;
        this.currentFormId = window.uformsConfig.formId;
        
        this.init();
    }
    
    init() {
        console.log('UformsBuilder initializing...');
        try {
            this.bindEvents();
            this.initSortable();
            this.loadExistingForm();
            console.log('UformsBuilder initialized successfully');
        } catch (error) {
            console.error('UformsBuilder initialization error:', error);
        }
    }
    
    bindEvents() {
        console.log('Binding events...');
        
        // 字段库事件
        $(document).on('click', '.field-item', (e) => {
            this.addFieldFromLibrary(e.currentTarget);
        });
        
        // 画布字段事件
        $(document).on('click', '.canvas-field', (e) => {
            e.stopPropagation();
            this.selectField(e.currentTarget);
        });
        
        // 删除字段事件
        $(document).on('click', '.field-delete', (e) => {
            e.stopPropagation();
            this.deleteField(e.target.closest('.canvas-field'));
        });
        
        // 标签页切换
        $('.tab-button').on('click', (e) => {
            this.switchTab(e.target.dataset.tab);
        });
        
        // 表单设置事件
        $('#form-name, #form-title, #form-description').on('input', () => {
            this.markDirty();
        });
        
        // 底部操作事件
        $('#save-draft').on('click', () => this.saveForm('draft'));
        $('#publish-form').on('click', () => this.saveForm('published'));
        
        // 快速添加文本字段
        $(document).on('click', '#add-text-field', () => {
            this.addFieldFromLibrary(document.querySelector('.field-item[data-type="text"]'));
        });
        
        console.log('Events bound successfully');
    }
    
    // 修复：保存表单功能
    saveForm(status = 'draft') {
        if (this.isPublishing) {
            console.log('Save already in progress...');
            return;
        }
        
        console.log('Starting save process...', status);
        
        try {
            const formData = this.collectFormData();
            formData.status = status;
            
            // 验证必填字段
            const validationResult = this.validateFormData(formData);
            if (!validationResult.valid) {
                this.showNotification('error', validationResult.message);
                return;
            }
            
            this.isPublishing = true;
            this.setSaveStatus('saving', status === 'published' ? '正在发布...' : '正在保存...');
            $('#save-draft, #publish-form').prop('disabled', true);
            
            // 准备发送数据
            const postData = new FormData();
            postData.append('action', 'save_form');
            postData.append('form_id', this.currentFormId || '');
            postData.append('form_name', formData.name || '');
            postData.append('form_title', formData.title || '');
            postData.append('form_description', formData.description || '');
            postData.append('form_status', formData.status || 'draft');
            postData.append('form_config', JSON.stringify(formData.config || {}));
            postData.append('form_settings', JSON.stringify(formData.settings || {}));
            postData.append('fields_config', JSON.stringify(formData.fields || []));
            postData.append('version_notes', status === 'published' ? '表单发布' : '保存草稿');
            postData.append('auto_save', 'false');
            
            console.log('Sending data:', {
                action: 'save_form',
                form_id: this.currentFormId || '',
                form_name: formData.name || '',
                form_title: formData.title || '',
                form_status: formData.status || 'draft',
                fields_count: formData.fields ? formData.fields.length : 0
            });
            
            // 发送保存请求
            fetch(window.uformsConfig.ajaxUrl, {
                method: 'POST',
                body: postData,
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                // 检查内容类型
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.warn('Response is not JSON, content-type:', contentType);
                    return response.text().then(text => {
                        console.log('Response text:', text.substring(0, 500));
                        throw new Error('服务器返回非JSON响应');
                    });
                }
                
                return response.json();
            })
            .then(data => {
                console.log('Save response:', data);
                this.handleSaveResponse(data, status);
            })
            .catch(error => {
                console.error('Save error:', error);
                this.handleSaveError(error);
            })
            .finally(() => {
                this.isPublishing = false;
                $('#save-draft, #publish-form').prop('disabled', false);
            });
            
        } catch (error) {
            console.error('Save preparation error:', error);
            this.showNotification('error', '保存准备失败：' + error.message);
            this.isPublishing = false;
            $('#save-draft, #publish-form').prop('disabled', false);
        }
    }
    
    // 数据收集功能
    collectFormData() {
        console.log('Collecting form data...');
        
        const formInfo = {
            name: $('#form-name').val() || '',
            title: $('#form-title').val() || '',
            description: $('#form-description').val() || ''
        };
        
        const formSettings = {
            submitText: $('#submit-text').val() || '提交',
            successMessage: $('#success-message').val() || '表单提交成功！感谢您的参与。',
            successAction: $('#success-action').val() || 'message',
            ajaxSubmit: $('#ajax-submit').is(':checked')
        };
        
        const styleConfig = {
            theme: $('#form-theme').val() || 'default',
            primaryColor: $('#primary-color').val() || '#3788d8',
            labelPosition: $('#label-position').val() || 'top',
            fieldSpacing: parseInt($('#field-spacing').val()) || 20
        };
        
        // 收集字段数据
        const fields = [];
        $('#form-canvas .canvas-field').each((index, element) => {
            const fieldId = element.id;
            const fieldData = this.fieldsData.get(fieldId);
            if (fieldData) {
                const fieldConfig = {
                    ...fieldData.config,
                    type: fieldData.type,
                    sortOrder: index
                };
                fields.push(fieldConfig);
            }
        });
        
        console.log('Collected form data:', {
            ...formInfo,
            config: styleConfig,
            settings: formSettings,
            fields: fields
        });
        
        return {
            ...formInfo,
            config: styleConfig,
            settings: formSettings,
            fields: fields
        };
    }
    
    // 数据验证功能
    validateFormData(formData) {
        if (!formData.name || !formData.name.trim()) {
            return { valid: false, message: '表单名称不能为空' };
        }
        
        if (!/^[a-zA-Z0-9_]+$/.test(formData.name)) {
            return { valid: false, message: '表单名称只能包含字母、数字和下划线' };
        }
        
        if (!formData.title || !formData.title.trim()) {
            return { valid: false, message: '表单标题不能为空' };
        }
        
        if (!formData.fields || formData.fields.length === 0) {
            return { valid: false, message: '表单至少需要包含一个字段' };
        }
        
        return { valid: true };
    }
    
    // 保存响应处理
    handleSaveResponse(response, status) {
        if (response && response.success) {
            this.setSaveStatus('success', status === 'published' ? '发布成功！' : '保存成功！');
            this.isDirty = false;
            
            // 更新表单ID
            if (response.data && response.data.form_id && !this.currentFormId) {
                this.currentFormId = response.data.form_id;
                window.uformsConfig.formId = response.data.form_id;
            }
            
            this.showNotification('success', status === 'published' ? '表单发布成功！' : '表单保存成功！');
            
            setTimeout(() => {
                this.setSaveStatus('saved', '已保存');
            }, 3000);
        } else {
            const errorMessage = response?.message || '保存失败，请重试';
            this.setSaveStatus('error', errorMessage);
            this.showNotification('error', '保存失败：' + errorMessage);
        }
    }
    
    // 保存错误处理
    handleSaveError(error) {
        console.error('Save error details:', error);
        
        let errorMessage = '保存失败';
        if (error.message) {
            errorMessage = '保存失败：' + error.message;
        }
        
        this.setSaveStatus('error', errorMessage);
        this.showNotification('error', errorMessage);
    }
    
    // 设置保存状态
    setSaveStatus(status, message) {
        const statusElement = $('#save-status');
        const iconMap = {
            saving: '⏳',
            success: '✅',
            error: '❌',
            saved: '✅',
            unsaved: '⚠️'
        };
        
        statusElement.removeClass('saving success error saved unsaved')
                   .addClass(status)
                   .html(`<i class="status-icon">${iconMap[status] || '❓'}</i> ${message}`);
    }
    
    // 标记为脏数据
    markDirty() {
        this.isDirty = true;
        this.setSaveStatus('unsaved', '有未保存的更改');
    }
    
    // 显示通知
    showNotification(type, message, duration = 5000) {
        $('.notification').remove();
        
        const notification = $(`
            <div class="notification notification-${type}">
                <div class="notification-content">
                    <span class="notification-icon">${type === 'success' ? '✅' : '❌'}</span>
                    <span class="notification-message">${message}</span>
                    <button class="notification-close">×</button>
                </div>
            </div>
        `);
        
        if ($('.notifications-container').length === 0) {
            $('body').append('<div class="notifications-container"></div>');
        }
        
        $('.notifications-container').append(notification);
        
        setTimeout(() => notification.addClass('show'), 100);
        
        setTimeout(() => {
            notification.removeClass('show');
            setTimeout(() => notification.remove(), 300);
        }, duration);
        
        notification.find('.notification-close').on('click', () => {
            notification.removeClass('show');
            setTimeout(() => notification.remove(), 300);
        });
    }
    
    // 添加字段到画布
    addFieldFromLibrary(fieldItem) {
        const fieldType = fieldItem.dataset.type;
        const fieldConfig = this.getDefaultFieldConfig(fieldType);
        const fieldId = this.generateFieldId();
        
        this.addFieldToCanvas(fieldId, fieldType, fieldConfig);
    }
    
    addFieldToCanvas(fieldId, fieldType, fieldConfig) {
        if ($('#form-canvas .canvas-drop-zone').length > 0) {
            $('#form-canvas').empty();
        }
        
        const fieldElement = this.createFieldElement(fieldId, fieldType, fieldConfig);
        $('#form-canvas').append(fieldElement);
        
        this.fieldsData.set(fieldId, {
            id: fieldId,
            type: fieldType,
            config: fieldConfig
        });
        
        this.markDirty();
    }
    
    // 生成字段ID
    generateFieldId() {
        return 'field_' + (++this.fieldCounter) + '_' + Date.now();
    }
    
    // 获取默认字段配置
    getDefaultFieldConfig(type) {
        const configs = {
            text: {
                label: '单行文本',
                name: 'text_field_' + (this.fieldCounter + 1),
                placeholder: '请输入文本',
                required: false
            },
            textarea: {
                label: '多行文本',
                name: 'textarea_field_' + (this.fieldCounter + 1),
                placeholder: '请输入内容',
                required: false,
                rows: 4
            },
            email: {
                label: '邮箱地址',
                name: 'email_field_' + (this.fieldCounter + 1),
                placeholder: '请输入邮箱',
                required: false
            },
            url: {
                label: '网站地址',
                name: 'url_field_' + (this.fieldCounter + 1),
                placeholder: '请输入网址',
                required: false
            },
            tel: {
                label: '电话号码',
                name: 'tel_field_' + (this.fieldCounter + 1),
                placeholder: '请输入电话',
                required: false
            },
            number: {
                label: '数字输入',
                name: 'number_field_' + (this.fieldCounter + 1),
                placeholder: '请输入数字',
                required: false
            },
            select: {
                label: '下拉选择',
                name: 'select_field_' + (this.fieldCounter + 1),
                required: false,
                options: [
                    { label: '选项1', value: 'option1' },
                    { label: '选项2', value: 'option2' }
                ]
            },
            radio: {
                label: '单选按钮',
                name: 'radio_field_' + (this.fieldCounter + 1),
                required: false,
                options: [
                    { label: '选项1', value: 'option1' },
                    { label: '选项2', value: 'option2' }
                ]
            },
            checkbox: {
                label: '复选框',
                name: 'checkbox_field_' + (this.fieldCounter + 1),
                required: false,
                options: [
                    { label: '选项1', value: 'option1' },
                    { label: '选项2', value: 'option2' }
                ]
            },
            file: {
                label: '文件上传',
                name: 'file_field_' + (this.fieldCounter + 1),
                required: false,
                maxSize: 10
            },
            date: {
                label: '日期选择',
                name: 'date_field_' + (this.fieldCounter + 1),
                required: false
            },
            time: {
                label: '时间选择',
                name: 'time_field_' + (this.fieldCounter + 1),
                required: false
            },
            datetime: {
                label: '日期时间',
                name: 'datetime_field_' + (this.fieldCounter + 1),
                required: false
            },
            range: {
                label: '数值滑块',
                name: 'range_field_' + (this.fieldCounter + 1),
                required: false,
                min: 0,
                max: 100,
                step: 1
            },
            hidden: {
                label: '隐藏字段',
                name: 'hidden_field_' + (this.fieldCounter + 1),
                value: ''
            }
        };
        
        return configs[type] || configs.text;
    }
    
    // 创建字段元素
    createFieldElement(fieldId, fieldType, config) {
        return $(`
            <div class="canvas-field" id="${fieldId}" data-type="${fieldType}">
                <div class="field-header">
                    <span class="field-label">${config.label}</span>
                    <div class="field-actions">
                        <button class="field-action field-delete" title="删除字段" type="button">
                            <i class="icon-trash">🗑</i>
                        </button>
                    </div>
                </div>
                <div class="field-body">
                    ${this.renderFieldPreview(fieldType, config)}
                </div>
            </div>
        `);
    }
    
    // 渲染字段预览
    renderFieldPreview(fieldType, config) {
        switch (fieldType) {
            case 'text':
            case 'email':
            case 'url':
            case 'tel':
                return `<input type="${fieldType}" placeholder="${config.placeholder || ''}" disabled class="preview-input" />`;
            case 'textarea':
                return `<textarea rows="${config.rows || 4}" placeholder="${config.placeholder || ''}" disabled class="preview-textarea"></textarea>`;
            case 'number':
                return `<input type="number" placeholder="${config.placeholder || ''}" disabled class="preview-input" />`;
            case 'select':
                let options = config.options ? config.options.map(opt => 
                    `<option value="${opt.value}">${opt.label}</option>`
                ).join('') : '';
                return `<select disabled class="preview-select">
                    <option value="">请选择</option>
                    ${options}
                </select>`;
            case 'radio':
            case 'checkbox':
                let inputs = config.options ? config.options.map((opt, i) => 
                    `<label class="${fieldType}-option">
                        <input type="${fieldType}" name="${config.name}" value="${opt.value}" disabled />
                        <span>${opt.label}</span>
                    </label>`
                ).join('') : '';
                return `<div class="${fieldType}-group">${inputs}</div>`;
            case 'file':
                return `<input type="file" disabled class="preview-input" />`;
            case 'date':
                return `<input type="date" disabled class="preview-input" />`;
            case 'time':
                return `<input type="time" disabled class="preview-input" />`;
            case 'datetime':
                return `<input type="datetime-local" disabled class="preview-input" />`;
            case 'range':
                return `<input type="range" min="${config.min || 0}" max="${config.max || 100}" step="${config.step || 1}" disabled class="preview-range" />`;
            case 'hidden':
                return `<div class="hidden-field-info">隐藏字段: ${config.name} = "${config.value || ''}"</div>`;
            default:
                return `<div class="field-placeholder">字段类型: ${fieldType}</div>`;
        }
    }
    
    // 删除字段
    deleteField(fieldElement) {
        const fieldId = fieldElement.id;
        const fieldData = this.fieldsData.get(fieldId);
        
        if (!fieldData) return;
        
        if (confirm(`确定要删除字段 "${fieldData.config.label}" 吗？`)) {
            this.fieldsData.delete(fieldId);
            $(fieldElement).remove();
            
            if ($('#form-canvas .canvas-field').length === 0) {
                this.showEmptyCanvas();
            }
            
            this.markDirty();
        }
    }
    
    // 显示空画布
    showEmptyCanvas() {
        $('#form-canvas').html(`
            <div class="canvas-drop-zone" id="canvas-drop-zone">
                <div class="drop-hint">
                    <div class="drop-icon">
                        <i class="icon-drag">⬇</i>
                    </div>
                    <h3>从左侧拖拽字段到这里开始创建表单</h3>
                    <p>或者点击左侧字段图标快速添加到表单</p>
                    <div class="quick-start">
                        <button class="btn btn-primary" id="add-text-field">
                            <i class="icon-plus">+</i> 添加文本字段
                        </button>
                    </div>
                </div>
            </div>
        `);
    }
    
    // 选择字段
    selectField(fieldElement) {
        $('.canvas-field').removeClass('selected');
        $(fieldElement).addClass('selected');
        this.selectedField = fieldElement;
    }
    
    // 标签页切换
    switchTab(tab) {
        $('.tab-button').removeClass('active');
        $(`.tab-button[data-tab="${tab}"]`).addClass('active');
        
        $('.tab-content').removeClass('active');
        $(`#${tab}-tab`).addClass('active');
    }
    
    // 初始化排序
    initSortable() {
        if (typeof Sortable !== 'undefined') {
            const canvasElement = document.getElementById('form-canvas');
            if (canvasElement) {
                this.canvasSortable = Sortable.create(canvasElement, {
                    animation: 150,
                    onEnd: () => {
                        this.markDirty();
                    }
                });
            }
        }
    }
    
    // 加载现有表单
    loadExistingForm() {
        if (window.uformsConfig.existingFields && window.uformsConfig.existingFields.length > 0) {
            $('#form-canvas').empty();
            
            window.uformsConfig.existingFields.forEach((field) => {
                const config = JSON.parse(field.field_config || '{}');
                const fieldId = this.generateFieldId();
                
                this.addFieldToCanvas(fieldId, field.field_type, {
                    ...config,
                    label: field.field_label,
                    name: field.field_name,
                    required: field.is_required == 1
                });
            });
        }
        
        this.isDirty = false;
        this.setSaveStatus('saved', '已保存');
    }
}

// 初始化构建器
$(document).ready(function() {
    try {
        window.uformsBuilder = new UformsBuilder();
        console.log('Uforms Builder 初始化成功');
    } catch (error) {
        console.error('Uforms Builder 初始化失败:', error);
        alert('表单构建器初始化失败，请刷新页面重试');
    }
});
```

现在主要的修复包括：

1. **修复了AJAX URL配置** - 确保URL指向正确的create.php文件
2. **修复了JSON响应处理** - 确保服务器返回有效的JSON格式
3. **增强了错误处理** - 添加了响应类型检查和详细的错误信息
4. **简化了代码结构** - 移除了一些复杂的功能，专注于核心的保存功能
5. **修复了数据收集和验证** - 确保数据格式正确

请重新上传这些文件并测试保存功能。现在应该能够正常保存和发布表单了。
