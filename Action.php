<?php
/**
 * Uforms Action处理类
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// 启用详细错误日志
ini_set('log_errors', 1);
ini_set('error_log', __TYPECHO_ROOT_DIR__ . '/uforms_error.log');
error_reporting(E_ALL);

// 确保必要的文件被加载
$uformsDir = __DIR__;
require_once $uformsDir . '/core/UformsHelper.php';
require_once $uformsDir . '/frontend/front.php';

class Uforms_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /**
     * 执行函数 - Typecho 1.2.1+ 要求实现的方法
     */
    public function execute()
    {
        // 这个方法用于初始化，实际处理在 action 方法中
    }

    /**
     * 动作处理函数 - 新版Typecho要求的方法
     */
    public function action()
    {
        $request = $this->request;
        $pathInfo = $request->getPathInfo();
        
        // 详细日志记录
        error_log('=== Uforms Action Start ===');
        error_log('Uforms Action pathInfo: ' . $pathInfo);
        error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('POST data: ' . print_r($_POST, true));
        error_log('GET data: ' . print_r($_GET, true));
        
        try {
            // 根据路径信息执行不同的方法
            if (preg_match('/^\/uforms\/form\/([^\/]+)/', $pathInfo, $matches)) {
                error_log('Uforms: Matched form pattern, name: ' . $matches[1]);
                $this->request->setParam('name', $matches[1]);
                $this->showForm();
            } elseif (preg_match('/^\/uforms\/form\/id\/(\d+)/', $pathInfo, $matches)) {
                error_log('Uforms: Matched form ID pattern, id: ' . $matches[1]);
                $this->request->setParam('id', $matches[1]);
                $this->showFormById();
            } elseif (preg_match('/^\/uforms\/submit/', $pathInfo)) {
                error_log('Uforms: Matched submit pattern');
                $this->submit();
            } else {
                error_log('Uforms: No pattern matched, pathInfo: ' . $pathInfo);
                // 尝试从参数获取表单名
                $name = $request->get('name');
                if ($name) {
                    error_log('Uforms: Using name from parameter: ' . $name);
                    $this->request->setParam('name', $name);
                    $this->showForm();
                } else {
                    error_log('Uforms: No name parameter, throwing exception');
                    throw new Typecho_Widget_Exception('未知操作: ' . $pathInfo, 404);
                }
            }
        } catch (Exception $e) {
            error_log('Uforms: Exception in action: ' . $e->getMessage());
            error_log('Uforms: Exception trace: ' . $e->getTraceAsString());
            throw $e;
        }
        
        error_log('=== Uforms Action End ===');
    }

    /**
     * 显示表单
     */
    public function showForm()
    {
        $request = $this->request;
        $name = $request->get('name');
        
        error_log('=== Uforms showForm Start ===');
        error_log('Uforms showForm called with name: ' . $name);
        error_log('Is POST: ' . ($request->isPost() ? 'YES' : 'NO'));
        
        if (empty($name)) {
            error_log('Uforms: Empty form name');
            throw new Typecho_Widget_Exception('表单名称不能为空', 404);
        }
        
        $form = UformsHelper::getFormByName($name);
        if (!$form || $form['status'] !== 'published') {
            error_log('Uforms: Form not found or not published: ' . $name);
            if ($form) {
                error_log('Uforms: Form status: ' . $form['status']);
            }
            throw new Typecho_Widget_Exception('表单不存在或未发布', 404);
        }
        
        error_log('Uforms: Found form: ID=' . $form['id'] . ', Name=' . $form['name']);
        
        // 增加访问统计
        $this->trackFormView($form['id']);
        
        // 获取表单字段
        $fields = UformsHelper::getFormFields($form['id']);
        $config = json_decode($form['config'], true) ?: array();
        $settings = json_decode($form['settings'], true) ?: array();
        
        error_log('Uforms: Form fields count: ' . count($fields));
        
        // 处理表单提交
        if ($request->isPost()) {
            error_log('=== POST Request Processing ===');
            error_log('Uforms: POST request detected');
            error_log('Uforms: All POST data: ' . print_r($_POST, true));
            
            $uform_name = $request->get('uform_name');
            error_log('Uforms: uform_name from POST: ' . var_export($uform_name, true));
            error_log('Uforms: Expected name: ' . var_export($name, true));
            error_log('Uforms: Names match: ' . ($uform_name === $name ? 'YES' : 'NO'));
            
            if ($uform_name === $name) {
                error_log('Uforms: Processing form submission for: ' . $name);
                
                try {
                    // 处理提交
                    $result = UformsFront::handleFormSubmission($form, $fields, $settings);
                    error_log('Uforms: Submission result type: ' . gettype($result));
                    error_log('Uforms: Submission result: ' . substr($result, 0, 200));
                    
                    // 检查结果类型
                    if (is_string($result)) {
                        if (strpos($result, 'UFORMS_SUBMISSION_SUCCESS') !== false) {
                            error_log('Uforms: Submission successful, preparing redirect');
                            // 提交成功，进行重定向避免重复提交
                            if (!empty($settings['redirect_url'])) {
                                error_log('Uforms: Redirecting to custom URL: ' . $settings['redirect_url']);
                                $this->response->redirect($settings['redirect_url']);
                            } else {
                                // 重定向到当前页面并添加成功标记
                                $redirect_url = Typecho_Common::url('uforms/form/' . $name, Helper::options()->index);
                                if (strpos($redirect_url, '?') === false) {
                                    $redirect_url .= '?success=1';
                                } else {
                                    $redirect_url .= '&success=1';
                                }
                                
                                // 确保URL是完整的
                                if (strpos($redirect_url, 'http') !== 0) {
                                    $redirect_url = Helper::options()->siteUrl . ltrim($redirect_url, '/');
                                }
                                
                                error_log('Uforms: Redirecting to: ' . $redirect_url);
                                $this->response->redirect($redirect_url);
                            }
                            return;
                        } else {
                            // 有错误，显示表单和错误信息
                            error_log('Uforms: Submission has errors, showing form with errors');
                            $this->renderFormPage($form, $fields, $config, $settings, $result);
                            return;
                        }
                    } else {
                        error_log('Uforms: Unexpected result type: ' . gettype($result));
                        $this->renderFormPage($form, $fields, $config, $settings, '<div class="uform-error">提交处理异常</div>');
                        return;
                    }
                } catch (Exception $e) {
                    error_log('Uforms: Exception in submission processing: ' . $e->getMessage());
                    error_log('Uforms: Exception trace: ' . $e->getTraceAsString());
                    $error_msg = '<div class="uform-error">提交失败：' . htmlspecialchars($e->getMessage()) . '</div>';
                    $this->renderFormPage($form, $fields, $config, $settings, $error_msg);
                    return;
                }
            } else {
                error_log('Uforms: Form name mismatch. Expected: ' . var_export($name, true) . ', Got: ' . var_export($uform_name, true));
            }
        }
        
        // 检查是否需要显示成功消息
        if ($request->get('success') === '1') {
            error_log('Uforms: Showing success message');
            $success_message = !empty($settings['success_message']) ? 
                              $settings['success_message'] : 
                              '表单提交成功！感谢您的参与。';
            $success_content = '<div class="uform-success">' . htmlspecialchars($success_message) . '</div>';
            $this->renderFormPage($form, $fields, $config, $settings, $success_content);
            return;
        }
        
        // 渲染表单页面
        error_log('Uforms: Rendering normal form page');
        $this->renderFormPage($form, $fields, $config, $settings);
        error_log('=== Uforms showForm End ===');
    }

    /**
     * 根据ID显示表单
     */
    public function showFormById()
    {
        $request = $this->request;
        $id = $request->get('id');
        
        error_log('Uforms showFormById called with ID: ' . $id);
        
        if (empty($id)) {
            throw new Typecho_Widget_Exception('表单ID不能为空', 404);
        }
        
        $form = UformsHelper::getForm($id);
        if (!$form || $form['status'] !== 'published') {
            throw new Typecho_Widget_Exception('表单不存在或未发布', 404);
        }
        
        // 重定向到名称URL
        $redirect_url = Helper::options()->siteUrl . 'uforms/form/' . $form['name'];
        error_log('Uforms: Redirecting from ID to name URL: ' . $redirect_url);
        $this->response->redirect($redirect_url);
    }

    /**
     * 处理表单提交
     */
    public function submit()
    {
        error_log('Uforms submit method called');
        
        if (!$this->request->isPost()) {
            $this->response->throwJson(array('success' => false, 'message' => '只接受POST请求'));
        }
        
        $form_name = $this->request->get('uform_name');
        $form_id = $this->request->get('form_id');
        
        error_log('Uforms submit: form_name=' . $form_name . ', form_id=' . $form_id);
        
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
            
            if (strpos($result, 'UFORMS_SUBMISSION_SUCCESS') !== false) {
                $this->response->throwJson(array(
                    'success' => true, 
                    'message' => '表单提交成功',
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
            error_log('Uforms submit error: ' . $e->getMessage());
            $this->response->throwJson(array(
                'success' => false, 
                'message' => '提交失败: ' . $e->getMessage()
            ));
        }
    }

    /**
     * 处理日历显示
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
        $template_file = __DIR__ . '/templates/calendar.php';
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>日历 - ' . htmlspecialchars($form['title']) . '</title></head><body>';
            echo '<h1>日历功能</h1><p>表单：' . htmlspecialchars($form['title']) . '</p>';
            echo '</body></html>';
        }
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
                    throw new Exception('未知的API动作: ' . $action);
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
        error_log('Uforms: Rendering form page for form: ' . $form['name']);
        
        // 设置正确的Content-Type
        $this->response->setContentType('text/html');
        
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
            $template_file = __DIR__ . '/templates/form.php';
            if (file_exists($template_file)) {
                // 将变量提取到模板作用域
                extract(array(
                    'form' => $form,
                    'fields' => $fields,
                    'config' => $config,
                    'settings' => $settings,
                    'content' => $content
                ));
                include $template_file;
            } else {
                // 回退到简单输出
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($form['title']) . '</title>';
                echo '<link rel="stylesheet" href="' . Helper::options()->pluginUrl . '/Uforms/assets/css/uforms.css">';
                echo '</head><body>';
                
                if ($content) {
                    echo $content;
                } else {
                    echo UformsFront::renderFormHTML($form, $fields, $config, $settings);
                }
                
                echo '<script src="' . Helper::options()->pluginUrl . '/Uforms/assets/js/uforms.js"></script>';
                echo '</body></html>';
            }
        }
        
        exit;
    }

    /**
     * 记录表单访问
     */
    private function trackFormView($form_id)
    {
        try {
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
        } catch (Exception $e) {
            error_log('Uforms: Failed to track form view: ' . $e->getMessage());
        }
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
                    'description' => $event['event_description']
                )
            );
        }
        
        echo json_encode(array(
            'success' => true,
            'events' => $events
        ));
    }
}
