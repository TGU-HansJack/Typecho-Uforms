<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Uforms 插件配置
 */
return array(
    // 基本设置
    'enable_forms' => array(
        'title' => '启用表单功能',
        'description' => '启用或禁用表单功能',
        'type' => 'checkbox',
        'default' => 1,
        'options' => array('1' => '启用')
    ),
    
    'enable_calendar' => array(
        'title' => '启用日历功能',
        'description' => '启用或禁用日历预约功能',
        'type' => 'checkbox',
        'default' => 1,
        'options' => array('1' => '启用')
    ),
    
    'enable_analytics' => array(
        'title' => '启用数据分析',
        'description' => '启用或禁用数据分析功能',
        'type' => 'checkbox',
        'default' => 1,
        'options' => array('1' => '启用')
    ),
    
    // 邮件设置
    'enable_email' => array(
        'title' => '启用邮件通知',
        'description' => '启用邮件通知功能',
        'type' => 'checkbox',
        'default' => 0,
        'options' => array('1' => '启用')
    ),
    
    'smtp_host' => array(
        'title' => 'SMTP 服务器',
        'description' => '邮件服务器地址',
        'type' => 'text',
        'default' => 'smtp.gmail.com'
    ),
    
    'smtp_port' => array(
        'title' => 'SMTP 端口',
        'description' => '邮件服务器端口',
        'type' => 'text',
        'default' => '587'
    ),
    
    'smtp_username' => array(
        'title' => 'SMTP 用户名',
        'description' => '邮件服务器用户名',
        'type' => 'text',
        'default' => ''
    ),
    
    'smtp_password' => array(
        'title' => 'SMTP 密码',
        'description' => '邮件服务器密码',
        'type' => 'password',
        'default' => ''
    ),
    
    // 文件上传设置
    'upload_enabled' => array(
        'title' => '启用文件上传',
        'description' => '允许表单中包含文件上传字段',
        'type' => 'checkbox',
        'default' => 1,
        'options' => array('1' => '启用')
    ),
    
    'upload_max_size' => array(
        'title' => '最大文件大小',
        'description' => '单个文件最大大小（MB）',
        'type' => 'text',
        'default' => '5'
    ),
    
    'allowed_file_types' => array(
        'title' => '允许的文件类型',
        'description' => '用逗号分隔，如：jpg,png,pdf,doc',
        'type' => 'text',
        'default' => 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip'
    ),
    
    // 安全设置
    'enable_captcha' => array(
        'title' => '启用验证码',
        'description' => '为表单启用验证码保护',
        'type' => 'checkbox',
        'default' => 0,
        'options' => array('1' => '启用')
    ),
    
    'recaptcha_site_key' => array(
        'title' => 'reCAPTCHA 站点密钥',
        'description' => 'Google reCAPTCHA 站点密钥',
        'type' => 'text',
        'default' => ''
    ),
    
    'recaptcha_secret_key' => array(
        'title' => 'reCAPTCHA 私钥',
        'description' => 'Google reCAPTCHA 私钥',
        'type' => 'password',
        'default' => ''
    ),
    
    'enable_spam_filter' => array(
        'title' => '启用垃圾内容过滤',
        'description' => '自动过滤垃圾内容',
        'type' => 'checkbox',
        'default' => 1,
        'options' => array('1' => '启用')
    ),
    
    'rate_limit' => array(
        'title' => '提交频率限制',
        'description' => '每分钟允许的最大提交次数',
        'type' => 'text',
        'default' => '3'
    ),
    
    // 数据保留设置
    'data_retention_days' => array(
        'title' => '数据保留天数',
        'description' => '已删除数据的保留天数，0表示永久保留',
        'type' => 'text',
        'default' => '365'
    ),
    
    'auto_cleanup' => array(
        'title' => '自动清理过期数据',
        'description' => '定期清理过期的删除数据',
        'type' => 'checkbox',
        'default' => 1,
        'options' => array('1' => '启用')
    ),
    
    // API设置
    'enable_api' => array(
        'title' => '启用API',
        'description' => '启用REST API接口',
        'type' => 'checkbox',
        'default' => 0,
        'options' => array('1' => '启用')
    ),
    
    'api_key' => array(
        'title' => 'API密钥',
        'description' => 'API访问密钥，留空则自动生成',
        'type' => 'password',
        'default' => ''
    ),
    
    // 第三方集成
    'webhook_secret' => array(
        'title' => 'Webhook密钥',
        'description' => 'Webhook验证密钥',
        'type' => 'password',
        'default' => ''
    ),
    
    'zapier_enabled' => array(
        'title' => '启用Zapier集成',
        'description' => '允许通过Zapier集成其他服务',
        'type' => 'checkbox',
        'default' => 0,
        'options' => array('1' => '启用')
    ),
    
    // 界面设置
    'admin_per_page' => array(
        'title' => '后台分页数量',
        'description' => '后台列表每页显示的记录数',
        'type' => 'text',
        'default' => '20'
    ),
    
    'date_format' => array(
        'title' => '日期格式',
        'description' => '日期显示格式',
        'type' => 'select',
        'default' => 'Y-m-d H:i:s',
        'options' => array(
            'Y-m-d H:i:s' => '2023-12-25 10:30:00',
            'Y/m/d H:i' => '2023/12/25 10:30',
            'm/d/Y H:i' => '12/25/2023 10:30',
            'd-m-Y H:i' => '25-12-2023 10:30'
        )
    ),
    
    'timezone' => array(
        'title' => '时区设置',
        'description' => '系统时区',
        'type' => 'select',
        'default' => 'Asia/Shanghai',
        'options' => array(
            'Asia/Shanghai' => 'Asia/Shanghai (北京时间)',
            'Asia/Tokyo' => 'Asia/Tokyo (东京时间)',
            'Europe/London' => 'Europe/London (伦敦时间)',
            'America/New_York' => 'America/New_York (纽约时间)',
            'America/Los_Angeles' => 'America/Los_Angeles (洛杉矶时间)'
        )
    )
);
?>
