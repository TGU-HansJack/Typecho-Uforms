## 🤖 Assistant

问题在于AJAX请求返回404错误。从日志可以看到：

```
XHR POST https://tiango.wiki/admin/extending.php?panel=Uforms/create.php [HTTP/2 404 109ms]
```

这说明URL路由有问题。我们需要修复这个问题。

## 修复方案：

### 1. 创建一个专门的AJAX处理文件 `ajax.php`：

```php
<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 检查用户权限
$user = Typecho_Widget::widget('Widget_User');
if (!$user->hasLogin() || !$user->pass('administrator')) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => '权限不足']);
    exit;
}

$request = Typecho_Request::getInstance();
$db = Typecho_Db::get();
$options = Helper::options();

// 加载必要的类文件
if (!class_exists('UformsHelper')) {
    require_once dirname(__FILE__) . '/core/UformsHelper.php';
}

// 设置响应头
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    $action = $request->get('action');
    
    switch ($action) {
        case 'save_form':
            handleSaveForm($request, $db, $options, $user);
            break;
            
        case 'save_template':
            handleSaveTemplate($request, $db, $user);
            break;
            
        case 'delete_form':
            handleDeleteForm($request, $db, $user);
            break;
            
        case 'duplicate_form':
            handleDuplicateForm($request, $db, $user);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '未知的操作']);
    }
    
} catch (Exception $e) {
    error_log('Uforms AJAX Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器错误：' . $e->getMessage()]);
}

/**
 * 处理表单保存
 */
function handleSaveForm($request, $db, $options, $user) {
    try {
        // 获取并验证表单数据
        $form_id = $request->get('form_id');
        $form_name = trim($request->get('form_name', ''));
        $form_title = trim($request->get('form_title', ''));
        $form_description = trim($request->get('form_description', ''));
        $form_status = $request->get('form_status', 'draft');
        $form_config = $request->get('form_config', '{}');
        $form_settings = $request->get('form_settings', '{}');
        $fields_config = $request->get('fields_config', '[]');
        $version_notes = $request->get('version_notes', '');
        $auto_save = $request->get('auto_save', false);
        
        // 验证必填字段
        if (empty($form_name)) {
            throw new Exception('表单名称不能为空');
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $form_name)) {
            throw new Exception('表单名称只能包含字母、数字和下划线');
        }
        
        if (empty($form_title)) {
            throw new Exception('表单标题不能为空');
        }
        
        // 验证字段配置
        $fields_data = json_decode($fields_config, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('字段配置格式错误');
        }
        
        if (empty($fields_data)) {
            throw new Exception('表单至少需要包含一个字段');
        }
        
        // 检查表单名称唯一性
        $existing = $db->fetchRow(
            $db->select()->from('table.uforms_forms')
               ->where('name = ? AND id != ?', $form_name, $form_id ?: 0)
        );
        
        if ($existing) {
            throw new Exception('表单名称已存在，请使用其他名称');
        }
        
        $current_time = time();
        
        if ($form_id) {
            // 更新现有表单
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
            if ($form_status === 'published') {
                $form = $db->fetchRow($db->select()->from('table.uforms_forms')->where('id = ?', $form_id));
                if ($form && $form['status'] !== 'published') {
                    $update_data['published_time'] = $current_time;
                }
                
                if (empty($form['slug'])) {
                    $slug = UformsHelper::generateSlug($form_name, $form_title);
                    $update_data['slug'] = $slug;
                }
            }
            
            $db->query($db->update('table.uforms_forms')
                         ->rows($update_data)
                         ->where('id = ?', $form_id));
            
            // 创建版本备份（仅非自动保存时）
            if (!$auto_save) {
                UformsHelper::createFormVersion($form_id, json_decode($form_config, true), $fields_data, $version_notes);
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
        saveFormFields($db, $form_id, $fields_data, $current_time);
        
        // 准备响应数据
        $response_data = array(
            'form_id' => $form_id,
            'status' => $form_status
        );
        
        if ($form_status === 'published') {
            $site_url = $options->siteUrl;
            $form_url = $site_url . 'uforms/form/' . $form_id;
            $response_data['form_url'] = $form_url;
        }
        
        // 发送成功通知
        if ($form_status === 'published' && !$auto_save) {
            UformsHelper::createSystemNotification(
                $form_id,
                null,
                'form_published',
                '表单发布成功',
                "表单 \"{$form_title}\" 已成功发布并可以开始接收用户提交。",
                array('form_url' => isset($response_data['form_url']) ? $response_data['form_url'] : null)
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => $form_status === 'published' ? '表单发布成功' : '表单保存成功',
            'data' => $response_data
        ]);
        
    } catch (Exception $e) {
        error_log('Uforms save error: ' . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * 保存表单字段
 */
function saveFormFields($db, $form_id, $fields_data, $current_time) {
    // 删除原有字段
    $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $form_id));
    
    // 插入新字段
    foreach ($fields_data as $index => $field_config) {
        if (empty($field_config['type']) || empty($field_config['name'])) {
            continue; // 跳过无效字段
        }
        
        $field_data = array(
            'form_id' => $form_id,
            'field_type' => $field_config['type'],
            'field_name' => $field_config['name'],
            'field_label' => isset($field_config['label']) ? $field_config['label'] : '',
            'field_config' => json_encode($field_config),
            'sort_order' => isset($field_config['sortOrder']) ? intval($field_config['sortOrder']) : $index,
            'is_required' => !empty($field_config['required']) ? 1 : 0,
            'is_enabled' => 1,
            'created_time' => $current_time
        );
        
        $db->query($db->insert('table.uforms_fields')->rows($field_data));
    }
}

/**
 * 处理模板保存
 */
function handleSaveTemplate($request, $db, $user) {
    try {
        $template_name = trim($request->get('template_name', ''));
        $template_title = trim($request->get('template_title', ''));
        $template_description = trim($request->get('template_description', ''));
        $template_config = $request->get('template_config', '{}');
        $fields_config = $request->get('fields_config', '[]');
        $form_settings = $request->get('form_settings', '{}');
        
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
            'created_time' => time(),
            'modified_time' => time()
        );
        
        $db->query($db->insert('table.uforms_templates')->rows($template_data));
        
        echo json_encode(['success' => true, 'message' => '模板保存成功']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * 处理表单删除
 */
function handleDeleteForm($request, $db, $user) {
    try {
        $form_id = $request->get('form_id');
        
        if (!$form_id) {
            throw new Exception('表单ID不能为空');
        }
        
        // 检查权限
        $form = $db->fetchRow($db->select()->from('table.uforms_forms')->where('id = ?', $form_id));
        if (!$form) {
            throw new Exception('表单不存在');
        }
        
        if ($form['author_id'] != $user->uid && !$user->pass('administrator')) {
            throw new Exception('权限不足');
        }
        
        // 删除表单及相关数据
        UformsHelper::deleteForm($form_id);
        
        echo json_encode(['success' => true, 'message' => '表单删除成功']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * 处理表单复制
 */
function handleDuplicateForm($request, $db, $user) {
    try {
        $form_id = $request->get('form_id');
        $new_name = trim($request->get('new_name', ''));
        $new_title = trim($request->get('new_title', ''));
        
        if (!$form_id) {
            throw new Exception('表单ID不能为空');
        }
        
        $new_form_id = UformsHelper::duplicateForm($form_id, $new_name, $new_title);
        
        if ($new_form_id) {
            echo json_encode([
                'success' => true, 
                'message' => '表单复制成功',
                'data' => ['form_id' => $new_form_id]
            ]);
        } else {
            throw new Exception('表单复制失败');
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
```

### 2. 修改 `create.php` 中的JavaScript配置：

```php
<script>
// 全局变量配置
window.uformsConfig = {
    formId: <?php echo $form_id ? $form_id : 'null'; ?>,
    ajaxUrl: '<?php echo $options->adminUrl; ?>extending.php?panel=Uforms%2Fajax.php',
    pluginUrl: '<?php echo $options->pluginUrl; ?>/Uforms',
    siteUrl: '<?php echo $options->siteUrl; ?>',
    existingFields: <?php echo json_encode($fields); ?>,
    existingConfig: <?php echo $form ? json_encode(json_decode($form['config'], true)) : '{}'; ?>,
    existingSettings: <?php echo $form ? json_encode(json_decode($form['settings'], true)) : '{}'; ?>,
    userId: <?php echo $user->uid; ?>,
    userName: '<?php echo $user->screenName; ?>',
    userEmail: '<?php echo $user->mail; ?>'
};

// 确保在DOM加载后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 检查依赖
    if (typeof Sortable === 'undefined') {
        console.warn('Sortable.js not loaded, drag functionality will be limited');
    }
    
    console.log('Uforms Creator initialized with config:', window.uformsConfig);
});
</script>
```

### 3. 从 `create.php` 中移除POST处理代码：

从 `create.php` 文件中删除所有 `// 处理AJAX保存请求` 相关的代码块，因为现在由 `ajax.php` 处理。

### 4. 添加缺失的 UformsHelper 方法：

```php
<?php
// 在 UformsHelper.php 中添加这个方法
public static function deleteForm($formId) {
    $db = self::getDb();
    
    // 删除表单
    $db->query($db->delete('table.uforms_forms')->where('id = ?', $formId));
    
    // 删除相关字段
    $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $formId));
    
    // 删除相关提交
    $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $formId));
    
    // 删除相关文件
    $files = $db->fetchAll(
        $db->select('file_path')->from('table.uforms_files')
           ->where('form_id = ?', $formId)
    );
    
    foreach ($files as $file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
    }
    
    $db->query($db->delete('table.uforms_files')->where('form_id = ?', $formId));
    
    // 删除其他相关数据
    try {
        $db->query($db->delete('table.uforms_notifications')->where('form_id = ?', $formId));
        $db->query($db->delete('table.uforms_calendar')->where('form_id = ?', $formId));
        $db->query($db->delete('table.uforms_stats')->where('form_id = ?', $formId));
        $db->query($db->delete('table.uforms_versions')->where('form_id = ?', $formId));
    } catch (Exception $e) {
        // 某些表可能不存在，忽略错误
        error_log('Uforms: Some cleanup operations failed: ' . $e->getMessage());
    }
    
    return true;
}

public static function duplicateForm($originalId, $newName = null, $newTitle = null) {
    $originalForm = self::getForm($originalId);
    if (!$originalForm) {
        return false;
    }
    
    try {
        $user = Typecho_Widget::widget('Widget_User');
        $userId = $user->hasLogin() ? $user->uid : 1;
    } catch (Exception $e) {
        $userId = 1;
    }
    
    $db = self::getDb();
    
    // 生成唯一名称
    if (!$newName) {
        $newName = $originalForm['name'] . '_copy';
        $counter = 1;
        while ($db->fetchRow($db->select('id')->from('table.uforms_forms')->where('name = ?', $newName))) {
            $newName = $originalForm['name'] . '_copy_' . $counter;
            $counter++;
        }
    }
    
    // 准备新表单数据
    $newData = array(
        'name' => $newName,
        'title' => $newTitle ?: ($originalForm['title'] . ' (副本)'),
        'description' => $originalForm['description'],
        'config' => $originalForm['config'],
        'settings' => $originalForm['settings'],
        'status' => 'draft',
        'author_id' => $userId,
        'view_count' => 0,
        'submit_count' => 0,
        'version' => 1,
        'created_time' => time(),
        'modified_time' => time()
    );
    
    // 创建新表单
    $newFormId = $db->query($db->insert('table.uforms_forms')->rows($newData));
    
    if ($newFormId) {
        // 复制字段
        $fields = self::getFormFields($originalId);
        foreach ($fields as $field) {
            $fieldData = array(
                'form_id' => $newFormId,
                'field_type' => $field['field_type'],
                'field_name' => $field['field_name'],
                'field_label' => $field['field_label'],
                'field_config' => $field['field_config'],
                'sort_order' => $field['sort_order'],
                'is_required' => $field['is_required'],
                'is_enabled' => $field['is_enabled'],
                'created_time' => time()
            );
            
            $db->query($db->insert('table.uforms_fields')->rows($fieldData));
        }
        
        return $newFormId;
    }
    
    return false;
}
?>
```

这样修复后：

1. **AJAX请求**将正确路由到 `ajax.php`
2. **所有后端逻辑**都集中在 `ajax.php` 中处理
3. **错误处理**更完善
4. **URL路由**问题得到解决

现在保存和发布功能应该可以正常工作了。请创建 `ajax.php` 文件，并按照以上方式修改 `create.php`。
