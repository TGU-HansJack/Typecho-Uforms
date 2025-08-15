<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<div class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2><?php echo $formId ? '编辑表单' : '创建表单'; ?></h2>
        </div>
        
        <div class="row typecho-page-main">
            <div class="col-mb-12 col-tb-8 col-tb-offset-2">
                <div class="typecho-form" id="form-editor">
                    <form method="post" action="<?php $options->pluginUrl('FormBuilder/Action/save'); ?>">
                        <?php if ($formId): ?>
                        <input type="hidden" name="form_id" value="<?php echo $formId; ?>" />
                        <?php endif; ?>
                        
                        <ul class="typecho-option">
                            <li>
                                <label class="typecho-label" for="form-title">表单标题 *</label>
                                <input type="text" id="form-title" name="title" class="text" 
                                       value="<?php echo htmlspecialchars($formData['title'] ?? ''); ?>" required />
                                <p class="description">输入表单的标题</p>
                            </li>
                        </ul>
                        
                        <ul class="typecho-option">
                            <li>
                                <label class="typecho-label" for="form-description">表单描述</label>
                                <textarea id="form-description" name="description" class="text" rows="3"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                                <p class="description">输入表单的描述信息</p>
                            </li>
                        </ul>
                        
                        <ul class="typecho-option">
                            <li>
                                <label class="typecho-label">表单字段</label>
                                <div id="form-fields">
                                    <?php if (!empty($formData['fields'])): ?>
                                        <?php foreach (json_decode($formData['fields'], true) as $index => $field): ?>
                                        <div class="form-field" data-index="<?php echo $index; ?>">
                                            <div class="field-header">
                                                <span class="field-title"><?php echo htmlspecialchars($field['label']); ?></span>
                                                <div class="field-actions">
                                                    <button type="button" class="btn btn-xs edit-field">编辑</button>
                                                    <button type="button" class="btn btn-xs delete-field">删除</button>
                                                    <span class="drag-handle">≡</span>
                                                </div>
                                            </div>
                                            <div class="field-content">
                                                <p><strong>类型:</strong> <?php echo $field['type']; ?></p>
                                                <?php if (!empty($field['required'])): ?>
                                                <p><strong>必填:</strong> 是</p>
                                                <?php endif; ?>
                                                <?php if (!empty($field['placeholder'])): ?>
                                                <p><strong>占位符:</strong> <?php echo htmlspecialchars($field['placeholder']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($field['options'])): ?>
                                                <p><strong>选项:</strong> <?php echo htmlspecialchars($field['options']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <input type="hidden" name="fields[]" value="<?php echo htmlspecialchars(json_encode($field)); ?>" />
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="add-field" class="btn btn-s">添加字段</button>
                                <p class="description">点击添加字段来构建表单</p>
                            </li>
                        </ul>
                        
                        <ul class="typecho-option">
                            <li>
                                <label class="typecho-label">表单设置</label>
                                <div>
                                    <input type="checkbox" id="enable-captcha" name="enable_captcha" value="1" 
                                           <?php echo !empty($formData['enable_captcha']) ? 'checked' : ''; ?> />
                                    <label for="enable-captcha">启用验证码</label>
                                </div>
                                <div style="margin-top: 10px;">
                                    <input type="checkbox" id="email-notification" name="email_notification" value="1"
                                           <?php echo !empty($formData['email_notification']) ? 'checked' : ''; ?> />
                                    <label for="email-notification">邮件通知</label>
                                </div>
                                <div style="margin-top: 10px;">
                                    <label for="notification-email">通知邮箱:</label>
                                    <input type="email" id="notification-email" name="notification_email" class="text"
                                           value="<?php echo htmlspecialchars($formData['notification_email'] ?? ''); ?>" />
                                </div>
                            </li>
                        </ul>
                        
                        <ul class="typecho-option typecho-option-submit">
                            <li>
                                <button type="submit" class="btn primary"><?php echo $formId ? '更新表单' : '创建表单'; ?></button>
                                <a href="<?php $options->pluginUrl('FormBuilder/Action/list'); ?>" class="btn">返回列表</a>
                                <?php if ($formId): ?>
                                <a href="<?php $options->pluginUrl('FormBuilder/Action/preview/' . $formId); ?>" class="btn" target="_blank">预览表单</a>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 字段编辑模态框 -->
<div id="field-modal" class="typecho-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>编辑字段</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="field-form">
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="field-label">字段标签 *</label>
                        <input type="text" id="field-label" name="label" class="text" required />
                    </li>
                </ul>
                
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="field-type">字段类型 *</label>
                        <select id="field-type" name="type" class="text" required>
                            <option value="text">单行文本</option>
                            <option value="textarea">多行文本</option>
                            <option value="email">邮箱</option>
                            <option value="tel">电话</option>
                            <option value="number">数字</option>
                            <option value="select">下拉选择</option>
                            <option value="radio">单选按钮</option>
                            <option value="checkbox">复选框</option>
                            <option value="file">文件上传</option>
                            <option value="date">日期</option>
                            <option value="time">时间</option>
                        </select>
                    </li>
                </ul>
                
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="field-name">字段名称</label>
                        <input type="text" id="field-name" name="name" class="text" />
                        <p class="description">留空将自动生成</p>
                    </li>
                </ul>
                
                <ul class="typecho-option">
                    <li>
                        <label class="typecho-label" for="field-placeholder">占位符</label>
                        <input type="text" id="field-placeholder" name="placeholder" class="text" />
                    </li>
                </ul>
                
                <ul class="typecho-option" id="field-options-wrapper" style="display: none;">
                    <li>
                        <label class="typecho-label" for="field-options">选项</label>
                        <textarea id="field-options" name="options" class="text" rows="3"></textarea>
                        <p class="description">每行一个选项，格式：值|显示文本</p>
                    </li>
                </ul>
                
                <ul class="typecho-option">
                    <li>
                        <input type="checkbox" id="field-required" name="required" value="1" />
                        <label for="field-required">必填字段</label>
                    </li>
                </ul>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" id="save-field" class="btn primary">保存</button>
            <button type="button" class="btn cancel-field">取消</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php $options->pluginUrl('FormBuilder/assets/css/admin.css'); ?>" />
<script src="<?php $options->pluginUrl('FormBuilder/assets/js/admin.js'); ?>"></script>

<?php $this->need('footer.php'); ?>
