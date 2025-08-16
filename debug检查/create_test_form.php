<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));
}
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

echo "<h2>创建测试表单</h2>";

try {
    $db = Typecho_Db::get();
    
    // 检查是否已存在测试表单
    $existing = $db->fetchRow($db->select('*')->from('table.uforms_forms')->where('name = ?', 'test_contact'));
    
    if ($existing) {
        echo "<p>✓ 测试表单已存在，ID: {$existing['id']}</p>";
        $form_id = $existing['id'];
    } else {
        // 创建测试表单
        $form_data = array(
            'name' => 'test_contact',
            'title' => '联系我们',
            'description' => '请填写您的联系信息，我们会尽快回复您',
            'config' => json_encode(array('theme' => 'default')),
            'settings' => json_encode(array(
                'success_message' => '谢谢您的留言，我们会在24小时内回复您！',
                'submit_text' => '发送消息',
                'enable_email_notification' => false,
                'redirect_url' => ''
            )),
            'status' => 'published',
            'author_id' => 1,
            'view_count' => 0,
            'submit_count' => 0,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        $form_id = $db->query($db->insert('table.uforms_forms')->rows($form_data));
        echo "<p>✓ 创建表单成功，ID: {$form_id}</p>";
    }
    
    // 检查是否已有字段
    $existing_fields = $db->fetchAll($db->select('*')->from('table.uforms_fields')->where('form_id = ?', $form_id));
    
    if (!empty($existing_fields)) {
        echo "<p>✓ 表单字段已存在，数量: " . count($existing_fields) . "</p>";
    } else {
        // 创建表单字段
        $fields = array(
            array(
                'form_id' => $form_id,
                'field_type' => 'text',
                'field_name' => 'name',
                'field_label' => '您的姓名',
                'field_config' => json_encode(array(
                    'placeholder' => '请输入您的姓名',
                    'required' => true
                )),
                'sort_order' => 0,
                'is_required' => 1,
                'created_time' => time()
            ),
            array(
                'form_id' => $form_id,
                'field_type' => 'email',
                'field_name' => 'email',
                'field_label' => '邮箱地址',
                'field_config' => json_encode(array(
                    'placeholder' => '请输入您的邮箱地址',
                    'required' => true
                )),
                'sort_order' => 1,
                'is_required' => 1,
                'created_time' => time()
            ),
            array(
                'form_id' => $form_id,
                'field_type' => 'select',
                'field_name' => 'subject',
                'field_label' => '咨询类型',
                'field_config' => json_encode(array(
                    'options' => array(
                        array('value' => 'general', 'label' => '一般咨询'),
                        array('value' => 'technical', 'label' => '技术支持'),
                        array('value' => 'business', 'label' => '商务合作')
                    ),
                    'placeholder' => '请选择咨询类型'
                )),
                'sort_order' => 2,
                'is_required' => 1,
                'created_time' => time()
            ),
            array(
                'form_id' => $form_id,
                'field_type' => 'textarea',
                'field_name' => 'message',
                'field_label' => '留言内容',
                'field_config' => json_encode(array(
                    'placeholder' => '请详细描述您的问题或需求',
                    'rows' => 5,
                    'required' => true
                )),
                'sort_order' => 3,
                'is_required' => 1,
                'created_time' => time()
            )
        );
        
        foreach ($fields as $field) {
            $db->query($db->insert('table.uforms_fields')->rows($field));
            echo "<p>✓ 创建字段: {$field['field_name']} ({$field['field_type']})</p>";
        }
    }
    
    echo "<h3>测试表单信息</h3>";
    echo "<p><strong>表单名称:</strong> test_contact</p>";
    echo "<p><strong>表单标题:</strong> 联系我们</p>";
    echo "<p><strong>访问URL:</strong></p>";
    
    // 获取站点URL
    $site_url = 'http://' . $_SERVER['HTTP_HOST'];
    echo "<ul>";
    echo "<li><a href='{$site_url}/uforms/form/test_contact' target='_blank'>{$site_url}/uforms/form/test_contact</a></li>";
    echo "<li><a href='{$site_url}/uforms/form/{$form_id}' target='_blank'>{$site_url}/uforms/form/{$form_id}</a></li>";
    echo "</ul>";
    
    echo "<h3>下一步</h3>";
    echo "<p>1. 点击上面的链接测试表单访问</p>";
    echo "<p>2. 填写并提交表单测试功能</p>";
    echo "<p>3. 检查数据库中是否有提交记录</p>";
    
} catch (Exception $e) {
    echo "<p>✗ 创建失败: {$e->getMessage()}</p>";
    echo "<p>文件: {$e->getFile()}:{$e->getLine()}</p>";
    echo "<pre>跟踪: {$e->getTraceAsString()}</pre>";
}
?>
