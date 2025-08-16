<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Uforms 核心辅助类 - 完整版本
 * 包含表单管理、创建、验证、提交等所有功能
 */
class UformsHelper
{
    private static $db = null;
    private static $cache = array();
    private static $pluginOptions = null;
    
    /**
     * 获取数据库实例
     */
    public static function getDb() {
        if (self::$db === null) {
            self::$db = Typecho_Db::get();
        }
        return self::$db;
    }
    
    /**
     * 获取插件配置
     */
    public static function getPluginOptions() {
        if (self::$pluginOptions === null) {
            self::$pluginOptions = Helper::options()->plugin('Uforms');
        }
        return self::$pluginOptions;
    }
    
    /**
     * 缓存机制
     */
    private static function getCache($key) {
        return isset(self::$cache[$key]) ? self::$cache[$key] : null;
    }
    
    private static function setCache($key, $value, $ttl = 300) {
        self::$cache[$key] = array(
            'value' => $value,
            'expires' => time() + $ttl
        );
    }
    
    private static function isCacheValid($key) {
        return isset(self::$cache[$key]) && self::$cache[$key]['expires'] > time();
    }
    
    /**
     * 获取表单列表 - 管理页面使用
     */
    public static function getForms($page = 1, $pageSize = 20, $status = null, $search = null) {
        $db = self::getDb();
        $select = $db->select('f.*')
                     ->from('table.uforms_forms f');
        
        // 搜索条件
        if ($search) {
            $select->where('f.title LIKE ? OR f.name LIKE ?', '%' . $search . '%', '%' . $search . '%');
        }
        
        // 状态筛选 - 支持草稿、已发布等状态
        if ($status) {
            $select->where('f.status = ?', $status);
        }
        
        // 分页
        $offset = ($page - 1) * $pageSize;
        $select->order('f.modified_time', Typecho_Db::SORT_DESC)
               ->limit($pageSize)
               ->offset($offset);
        
        $forms = $db->fetchAll($select);
        
        // 解析JSON配置
        foreach ($forms as &$form) {
            $form['config'] = json_decode($form['config'] ?? '{}', true);
            $form['settings'] = json_decode($form['settings'] ?? '{}', true);
        }
        
        return $forms;
    }
    
    /**
     * 获取表单总数
     */
    public static function getFormsCount($status = null, $search = null) {
        $cacheKey = "forms_count_{$status}_{$search}";
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        $select = $db->select('COUNT(*) AS count')->from('table.uforms_forms');
        
        if ($search) {
            $select->where('title LIKE ? OR name LIKE ?', '%' . $search . '%', '%' . $search . '%');
        }
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        $result = $db->fetchRow($select);
        $count = $result ? intval($result['count']) : 0;
        
        self::setCache($cacheKey, $count, 60);
        return $count;
    }
    
    /**
     * 根据ID获取表单
     */
    public static function getForm($id) {
        $cacheKey = "form_{$id}";
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        $form = $db->fetchRow($db->select()->from('table.uforms_forms')->where('id = ?', $id));
        
        if ($form) {
            // 解析JSON字段
            $form['config'] = json_decode($form['config'] ?? '{}', true);
            $form['settings'] = json_decode($form['settings'] ?? '{}', true);
        }
        
        self::setCache($cacheKey, $form, 300);
        return $form;
    }
    
    /**
     * 根据名称获取表单
     */
    public static function getFormByName($name) {
        $cacheKey = "form_name_{$name}";
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        $form = $db->fetchRow($db->select()->from('table.uforms_forms')->where('name = ?', $name));
        
        if ($form) {
            $form['config'] = json_decode($form['config'] ?? '{}', true);
            $form['settings'] = json_decode($form['settings'] ?? '{}', true);
        }
        
        self::setCache($cacheKey, $form, 300);
        return $form;
    }
    
    /**
     * 获取表单字段 - 创建页面和表单显示使用
     */
    public static function getFormFields($formId) {
        $cacheKey = "form_fields_{$formId}";
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        $fields = $db->fetchAll(
            $db->select()->from('table.uforms_fields')
               ->where('form_id = ?', $formId)
               ->order('sort_order', Typecho_Db::SORT_ASC)
        );
        
        // 解析字段配置
        foreach ($fields as &$field) {
            $field['field_config'] = json_decode($field['field_config'] ?? '{}', true);
        }
        
        self::setCache($cacheKey, $fields, 300);
        return $fields;
    }
    
    /**
     * 创建表单 - 创建页面使用
     */
    public static function createForm($data) {
        $db = self::getDb();
        $user = Typecho_Widget::widget('Widget_User');
        
        $formData = array(
            'name' => $data['name'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'config' => is_array($data['config']) ? json_encode($data['config']) : ($data['config'] ?? '{}'),
            'settings' => is_array($data['settings']) ? json_encode($data['settings']) : ($data['settings'] ?? '{}'),
            'status' => $data['status'] ?? 'draft', // 默认为草稿状态
            'author_id' => $user->uid,
            'view_count' => 0,
            'submit_count' => 0,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        $formId = $db->query($db->insert('table.uforms_forms')->rows($formData));
        
        // 清除相关缓存
        self::clearFormsCache();
        
        return $formId;
    }
    
    /**
     * 更新表单 - 创建页面编辑使用
     */
    public static function updateForm($id, $data) {
        $db = self::getDb();
        
        $formData = array(
            'name' => $data['name'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'config' => is_array($data['config']) ? json_encode($data['config']) : ($data['config'] ?? '{}'),
            'settings' => is_array($data['settings']) ? json_encode($data['settings']) : ($data['settings'] ?? '{}'),
            'status' => $data['status'] ?? 'draft',
            'modified_time' => time()
        );
        
        $result = $db->query($db->update('table.uforms_forms')->rows($formData)->where('id = ?', $id));
        
        // 清除缓存
        unset(self::$cache["form_{$id}"]);
        self::clearFormsCache();
        
        return $result;
    }
    
    /**
     * 发布表单 - 将草稿状态改为已发布
     */
    public static function publishForm($id) {
        $db = self::getDb();
        
        $result = $db->query($db->update('table.uforms_forms')
                                ->rows(array('status' => 'published', 'modified_time' => time()))
                                ->where('id = ?', $id));
        
        // 清除缓存
        unset(self::$cache["form_{$id}"]);
        self::clearFormsCache();
        
        return $result;
    }
    
    /**
     * 将表单状态改为草稿
     */
    public static function unpublishForm($id) {
        $db = self::getDb();
        
        $result = $db->query($db->update('table.uforms_forms')
                                ->rows(array('status' => 'draft', 'modified_time' => time()))
                                ->where('id = ?', $id));
        
        // 清除缓存
        unset(self::$cache["form_{$id}"]);
        self::clearFormsCache();
        
        return $result;
    }
    
    /**
     * 删除表单 - 管理页面使用
     */
    public static function deleteForm($id) {
        $db = self::getDb();
        
        try {
            // 开始事务
            $db->query('START TRANSACTION');
            
            // 获取提交记录中的文件
            $submissions = $db->fetchAll($db->select()->from('table.uforms_submissions')->where('form_id = ?', $id));
            
            // 删除相关文件
            foreach ($submissions as $submission) {
                $files = $db->fetchAll($db->select()->from('table.uforms_files')->where('submission_id = ?', $submission['id']));
                foreach ($files as $file) {
                    if (file_exists($file['file_path'])) {
                        unlink($file['file_path']);
                    }
                }
            }
            
            // 删除数据库记录
            $db->query($db->delete('table.uforms_forms')->where('id = ?', $id));
            $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_notifications')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_files')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_calendar')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_stats')->where('form_id = ?', $id));
            $db->query($db->delete('table.uforms_webhooks')->where('form_id = ?', $id));
            
            // 提交事务
            $db->query('COMMIT');
            
            // 清除缓存
            unset(self::$cache["form_{$id}"]);
            self::clearFormsCache();
            
            return true;
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }


        /**
     * 保存表单字段 - 创建页面拖拽后保存使用
     */
    public static function saveFormFields($formId, $fields) {
        $db = self::getDb();
        
        try {
            // 开始事务
            $db->query('START TRANSACTION');
            
            // 删除现有字段
            $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $formId));
            
            // 插入新字段
            if (is_array($fields)) {
                foreach ($fields as $index => $field) {
                    if (!isset($field['name']) || !isset($field['type'])) {
                        continue;
                    }
                    
                    $fieldData = array(
                        'form_id' => $formId,
                        'field_type' => $field['type'],
                        'field_name' => $field['name'],
                        'field_label' => $field['label'] ?? '',
                        'field_config' => json_encode($field),
                        'sort_order' => $field['sortOrder'] ?? $index,
                        'is_required' => !empty($field['required']) ? 1 : 0,
                        'created_time' => time()
                    );
                    
                    $db->query($db->insert('table.uforms_fields')->rows($fieldData));
                }
            }
            
            // 提交事务
            $db->query('COMMIT');
            
            // 清除缓存
            unset(self::$cache["form_fields_{$formId}"]);
            
            return true;
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * 获取字段库配置 - 创建页面左侧字段库使用
     */
    public static function getFieldLibrary() {
        return array(
            'basic' => array(
                'title' => '基础字段',
                'fields' => array(
                    array(
                        'type' => 'text',
                        'name' => '单行文本',
                        'icon' => 'text',
                        'description' => '单行文本输入框',
                        'config' => array(
                            'placeholder' => '',
                            'maxLength' => 255,
                            'required' => false,
                            'pattern' => '',
                            'errorMessage' => ''
                        )
                    ),
                    array(
                        'type' => 'textarea',
                        'name' => '多行文本',
                        'icon' => 'textarea',
                        'description' => '多行文本输入框',
                        'config' => array(
                            'placeholder' => '',
                            'rows' => 4,
                            'maxLength' => 1000,
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'email',
                        'name' => '邮箱',
                        'icon' => 'email',
                        'description' => '邮箱地址输入',
                        'config' => array(
                            'placeholder' => '请输入邮箱地址',
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'tel',
                        'name' => '电话',
                        'icon' => 'phone',
                        'description' => '电话号码输入',
                        'config' => array(
                            'placeholder' => '请输入电话号码',
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'url',
                        'name' => '网址',
                        'icon' => 'link',
                        'description' => 'URL地址输入',
                        'config' => array(
                            'placeholder' => 'https://',
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'password',
                        'name' => '密码',
                        'icon' => 'lock',
                        'description' => '密码输入框',
                        'config' => array(
                            'placeholder' => '请输入密码',
                            'minLength' => 6,
                            'required' => false
                        )
                    )
                )
            ),
            'advanced' => array(
                'title' => '高级字段',
                'fields' => array(
                    array(
                        'type' => 'number',
                        'name' => '数字',
                        'icon' => 'number',
                        'description' => '数字输入框',
                        'config' => array(
                            'placeholder' => '请输入数字',
                            'min' => null,
                            'max' => null,
                            'step' => 1,
                            'unit' => '',
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'decimal',
                        'name' => '小数',
                        'icon' => 'decimal',
                        'description' => '小数输入框',
                        'config' => array(
                            'placeholder' => '请输入小数',
                            'min' => null,
                            'max' => null,
                            'step' => 0.01,
                            'unit' => '',
                            'precision' => 2,
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'select',
                        'name' => '下拉选择',
                        'icon' => 'select',
                        'description' => '下拉选择框',
                        'config' => array(
                            'options' => array('选项1', '选项2', '选项3'),
                            'multiple' => false,
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'radio',
                        'name' => '单选按钮',
                        'icon' => 'radio',
                        'description' => '单选按钮组',
                        'config' => array(
                            'options' => array('选项1', '选项2', '选项3'),
                            'layout' => 'vertical',
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'checkbox',
                        'name' => '复选框',
                        'icon' => 'checkbox',
                        'description' => '复选框组',
                        'config' => array(
                            'options' => array('选项1', '选项2', '选项3'),
                            'layout' => 'vertical',
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'slider',
                        'name' => '滑块',
                        'icon' => 'slider',
                        'description' => '数值滑块',
                        'config' => array(
                            'min' => 0,
                            'max' => 100,
                            'step' => 1,
                            'defaultValue' => 50,
                            'showValue' => true,
                            'required' => false
                        )
                    )
                )
            ),
            'layout' => array(
                'title' => '布局',
                'fields' => array(
                    array(
                        'type' => 'column',
                        'name' => '分栏容器',
                        'icon' => 'columns',
                        'description' => '多栏布局容器',
                        'config' => array(
                            'columns' => 2,
                            'columnWidths' => array('50%', '50%'),
                            'gap' => '20px',
                            'padding' => '10px',
                            'background' => '',
                            'border' => ''
                        )
                    ),
                    array(
                        'type' => 'repeater',
                        'name' => '重复器',
                        'icon' => 'repeat',
                        'description' => '可重复的字段组',
                        'config' => array(
                            'min' => 1,
                            'max' => 10,
                            'addButtonText' => '添加项目',
                            'removeButtonText' => '删除',
                            'fields' => array()
                        )
                    ),
                    array(
                        'type' => 'divider',
                        'name' => '分隔线',
                        'icon' => 'divider',
                        'description' => '内容分隔线',
                        'config' => array(
                            'style' => 'solid',
                            'color' => '#e0e0e0',
                            'margin' => '20px 0'
                        )
                    ),
                    array(
                        'type' => 'html',
                        'name' => 'HTML内容',
                        'icon' => 'code',
                        'description' => '自定义HTML内容',
                        'config' => array(
                            'content' => '<p>HTML内容</p>'
                        )
                    )
                )
            ),
            'special' => array(
                'title' => '特殊字段',
                'fields' => array(
                    array(
                        'type' => 'file',
                        'name' => '文件上传',
                        'icon' => 'upload',
                        'description' => '文件上传功能',
                        'config' => array(
                            'accept' => 'image/*,application/pdf',
                            'maxSize' => 10,
                            'maxFiles' => 5,
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'date',
                        'name' => '日期选择',
                        'icon' => 'calendar',
                        'description' => '日期选择器',
                        'config' => array(
                            'format' => 'YYYY-MM-DD',
                            'minDate' => '',
                            'maxDate' => '',
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'time',
                        'name' => '时间选择',
                        'icon' => 'clock',
                        'description' => '时间选择器',
                        'config' => array(
                            'format' => 'HH:mm',
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'datetime',
                        'name' => '日期时间',
                        'icon' => 'datetime',
                        'description' => '日期时间选择器',
                        'config' => array(
                            'format' => 'YYYY-MM-DD HH:mm',
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'calendar',
                        'name' => '日历预约',
                        'icon' => 'calendar-check',
                        'description' => '日历时间段预约',
                        'config' => array(
                            'view' => 'month',
                            'timeSlots' => array(),
                            'blackoutDates' => array(),
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'tags',
                        'name' => '标签选择',
                        'icon' => 'tags',
                        'description' => '标签选择器',
                        'config' => array(
                            'options' => array('标签1', '标签2', '标签3'),
                            'allowCustom' => true,
                            'maxTags' => 5,
                            'required' => false
                        )
                    ),
                    array(
                        'type' => 'cascade',
                        'name' => '级联选择',
                        'icon' => 'cascade',
                        'description' => '级联下拉选择',
                        'config' => array(
                            'levels' => 2,
                            'options' => array(),
                            'required' => false
                        )
                    )
                )
            ),
            'system' => array(
                'title' => '系统集成',
                'fields' => array(
                    array(
                        'type' => 'user_info',
                        'name' => '用户信息',
                        'icon' => 'user',
                        'description' => 'Typecho用户信息',
                        'config' => array(
                            'fields' => array('uid', 'name', 'mail'),
                            'readonly' => true
                        )
                    ),
                    array(
                        'type' => 'captcha',
                        'name' => '验证码',
                        'icon' => 'shield',
                        'description' => '图形验证码',
                        'config' => array(
                            'width' => 120,
                            'height' => 40,
                            'length' => 4,
                            'required' => true
                        )
                    ),
                    array(
                        'type' => 'honeypot',
                        'name' => '蜜罐字段',
                        'icon' => 'eye-slash',
                        'description' => '反垃圾蜜罐字段（用户不可见）',
                        'config' => array(
                            'name' => 'hp_' . uniqid()
                        )
                    )
                )
            )
        );
    }
    
    /**
     * 获取表单URL
     */
    public static function getFormUrl($formId, $useId = true) {
        $options = Helper::options();
        
        if ($useId) {
            return $options->siteUrl . 'uforms/form/' . $formId;
        } else {
            $form = self::getForm($formId);
            if ($form) {
                return $options->siteUrl . 'uforms/form/' . $form['name'];
            }
        }
        
        return $options->siteUrl . 'uforms/form/' . $formId;
    }
    
    /**
     * 生成表单iframe代码
     */
    public static function getFormIframeCode($formId, $width = '100%', $height = '600px') {
        $url = self::getFormUrl($formId);
        return '<iframe src="' . $url . '" width="' . $width . '" height="' . $height . '" frameborder="0"></iframe>';
    }
    
    /**
     * 复制表单
     */
    public static function duplicateForm($formId, $newName = null, $newTitle = null) {
        $form = self::getForm($formId);
        if (!$form) {
            return false;
        }
        
        // 生成新的名称和标题
        if (!$newName) {
            $newName = $form['name'] . '_copy_' . time();
        }
        if (!$newTitle) {
            $newTitle = $form['title'] . ' (副本)';
        }
        
        // 创建新表单
        $newFormData = array(
            'name' => $newName,
            'title' => $newTitle,
            'description' => $form['description'],
            'config' => $form['config'],
            'settings' => $form['settings'],
            'status' => 'draft' // 复制的表单默认为草稿状态
        );
        
        $newFormId = self::createForm($newFormData);
        
        // 复制字段
        $fields = self::getFormFields($formId);
        if ($fields) {
            $fieldsData = array();
            foreach ($fields as $field) {
                $fieldsData[] = array(
                    'type' => $field['field_type'],
                    'name' => $field['field_name'],
                    'label' => $field['field_label'],
                    'config' => $field['field_config'],
                    'sortOrder' => $field['sort_order'],
                    'required' => $field['is_required']
                );
            }
            self::saveFormFields($newFormId, $fieldsData);
        }
        
        return $newFormId;
    }


        /**
     * 提交表单数据
     */
    public static function submitForm($formId, $data, $files = array()) {
        $db = self::getDb();
        $form = self::getForm($formId);
        
        if (!$form) {
            throw new Exception('表单不存在');
        }
        
        if ($form['status'] !== 'published') {
            throw new Exception('表单未发布');
        }
        
        try {
            // 开始事务
            $db->query('START TRANSACTION');
            
            // 验证表单数据
            $validationErrors = self::validateSubmissionData($formId, $data);
            if (!empty($validationErrors)) {
                throw new Exception('数据验证失败: ' . implode(', ', $validationErrors));
            }
            
            // 反垃圾检测
            if (self::isSpam($formId, $data)) {
                self::logSpam($formId, $data, 'Content detected as spam');
                throw new Exception('提交被识别为垃圾信息');
            }
            
            // 频率限制检测
            if (self::isRateLimited($formId)) {
                throw new Exception('提交过于频繁，请稍后再试');
            }
            
            // 保存提交记录
            $submissionData = array(
                'form_id' => $formId,
                'data' => json_encode($data),
                'ip' => self::getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'status' => 'new',
                'source' => 'web',
                'created_time' => time(),
                'modified_time' => time()
            );
            
            $submissionId = $db->query($db->insert('table.uforms_submissions')->rows($submissionData));
            
            // 处理文件上传
            if (!empty($files)) {
                self::processFileUploads($formId, $submissionId, $files);
            }
            
            // 更新表单提交计数
            $db->query($db->update('table.uforms_forms')
                          ->rows(array('submit_count' => new Typecho_Db_Query_Exception('`submit_count` + 1')))
                          ->where('id = ?', $formId));
            
            // 发送通知
            self::sendNotifications($formId, $submissionId, $data);
            
            // 触发Webhook
            self::triggerWebhooks($formId, $submissionId, $data);
            
            // 记录统计
            self::recordSubmissionStat($formId, 'submit', $data);
            
            // 提交事务
            $db->query('COMMIT');
            
            // 清除缓存
            self::clearFormsCache();
            
            return $submissionId;
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * 验证提交数据
     */
    public static function validateSubmissionData($formId, $data) {
        $fields = self::getFormFields($formId);
        $errors = array();
        
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            $fieldConfig = $field['field_config'];
            $value = isset($data[$fieldName]) ? $data[$fieldName] : '';
            
            // 必填验证
            if ($field['is_required'] && empty($value)) {
                $errors[] = "{$field['field_label']}是必填项";
                continue;
            }
            
            // 跳过空值的其他验证
            if (empty($value)) {
                continue;
            }
            
            // 使用单独的字段验证方法
            $fieldErrors = self::validateField($field['field_type'], $value, $fieldConfig);
            if (!empty($fieldErrors)) {
                foreach ($fieldErrors as $error) {
                    $errors[] = "{$field['field_label']}: {$error}";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * 验证单个字段
     */
    public static function validateField($type, $value, $config) {
        $errors = array();
        
        // 根据类型验证
        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = '邮箱格式不正确';
                }
                break;
                
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = 'URL格式不正确';
                }
                break;
                
            case 'number':
            case 'decimal':
                if (!is_numeric($value)) {
                    $errors[] = '必须是数字';
                } else {
                    $numValue = floatval($value);
                    if (isset($config['min']) && $numValue < $config['min']) {
                        $errors[] = "不能小于{$config['min']}";
                    }
                    if (isset($config['max']) && $numValue > $config['max']) {
                        $errors[] = "不能大于{$config['max']}";
                    }
                }
                break;
                
            case 'tel':
                if (!preg_match('/^[0-9\-\+\s\(\)]+$/', $value)) {
                    $errors[] = '电话号码格式不正确';
                }
                break;
                
            case 'text':
            case 'textarea':
                $length = mb_strlen($value, 'UTF-8');
                if (isset($config['minLength']) && $length < $config['minLength']) {
                    $errors[] = "长度不能少于{$config['minLength']}个字符";
                }
                if (isset($config['maxLength']) && $length > $config['maxLength']) {
                    $errors[] = "长度不能超过{$config['maxLength']}个字符";
                }
                if (!empty($config['pattern']) && !preg_match('/' . $config['pattern'] . '/', $value)) {
                    $message = $config['errorMessage'] ?? '格式不正确';
                    $errors[] = $message;
                }
                break;
                
            case 'password':
                $length = mb_strlen($value, 'UTF-8');
                if (isset($config['minLength']) && $length < $config['minLength']) {
                    $errors[] = "密码长度不能少于{$config['minLength']}个字符";
                }
                break;
                
            case 'file':
                // 文件验证在上传时处理
                break;
        }
        
        return $errors;
    }
    
    /**
     * 处理文件上传
     */
    public static function processFileUploads($formId, $submissionId, $files) {
        $options = self::getPluginOptions();
        
        if (!$options->upload_enabled) {
            throw new Exception('文件上传功能已禁用');
        }
        
        $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms/files/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $maxSize = intval($options->upload_max_size ?? 5) * 1024 * 1024; // MB to bytes
        $allowedTypes = explode(',', $options->allowed_file_types ?? 'jpg,png,pdf,doc,txt');
        $allowedTypes = array_map('trim', $allowedTypes);
        
        $db = self::getDb();
        
        foreach ($files as $fieldName => $fileArray) {
            if (!is_array($fileArray['name'])) {
                $fileArray = array(
                    'name' => array($fileArray['name']),
                    'type' => array($fileArray['type']),
                    'tmp_name' => array($fileArray['tmp_name']),
                    'error' => array($fileArray['error']),
                    'size' => array($fileArray['size'])
                );
            }
            
            for ($i = 0; $i < count($fileArray['name']); $i++) {
                if ($fileArray['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                $originalName = $fileArray['name'][$i];
                $tmpName = $fileArray['tmp_name'][$i];
                $fileSize = $fileArray['size'][$i];
                $fileType = $fileArray['type'][$i];
                
                // 验证文件大小
                if ($fileSize > $maxSize) {
                    throw new Exception("文件 {$originalName} 大小超过限制");
                }
                
                // 验证文件类型
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedTypes)) {
                    throw new Exception("文件 {$originalName} 类型不被允许");
                }
                
                // 生成唯一文件名
                $fileName = uniqid() . '_' . time() . '.' . $extension;
                $filePath = $uploadDir . $fileName;
                
                // 移动文件
                if (!move_uploaded_file($tmpName, $filePath)) {
                    throw new Exception("文件 {$originalName} 上传失败");
                }
                
                // 保存文件记录
                $fileData = array(
                    'form_id' => $formId,
                    'submission_id' => $submissionId,
                    'field_name' => $fieldName,
                    'original_name' => $originalName,
                    'filename' => $fileName,
                    'file_path' => $filePath,
                    'file_size' => $fileSize,
                    'file_type' => $fileType,
                    'uploaded_by' => self::getCurrentUserId(),
                    'created_time' => time()
                );
                
                $db->query($db->insert('table.uforms_files')->rows($fileData));
            }
        }
    }
    
    /**
     * 处理单个文件上传
     */
    public static function handleFileUpload($file, $field_config, $form_id) {
        $result = array(
            'success' => false,
            'message' => '',
            'file_path' => '',
            'file_url' => '',
            'file_id' => null
        );
        
        try {
            // 检查上传错误
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('文件上传失败：' . self::getUploadErrorMessage($file['error']));
            }
            
            // 检查文件大小
            $maxSize = ($field_config['maxSize'] ?? 10) * 1024 * 1024; // MB转字节
            if ($file['size'] > $maxSize) {
                throw new Exception('文件大小超过限制');
            }
            
            // 检查文件类型
            $allowedTypes = explode(',', $field_config['accept'] ?? 'jpg,png,pdf');
            $allowedTypes = array_map('trim', $allowedTypes);
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowedTypes)) {
                throw new Exception('不支持的文件类型');
            }
            
            // 创建上传目录
            $uploadDir = __TYPECHO_ROOT_DIR__ . '/usr/uploads/uforms/files/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 生成唯一文件名
            $fileName = time() . '_' . mt_rand(1000, 9999) . '.' . $extension;
            $filePath = $uploadDir . $fileName;
            
            // 移动文件
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('文件保存失败');
            }
            
            // 保存文件信息到数据库
            $db = self::getDb();
            $fileData = array(
                'form_id' => $form_id,
                'field_name' => $field_config['field_name'] ?? '',
                'original_name' => $file['name'],
                'filename' => $fileName,
                'file_path' => $filePath,
                'file_size' => $file['size'],
                'file_type' => $file['type'],
                'created_time' => time()
            );
            
            $file_id = $db->query($db->insert('table.uforms_files')->rows($fileData));
            
            $result['success'] = true;
            $result['file_path'] = $filePath;
            $result['file_url'] = Helper::options()->siteUrl . 'usr/uploads/uforms/files/' . $fileName;
            $result['file_id'] = $file_id;
            
        } catch (Exception $e) {
            $result['message'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 获取上传错误信息
     */
    private static function getUploadErrorMessage($error) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return '文件大小超过限制';
            case UPLOAD_ERR_PARTIAL:
                return '文件上传不完整';
            case UPLOAD_ERR_NO_FILE:
                return '未选择文件';
            case UPLOAD_ERR_NO_TMP_DIR:
                return '临时目录不存在';
            case UPLOAD_ERR_CANT_WRITE:
                return '文件写入失败';
            case UPLOAD_ERR_EXTENSION:
                return '文件上传被扩展阻止';
            default:
                return '未知上传错误';
        }
    }


        /**
     * 反垃圾检测
     */
    public static function isSpam($formId, $data) {
        $options = self::getPluginOptions();
        
        if (!$options->enable_spam_filter) {
            return false;
        }
        
        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 检查IP黑名单
        if (self::isBlacklistedIP($ip)) {
            return true;
        }
        
        // 检查关键词过滤
        $content = implode(' ', array_values($data));
        if (self::containsSpamKeywords($content)) {
            return true;
        }
        
        // 检查提交频率
        if (self::isSubmittingTooFast($ip)) {
            return true;
        }
        
        // 检查蜜罐字段
        if (isset($data['honeypot']) && !empty($data['honeypot'])) {
            return true;
        }
        
        // 检查用户代理
        if (self::isSuspiciousUserAgent($userAgent)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 记录垃圾信息
     */
    public static function logSpam($formId, $data, $reason) {
        $db = self::getDb();
        
        $logData = array(
            'form_id' => $formId,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'reason' => $reason,
            'data' => json_encode($data),
            'created_time' => time()
        );
        
        return $db->query($db->insert('table.uforms_spam_log')->rows($logData));
    }
    
    /**
     * 频率限制检测
     */
    public static function isRateLimited($formId) {
        $options = self::getPluginOptions();
        $rateLimit = intval($options->rate_limit ?? 3);
        
        if ($rateLimit <= 0) {
            return false;
        }
        
        $db = self::getDb();
        $ip = self::getClientIP();
        $timeWindow = time() - 60; // 1分钟内
        
        $count = $db->fetchObject($db->select('COUNT(*) AS count')
                                     ->from('table.uforms_submissions')
                                     ->where('form_id = ? AND ip = ? AND created_time > ?', $formId, $ip, $timeWindow));
        
        return $count && $count->count >= $rateLimit;
    }
    
    /**
     * 检查提交频率
     */
    public static function checkSubmissionRate($form_id, $ip, $limit = 3, $timeframe = 3600) {
        $db = self::getDb();
        $since = time() - $timeframe;
        
        $count = $db->fetchObject(
            $db->select('COUNT(*) as count')
               ->from('table.uforms_submissions')
               ->where('form_id = ? AND ip = ? AND created_time > ?', $form_id, $ip, $since)
        )->count;
        
        return $count < $limit;
    }
    
    /**
     * 检查垃圾内容
     */
    public static function isSpamContent($data, $settings = array()) {
        // 简单的垃圾内容检测
        $spamKeywords = array('viagra', 'casino', 'poker', 'loan', 'insurance', 'mortgage');
        
        foreach ($data as $value) {
            if (is_string($value)) {
                foreach ($spamKeywords as $keyword) {
                    if (stripos($value, $keyword) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * 发送通知
     */
    public static function sendNotifications($formId, $submissionId, $data) {
        $form = self::getForm($formId);
        $settings = $form['settings'] ?? array();
        
        // 发送管理员通知
        if (!empty($settings['adminNotification']['enabled'])) {
            self::sendAdminNotification($formId, $submissionId, $data, $settings['adminNotification']);
        }
        
        // 发送用户确认邮件
        if (!empty($settings['userNotification']['enabled'])) {
            self::sendUserNotification($formId, $submissionId, $data, $settings['userNotification']);
        }
        
        // 发送Slack通知
        if (!empty($settings['slackNotification']['enabled'])) {
            self::sendSlackNotification($formId, $submissionId, $data, $settings['slackNotification']);
        }
    }
    
    /**
     * 发送管理员通知
     */
    public static function sendAdminNotification($formId, $submissionId, $data, $config) {
        $form = self::getForm($formId);
        $recipients = array_filter(array_map('trim', explode(',', $config['recipients'] ?? '')));
        
        if (empty($recipients)) {
            return;
        }
        
        $subject = self::parseNotificationTemplate($config['subject'] ?? '新的表单提交', $form, $data);
        $message = self::parseNotificationTemplate($config['message'] ?? '', $form, $data);
        
        foreach ($recipients as $recipient) {
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                self::sendEmail($recipient, $subject, $message, $formId, $submissionId);
            }
        }
    }
    
    /**
     * 发送用户确认邮件
     */
    public static function sendUserNotification($formId, $submissionId, $data, $config) {
        $form = self::getForm($formId);
        $emailField = $config['emailField'] ?? '';
        
        if (empty($emailField) || empty($data[$emailField])) {
            return;
        }
        
        $userEmail = $data[$emailField];
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        
        $subject = self::parseNotificationTemplate($config['subject'] ?? '表单提交确认', $form, $data);
        $message = self::parseNotificationTemplate($config['message'] ?? '', $form, $data);
        
        self::sendEmail($userEmail, $subject, $message, $formId, $submissionId, 'user');
    }
    
    /**
     * 发送Slack通知
     */
    public static function sendSlackNotification($formId, $submissionId, $data, $config) {
        if (empty($config['webhookUrl'])) {
            return false;
        }
        
        $form = self::getForm($formId);
        $fieldsText = '';
        $fields = self::getFormFields($formId);
        
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            $fieldLabel = $field['field_label'];
            $value = isset($data[$fieldName]) ? $data[$fieldName] : '';
            
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            $fieldsText .= "*{$fieldLabel}:* {$value}\n";
        }
        
        $message = array(
            'text' => "新的表单提交: {$form['title']}",
            'attachments' => array(
                array(
                    'color' => 'good',
                    'fields' => array(
                        array(
                            'title' => '表单详情',
                            'value' => $fieldsText,
                            'short' => false
                        ),
                        array(
                            'title' => '提交时间',
                            'value' => date('Y-m-d H:i:s'),
                            'short' => true
                        ),
                        array(
                            'title' => 'IP地址',
                            'value' => self::getClientIP(),
                            'short' => true
                        )
                    )
                )
            )
        );
        
        return self::sendSlackMessage($config['webhookUrl'], $message);
    }
    
    /**
     * 发送Slack消息
     */
    public static function sendSlackMessage($webhook, $message) {
        $payload = json_encode($message);
        
        $ch = curl_init($webhook);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ));
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $http_code == 200;
    }
    
    /**
     * 解析通知模板
     */
    public static function parseNotificationTemplate($template, $form, $data) {
        $replacements = array(
            '{form_title}' => $form['title'],
            '{form_name}' => $form['name'],
            '{submit_time}' => date('Y-m-d H:i:s'),
            '{ip_address}' => self::getClientIP(),
            '{user_agent}' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        // 替换字段值
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $replacements['{' . $key . '}'] = $value;
        }
        
        // 生成所有字段列表
        $fieldsText = '';
        $fields = self::getFormFields($form['id']);
        foreach ($fields as $field) {
            $fieldName = $field['field_name'];
            $fieldLabel = $field['field_label'];
            $value = isset($data[$fieldName]) ? $data[$fieldName] : '';
            
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            $fieldsText .= "{$fieldLabel}: {$value}\n";
        }
        $replacements['{all_fields}'] = $fieldsText;
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * 发送邮件
     */
    public static function sendEmail($to, $subject, $message, $formId = null, $submissionId = null, $type = 'admin') {
        $options = self::getPluginOptions();
        
        if (!$options->enable_email) {
            return false;
        }
        
        $db = self::getDb();
        
        // 记录通知
        $notificationData = array(
            'form_id' => $formId,
            'submission_id' => $submissionId,
            'type' => $type,
            'recipient' => $to,
            'subject' => $subject,
            'message' => $message,
            'status' => 'pending',
            'created_time' => time()
        );
        
        $notificationId = $db->query($db->insert('table.uforms_notifications')->rows($notificationData));
        
        try {
            $sent = self::sendEmailViaPHP($to, $subject, $message);
            
            // 更新通知状态
            $updateData = array(
                'status' => $sent ? 'sent' : 'failed',
                'sent_time' => time()
            );
            
            if (!$sent) {
                $updateData['error_message'] = 'Failed to send email';
            }
            
            $db->query($db->update('table.uforms_notifications')
                          ->rows($updateData)
                          ->where('id = ?', $notificationId));
            
            return $sent;
            
        } catch (Exception $e) {
            // 更新错误状态
            $db->query($db->update('table.uforms_notifications')
                          ->rows(array(
                              'status' => 'failed',
                              'error_message' => $e->getMessage()
                          ))
                          ->where('id = ?', $notificationId));
            
            return false;
        }
    }
    
    /**
     * 使用PHPMailer发送邮件
     */
    public static function sendEmailNotification($to, $subject, $message, $form_data = array()) {
        // 检查是否启用邮件功能
        $settings_result = self::getDb()->fetchRow(
            self::getDb()->select('value')->from('table.options')
               ->where('name = ?', 'plugin:Uforms')
        );
        
        if (!$settings_result) {
            return false;
        }
        
        $settings = unserialize($settings_result['value']);
        if (empty($settings['enable_email'])) {
            return false;
        }
        
        // 使用PHPMailer发送邮件（如果存在）
        $phpMailerPath = dirname(__FILE__) . '/../lib/PHPMailer/PHPMailer.php';
        if (file_exists($phpMailerPath)) {
            require_once $phpMailerPath;
            require_once dirname(__FILE__) . '/../lib/PHPMailer/SMTP.php';
            require_once dirname(__FILE__) . '/../lib/PHPMailer/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            
            try {
                // SMTP配置
                if (!empty($settings['smtp_host'])) {
                    $mail->isSMTP();
                    $mail->Host = $settings['smtp_host'];
                    $mail->Port = $settings['smtp_port'] ?: 587;
                    $mail->SMTPAuth = true;
                    $mail->Username = $settings['smtp_username'];
                    $mail->Password = $settings['smtp_password'];
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }
                
                // 发件人和收件人
                $mail->setFrom($settings['smtp_username'], Helper::options()->title);
                $mail->addAddress($to);
                
                // 邮件内容
                $mail->isHTML(true);
                $mail->CharSet = 'UTF-8';
                $mail->Subject = $subject;
                $mail->Body = $message;
                
                return $mail->send();
            } catch (Exception $e) {
                error_log('邮件发送失败: ' . $mail->ErrorInfo);
                return false;
            }
        } else {
            // 使用原生mail函数
            return self::sendEmailViaPHP($to, $subject, $message);
        }
    }
    
    /**
     * 使用PHP mail()函数发送邮件
     */
    private static function sendEmailViaPHP($to, $subject, $message) {
        $headers = array(
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . Helper::options()->title . ' <noreply@' . $_SERVER['HTTP_HOST'] . '>',
            'Reply-To: noreply@' . $_SERVER['HTTP_HOST'],
            'X-Mailer: PHP/' . phpversion()
        );
        
        return mail($to, $subject, nl2br($message), implode("\r\n", $headers));
    }
    
    /**
     * 触发Webhooks
     */
    public static function triggerWebhooks($formId, $submissionId, $data) {
        $db = self::getDb();
        $webhooks = $db->fetchAll($db->select()->from('table.uforms_webhooks')
                                     ->where('form_id = ? OR form_id IS NULL', $formId)
                                     ->where('status = ?', 'active'));
        
        foreach ($webhooks as $webhook) {
            self::sendWebhook($webhook, $formId, $submissionId, $data);
        }
    }
    
    /**
     * 发送Webhook
     */
    public static function sendWebhook($webhook, $formId, $submissionId, $data) {
        $form = self::getForm($formId);
        
        $payload = array(
            'event' => 'form_submitted',
            'form_id' => $formId,
            'form_name' => $form['name'],
            'form_title' => $form['title'],
            'submission_id' => $submissionId,
            'data' => $data,
            'timestamp' => time(),
            'ip' => self::getClientIP()
        );
        
        $jsonPayload = json_encode($payload);
        
        // 创建签名
        $signature = hash_hmac('sha256', $jsonPayload, $webhook['token']);
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $webhook['target_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-Uforms-Signature: sha256=' . $signature,
                'User-Agent: Uforms/2.0'
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true
        ));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // 记录Webhook日志
        $db = self::getDb();
        $logData = array(
            'webhook_id' => $webhook['id'],
            'payload' => $jsonPayload,
            'response' => $response,
            'http_code' => $httpCode,
            'error' => $error,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_time' => time()
        );
        
        $db->query($db->insert('table.uforms_webhook_logs')->rows($logData));
        
        return $httpCode >= 200 && $httpCode < 300;
    }


        /**
     * 获取表单提交数据
     */
    public static function getSubmissions($form_id = null, $limit = 20, $offset = 0, $status = null, $search = null) {
        $db = self::getDb();
        $select = $db->select('*')->from('table.uforms_submissions');
        
        if ($form_id) {
            $select->where('form_id = ?', $form_id);
        }
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        if ($search) {
            $select->where('data LIKE ?', '%' . $search . '%');
        }
        
        $select->order('created_time', Typecho_Db::SORT_DESC)->limit($limit)->offset($offset);
        
        $submissions = $db->fetchAll($select);
        
        // 解析JSON数据
        foreach ($submissions as &$submission) {
            $submission['data'] = json_decode($submission['data'] ?? '{}', true);
        }
        
        return $submissions;
    }
    
    /**
     * 获取提交数量
     */
    public static function getSubmissionsCount($form_id = null, $status = null, $search = null) {
        $db = self::getDb();
        $select = $db->select('COUNT(*) AS count')->from('table.uforms_submissions');
        
        if ($form_id) {
            $select->where('form_id = ?', $form_id);
        }
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        if ($search) {
            $select->where('data LIKE ?', '%' . $search . '%');
        }
        
        $result = $db->fetchRow($select);
        return $result ? intval($result['count']) : 0;
    }
    
    /**
     * 获取单个提交记录
     */
    public static function getSubmission($id) {
        $db = self::getDb();
        $submission = $db->fetchRow($db->select()->from('table.uforms_submissions')->where('id = ?', $id));
        
        if ($submission) {
            $submission['data'] = json_decode($submission['data'] ?? '{}', true);
            
            // 获取相关文件
            $files = $db->fetchAll($db->select()->from('table.uforms_files')->where('submission_id = ?', $id));
            $submission['files'] = $files;
        }
        
        return $submission;
    }
    
    /**
     * 更新提交状态
     */
    public static function updateSubmissionStatus($id, $status) {
        $db = self::getDb();
        
        $validStatuses = array('new', 'read', 'replied', 'spam', 'deleted');
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        return $db->query($db->update('table.uforms_submissions')
                             ->rows(array('status' => $status, 'modified_time' => time()))
                             ->where('id = ?', $id));
    }
    
    /**
     * 删除提交记录
     */
    public static function deleteSubmission($id) {
        $db = self::getDb();
        
        try {
            // 开始事务
            $db->query('START TRANSACTION');
            
            // 获取并删除相关文件
            $files = $db->fetchAll($db->select()->from('table.uforms_files')->where('submission_id = ?', $id));
            foreach ($files as $file) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
            }
            
            // 删除数据库记录
            $db->query($db->delete('table.uforms_files')->where('submission_id = ?', $id));
            $db->query($db->delete('table.uforms_submissions')->where('id = ?', $id));
            $db->query($db->delete('table.uforms_notifications')->where('submission_id = ?', $id));
            
            // 提交事务
            $db->query('COMMIT');
            
            return true;
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * 批量删除提交记录
     */
    public static function batchDeleteSubmissions($ids) {
        if (empty($ids) || !is_array($ids)) {
            return false;
        }
        
        $db = self::getDb();
        
        try {
            $db->query('START TRANSACTION');
            
            foreach ($ids as $id) {
                self::deleteSubmission($id);
            }
            
            $db->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * 导出数据为CSV
     */
    public static function exportToCSV($formId, $startDate = null, $endDate = null) {
        $form = self::getForm($formId);
        if (!$form) {
            return false;
        }
        
        $fields = self::getFormFields($formId);
        $submissions = self::getSubmissions($formId, 1000); // 限制1000条
        
        // 创建临时文件
        $tempFile = tempnam(sys_get_temp_dir(), 'uforms_export_');
        $handle = fopen($tempFile, 'w');
        
        // 写入UTF-8 BOM
        fwrite($handle, "\xEF\xBB\xBF");
        
        // 写入表头
        $headers = array('提交时间', 'IP地址', '状态');
        foreach ($fields as $field) {
            $headers[] = $field['field_label'];
        }
        fputcsv($handle, $headers);
        
        // 写入数据
        foreach ($submissions as $submission) {
            $row = array(
                date('Y-m-d H:i:s', $submission['created_time']),
                $submission['ip'],
                $submission['status']
            );
            
            foreach ($fields as $field) {
                $fieldName = $field['field_name'];
                $value = isset($submission['data'][$fieldName]) ? $submission['data'][$fieldName] : '';
                
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                
                $row[] = $value;
            }
            
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        return $tempFile;
    }
    
    /**
     * 导出数据为PDF
     */
    public static function exportToPDF($formId, $startDate = null, $endDate = null) {
        // 这里可以集成PDF生成库，如TCPDF
        // 暂时返回false表示未实现
        return false;
    }
    
    /**
     * 获取统计数据
     */
    public static function getStats() {
        $cacheKey = 'uforms_stats';
        
        if (self::isCacheValid($cacheKey)) {
            return self::getCache($cacheKey)['value'];
        }
        
        $db = self::getDb();
        
        // 总表单数
        $totalForms = $db->fetchObject($db->select('COUNT(*) AS count')->from('table.uforms_forms'));
        
        // 已发布表单数
        $publishedForms = $db->fetchObject($db->select('COUNT(*) AS count')
                                              ->from('table.uforms_forms')
                                              ->where('status = ?', 'published'));
        
        // 草稿表单数
        $draftForms = $db->fetchObject($db->select('COUNT(*) AS count')
                                          ->from('table.uforms_forms')
                                          ->where('status = ?', 'draft'));
        
        // 总提交数
        $totalSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')->from('table.uforms_submissions'));
        
        // 未读提交数
        $newSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                              ->from('table.uforms_submissions')
                                              ->where('status = ?', 'new'));
        
        // 今日提交数
        $todaySubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_submissions')
                                                ->where('created_time >= ?', strtotime('today')));
        
        // 本周提交数
        $weekSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                               ->from('table.uforms_submissions')
                                               ->where('created_time >= ?', strtotime('monday this week')));
        
        // 本月提交数
        $monthSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_submissions')
                                                ->where('created_time >= ?', strtotime('first day of this month')));
        
        // 垃圾信息数
        $spamSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                               ->from('table.uforms_submissions')
                                               ->where('status = ?', 'spam'));
        
        $stats = array(
            'total_forms' => $totalForms ? $totalForms->count : 0,
            'published_forms' => $publishedForms ? $publishedForms->count : 0,
            'draft_forms' => $draftForms ? $draftForms->count : 0,
            'total_submissions' => $totalSubmissions ? $totalSubmissions->count : 0,
            'new_submissions' => $newSubmissions ? $newSubmissions->count : 0,
            'today_submissions' => $todaySubmissions ? $todaySubmissions->count : 0,
            'week_submissions' => $weekSubmissions ? $weekSubmissions->count : 0,
            'month_submissions' => $monthSubmissions ? $monthSubmissions->count : 0,
            'spam_submissions' => $spamSubmissions ? $spamSubmissions->count : 0
        );
        
        self::setCache($cacheKey, $stats, 60);
        return $stats;
    }
    
    /**
     * 获取表单统计图表数据
     */
    public static function getFormChartData($formId, $days = 30) {
        $db = self::getDb();
        $startDate = strtotime("-{$days} days");
        
        $data = array();
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dayStart = strtotime($date);
            $dayEnd = $dayStart + 86400; // 24小时
            
            $count = $db->fetchObject($db->select('COUNT(*) AS count')
                                         ->from('table.uforms_submissions')
                                         ->where('form_id = ? AND created_time >= ? AND created_time < ?', 
                                                $formId, $dayStart, $dayEnd));
            
            $data[] = array(
                'date' => $date,
                'count' => $count ? intval($count->count) : 0
            );
        }
        
        return $data;
    }
    
    /**
     * 记录提交统计
     */
    public static function recordSubmissionStat($form_id, $action = 'submit', $data = null) {
        $db = self::getDb();
        
        $stat_data = array(
            'form_id' => $form_id,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'action' => $action,
            'data' => $data ? json_encode($data) : null,
            'created_time' => time()
        );
        
        return $db->query($db->insert('table.uforms_stats')->rows($stat_data));
    }
    
    /**
     * 清理过期数据
     */
    public static function cleanupExpiredData($days = 365) {
        $db = self::getDb();
        $expire_time = time() - ($days * 24 * 60 * 60);
        
        try {
            $db->query('START TRANSACTION');
            
            // 删除过期的提交数据
            $expired_submissions = $db->fetchAll(
                $db->select('id')->from('table.uforms_submissions')
                   ->where('created_time < ? AND status = ?', $expire_time, 'deleted')
            );
            
            foreach ($expired_submissions as $submission) {
                // 删除相关文件
                $files = $db->fetchAll(
                    $db->select('file_path')->from('table.uforms_files')
                       ->where('submission_id = ?', $submission['id'])
                );
                
                foreach ($files as $file) {
                    if (file_exists($file['file_path'])) {
                        unlink($file['file_path']);
                    }
                }
                
                // 删除文件记录
                $db->query($db->delete('table.uforms_files')->where('submission_id = ?', $submission['id']));
            }
            
            // 删除过期提交记录
            $db->query(
                $db->delete('table.uforms_submissions')
                   ->where('created_time < ? AND status = ?', $expire_time, 'deleted')
            );
            
            // 删除过期通知记录
            $db->query(
                $db->delete('table.uforms_notifications')
                   ->where('created_time < ?', $expire_time)
            );
            
            // 删除过期垃圾日志
            $db->query(
                $db->delete('table.uforms_spam_log')
                   ->where('created_time < ?', $expire_time)
            );
            
            // 删除过期统计记录
            $db->query(
                $db->delete('table.uforms_stats')
                   ->where('created_time < ?', $expire_time)
            );
            
            $db->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    }


        /**
     * 获取客户端IP
     */
    public static function getClientIP() {
        $ipKeys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                       'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                                   FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * 获取当前用户ID
     */
    public static function getCurrentUserId() {
        $user = Typecho_Widget::widget('Widget_User');
        return $user->hasLogin() ? $user->uid : null;
    }
    
    /**
     * 清除缓存
     */
    public static function clearFormsCache() {
        $keysToRemove = array();
        foreach (array_keys(self::$cache) as $key) {
            if (strpos($key, 'forms_') === 0 || strpos($key, 'form_') === 0 || $key === 'uforms_stats') {
                $keysToRemove[] = $key;
            }
        }
        
        foreach ($keysToRemove as $key) {
            unset(self::$cache[$key]);
        }
    }
    
    /**
     * 清除所有缓存
     */
    public static function clearAllCache() {
        self::$cache = array();
    }
    
    /**
     * 格式化时间
     */
    public static function formatTime($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * 格式化相对时间
     */
    public static function timeAgo($timestamp) {
        $time = time() - $timestamp;
        
        if ($time < 60) {
            return '刚刚';
        } elseif ($time < 3600) {
            return floor($time / 60) . '分钟前';
        } elseif ($time < 86400) {
            return floor($time / 3600) . '小时前';
        } elseif ($time < 2592000) {
            return floor($time / 86400) . '天前';
        } else {
            return date('Y-m-d', $timestamp);
        }
    }
    
    /**
     * 生成随机字符串
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * 安全的文件名
     */
    public static function sanitizeFilename($filename) {
        // 移除危险字符
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // 限制长度
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }
        
        return $filename;
    }
    
    /**
     * 格式化文件大小
     */
    public static function formatFileSize($bytes) {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    /**
     * 检查文件类型
     */
    public static function isAllowedFileType($filename, $allowed_types = array()) {
        if (empty($allowed_types)) {
            $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt');
        }
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowed_types);
    }
    
    /**
     * 生成缩略图
     */
    public static function generateThumbnail($source_path, $thumb_path, $max_width = 200, $max_height = 200) {
        if (!file_exists($source_path)) {
            return false;
        }
        
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return false;
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        $mime = $image_info['mime'];
        
        // 计算缩略图尺寸
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = $width * $ratio;
        $new_height = $height * $ratio;
        
        // 创建画布
        $thumb = imagecreatetruecolor($new_width, $new_height);
        
        // 根据图片类型处理
        switch ($mime) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source = imagecreatefrompng($source_path);
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }
        
        // 生成缩略图
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        // 保存缩略图
        $result = false;
        switch ($mime) {
            case 'image/jpeg':
                $result = imagejpeg($thumb, $thumb_path, 90);
                break;
            case 'image/png':
                $result = imagepng($thumb, $thumb_path);
                break;
            case 'image/gif':
                $result = imagegif($thumb, $thumb_path);
                break;
        }
        
        // 释放资源
        imagedestroy($source);
        imagedestroy($thumb);
        
        return $result;
    }
    
    /**
     * 辅助方法 - 检查IP黑名单
     */
    private static function isBlacklistedIP($ip) {
        $options = self::getPluginOptions();
        $blacklist = explode("\n", $options->ip_blacklist ?? '');
        $blacklist = array_map('trim', $blacklist);
        $blacklist = array_filter($blacklist);
        
        return in_array($ip, $blacklist);
    }
    
    /**
     * 辅助方法 - 检查垃圾关键词
     */
    private static function containsSpamKeywords($content) {
        $options = self::getPluginOptions();
        $keywords = explode("\n", $options->spam_keywords ?? '');
        $keywords = array_map('trim', $keywords);
        $keywords = array_filter($keywords);
        
        if (empty($keywords)) {
            // 默认垃圾关键词
            $keywords = array('viagra', 'casino', 'porn', 'xxx', 'gambling');
        }
        
        $content = strtolower($content);
        
        foreach ($keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 辅助方法 - 检查提交速度
     */
    private static function isSubmittingTooFast($ip) {
        $db = self::getDb();
        $recentSubmission = $db->fetchRow($db->select('created_time')
                                             ->from('table.uforms_submissions')
                                             ->where('ip = ?', $ip)
                                             ->order('created_time', Typecho_Db::SORT_DESC)
                                             ->limit(1));
        
        if ($recentSubmission && (time() - $recentSubmission['created_time']) < 10) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 辅助方法 - 检查可疑用户代理
     */
    private static function isSuspiciousUserAgent($userAgent) {
        $suspiciousAgents = array(
            'curl', 'wget', 'python', 'bot', 'crawler', 'scraper'
        );
        
        $userAgent = strtolower($userAgent);
        
        foreach ($suspiciousAgents as $agent) {
            if (strpos($userAgent, $agent) !== false) {
                return true;
            }
        }
        
        return false;
    }
}
?>
