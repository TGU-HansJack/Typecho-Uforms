<?php
/**
 * Uforms - 通用表单系统 (Universal Form System) 是Typecho轻博客第一款表单系统，给你无限的体验！
 * 
 * @package Uforms
 * @author HansJack
 * @version 2.0.0
 * @link https://www.hansjack.top
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class Uforms_Plugin implements Typecho_Plugin_Interface
{
     /**
     * 激活插件方法，如果激活失败，直接抛出异常
     */
    public static function activate()
    {
        try {
            // 创建数据库表
            self::createTables();
            
            // 更新数据库表结构（为已存在的表添加新字段）
            self::updateTables();
            
            // 注册路由 - 修复路由注册方式
            Helper::addRoute('uforms_form', '/uforms/form/[name]', 'Uforms_Action', 'showForm');
            Helper::addRoute('uforms_form_id', '/uforms/form/[id:digital]', 'Uforms_Action', 'showFormById');
            Helper::addRoute('uforms_submit', '/uforms/submit', 'Uforms_Action', 'submit');
            Helper::addRoute('uforms_calendar', '/uforms/calendar/[id:digital]', 'Uforms_Action', 'calendar');
            Helper::addRoute('uforms_api', '/uforms/api/[action]', 'Uforms_Action', 'apiHandler');
            
            // 注册后台管理界面
            Helper::addPanel(1, 'Uforms/index.php', '表单系统', '表单系统管理', 'administrator');
            
            // 注册插件挂载点
            Typecho_Plugin::factory('admin/header.php')->header = array('Uforms_Plugin', 'adminHeader');
            Typecho_Plugin::factory('Widget_Archive')->header = array('Uforms_Plugin', 'frontHeader');
            Typecho_Plugin::factory('Widget_Archive')->footer = array('Uforms_Plugin', 'frontFooter');
            
            // 注册短代码
            Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('Uforms_Plugin', 'parseShortcode');
            Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('Uforms_Plugin', 'parseShortcode');
            
            // 创建上传目录
            self::createUploadDirs();
            
            // 设置默认配置
            self::setDefaultOptions();
            
            return '插件安装成功！请在管理界面进行配置。';
            
        } catch (Exception $e) {
            throw new Typecho_Plugin_Exception('插件激活失败: ' . $e->getMessage());
        }
    }

    /**
     * 禁用插件方法
     */
    public static function deactivate()
    {
        // 移除路由
        Helper::removeRoute('uforms_form');
        Helper::removeRoute('uforms_form_id');
        Helper::removeRoute('uforms_submit');
        Helper::removeRoute('uforms_calendar');
        Helper::removeRoute('uforms_api');
        
        // 移除面板
        Helper::removePanel(1, 'Uforms/index.php');
 
        return '插件已禁用';
    }

    /**
     * 获取插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        // 基本设置
        $enable_forms = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_forms',
            array('1' => '启用', '0' => '禁用'),
            '1',
            '启用表单功能',
            '启用或禁用表单功能'
        );
        $form->addInput($enable_forms);
        
        $enable_calendar = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_calendar',
            array('1' => '启用', '0' => '禁用'),
            '1',
            '启用日历功能',
            '启用或禁用日历预约功能'
        );
        $form->addInput($enable_calendar);
        
        $enable_analytics = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_analytics',
            array('1' => '启用', '0' => '禁用'),
            '1',
            '启用数据分析',
            '启用或禁用数据分析功能'
        );
        $form->addInput($enable_analytics);
        
        // 邮件设置
        $enable_email = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_email',
            array('1' => '启用', '0' => '禁用'),
            '0',
            '启用邮件通知',
            '启用邮件通知功能'
        );
        $form->addInput($enable_email);
        
        $smtp_host = new Typecho_Widget_Helper_Form_Element_Text(
            'smtp_host',
            NULL,
            'smtp.gmail.com',
            'SMTP 服务器',
            '邮件服务器地址'
        );
        $form->addInput($smtp_host);
        
        $smtp_port = new Typecho_Widget_Helper_Form_Element_Text(
            'smtp_port',
            NULL,
            '587',
            'SMTP 端口',
            '邮件服务器端口'
        );
        $form->addInput($smtp_port);
        
        $smtp_username = new Typecho_Widget_Helper_Form_Element_Text(
            'smtp_username',
            NULL,
            '',
            'SMTP 用户名',
            '邮件服务器用户名'
        );
        $form->addInput($smtp_username);
        
        $smtp_password = new Typecho_Widget_Helper_Form_Element_Password(
            'smtp_password',
            NULL,
            '',
            'SMTP 密码',
            '邮件服务器密码'
        );
        $form->addInput($smtp_password);
        
        // 文件上传设置
        $upload_enabled = new Typecho_Widget_Helper_Form_Element_Radio(
            'upload_enabled',
            array('1' => '启用', '0' => '禁用'),
            '1',
            '启用文件上传',
            '允许表单中包含文件上传字段'
        );
        $form->addInput($upload_enabled);
        
        $upload_max_size = new Typecho_Widget_Helper_Form_Element_Text(
            'upload_max_size',
            NULL,
            '5',
            '最大文件大小',
            '单个文件最大大小（MB）'
        );
        $form->addInput($upload_max_size);
        
        $allowed_file_types = new Typecho_Widget_Helper_Form_Element_Text(
            'allowed_file_types',
            NULL,
            'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip',
            '允许的文件类型',
            '用逗号分隔，如：jpg,png,pdf,doc'
        );
        $form->addInput($allowed_file_types);
        
        // 安全设置
        $enable_captcha = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_captcha',
            array('1' => '启用', '0' => '禁用'),
            '0',
            '启用验证码',
            '为表单启用验证码保护'
        );
        $form->addInput($enable_captcha);
        
        // 修复：添加缺少的 enable_spam_filter 配置
        $enable_spam_filter = new Typecho_Widget_Helper_Form_Element_Radio(
            'enable_spam_filter',
            array('1' => '启用', '0' => '禁用'),
            '1',
            '启用垃圾内容过滤',
            '自动过滤垃圾内容'
        );
        $form->addInput($enable_spam_filter);
        
        $rate_limit = new Typecho_Widget_Helper_Form_Element_Text(
            'rate_limit',
            NULL,
            '3',
            '提交频率限制',
            '同一IP在1分钟内最多提交次数'
        );
        $form->addInput($rate_limit);
        
        // 后台设置
        $admin_per_page = new Typecho_Widget_Helper_Form_Element_Select(
            'admin_per_page',
            array('10' => '10条/页', '20' => '20条/页', '50' => '50条/页', '100' => '100条/页'),
            '20',
            '后台分页数量',
            '后台列表页面每页显示的记录数'
        );
        $form->addInput($admin_per_page);
    }

    /**
     * 个人用户的配置面板
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 管理后台头部资源
     */
    public static function adminHeader()
    {
        $request = Typecho_Request::getInstance();
        if (strpos($request->getRequestUri(), 'Uforms') !== false) {
            $options = Helper::options();
            $pluginUrl = $options->pluginUrl . '/Uforms';
            echo '<link rel="stylesheet" href="' . $pluginUrl . '/assets/css/admin.css">';
            echo '<script>var uformsAjaxUrl = "' . Helper::options()->adminUrl . 'extending.php?panel=Uforms%2Fadmin%2Findex.php";</script>';
        }
    }

    /**
     * 前端头部资源
     */
    public static function frontHeader()
    {
        $request = Typecho_Request::getInstance();
        $pathInfo = $request->getPathInfo();
        
        // 只在需要的页面加载资源
        if (strpos($pathInfo, '/uforms/') === 0 || self::hasUformsShortcode()) {
            $options = Helper::options();
            $pluginUrl = $options->pluginUrl . '/Uforms';
            echo '<link rel="stylesheet" href="' . $pluginUrl . '/assets/css/uforms.css">';
        }
    }

    /**
     * 前端底部资源
     */
    public static function frontFooter()
    {
        $request = Typecho_Request::getInstance();
        $pathInfo = $request->getPathInfo();
        
        if (strpos($pathInfo, '/uforms/') === 0 || self::hasUformsShortcode()) {
            $options = Helper::options();
            $pluginUrl = $options->pluginUrl . '/Uforms';
            echo '<script src="' . $pluginUrl . '/assets/js/uforms.js"></script>';
        }
    }

    /**
     * 检查是否包含Uforms短代码
     */
    private static function hasUformsShortcode()
    {
        return false;
    }

    // ... 其余保持不变的方法
    
    /**
     * 创建数据库表 - 完全相同
     */
    private static function createTables()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        // 表单表 - 修复DESC字段问题
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_forms` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL COMMENT '表单名称',
            `title` varchar(200) NOT NULL COMMENT '表单标题',
            `description` text COMMENT '表单描述',
            `config` longtext COMMENT '表单配置',
            `settings` longtext COMMENT '表单设置',
            `status` varchar(20) DEFAULT 'draft' COMMENT '状态',
            `author_id` int(11) NOT NULL COMMENT '创建者ID',
            `view_count` int(11) DEFAULT 0 COMMENT '访问次数',
            `submit_count` int(11) DEFAULT 0 COMMENT '提交次数',
            `created_time` int(11) NOT NULL COMMENT '创建时间',
            `modified_time` int(11) NOT NULL COMMENT '修改时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`),
            KEY `status` (`status`),
            KEY `author_id` (`author_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='表单表';";
        $db->query($sql);

        // 字段表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_fields` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) NOT NULL COMMENT '表单ID',
            `field_type` varchar(50) NOT NULL COMMENT '字段类型',
            `field_name` varchar(100) NOT NULL COMMENT '字段名称',
            `field_label` varchar(200) NOT NULL COMMENT '字段标签',
            `field_config` text COMMENT '字段配置',
            `sort_order` int(11) DEFAULT 0 COMMENT '排序',
            `is_required` tinyint(1) DEFAULT 0 COMMENT '是否必填',
            `created_time` int(11) NOT NULL COMMENT '创建时间',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `sort_order` (`sort_order`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='表单字段表';";
        $db->query($sql);

        // 提交表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_submissions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) NOT NULL COMMENT '表单ID',
            `data` longtext NOT NULL COMMENT '提交数据',
            `ip` varchar(45) NOT NULL COMMENT 'IP地址',
            `user_agent` varchar(500) DEFAULT NULL COMMENT '用户代理',
            `status` varchar(20) DEFAULT 'new' COMMENT '状态',
            `notes` text COMMENT '备注',
            `source` varchar(50) DEFAULT 'web' COMMENT '来源',
            `created_time` int(11) NOT NULL COMMENT '提交时间',
            `modified_time` int(11) DEFAULT 0 COMMENT '修改时间',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `status` (`status`),
            KEY `ip` (`ip`),
            KEY `created_time` (`created_time`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='表单提交表';";
        $db->query($sql);

        // 文件表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_files` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) DEFAULT NULL COMMENT '表单ID',
            `submission_id` int(11) DEFAULT NULL COMMENT '提交ID',
            `field_name` varchar(100) NOT NULL COMMENT '字段名',
            `original_name` varchar(255) NOT NULL COMMENT '原始文件名',
            `filename` varchar(255) NOT NULL COMMENT '存储文件名',
            `file_path` varchar(500) NOT NULL COMMENT '文件路径',
            `file_size` int(11) NOT NULL COMMENT '文件大小',
            `file_type` varchar(100) NOT NULL COMMENT '文件类型',
            `uploaded_by` int(11) DEFAULT NULL COMMENT '上传者ID',
            `created_time` int(11) NOT NULL COMMENT '上传时间',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `submission_id` (`submission_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='文件上传表';";
        $db->query($sql);

        // 通知表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) DEFAULT NULL COMMENT '表单ID',
            `submission_id` int(11) DEFAULT NULL COMMENT '提交ID',
            `type` varchar(50) NOT NULL COMMENT '通知类型',
            `recipient` varchar(200) NOT NULL COMMENT '接收者',
            `subject` varchar(300) NOT NULL COMMENT '主题',
            `message` text NOT NULL COMMENT '消息内容',
            `status` varchar(20) DEFAULT 'pending' COMMENT '发送状态',
            `sent_time` int(11) DEFAULT 0 COMMENT '发送时间',
            `created_time` int(11) NOT NULL COMMENT '创建时间',
            `error_message` text COMMENT '错误信息',
            `is_read` tinyint(1) DEFAULT 0 COMMENT '是否已读',
            `read_time` int(11) DEFAULT 0 COMMENT '阅读时间',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `submission_id` (`submission_id`),
            KEY `status` (`status`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='通知记录表';";
        $db->query($sql);

        // 日历表 - 修复DESC字段问题
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_calendar` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) DEFAULT NULL COMMENT '关联表单ID',
            `title` varchar(200) NOT NULL COMMENT '事件标题',
            `start_time` int(11) NOT NULL COMMENT '开始时间',
            `end_time` int(11) DEFAULT NULL COMMENT '结束时间',
            `all_day` tinyint(1) DEFAULT 0 COMMENT '是否全天',
            `status` varchar(20) DEFAULT 'available' COMMENT '状态',
            `color` varchar(20) DEFAULT '#3788d8' COMMENT '颜色',
            `event_description` text COMMENT '描述',
            `author_id` int(11) NOT NULL COMMENT '创建者ID',
            `form_title` varchar(200) DEFAULT NULL COMMENT '表单标题',
            `created_time` int(11) NOT NULL COMMENT '创建时间',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `start_time` (`start_time`),
            KEY `status` (`status`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='日历事件表';";
        $db->query($sql);

        // 统计表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_stats` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) NOT NULL COMMENT '表单ID',
            `ip` varchar(45) NOT NULL COMMENT 'IP地址',
            `user_agent` varchar(500) DEFAULT NULL COMMENT '用户代理',
            `action` varchar(50) NOT NULL COMMENT '动作',
            `data` text COMMENT '附加数据',
            `created_time` int(11) NOT NULL COMMENT '时间',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `action` (`action`),
            KEY `created_time` (`created_time`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='统计数据表';";
        $db->query($sql);

        // 系统通知表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_system_notifications` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) DEFAULT NULL COMMENT '表单ID',
            `submission_id` int(11) DEFAULT NULL COMMENT '提交ID',
            `type` varchar(50) NOT NULL COMMENT '通知类型',
            `title` varchar(200) NOT NULL COMMENT '标题',
            `message` text NOT NULL COMMENT '消息',
            `data` text COMMENT '附加数据',
            `is_read` tinyint(1) DEFAULT 0 COMMENT '是否已读',
            `read_time` int(11) DEFAULT 0 COMMENT '阅读时间',
            `created_time` int(11) NOT NULL COMMENT '创建时间',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `type` (`type`),
            KEY `is_read` (`is_read`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='系统通知表';";
        $db->query($sql);

        // 垃圾内容日志表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_spam_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) NOT NULL COMMENT '表单ID',
            `ip` varchar(45) NOT NULL COMMENT 'IP地址',
            `reason` varchar(100) NOT NULL COMMENT '拦截原因',
            `data` text COMMENT '提交数据',
            `created_time` int(11) NOT NULL COMMENT '时间',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `ip` (`ip`),
            KEY `reason` (`reason`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='垃圾内容日志表';";
        $db->query($sql);
        
        // Webhooks表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_webhooks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `form_id` int(11) DEFAULT NULL COMMENT '关联表单ID',
            `event_type` varchar(50) NOT NULL COMMENT '事件类型',
            `target_url` varchar(500) NOT NULL COMMENT '目标URL',
            `token` varchar(100) NOT NULL COMMENT '安全令牌',
            `status` varchar(20) DEFAULT 'active' COMMENT '状态',
            `created_time` int(11) NOT NULL COMMENT '创建时间',
            `modified_time` int(11) DEFAULT 0 COMMENT '修改时间',
            PRIMARY KEY (`id`),
            KEY `form_id` (`form_id`),
            KEY `status` (`status`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Webhooks表';";
        $db->query($sql);

        // Webhook日志表
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}uforms_webhook_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `webhook_id` int(11) NOT NULL COMMENT 'Webhook ID',
            `payload` longtext NOT NULL COMMENT '请求数据',
            `ip` varchar(45) NOT NULL COMMENT 'IP地址',
            `user_agent` varchar(500) DEFAULT NULL COMMENT '用户代理',
            `created_time` int(11) NOT NULL COMMENT '创建时间',
            PRIMARY KEY (`id`),
            KEY `webhook_id` (`webhook_id`),
            KEY `created_time` (`created_time`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Webhook日志表';";
        $db->query($sql);
    }
    
    /**
     * 更新数据库表结构
     */
    private static function updateTables()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        // 检查并添加 uforms_notifications 表中的 is_read 字段
        try {
            $db->query("ALTER TABLE `{$prefix}uforms_notifications` ADD `is_read` TINYINT(1) DEFAULT 0 COMMENT '是否已读'");
        } catch (Exception $e) {
            // 字段可能已存在，忽略错误
        }
        
        // 检查并添加 uforms_notifications 表中的 read_time 字段
        try {
            $db->query("ALTER TABLE `{$prefix}uforms_notifications` ADD `read_time` INT(11) DEFAULT 0 COMMENT '阅读时间'");
        } catch (Exception $e) {
            // 字段可能已存在，忽略错误
        }
    }

    /**
     * 创建上传目录
     */
    private static function createUploadDirs()
    {
        $dirs = array(
            __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms',
            __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms/files',
            __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms/thumbs',
            __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms/temp'
        );
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception('无法创建目录: ' . $dir);
                }
            }
            
            // 创建.htaccess文件保护上传目录
            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Options -Indexes\nDeny from all\n<Files *.php>\nDeny from all\n</Files>");
            }
        }
    }

    /**
     * 设置默认配置选项
     */
    private static function setDefaultOptions()
    {
        $db = Typecho_Db::get();
        
        // 检查是否已经设置过
        $existing = $db->fetchRow(
            $db->select()->from('table.options')
               ->where('name = ?', 'plugin:Uforms')
        );
        
        if (!$existing) {
            $defaultConfig = array(
                'enable_forms' => 1,
                'enable_calendar' => 1,
                'enable_analytics' => 1,
                'enable_email' => 0,
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => '587',
                'upload_enabled' => 1,
                'upload_max_size' => '5',
                'allowed_file_types' => 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip',
                'enable_spam_filter' => 1,
                'rate_limit' => '3',
                'admin_per_page' => '20'
            );
            
            $db->query($db->insert('table.options')->rows(array(
                'name' => 'plugin:Uforms',
                'user' => 0,
                'value' => serialize($defaultConfig)
            )));
        }
    }

    /**
     * 解析短代码
     */
    public static function parseShortcode($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        
        // 解析 [uforms] 短代码
        $pattern = '/\[uforms\s+([^\]]*)\]/';
        $content = preg_replace_callback($pattern, function($matches) {
            $attributes = self::parseShortcodeAttributes($matches[1]);
            
            if (!empty($attributes['name'])) {
                require_once dirname(__FILE__) . '/frontend/front.php';
                return UformsFront::renderForm($attributes['name'], $attributes['template'] ?? 'default');
            } elseif (!empty($attributes['id'])) {
                require_once dirname(__FILE__) . '/core/UformsHelper.php';
                $form = UformsHelper::getForm($attributes['id']);
                if ($form) {
                    require_once dirname(__FILE__) . '/frontend/front.php';
                    return UformsFront::renderForm($form['name'], $attributes['template'] ?? 'default');
                }
            }
            
            return '<div class="uform-error">表单不存在</div>';
        }, $content);
        
        return $content;
    }

    /**
     * 解析短代码属性
     */
    private static function parseShortcodeAttributes($text)
    {
        $attributes = array();
        $pattern = '/(\w+)=["\']([^"\']*)["\']|(\w+)=(\S+)|(\w+)/';
        
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            if (!empty($match[1]) && !empty($match[2])) {
                // 有引号的属性
                $attributes[$match[1]] = $match[2];
            } elseif (!empty($match[3]) && !empty($match[4])) {
                // 无引号的属性
                $attributes[$match[3]] = $match[4];
            } elseif (!empty($match[5])) {
                // 布尔属性
                $attributes[$match[5]] = true;
            }
        }
        
        return $attributes;
    }
}
?>
