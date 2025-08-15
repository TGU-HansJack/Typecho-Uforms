<?php
/**
 * Uforms Action处理类
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once 'UformsHelper.php';
require_once 'front.php';

class Uforms_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * 执行函数
     */
    public function execute()
    {
        // 根据不同的action执行不同的方法
    }

    /**
     * 显示表单
     */
    public function showForm()
    {
        $request = $this->request;
        $name = $request->get('name');
        
        if (empty($name)) {
            throw new Typecho_Widget_Exception('表单名称不能为空', 404);
        }
        
        $form = UformsHelper::getFormByName($name);
        if (!$form || $form['status'] !== 'published') {
            throw new Typecho_Widget_Exception('表单不存在或未发布', 404);
        }
        
        // 增加访问统计
        $this->trackFormView($form['id']);
        
        // 获取表单字段
        $fields = UformsHelper::getFormFields($form['id']);
        $config = json_decode($form['config'], true) ?: array();
        $settings = json_decode($form['settings'], true) ?: array();
        
        // 处理表单提交
        if ($request->isPost() && $request->get('uform_name') === $name) {
            $result = UformsFront::handleFormSubmission($form, $fields, $settings);
            if (is_string($result)) {
                // 如果返回HTML（成功或错误消息），直接显示
                $this->renderFormPage($form, $fields, $config, $settings, $result);
                return;
            }
        }
        
        // 渲染表单页面
        $this->renderFormPage($form, $fields, $config, $settings);
    }

    /**
     * 根据ID显示表单
     */
    public function showFormById()
    {
        $request = $this->request;
        $id = $request->get('id');
        
        if (empty($id)) {
            throw new Typecho_Widget_Exception('表单ID不能为空', 404);
        }
        
        $form = UformsHelper::getForm($id);
        if (!$form || $form['status'] !== 'published') {
            throw new Typecho_Widget_Exception('表单不存在或未发布', 404);
        }
        
        // 重定向到名称URL
        $this->response->redirect(Helper::options()->siteUrl . 'uforms/form/' . $form['name']);
    }

    /**
     * 处理表单提交
     */
    public function submit()
    {
        if ($this->request->getMethod() !== 'POST') {
            $this->response->throwJson(array('success' => false, 'message' => '只接受POST请求'));
        }
        
        $form_name = $this->request->get('uform_name');
        $form_id = $this->request->get('form_id');
        
        if (empty($form_name) && empty($form_id)) {
            $this->response->throwJson(array('success' => false, 'message' => '表单标识不能为空'));
        }
        
        try {
            // 获取表单
            if ($form_name) {
                $form = UformsHelper::getFormByName($form_name);
            } else {
                $form = UformsHelper::getForm($form_id);
            }
            
            if (!$form) {
                throw new Exception('表单不存在');
            }
            
            // 获取字段
            $fields = UformsHelper::getFormFields($form['id']);
            $settings = json_decode($form['settings'], true) ?: array();
            
            // 处理提交
            $result = UformsFront::handleFormSubmission($form, $fields, $settings);
            
            if (strpos($result, 'uform-success') !== false) {
                $this->response->throwJson(array(
                    'success' => true, 
                    'message' => strip_tags($result),
                    'redirect' => $settings['redirect_url'] ?? null
                ));
            } else {
                // 提取错误信息
                preg_match_all('/<li>(.*?)<\/li>/', $result, $matches);
                $errors = $matches[1] ?: array('提交失败');
                
                $this->response->throwJson(array(
                    'success' => false, 
                    'errors' => $errors
                ));
            }
            
        } catch (Exception $e) {
            $this->response->throwJson(array(
                'success' => false, 
                'message' => '提交失败: ' . $e->getMessage()
            ));
        }
    }

    /**
     * 显示日历
     */
    public function calendar()
    {
        $form_id = $this->request->get('id');
        if (!$form_id) {
            throw new Typecho_Widget_Exception('表单ID不能为空', 404);
        }
        
        $form = UformsHelper::getForm($form_id);
        if (!$form || $form['status'] !== 'published') {
            throw new Typecho_Widget_Exception('表单不存在或未发布', 404);
        }
        
        $settings = json_decode($form['settings'], true) ?: array();
        
        // 检查是否启用日历功能
        if (empty($settings['enable_calendar'])) {
            throw new Typecho_Widget_Exception('此表单未启用日历功能', 404);
        }
        
        // 渲染日历页面
        include dirname(__FILE__) . '/templates/calendar.php';
        exit;
    }

    /**
     * API处理器
     */
    public function apiHandler()
    {
        $action = $this->request->get('action');
        
        header('Content-Type: application/json; charset=UTF-8');
        
        try {
            switch ($action) {
                case 'validate':
                    $this->handleApiValidate();
                    break;
                    
                case 'upload':
                    $this->handleApiUpload();
                    break;
                    
                case 'calendar_events':
                    $this->handleApiCalendarEvents();
                    break;
                    
                default:
                    throw new Exception('未知的API动作');
            }
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * 渲染表单页面
     */
    private function renderFormPage($form, $fields, $config, $settings, $content = null)
    {
        if (!empty($settings['embed_mode']) && $settings['embed_mode'] === 'iframe') {
            // iframe模式，使用简单布局
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
            echo '<link rel="stylesheet" href="' . Helper::options()->pluginUrl . '/Uforms/assets/css/uforms.css">';
            echo '</head><body style="margin: 0; padding: 20px;">';
            
            if ($content) {
                echo $content;
            } else {
                echo UformsFront::renderFormHTML($form, $fields, $config, $settings, 'iframe');
            }
            
            echo '<script src="' . Helper::options()->pluginUrl . '/Uforms/assets/js/uforms.js"></script>';
            echo '</body></html>';
        } else {
            // 完整页面模式
            $form_id = $form['id'];
            include dirname(__FILE__) . '/templates/form.php';
        }
        
        exit;
    }

    /**
     * 记录表单访问
     */
    private function trackFormView($form_id)
    {
        $db = Typecho_Db::get();
        $ip = UformsHelper::getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // 记录访问统计
        $stat_data = array(
            'form_id' => $form_id,
            'ip' => $ip,
            'user_agent' => $user_agent,
            'action' => 'view',
            'created_time' => time()
        );
        
        $db->query($db->insert('table.uforms_stats')->rows($stat_data));
        
        // 更新表单访问计数
        $db->query($db->update('table.uforms_forms')
                     ->expression('view_count', 'view_count + 1')
                     ->where('id = ?', $form_id));
    }

    /**
     * 处理API验证
     */
    private function handleApiValidate()
    {
        $field_type = $this->request->get('field_type');
        $field_value = $this->request->get('field_value');
        $field_config = json_decode($this->request->get('field_config', '{}'), true);
        
        $errors = UformsHelper::validateField($field_type, $field_value, $field_config);
        
        echo json_encode(array(
            'success' => empty($errors),
            'errors' => $errors
        ));
    }

    /**
     * 处理API上传
     */
    private function handleApiUpload()
    {
        if (!isset($_FILES['file'])) {
            throw new Exception('没有上传文件');
        }
        
        $form_id = $this->request->get('form_id');
        $field_name = $this->request->get('field_name');
        
        $result = UformsFront::handleFileUpload($_FILES['file'], 
                                               array('field_name' => $field_name), 
                                               $form_id);
        
        echo json_encode($result);
    }

    /**
     * 处理日历事件API
     */
    private function handleApiCalendarEvents()
    {
        $form_id = $this->request->get('form_id');
        $start = $this->request->get('start');
        $end = $this->request->get('end');
        
        $db = Typecho_Db::get();
        $events = array();
        
        // 获取日历事件
        $calendar_events = $db->fetchAll(
            $db->select('*')->from('table.uforms_calendar')
               ->where('form_id = ? AND start_time >= ? AND start_time <= ?', 
                      $form_id, strtotime($start), strtotime($end))
        );
        
        foreach ($calendar_events as $event) {
            $events[] = array(
                'id' => $event['id'],
                'title' => $event['title'],
                'start' => date('c', $event['start_time']),
                'end' => $event['end_time'] ? date('c', $event['end_time']) : null,
                'allDay' => $event['all_day'],
                'color' => $event['color'],
                'extendedProps' => array(
                    'status' => $event['status'],
                    'description' => $event['description']
                )
            );
        }
        
        echo json_encode(array(
            'success' => true,
            'events' => $events
        ));
    }

    /**
     * 处理动作 - 修复方法
     */
    public function action()
    {
        $request = $this->request;
        $pathInfo = $request->getPathInfo();
        
        // 解析路径信息确定要执行的动作
        if (preg_match('/^\/uforms\/form\/([^\/]+)/', $pathInfo, $matches)) {
            $this->request->setParam('name', $matches[1]);
            $this->showForm();
        } elseif (preg_match('/^\/uforms\/form\/(\d+)/', $pathInfo, $matches)) {
            $this->request->setParam('id', $matches[1]);
            $this->showFormById();
        } elseif (preg_match('/^\/uforms\/calendar\/(\d+)/', $pathInfo, $matches)) {
            $this->request->setParam('id', $matches[1]);
            $this->calendar();
        } elseif (preg_match('/^\/uforms\/api\/([^\/]+)/', $pathInfo, $matches)) {
            $this->apiHandler();
        } elseif (preg_match('/^\/uforms\/submit/', $pathInfo)) {
            $this->submit();
        } else {
            throw new Typecho_Widget_Exception('未知操作', 404);
        }
    }
}