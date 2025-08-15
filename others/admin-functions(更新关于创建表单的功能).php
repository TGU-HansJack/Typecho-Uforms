<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Uforms 后台功能函数库
 */

// 权限验证函数
function uforms_check_permission($permission = 'administrator') {
    $user = Typecho_Widget::widget('Widget_User');
    
    if (!$user->hasLogin()) {
        return false;
    }
    
    // 检查用户权限
    switch ($permission) {
        case 'administrator':
            return $user->pass('administrator', true);
        case 'editor':
            return $user->pass('editor', true) || $user->pass('administrator', true);
        case 'contributor':
            return $user->pass('contributor', true) || $user->pass('editor', true) || $user->pass('administrator', true);
        default:
            return $user->hasLogin();
    }
}

// 获取当前用户
function uforms_get_current_user() {
    return Typecho_Widget::widget('Widget_User');
}

// 输出防护函数
function uforms_escape_html($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Uforms 核心辅助类
 */
class UformsHelper
{
    private static $db = null;
    
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
     * 获取表单列表
     */
    public static function getForms($page = 1, $pageSize = 20, $status = null, $search = null) {
        $db = self::getDb();
        $select = $db->select()->from('table.uforms_forms');
        
        // 搜索条件
        if ($search) {
            $select->where('title LIKE ? OR name LIKE ?', '%' . $search . '%', '%' . $search . '%');
        }
        
        // 状态筛选
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        // 分页
        $offset = ($page - 1) * $pageSize;
        $select->order('modified_time', Typecho_Db::SORT_DESC)
               ->limit($pageSize)
               ->offset($offset);
        
        return $db->fetchAll($select);
    }
    
    /**
     * 获取表单总数
     */
    public static function getFormsCount($status = null, $search = null) {
        $db = self::getDb();
        $select = $db->select('COUNT(*) AS count')->from('table.uforms_forms');
        
        if ($search) {
            $select->where('title LIKE ? OR name LIKE ?', '%' . $search . '%', '%' . $search . '%');
        }
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        $result = $db->fetchRow($select);
        return $result ? intval($result['count']) : 0;
    }
    
    /**
     * 根据ID获取表单
     */
    public static function getForm($id) {
        $db = self::getDb();
        return $db->fetchRow($db->select()->from('table.uforms_forms')->where('id = ?', $id));
    }
    
    /**
     * 根据名称获取表单
     */
    public static function getFormByName($name) {
        $db = self::getDb();
        return $db->fetchRow($db->select()->from('table.uforms_forms')->where('name = ?', $name));
    }
    
    /**
     * 获取表单字段
     */
    public static function getFormFields($formId) {
        $db = self::getDb();
        return $db->fetchAll(
            $db->select()->from('table.uforms_fields')
               ->where('form_id = ?', $formId)
               ->order('sort_order', Typecho_Db::SORT_ASC)
        );
    }
    
    /**
     * 创建表单
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
            'status' => $data['status'] ?? 'draft',
            'author_id' => $user->uid,
            'view_count' => 0,
            'submit_count' => 0,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        return $db->query($db->insert('table.uforms_forms')->rows($formData));
    }
    
    /**
     * 更新表单
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
        
        return $db->query($db->update('table.uforms_forms')->rows($formData)->where('id = ?', $id));
    }
    
    /**
     * 删除表单
     */
    public static function deleteForm($id) {
        $db = self::getDb();
        
        // 删除表单
        $db->query($db->delete('table.uforms_forms')->where('id = ?', $id));
        
        // 删除相关字段
        $db->query($db->delete('table.uforms_fields')->where('form_id = ?', $id));
        
        // 删除相关提交
        $db->query($db->delete('table.uforms_submissions')->where('form_id = ?', $id));
        
        // 删除相关通知
        $db->query($db->delete('table.uforms_notifications')->where('form_id = ?', $id));
        
        // 删除相关文件
        $db->query($db->delete('table.uforms_files')->where('form_id = ?', $id));
        
        // 删除日历事件
        $db->query($db->delete('table.uforms_calendar')->where('form_id = ?', $id));
        
        return true;
    }
    
    /**
     * 保存表单字段
     */
    public static function saveFormFields($formId, $fields) {
        $db = self::getDb();
        
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
        
        return true;
    }
    
    /**
     * 获取表单提交记录
     */
    public static function getSubmissions($formId, $page = 1, $pageSize = 20, $status = null) {
        $db = self::getDb();
        $select = $db->select()->from('table.uforms_submissions')->where('form_id = ?', $formId);
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        $offset = ($page - 1) * $pageSize;
        $select->order('created_time', Typecho_Db::SORT_DESC)
               ->limit($pageSize)
               ->offset($offset);
        
        return $db->fetchAll($select);
    }
    
    /**
     * 获取提交记录总数
     */
    public static function getSubmissionsCount($formId, $status = null) {
        $db = self::getDb();
        $select = $db->select('COUNT(*) AS count')
                     ->from('table.uforms_submissions')
                     ->where('form_id = ?', $formId);
        
        if ($status) {
            $select->where('status = ?', $status);
        }
        
        $result = $db->fetchRow($select);
        return $result ? intval($result['count']) : 0;
    }
    
    /**
     * 获取通知记录
     */
    public static function getNotifications($page = 1, $pageSize = 20, $isRead = null, $formId = null) {
        $db = self::getDb();
        $select = $db->select()->from('table.uforms_notifications');
        
        if ($isRead !== null) {
            $select->where('is_read = ?', $isRead ? 1 : 0);
        }
        
        if ($formId) {
            $select->where('form_id = ?', $formId);
        }
        
        $offset = ($page - 1) * $pageSize;
        $select->order('created_time', Typecho_Db::SORT_DESC)
               ->limit($pageSize)
               ->offset($offset);
        
        return $db->fetchAll($select);
    }
    
    /**
     * 获取通知记录总数
     */
    public static function getNotificationsCount($isRead = null, $formId = null) {
        $db = self::getDb();
        $select = $db->select('COUNT(*) AS count')->from('table.uforms_notifications');
        
        if ($isRead !== null) {
            $select->where('is_read = ?', $isRead ? 1 : 0);
        }
        
        if ($formId) {
            $select->where('form_id = ?', $formId);
        }
        
        $result = $db->fetchRow($select);
        return $result ? intval($result['count']) : 0;
    }
    
    /**
     * 标记通知为已读
     */
    public static function markNotificationAsRead($id) {
        $db = self::getDb();
        return $db->query($db->update('table.uforms_notifications')
                             ->rows(array('is_read' => 1, 'read_time' => time()))
                             ->where('id = ?', $id));
    }
    
    /**
     * 获取统计数据
     */
    public static function getStats() {
        $db = self::getDb();
        
        // 总表单数
        $totalForms = $db->fetchObject($db->select('COUNT(*) AS count')->from('table.uforms_forms'));
        
        // 已发布表单数
        $publishedForms = $db->fetchObject($db->select('COUNT(*) AS count')
                                              ->from('table.uforms_forms')
                                              ->where('status = ?', 'published'));
        
        // 总提交数
        $totalSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')->from('table.uforms_submissions'));
        
        // 未读通知数
        $newNotifications = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_notifications')
                                                ->where('is_read = ?', 0));
        
        // 今日提交数
        $todaySubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_submissions')
                                                ->where('created_time >= ?', strtotime('today')));
        
        // 本月提交数
        $monthSubmissions = $db->fetchObject($db->select('COUNT(*) AS count')
                                                ->from('table.uforms_submissions')
                                                ->where('created_time >= ?', strtotime('first day of this month')));
        
        return array(
            'total_forms' => $totalForms ? $totalForms->count : 0,
            'published_forms' => $publishedForms ? $publishedForms->count : 0,
            'total_submissions' => $totalSubmissions ? $totalSubmissions->count : 0,
            'new_submissions' => $totalSubmissions ? $totalSubmissions->count : 0, // 这里可以根据需要调整
            'new_notifications' => $newNotifications ? $newNotifications->count : 0,
            'today_submissions' => $todaySubmissions ? $todaySubmissions->count : 0,
            'month_submissions' => $monthSubmissions ? $monthSubmissions->count : 0
        );
    }
    
    /**
     * 生成表单URL
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
     * 格式化时间
     */
    public static function formatTime($timestamp) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * 格式化相对时间
     */
    public static function timeAgo($timestamp) {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return '刚刚';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . '天前';
        } elseif ($diff < 31536000) {
            return floor($diff / 2592000) . '个月前';
        } else {
            return floor($diff / 31536000) . '年前';
        }
    }
    
    /**
     * 格式化文件大小
     */
    public static function formatFileSize($bytes) {
        if ($bytes == 0) return '0 B';
        
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * 验证表单配置
     */
    public static function validateFormConfig($config) {
        $errors = array();
        
        if (empty($config['name'])) {
            $errors[] = '表单名称不能为空';
        }
        
        if (empty($config['title'])) {
            $errors[] = '表单标题不能为空';
        }
        
        if (!empty($config['name']) && !preg_match('/^[a-zA-Z0-9_-]+$/', $config['name'])) {
            $errors[] = '表单名称只能包含字母、数字、下划线和短横线';
        }
        
        return $errors;
    }
    
    /**
     * 生成唯一表单名称
     */
    public static function generateUniqueFormName($baseName) {
        $db = self::getDb();
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($baseName));
        $originalName = $name;
        $counter = 1;
        
        while (true) {
            $exists = $db->fetchRow($db->select('id')->from('table.uforms_forms')->where('name = ?', $name));
            if (!$exists) {
                return $name;
            }
            
            $name = $originalName . '_' . $counter;
            $counter++;
        }
    }
    
    /**
     * 复制表单
     */
    public static function duplicateForm($originalId, $newName = null, $newTitle = null) {
        $originalForm = self::getForm($originalId);
        if (!$originalForm) {
            return false;
        }
        
        $user = Typecho_Widget::widget('Widget_User');
        
        // 准备新表单数据
        $newData = array(
            'name' => $newName ?? self::generateUniqueFormName($originalForm['name'] . '_copy'),
            'title' => $newTitle ?? ($originalForm['title'] . ' (副本)'),
            'description' => $originalForm['description'],
            'config' => $originalForm['config'],
            'settings' => $originalForm['settings'],
            'status' => 'draft',
            'author_id' => $user->uid,
            'view_count' => 0,
            'submit_count' => 0,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        // 创建新表单
        $newFormId = self::createForm($newData);
        
        if ($newFormId) {
            // 复制字段
            $fields = self::getFormFields($originalId);
            $fieldsData = array();
            
            foreach ($fields as $field) {
                $fieldsData[] = json_decode($field['field_config'], true);
            }
            
            self::saveFormFields($newFormId, $fieldsData);
            return $newFormId;
        }
        
        return false;
    }
    
    /**
     * 批量更新表单状态
     */
    public static function bulkUpdateStatus($formIds, $status) {
        if (!is_array($formIds) || empty($formIds)) {
            return false;
        }
        
        $allowedStatus = array('draft', 'published', 'archived');
        if (!in_array($status, $allowedStatus)) {
            return false;
        }
        
        $db = self::getDb();
        $placeholders = str_repeat('?,', count($formIds) - 1) . '?';
        
        $query = "UPDATE `{$db->getPrefix()}uforms_forms` SET `status` = ?, `modified_time` = ? WHERE `id` IN ({$placeholders})";
        $params = array_merge(array($status, time()), $formIds);
        
        return $db->query($query, $params);
    }
    
    /**
     * 批量删除表单
     */
    public static function bulkDeleteForms($formIds) {
        if (!is_array($formIds) || empty($formIds)) {
            return false;
        }
        
        foreach ($formIds as $formId) {
            self::deleteForm($formId);
        }
        
        return true;
    }
    
    /**
     * 导出表单配置
     */
    public static function exportForm($formId) {
        $form = self::getForm($formId);
        if (!$form) {
            return false;
        }
        
        $fields = self::getFormFields($formId);
        
        $exportData = array(
            'form' => $form,
            'fields' => $fields,
            'export_time' => time(),
            'plugin_version' => '2.0.0'
        );
        
        return $exportData;
    }
    
    /**
     * 导入表单配置
     */
    public static function importForm($importData, $newName = null) {
        if (!is_array($importData) || !isset($importData['form'])) {
            return false;
        }
        
        $formData = $importData['form'];
        $fieldsData = $importData['fields'] ?? array();
        
        // 创建新表单
        $user = Typecho_Widget::widget('Widget_User');
        $newFormData = array(
            'name' => $newName ?? self::generateUniqueFormName($formData['name']),
            'title' => $formData['title'] . ' (导入)',
            'description' => $formData['description'],
            'config' => $formData['config'],
            'settings' => $formData['settings'],
            'status' => 'draft',
            'author_id' => $user->uid,
            'view_count' => 0,
            'submit_count' => 0,
            'created_time' => time(),
            'modified_time' => time()
        );
        
        $newFormId = self::createForm($newFormData);
        
        if ($newFormId && !empty($fieldsData)) {
            // 导入字段
            $fields = array();
            foreach ($fieldsData as $field) {
                $fields[] = json_decode($field['field_config'], true);
            }
            
            self::saveFormFields($newFormId, $fields);
        }
        
        return $newFormId;
    }
    
    /**
     * 清理旧数据
     */
    public static function cleanupOldData($days = 30) {
        $db = self::getDb();
        $cutoffTime = time() - ($days * 24 * 60 * 60);
        
        // 清理旧的通知记录
        $db->query("DELETE FROM `{$db->getPrefix()}uforms_notifications` WHERE `created_time` < ? AND `is_read` = 1", $cutoffTime);
        
        // 清理旧的统计记录
        $db->query("DELETE FROM `{$db->getPrefix()}uforms_stats` WHERE `created_time` < ?", $cutoffTime);
        
        // 清理旧的垃圾内容日志
        $db->query("DELETE FROM `{$db->getPrefix()}uforms_spam_log` WHERE `created_time` < ?", $cutoffTime);
        
        return true;
    }
    
    /**
     * 获取字段配置模板
     */
    public static function getFieldTemplates() {
        return array(
            'contact_form' => array(
                'name' => '联系表单',
                'fields' => array(
                    array('type' => 'text', 'name' => 'name', 'label' => '姓名', 'required' => true),
                    array('type' => 'email', 'name' => 'email', 'label' => '邮箱', 'required' => true),
                    array('type' => 'tel', 'name' => 'phone', 'label' => '电话', 'required' => false),
                    array('type' => 'textarea', 'name' => 'message', 'label' => '留言内容', 'required' => true, 'rows' => 5)
                )
            ),
            'registration_form' => array(
                'name' => '报名表单',
                'fields' => array(
                    array('type' => 'text', 'name' => 'name', 'label' => '姓名', 'required' => true),
                    array('type' => 'email', 'name' => 'email', 'label' => '邮箱', 'required' => true),
                    array('type' => 'tel', 'name' => 'phone', 'label' => '手机号', 'required' => true),
                    array('type' => 'radio', 'name' => 'gender', 'label' => '性别', 'required' => true, 
                           'options' => array(array('label' => '男', 'value' => 'male'), array('label' => '女', 'value' => 'female'))),
                    array('type' => 'date', 'name' => 'birthday', 'label' => '出生日期', 'required' => false),
                    array('type' => 'textarea', 'name' => 'note', 'label' => '备注', 'required' => false)
                )
            ),
            'survey_form' => array(
                'name' => '调查问卷',
                'fields' => array(
                    array('type' => 'radio', 'name' => 'age_group', 'label' => '年龄段', 'required' => true,
                           'options' => array(
                               array('label' => '18-25岁', 'value' => '18-25'),
                               array('label' => '26-35岁', 'value' => '26-35'),
                               array('label' => '36-45岁', 'value' => '36-45'),
                               array('label' => '46岁以上', 'value' => '46+')
                           )),
                    array('type' => 'checkbox', 'name' => 'interests', 'label' => '兴趣爱好', 'required' => false,
                           'options' => array(
                               array('label' => '运动', 'value' => 'sports'),
                               array('label' => '音乐', 'value' => 'music'),
                               array('label' => '读书', 'value' => 'reading'),
                               array('label' => '旅行', 'value' => 'travel')
                           )),
                    array('type' => 'rating', 'name' => 'satisfaction', 'label' => '满意度评分', 'required' => true, 'max' => 5),
                    array('type' => 'textarea', 'name' => 'suggestions', 'label' => '意见建议', 'required' => false)
                )
            )
        );
    }
    
    /**
     * 应用模板到表单
     */
    public static function applyTemplate($formId, $templateName) {
        $templates = self::getFieldTemplates();
        if (!isset($templates[$templateName])) {
            return false;
        }
        
        $template = $templates[$templateName];
        return self::saveFormFields($formId, $template['fields']);
    }
    
    /**
     * 记录操作日志
     */
    public static function logAction($action, $formId = null, $details = null) {
        $user = Typecho_Widget::widget('Widget_User');
        $db = self::getDb();
        
        $logData = array(
            'user_id' => $user->uid,
            'action' => $action,
            'form_id' => $formId,
            'details' => is_array($details) ? json_encode($details) : $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_time' => time()
        );
        
        // 这里可以扩展为更完整的日志系统
        return true;
    }
}

// 格式化函数
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function escapeHtml($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
