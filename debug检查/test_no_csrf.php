<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    define('__TYPECHO_ROOT_DIR__', dirname(__FILE__));
}
require_once __TYPECHO_ROOT_DIR__ . '/config.inc.php';

echo "<h2>无CSRF验证的表单测试</h2>";

$form_url = 'http://tiango.wiki/uforms/form/test_contact';
?>

<form method="post" action="<?php echo $form_url; ?>">
    <input name="uform_name" type="hidden" value="test_contact" />
    <input name="_token" type="hidden" value="bypass_token" />
    
    <p>姓名：<input name="name" value="测试用户" required /></p>
    <p>邮箱：<input name="email" type="email" value="test@example.com" required /></p>
    <p>咨询类型：
        <select name="subject" required>
            <option value="general">一般咨询</option>
            <option value="technical">技术支持</option>
        </select>
    </p>
    <p>留言：<textarea name="message" required>这是测试留言内容</textarea></p>
    
    <button type="submit">提交测试</button>
</form>

<p><strong>同时，临时修改验证逻辑：</strong></p>
<p>在 handleFormSubmission 方法中，将令牌验证部分临时改为：</p>
<pre>
// 临时绕过CSRF验证用于测试
$token = $request->get('_token');
if ($token === 'bypass_token') {
    error_log('Uforms: Using bypass token for testing');
    $token_valid = true;
} else {
    // 原有的验证逻辑...
}
</pre>
