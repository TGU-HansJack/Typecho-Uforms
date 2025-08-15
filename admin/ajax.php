<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 获取请求和用户对象
$request = Typecho_Request::getInstance();
$user = Typecho_Widget::widget('Widget_User');

// 检查用户权限
if (!$user->hasLogin() || !$user->pass('administrator')) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => '权限不足']);
    exit;
}

$db = Typecho_Db::get();
$options = Helper::options();

// 加载必要的类文件
if (!class_exists('UformsHelper')) {
    require_once dirname(__FILE__) . '/../core/UformsHelper.php';
}

// 处理 AJAX 请求
if ($request->isPost()) {
    $ajax_action = $request->get('action');
    
    switch ($ajax_action) {
        case 'save_form':
            try {
                // 验证数据
                $form_name = trim($request->get('form_name'));
                $form_title = trim($request->get('form_title'));
                $form_id = $request->get('form_id');
                
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
                    'config' => $request->get('form_config', '{}'),
                    'settings' => $request->get('form_settings', '{}'),
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
                    $form_data['version'] = 1;
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
                            'is_enabled' => 1,
                            'created_time' => time()
                        );
                        
                        $db->query($db->insert('table.uforms_fields')->rows($field_data));
                    }
                }
                
                // 返回成功响应
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(array(
                    'success' => true,
                    'message' => '表单保存成功',
                    'form_id' => $updated_form_id,
                    'status' => $form_data['status']
                ));
                
            } catch (Exception $e) {
                // 错误处理
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(array(
                    'success' => false,
                    'message' => $e->getMessage()
                ));
            }
            exit;
            
        case 'save_template':
            try {
                $template_name = trim($request->get('template_name'));
                $template_title = trim($request->get('template_title'));
                $template_description = trim($request->get('template_description'));
                $template_config = $request->get('template_config', '{}');
                $fields_config = $request->get('fields_config', '[]');
                $form_settings = $request->get('form_settings', '{}');
                
                if (empty($template_name)) {
                    throw new Exception('模板名称不能为空');
                }
                
                // 检查模板名称是否已存在
                $existing = $db->fetchRow(
                    $db->select()->from('table.uforms_templates')
                       ->where('name = ?', $template_name)
                       ->where('author_id = ?', $user->uid)
                );
                
                if ($existing) {
                    throw new Exception('模板名称已存在，请使用其他名称');
                }
                
                // 保存模板到数据库
                $template_data = array(
                    'name' => $template_name,
                    'title' => $template_title,
                    'description' => $template_description,
                    'config' => $template_config,
                    'fields_config' => $fields_config,
                    'form_settings' => $form_settings,
                    'author_id' => $user->uid,
                    'created_time' => time(),
                    'modified_time' => time()
                );
                
                $db->query($db->insert('table.uforms_templates')->rows($template_data));
                
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(array(
                    'success' => true,
                    'message' => '模板保存成功'
                ));
                
            } catch (Exception $e) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(array(
                    'success' => false,
                    'message' => $e->getMessage()
                ));
            }
            exit;
            
        default:
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array(
                'success' => false,
                'message' => '未知的操作'
            ));
            exit;
    }
}

// 如果不是POST请求，返回405错误
header('HTTP/1.1 405 Method Not Allowed');
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(array(
    'success' => false,
    'message' => '请求方法不被允许'
));
exit;
?>