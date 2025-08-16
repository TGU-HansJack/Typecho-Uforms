<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<div class="main">
    <div class="body container">
<?php
$form_id = $request->get('id');
$form = null;
$fields = array();

if ($form_id) {
    $form = UformsHelper::getForm($form_id);
    $fields = UformsHelper::getFormFields($form_id);
}
?>

<div class="uforms-creator">
    <!-- 表单构建器 -->
    <div class="form-builder">
        <!-- 左侧字段库 -->
        <div class="fields-panel">
            <h3><i class="puzzle icon"></i> 字段库</h3>
            
            <!-- 基础字段 -->
            <div class="field-category">
                <h4><i class="file alternate icon"></i> 基础字段</h4>
                <div class="field-items">
                    <div class="field-item" data-type="text" draggable="true">
                        <i class="font icon"></i>
                        <span>单行文本</span>
                        <div class="field-description">用于输入短文本内容</div>
                    </div>
                    <div class="field-item" data-type="textarea" draggable="true">
                        <i class="align left icon"></i>
                        <span>多行文本</span>
                        <div class="field-description">用于输入长文本内容</div>
                    </div>
                    <div class="field-item" data-type="email" draggable="true">
                        <i class="at icon"></i>
                        <span>邮箱</span>
                        <div class="field-description">自动验证邮箱格式</div>
                    </div>
                    <div class="field-item" data-type="url" draggable="true">
                        <i class="linkify icon"></i>
                        <span>网址</span>
                        <div class="field-description">自动验证网址格式</div>
                    </div>
                    <div class="field-item" data-type="tel" draggable="true">
                        <i class="phone icon"></i>
                        <span>电话</span>
                        <div class="field-description">输入电话号码</div>
                    </div>
                    <div class="field-item" data-type="number" draggable="true">
                        <i class="hashtag icon"></i>
                        <span>数字</span>
                        <div class="field-description">只能输入数字</div>
                    </div>
                    <div class="field-item" data-type="password" draggable="true">
                        <i class="lock icon"></i>
                        <span>密码</span>
                        <div class="field-description">密码输入框</div>
                    </div>
                    <div class="field-item" data-type="select" draggable="true">
                        <i class="caret down icon"></i>
                        <span>下拉选择</span>
                        <div class="field-description">从选项中选择一项</div>
                    </div>
                    <div class="field-item" data-type="radio" draggable="true">
                        <i class="dot circle icon"></i>
                        <span>单选按钮</span>
                        <div class="field-description">从选项中选择一项</div>
                    </div>
                    <div class="field-item" data-type="checkbox" draggable="true">
                        <i class="check square icon"></i>
                        <span>复选框</span>
                        <div class="field-description">可选择多个选项</div>
                    </div>
                    <div class="field-item" data-type="file" draggable="true">
                        <i class="file icon"></i>
                        <span>文件上传</span>
                        <div class="field-description">上传文件或图片</div>
                    </div>
                </div>
            </div>
            
            <!-- 高级字段 -->
            <div class="field-category">
                <h4><i class="folder open icon"></i> 高级字段</h4>
                <div class="field-items">
                    <div class="field-item" data-type="date" draggable="true">
                        <i class="calendar outline icon"></i>
                        <span>日期选择</span>
                        <div class="field-description">选择日期</div>
                    </div>
                    <div class="field-item" data-type="time" draggable="true">
                        <i class="clock icon"></i>
                        <span>时间选择</span>
                        <div class="field-description">选择时间</div>
                    </div>
                    <div class="field-item" data-type="datetime" draggable="true">
                        <i class="calendar alternate icon"></i>
                        <span>日期时间</span>
                        <div class="field-description">选择日期和时间</div>
                    </div>
                    <div class="field-item" data-type="range" draggable="true">
                        <i class="sliders horizontal icon"></i>
                        <span>滑块</span>
                        <div class="field-description">拖拽选择数值</div>
                    </div>
                    <div class="field-item" data-type="rating" draggable="true">
                        <i class="star icon"></i>
                        <span>评分</span>
                        <div class="field-description">星级评分选择</div>
                    </div>
                    <div class="field-item" data-type="color" draggable="true">
                        <i class="palette icon"></i>
                        <span>颜色选择</span>
                        <div class="field-description">选择颜色值</div>
                    </div>
                    <div class="field-item" data-type="signature" draggable="true">
                        <i class="signature icon"></i>
                        <span>签名板</span>
                        <div class="field-description">手写签名输入</div>
                    </div>
                    <div class="field-item" data-type="hidden" draggable="true">
                        <i class="eye slash icon"></i>
                        <span>隐藏字段</span>
                        <div class="field-description">不显示的数据字段</div>
                    </div>
                </div>
            </div>
            
            <!-- 布局字段 -->
            <div class="field-category">
                <h4><i class="columns icon"></i> 布局字段</h4>
                <div class="field-items">
                    <div class="field-item" data-type="heading" draggable="true">
                        <i class="heading icon"></i>
                        <span>标题</span>
                        <div class="field-description">添加章节标题</div>
                    </div>
                    <div class="field-item" data-type="paragraph" draggable="true">
                        <i class="paragraph icon"></i>
                        <span>段落</span>
                        <div class="field-description">添加说明文字</div>
                    </div>
                    <div class="field-item" data-type="divider" draggable="true">
                        <i class="grip line icon"></i>
                        <span>分割线</span>
                        <div class="field-description">分隔不同区域</div>
                    </div>
                    <div class="field-item" data-type="columns" draggable="true">
                        <i class="columns icon"></i>
                        <span>多列布局</span>
                        <div class="field-description">创建多列容器</div>
                    </div>
                    <div class="field-item" data-type="html" draggable="true">
                        <i class="code icon"></i>
                        <span>HTML代码</span>
                        <div class="field-description">自定义HTML内容</div>
                    </div>
                </div>
            </div>
            
            <!-- 特殊字段 -->
            <div class="field-category">
                <h4><i class="star icon"></i> 特殊字段</h4>
                <div class="field-items">
                    <div class="field-item" data-type="calendar" draggable="true">
                        <i class="calendar icon"></i>
                        <span>日历预约</span>
                        <div class="field-description">日历预约选择</div>
                    </div>
                    <div class="field-item" data-type="cascade" draggable="true">
                        <i class="sitemap icon"></i>
                        <span>级联选择</span>
                        <div class="field-description">多级关联选择</div>
                    </div>
                    <div class="field-item" data-type="tags" draggable="true">
                        <i class="tags icon"></i>
                        <span>标签选择</span>
                        <div class="field-description">多标签输入</div>
                    </div>
                    <div class="field-item" data-type="repeater" draggable="true">
                        <i class="clone icon"></i>
                        <span>重复器</span>
                        <div class="field-description">可重复添加的字段组</div>
                    </div>
                </div>
            </div>

            <!-- 系统字段（Typecho集成） -->
            <div class="field-category">
                <h4><i class="cogs icon"></i> 系统字段</h4>
                <div class="field-items">
                    <div class="field-item" data-type="user_name" draggable="true">
                        <i class="user icon"></i>
                        <span>用户姓名</span>
                        <div class="field-description">获取当前用户姓名</div>
                    </div>
                    <div class="field-item" data-type="user_email" draggable="true">
                        <i class="envelope icon"></i>
                        <span>用户邮箱</span>
                        <div class="field-description">获取当前用户邮箱</div>
                    </div>
                    <div class="field-item" data-type="page_url" draggable="true">
                        <i class="linkify icon"></i>
                        <span>页面URL</span>
                        <div class="field-description">当前页面地址</div>
                    </div>
                    <div class="field-item" data-type="page_title" draggable="true">
                        <i class="file alternate icon"></i>
                        <span>页面标题</span>
                        <div class="field-description">当前页面标题</div>
                    </div>
                    <div class="field-item" data-type="timestamp" draggable="true">
                        <i class="clock icon"></i>
                        <span>时间戳</span>
                        <div class="field-description">当前时间戳</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 中间画布 -->
        <div class="form-canvas">
            <div class="canvas-toolbar">
                <div class="toolbar-left">
                    <button id="preview-desktop" class="preview-btn active" data-view="desktop">
                        <i class="desktop icon"></i>桌面
                    </button>
                    <button id="preview-tablet" class="preview-btn" data-view="tablet">
                        <i class="tablet alternate icon"></i>平板
                    </button>
                    <button id="preview-mobile" class="preview-btn" data-view="mobile">
                        <i class="mobile alternate icon"></i>手机
                    </button>
                </div>
                <div class="toolbar-center">
                    <span id="canvas-scale">100%</span>
                </div>
                <div class="toolbar-right">
                    <button id="clear-form" title="清空表单">
                        <i class="trash alternate icon"></i>清空
                    </button>
                    <button id="preview-form" title="预览表单">
                        <i class="eye icon"></i>预览
                    </button>
                    <button id="toggle-grid" title="显示网格">
                        <i class="th icon"></i>网格
                    </button>
                </div>
            </div>
            
            <div class="canvas-content" id="form-canvas">
                <div class="canvas-drop-zone" id="canvas-drop-zone">
                    <div class="drop-hint">
                        <div class="drop-icon">
                            <i class="hand point up icon"></i>
                        </div>
                        <h3>从左侧拖拽字段到这里开始创建表单</h3>
                        <p>或者点击左侧字段图标快速添加到表单</p>
                        <div class="quick-start">
                            <button class="btn btn-primary" id="add-text-field">
                                <i class="plus icon"></i> 添加文本字段
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 右侧属性面板 -->
        <div class="properties-panel">
            <div class="panel-tabs">
                <button class="tab-button active" data-tab="field">
                    <i class="pencil alternate icon"></i>字段设置
                </button>
                <button class="tab-button" data-tab="form">
                    <i class="wpforms icon"></i>表单设置
                </button>
                <button class="tab-button" data-tab="style">
                    <i class="paint brush icon"></i>样式设置
                </button>
            </div>
            
            <!-- 字段设置面板 -->
            <div class="tab-content active" id="field-tab">
                <div class="no-selection">
                    <div class="no-selection-icon">
                        <i class="mouse pointer icon"></i>
                    </div>
                    <h4>选择一个字段</h4>
                    <p>点击表单中的字段来配置其属性和行为</p>
                </div>
                
                <div class="field-properties" style="display: none;">
                    <!-- 基本设置 -->
                    <div class="property-group">
                        <h4><i class="file alternate icon"></i> 基本设置</h4>
                        <div class="property-item">
                            <label for="field-label">字段标签 <span class="required">*</span></label>
                            <input type="text" id="field-label" placeholder="输入字段标签" />
                            <div class="field-tip">显示在字段上方的标签文字</div>
                        </div>
                        <div class="property-item">
                            <label for="field-name">字段名称</label>
                            <input type="text" id="field-name" placeholder="字段的唯一标识" />
                            <div class="field-tip">用于表单提交的字段名，留空自动生成</div>
                        </div>
                        <div class="property-item">
                            <label for="field-placeholder">占位符文本</label>
                            <input type="text" id="field-placeholder" placeholder="提示用户输入的文字" />
                            <div class="field-tip">显示在输入框内的提示文字</div>
                        </div>
                        <div class="property-item">
                            <label for="field-default">默认值</label>
                            <input type="text" id="field-default" placeholder="字段的默认值" />
                            <div class="field-tip">字段的初始值</div>
                        </div>
                        <div class="property-item">
                            <label for="field-help">帮助文本</label>
                            <textarea id="field-help" placeholder="字段的详细说明" rows="3"></textarea>
                            <div class="field-tip">显示在字段下方的帮助说明</div>
                        </div>
                        <div class="property-item">
                            <div class="ui checkbox">
                                <input type="checkbox" id="field-required" />
                                <label for="field-required">设为必填字段</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 高级设置 -->
                    <div class="property-group">
                        <h4><i class="folder open icon"></i> 高级设置</h4>
                        <div class="property-item">
                            <label for="field-css-class">CSS类名</label>
                            <input type="text" id="field-css-class" placeholder="自定义CSS类" />
                            <div class="field-tip">为字段添加自定义CSS类名</div>
                        </div>
                        <div class="property-item">
                            <label for="field-css-id">CSS ID</label>
                            <input type="text" id="field-css-id" placeholder="自定义CSS ID" />
                            <div class="field-tip">为字段设置唯一的CSS ID</div>
                        </div>
                        <div class="property-item">
                            <label for="field-width">字段宽度</label>
                            <select id="field-width">
                                <option value="full">100% - 全宽</option>
                                <option value="half">50% - 半宽</option>
                                <option value="third">33.33% - 三分之一</option>
                                <option value="quarter">25% - 四分之一</option>
                                <option value="auto">自动宽度</option>
                                <option value="custom">自定义</option>
                            </select>
                        </div>
                        <div class="property-item" id="custom-width-input" style="display: none;">
                            <label for="field-custom-width">自定义宽度</label>
                            <div class="input-group">
                                <input type="number" id="field-custom-width" placeholder="数值" />
                                <select id="field-width-unit">
                                    <option value="px">px</option>
                                    <option value="%">%</option>
                                    <option value="rem">rem</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 验证规则 -->
                    <div class="property-group">
                        <h4><i class="check circle icon"></i> 验证规则</h4>
                        <div class="property-item">
                            <label for="field-min-length">最小长度</label>
                            <input type="number" id="field-min-length" min="0" placeholder="0" />
                        </div>
                        <div class="property-item">
                            <label for="field-max-length">最大长度</label>
                            <input type="number" id="field-max-length" min="0" placeholder="无限制" />
                        </div>
                        <div class="property-item">
                            <label for="field-pattern">正则表达式</label>
                            <input type="text" id="field-pattern" placeholder="验证模式" />
                            <div class="field-tip">用于验证输入格式的正则表达式</div>
                        </div>
                        <div class="property-item">
                            <label for="field-error-message">错误提示</label>
                            <input type="text" id="field-error-message" placeholder="验证失败时的提示" />
                        </div>
                    </div>
                    
                    <!-- 条件逻辑 -->
                    <div class="property-group">
                        <h4><i class="random icon"></i> 条件逻辑</h4>
                        <div class="property-item">
                            <div class="ui checkbox">
                                <input type="checkbox" id="field-conditional" />
                                <label for="field-conditional">启用条件显示</label>
                            </div>
                        </div>
                        <div class="conditional-rules" id="conditional-rules" style="display: none;">
                            <div class="rule-builder">
                                <div class="rule-header">
                                    <span>显示此字段当：</span>
                                </div>
                                <div class="rule-item ui-sortable-handle">
                                    <div class="rule-drag-handle">
                                        <i class="bars icon"></i>
                                    </div>
                                    <select id="condition-field" class="ui fluid search selection dropdown rule-select">
                                        <option value="">选择字段</option>
                                    </select>
                                    <select id="condition-operator" class="ui fluid selection dropdown rule-select">
                                        <option value="equals">等于</option>
                                        <option value="not_equals">不等于</option>
                                        <option value="contains">包含</option>
                                        <option value="not_contains">不包含</option>
                                        <option value="empty">为空</option>
                                        <option value="not_empty">不为空</option>
                                        <option value="greater">大于</option>
                                        <option value="less">小于</option>
                                    </select>
                                    <input type="text" id="condition-value" class="rule-input" placeholder="比较值" />
                                    <button type="button" class="ui icon button rule-remove" title="删除规则">
                                        <i class="times icon"></i>
                                    </button>
                                </div>
                                <button type="button" id="add-condition-rule" class="ui button">
                                    <i class="plus icon"></i> 添加条件
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 选项设置（用于select/radio/checkbox） -->
                    <div class="property-group options-group" style="display: none;">
                        <h4><i class="list icon"></i> 选项设置</h4>
                        <div class="options-list" id="options-list">
                            <div class="option-item ui-sortable-handle">
                                <div class="option-drag-handle">
                                    <i class="bars icon"></i>
                                </div>
                                <div class="option-inputs">
                                    <input type="text" placeholder="选项标签" class="option-label" />
                                    <input type="text" placeholder="选项值" class="option-value" />
                                </div>
                                <button type="button" class="ui icon button option-remove" title="删除选项">
                                    <i class="times icon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="options-actions">
                            <button type="button" id="add-option" class="ui button">
                                <i class="plus icon"></i> 添加选项
                            </button>
                            <button type="button" id="bulk-add-options" class="ui button">
                                <i class="upload icon"></i> 批量添加
                            </button>
                        </div>
                        <div class="property-item">
                            <div class="ui checkbox">
                                <input type="checkbox" id="allow-other" />
                                <label for="allow-other">允许用户输入其他选项</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 文件上传设置 -->
                    <div class="property-group file-group" style="display: none;">
                        <h4><i class="file icon"></i> 文件上传设置</h4>
                        <div class="property-item">
                            <label for="file-types">允许的文件类型</label>
                            <input type="text" id="file-types" placeholder="jpg,png,pdf" value="jpg,png,pdf" />
                            <div class="field-tip">用逗号分隔多个类型</div>
                        </div>
                        <div class="property-item">
                            <label for="file-max-size">最大文件大小(MB)</label>
                            <input type="number" id="file-max-size" value="10" min="1" max="100" />
                        </div>
                        <div class="property-item">
                            <div class="ui checkbox">
                                <input type="checkbox" id="file-multiple" />
                                <label for="file-multiple">允许多文件上传</label>
                            </div>
                        </div>
                        <div class="property-item">
                            <label for="file-max-count">最多上传文件数</label>
                            <input type="number" id="file-max-count" value="5" min="1" max="20" />
                        </div>
                    </div>

                    <!-- 数字字段设置 -->
                    <div class="property-group number-group" style="display: none;">
                        <h4><i class="hashtag icon"></i> 数字设置</h4>
                        <div class="property-item">
                            <label for="number-min">最小值</label>
                            <input type="number" id="number-min" placeholder="无限制" />
                        </div>
                        <div class="property-item">
                            <label for="number-max">最大值</label>
                            <input type="number" id="number-max" placeholder="无限制" />
                        </div>
                        <div class="property-item">
                            <label for="number-step">步长</label>
                            <input type="number" id="number-step" value="1" min="0.01" step="0.01" />
                        </div>
                    </div>

                    <!-- 日期时间设置 -->
                    <div class="property-group datetime-group" style="display: none;">
                        <h4><i class="calendar alternate icon"></i> 日期时间设置</h4>
                        <div class="property-item">
                            <label for="date-min">最早日期</label>
                            <input type="date" id="date-min" />
                        </div>
                        <div class="property-item">
                            <label for="date-max">最晚日期</label>
                            <input type="date" id="date-max" />
                        </div>
                        <div class="property-item">
                            <label for="date-format">日期格式</label>
                            <select id="date-format">
                                <option value="YYYY-MM-DD">2023-12-25</option>
                                <option value="DD/MM/YYYY">25/12/2023</option>
                                <option value="MM/DD/YYYY">12/25/2023</option>
                                <option value="DD-MM-YYYY">25-12-2023</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 表单设置面板 -->
            <div class="tab-content" id="form-tab">
                <div class="property-group">
                    <h4><i class="info circle icon"></i> 基本信息</h4>
                    <div class="property-item">
                        <label for="form-name">表单名称 <span class="required">*</span></label>
                        <input type="text" id="form-name" value="<?php echo $form ? htmlspecialchars($form['name']) : ''; ?>" placeholder="表单的唯一标识" />
                        <div class="field-tip">用于生成表单URL，只能包含字母、数字和下划线</div>
                    </div>
                    <div class="property-item">
                        <label for="form-title">表单标题 <span class="required">*</span></label>
                        <input type="text" id="form-title" value="<?php echo $form ? htmlspecialchars($form['title']) : ''; ?>" placeholder="表单的显示标题" />
                    </div>
                    <div class="property-item">
                        <label for="form-description">表单描述</label>
                        <textarea id="form-description" rows="3" placeholder="表单的详细说明"><?php echo $form ? htmlspecialchars($form['description']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="paper plane icon"></i> 提交设置</h4>
                    <div class="property-item">
                        <label for="submit-text">提交按钮文本</label>
                        <input type="text" id="submit-text" value="提交" />
                    </div>
                    <div class="property-item">
                        <label for="success-message">成功消息</label>
                        <textarea id="success-message" rows="2">表单提交成功！感谢您的参与。</textarea>
                    </div>
                    <div class="property-item">
                        <label for="success-action">成功后行为</label>
                        <select id="success-action">
                            <option value="message">显示消息</option>
                            <option value="redirect">跳转页面</option>
                            <option value="refresh">刷新页面</option>
                            <option value="block">显示内容区块</option>
                        </select>
                    </div>
                    <div class="property-item" id="redirect-url-setting" style="display: none;">
                        <label for="redirect-url">跳转地址</label>
                        <input type="url" id="redirect-url" placeholder="https://example.com/thanks" />
                    </div>
                    <div class="property-item" id="success-block-setting" style="display: none;">
                        <label for="success-block">成功内容</label>
                        <textarea id="success-block" rows="4" placeholder="成功后要显示的HTML内容"></textarea>
                    </div>
                    <div class="property-item">
                        <div class="ui checkbox">
                            <input type="checkbox" id="ajax-submit" checked />
                            <label for="ajax-submit">使用AJAX提交（无刷新提交）</label>
                        </div>
                    </div>
                    <div class="property-item">
                        <div class="ui checkbox">
                            <input type="checkbox" id="prevent-duplicate" />
                            <label for="prevent-duplicate">防止重复提交</label>
                        </div>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="shield alternate icon"></i> 安全设置</h4>
                    <div class="property-item">
                        <div class="ui checkbox">
                            <input type="checkbox" id="enable-captcha" />
                            <label for="enable-captcha">启用验证码</label>
                        </div>
                    </div>
                    <div class="property-item">
                        <div class="ui checkbox">
                            <input type="checkbox" id="enable-honeypot" checked />
                            <label for="enable-honeypot">启用蜜罐防spam</label>
                        </div>
                    </div>
                    <div class="property-item">
                        <label for="submit-limit">提交限制(秒)</label>
                        <input type="number" id="submit-limit" value="60" min="0" />
                        <div class="field-tip">同一IP再次提交的最小间隔时间</div>
                    </div>
                    <div class="property-item">
                        <label for="max-submissions">最大提交次数</label>
                        <input type="number" id="max-submissions" value="0" min="0" />
                        <div class="field-tip">表单最多接受的提交次数，0表示无限制</div>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="envelope icon"></i> 邮件通知</h4>
                    <div class="property-item">
                        <div class="ui checkbox">
                            <input type="checkbox" id="admin-notification" />
                            <label for="admin-notification">发送管理员通知</label>
                        </div>
                    </div>
                    <div class="admin-notification-settings" id="admin-notification-settings" style="display: none;">
                        <div class="property-item">
                            <label for="admin-email">收件人邮箱</label>
                            <input type="email" id="admin-email" placeholder="多个邮箱用逗号分隔" />
                        </div>
                        <div class="property-item">
                            <label for="admin-subject">邮件主题</label>
                            <input type="text" id="admin-subject" value="新的表单提交 - {form_title}" />
                        </div>
                        <div class="property-item">
                            <label for="admin-message">邮件内容</label>
                            <textarea id="admin-message" rows="6">您收到一个新的表单提交：

{all_fields}

提交时间：{submit_time}
IP地址：{ip_address}
用户代理：{user_agent}</textarea>
                            <div class="field-tip">
                                可用变量：{form_title}, {all_fields}, {submit_time}, {ip_address}, {user_agent}
                            </div>
                        </div>
                    </div>
                    
                    <div class="property-item">
                        <div class="ui checkbox">
                            <input type="checkbox" id="user-notification" />
                            <label for="user-notification">发送用户确认邮件</label>
                        </div>
                    </div>
                    <div class="user-notification-settings" id="user-notification-settings" style="display: none;">
                        <div class="property-item">
                            <label for="user-email-field">用户邮箱字段</label>
                            <select id="user-email-field">
                                <option value="">选择邮箱字段</option>
                            </select>
                        </div>
                        <div class="property-item">
                            <label for="user-subject">邮件主题</label>
                            <input type="text" id="user-subject" value="表单提交确认 - {form_title}" />
                        </div>
                        <div class="property-item">
                            <label for="user-message">邮件内容</label>
                            <textarea id="user-message" rows="6">感谢您的提交！

我们已收到您的表单信息，将尽快处理。

您的提交内容：
{all_fields}

如有疑问，请联系我们。</textarea>
                        </div>
                    </div>
                </div>

                <div class="property-group">
                    <h4><i class="cloud icon"></i> 第三方集成</h4>
                    <div class="property-item">
                        <div class="ui checkbox">
                            <input type="checkbox" id="enable-webhook" />
                            <label for="enable-webhook">启用Webhook</label>
                        </div>
                    </div>
                    <div class="webhook-settings" id="webhook-settings" style="display: none;">
                        <div class="property-item">
                            <label for="webhook-url">Webhook URL</label>
                            <input type="url" id="webhook-url" placeholder="https://example.com/webhook" />
                        </div>
                        <div class="property-item">
                            <label for="webhook-secret">密钥</label>
                            <input type="text" id="webhook-secret" placeholder="用于验证的密钥" />
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 样式设置面板 -->
            <div class="tab-content" id="style-tab">
                <div class="property-group">
                    <h4><i class="palette icon"></i> 表单样式</h4>
                    <div class="property-item">
                        <label for="form-theme">样式主题</label>
                        <select id="form-theme">
                            <option value="default">默认样式</option>
                            <option value="minimal">简约风格</option>
                            <option value="modern">现代风格</option>
                            <option value="classic">经典风格</option>
                            <option value="bootstrap">Bootstrap风格</option>
                            <option value="material">Material Design</option>
                        </select>
                        <div class="theme-preview" id="theme-preview">
                            <div class="preview-box">主题预览</div>
                        </div>
                    </div>
                    <div class="property-item">
                        <label for="primary-color">主色调</label>
                        <div class="color-picker-group">
                            <input type="color" id="primary-color" value="#3788d8" />
                            <input type="text" id="primary-color-text" value="#3788d8" />
                        </div>
                    </div>
                    <div class="property-item">
                        <label for="form-width">表单宽度</label>
                        <div class="input-group">
                            <input type="text" id="form-width" value="100%" placeholder="如: 100%, 800px" />
                            <select id="form-width-unit">
                                <option value="px">px</option>
                                <option value="%">%</option>
                                <option value="rem">rem</option>
                                <option value="auto">auto</option>
                            </select>
                        </div>
                    </div>
                    <div class="property-item">
                        <label for="form-max-width">最大宽度</label>
                        <input type="text" id="form-max-width" placeholder="如: 800px, none" />
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="columns icon"></i> 布局设置</h4>
                    <div class="property-item">
                        <label for="label-position">标签位置</label>
                        <select id="label-position">
                            <option value="top">顶部</option>
                            <option value="left">左侧</option>
                            <option value="inside">内部（浮动标签）</option>
                            <option value="hidden">隐藏</option>
                        </select>
                    </div>
                    <div class="property-item">
                        <label for="field-spacing">字段间距</label>
                        <input type="range" id="field-spacing" min="0" max="40" value="20" />
                        <span class="range-value">20px</span>
                    </div>
                    <div class="property-item">
                        <label for="form-padding">表单内边距</label>
                        <input type="range" id="form-padding" min="0" max="60" value="20" />
                        <span class="range-value">20px</span>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="paint brush icon"></i> 字段样式</h4>
                    <div class="property-item">
                        <label for="input-border-radius">输入框圆角</label>
                        <input type="range" id="input-border-radius" min="0" max="20" value="4" />
                        <span class="range-value">4px</span>
                    </div>
                    <div class="property-item">
                        <label for="input-border-width">边框粗细</label>
                        <input type="range" id="input-border-width" min="0" max="5" value="1" />
                        <span class="range-value">1px</span>
                    </div>
                    <div class="property-item">
                        <label for="input-height">输入框高度</label>
                        <input type="range" id="input-height" min="30" max="60" value="40" />
                        <span class="range-value">40px</span>
                    </div>
                </div>

                <div class="property-group">
                    <h4><i class="fill drip icon"></i> 颜色配置</h4>
                    <div class="color-grid">
                        <div class="color-item">
                            <label>背景色</label>
                            <input type="color" id="bg-color" value="#ffffff" />
                        </div>
                        <div class="color-item">
                            <label>文字色</label>
                            <input type="color" id="text-color" value="#333333" />
                        </div>
                        <div class="color-item">
                            <label>边框色</label>
                            <input type="color" id="border-color" value="#dddddd" />
                        </div>
                        <div class="color-item">
                            <label>错误色</label>
                            <input type="color" id="error-color" value="#e74c3c" />
                        </div>
                        <div class="color-item">
                            <label>成功色</label>
                            <input type="color" id="success-color" value="#27ae60" />
                        </div>
                        <div class="color-item">
                            <label>警告色</label>
                            <input type="color" id="warning-color" value="#f39c12" />
                        </div>
                    </div>
                </div>
                
                <div class="property-group">
                    <h4><i class="code icon"></i> 自定义CSS</h4>
                    <div class="property-item">
                        <label for="custom-css">CSS代码</label>
                        <textarea id="custom-css" rows="10" placeholder="输入自定义CSS代码" class="code-editor"></textarea>
                        <div class="css-tips">
                            <details>
                                <summary>常用CSS选择器</summary>
                                <ul>
                                    <li><code>.uform</code> - 表单容器</li>
                                    <li><code>.uform-field</code> - 字段容器</li>
                                    <li><code>.uform-label</code> - 字段标签</li>
                                    <li><code>.uform-input</code> - 输入框</li>
                                    <li><code>.uform-submit</code> - 提交按钮</li>
                                    <li><code>.uform-error</code> - 错误消息</li>
                                </ul>
                            </details>
                        </div>
                    </div>
                    <div class="property-item">
                        <button type="button" id="preview-css" class="btn btn-primary">
                            <i class="eye icon"></i> 预览样式
                        </button>
                        <button type="button" id="reset-css" class="btn btn-default">
                            <i class="refresh icon"></i> 重置
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 底部操作栏 -->
    <div class="form-actions">
        <div class="actions-left">
            <button id="save-draft" class="btn btn-default">
                <i class="save icon"></i>保存草稿
            </button>
            <button id="save-template" class="btn btn-default">
                <i class="clone icon"></i>保存为模板
            </button>
        </div>
        <div class="actions-center">
            <span class="save-status" id="save-status">
                <i class="check icon"></i> 已保存
            </span>
        </div>
        <div class="actions-right">
            <button id="preview-form-btn" class="btn btn-info">
                <i class="eye icon"></i>预览表单
            </button>
            <button id="publish-form" class="btn btn-primary">
                <i class="paper plane icon"></i>发布表单
            </button>
            <?php if ($form && $form['status'] === 'published'): ?>
            <button id="get-code" class="btn btn-success">
                <i class="code icon"></i>获取代码
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 批量添加选项弹窗 -->
<div id="bulk-options-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>批量添加选项</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="bulk-options-tabs">
                <button class="bulk-tab active" data-tab="text">文本输入</button>
                <button class="bulk-tab" data-tab="preset">预设选项</button>
            </div>
            <div class="bulk-tab-content active" id="text-tab">
                <textarea id="bulk-options-text" rows="10" placeholder="每行一个选项，格式：&#10;选项1&#10;选项2|value2&#10;选项3"></textarea>
                <div class="bulk-tips">
                    <p>格式说明：</p>
                    <ul>
                        <li>每行一个选项</li>
                        <li>使用 | 分隔显示文本和值</li>
                        <li>例：北京|beijing</li>
                    </ul>
                </div>
            </div>
            <div class="bulk-tab-content" id="preset-tab">
                <div class="preset-categories">
                    <button class="preset-btn" data-preset="yesno">是/否</button>
                    <button class="preset-btn" data-preset="gender">性别</button>
                    <button class="preset-btn" data-preset="rating">满意度</button>
                    <button class="preset-btn" data-preset="education">学历</button>
                    <button class="preset-btn" data-preset="cities">主要城市</button>
                    <button class="preset-btn" data-preset="provinces">省份</button>
                    <button class="preset-btn" data-preset="countries">国家</button>
                    <button class="preset-btn" data-preset="numbers">数字 1-10</button>
                </div>
                <div class="preset-preview" id="preset-preview">
                    选择一个预设类别查看选项
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button id="apply-bulk-options" class="btn btn-primary">应用</button>
            <button class="btn btn-default modal-close">取消</button>
        </div>
    </div>
</div>

<!-- 表单预览弹窗 -->
<div id="preview-modal" class="modal large-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>表单预览</h3>
            <div class="preview-controls">
                <button id="preview-desktop-btn" class="preview-device active" data-device="desktop">
                    <i class="desktop icon"></i>
                </button>
                <button id="preview-tablet-btn" class="preview-device" data-device="tablet">
                    <i class="tablet alternate icon"></i>
                </button>
                <button id="preview-mobile-btn" class="preview-device" data-device="mobile">
                    <i class="mobile alternate icon"></i>
                </button>
            </div>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="preview-container" id="preview-container">
                <iframe id="preview-iframe" src="about:blank" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- 代码获取弹窗 -->
<div id="code-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>获取表单代码</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="code-tabs">
                <button class="code-tab active" data-tab="link">直接链接</button>
                <button class="code-tab" data-tab="iframe">iframe嵌入</button>
                <button class="code-tab" data-tab="shortcode">短代码</button>
                <button class="code-tab" data-tab="api">API接口</button>
            </div>
            <div class="code-content">
                <div class="code-tab-content active" id="link-tab">
                    <label>表单访问链接：</label>
                    <div class="code-input-group">
                        <input type="text" id="form-link" readonly>
                        <button id="copy-link" class="btn">复制链接</button>
                    </div>
                    <div class="code-example">
                        <p>用户可以通过此链接直接访问表单</p>
                    </div>
                </div>
                <div class="code-tab-content" id="iframe-tab">
                    <label>iframe嵌入代码：</label>
                    <div class="code-input-group">
                        <textarea id="iframe-code" rows="4" readonly></textarea>
                        <button id="copy-iframe" class="btn">复制代码</button>
                    </div>
                    <div class="iframe-options">
                        <label>宽度: <input type="text" id="iframe-width" value="100%"></label>
                        <label>高度: <input type="text" id="iframe-height" value="600px"></label>
                        <button id="update-iframe" class="btn btn-small">更新代码</button>
                    </div>
                </div>
                <div class="code-tab-content" id="shortcode-tab">
                    <label>Typecho短代码：</label>
                    <div class="code-input-group">
                        <input type="text" id="shortcode" readonly>
                        <button id="copy-shortcode" class="btn">复制短代码</button>
                    </div>
                    <div class="code-example">
                        <p>在文章或页面中使用：</p>
                        <code>[uforms name="contact"]</code><br>
                        <code>[uforms id="123"]</code>
                    </div>
                </div>
                <div class="code-tab-content" id="api-tab">
                    <label>API提交地址：</label>
                    <div class="code-input-group">
                        <input type="text" id="api-url" readonly>
                        <button id="copy-api" class="btn">复制API</button>
                    </div>
                    <div class="api-docs">
                        <h5>API文档：</h5>
                        <p><strong>方法：</strong>POST</p>
                        <p><strong>内容类型：</strong>application/json</p>
                        <p><strong>请求示例：</strong></p>
                        <pre><code>{
  "field_name_1": "value1",
  "field_name_2": "value2"
}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 全局变量
window.uformsConfig = {
    formId: <?php echo $form_id ? (int)$form_id : 'null'; ?>,
    ajaxUrl: '<?php echo $options->adminUrl . 'extending.php?panel=' . safe_urlencode('Uforms/admin/ajax.php'); ?>',
    pluginUrl: '<?php echo $options->pluginUrl; ?>/Uforms',
    siteUrl: '<?php echo $options->siteUrl; ?>',
    existingFields: <?php echo json_encode($fields); ?>,
    existingConfig: <?php echo $form ? json_encode(json_decode($form['config'], true)) : '{}'; ?>,
    existingSettings: <?php echo $form ? json_encode(json_decode($form['settings'], true)) : '{}'; ?>
};

// 代码标签切换功能
document.addEventListener('DOMContentLoaded', function() {
    // 代码标签切换
    const codeTabs = document.querySelectorAll('.code-tab');
    codeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // 更新激活的标签
            document.querySelectorAll('.code-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // 显示对应的内容
            document.querySelectorAll('.code-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });
    
    // 关闭模态框
    const closeButtons = document.querySelectorAll('.modal-close');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // 点击模态框外部关闭
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
    
    // 更新iframe代码
    const updateIframeBtn = document.getElementById('update-iframe');
    if (updateIframeBtn) {
        updateIframeBtn.addEventListener('click', function() {
            const width = document.getElementById('iframe-width').value || '100%';
            const height = document.getElementById('iframe-height').value || '600px';
            const formLink = document.getElementById('form-link').value;
            
            const iframeCode = `<iframe src="${formLink}" width="${width}" height="${height}" frameborder="0"></iframe>`;
            document.getElementById('iframe-code').value = iframeCode;
        });
    }
    
    // 复制功能
    const copyButtons = document.querySelectorAll('[id^="copy-"]');
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.id.replace('copy-', '');
            let targetElement;
            
            switch (targetId) {
                case 'link':
                    targetElement = document.getElementById('form-link');
                    break;
                case 'iframe':
                    targetElement = document.getElementById('iframe-code');
                    break;
                case 'shortcode':
                    targetElement = document.getElementById('shortcode');
                    break;
                case 'api':
                    targetElement = document.getElementById('api-url');
                    break;
            }
            
            if (targetElement) {
                targetElement.select();
                document.execCommand('copy');
                
                // 显示复制成功提示
                const originalText = this.textContent;
                this.textContent = '已复制';
                setTimeout(() => {
                    this.textContent = originalText;
                }, 2000);
            }
        });
    });
});
</script>

<?php
// Helper to resolve asset URLs correctly
function uforms_asset($path) {
    echo htmlspecialchars($path);
}
?>
<script src="<?php uforms_asset($options->pluginUrl . '/Uforms/assets/js/sortable.min.js'); ?>"></script>
<script src="<?php uforms_asset($options->pluginUrl . '/Uforms/assets/js/uformsbuilder.js'); ?>"></script>
<link rel="stylesheet" href="<?php uforms_asset($options->pluginUrl . '/Uforms/assets/css/create.css'); ?>">
