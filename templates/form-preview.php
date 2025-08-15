<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>

<div class="uforms-preview">
    <div class="form-header">
        <?php if (!empty($form_data['title'])): ?>
        <h3 class="form-title"><?php echo escapeHtml($form_data['title']); ?></h3>
        <?php endif; ?>
        
        <?php if (!empty($form_data['description'])): ?>
        <div class="form-description"><?php echo escapeHtml($form_data['description']); ?></div>
        <?php endif; ?>
    </div>
    
    <form class="uform-preview" method="post">
        <div class="preview-notice">
            <i class="icon-info"></i>
            这是表单预览，所有字段均已禁用
        </div>
        
        <?php if (!empty($fields)): ?>
        <?php foreach ($fields as $field): ?>
        <div class="form-group field-<?php echo $field['type']; ?><?php echo !empty($field['required']) ? ' required' : ''; ?>">
            <?php if ($field['type'] !== 'hidden'): ?>
            <label class="form-label">
                <?php echo escapeHtml($field['label']); ?>
                <?php if (!empty($field['required'])): ?>
                <span class="required-mark">*</span>
                <?php endif; ?>
            </label>
            <?php endif; ?>
            
            <?php
            $field_config = $field['config'] ?? array();
            switch ($field['type']):
                case 'text':
                case 'email':
                case 'tel':
                case 'url':
                case 'password':
            ?>
            <input type="<?php echo $field['type']; ?>" 
                   name="<?php echo escapeHtml($field['name']); ?>"
                   placeholder="<?php echo escapeHtml($field_config['placeholder'] ?? ''); ?>"
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   <?php if (!empty($field_config['maxlength'])): ?>maxlength="<?php echo intval($field_config['maxlength']); ?>"<?php endif; ?>
                   class="form-input" disabled>
            
            <?php break; case 'number': ?>
            <input type="number" 
                   name="<?php echo escapeHtml($field['name']); ?>"
                   placeholder="<?php echo escapeHtml($field_config['placeholder'] ?? ''); ?>"
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   <?php if (!empty($field_config['min'])): ?>min="<?php echo floatval($field_config['min']); ?>"<?php endif; ?>
                   <?php if (!empty($field_config['max'])): ?>max="<?php echo floatval($field_config['max']); ?>"<?php endif; ?>
                   <?php if (!empty($field_config['step'])): ?>step="<?php echo floatval($field_config['step']); ?>"<?php endif; ?>
                   class="form-input" disabled>
            
            <?php break; case 'range': ?>
            <input type="range" 
                   name="<?php echo escapeHtml($field['name']); ?>"
                   value="<?php echo escapeHtml($field_config['default'] ?? '0'); ?>"
                   <?php if (!empty($field_config['min'])): ?>min="<?php echo floatval($field_config['min']); ?>"<?php endif; ?>
                   <?php if (!empty($field_config['max'])): ?>max="<?php echo floatval($field_config['max']); ?>"<?php endif; ?>
                   <?php if (!empty($field_config['step'])): ?>step="<?php echo floatval($field_config['step']); ?>"<?php endif; ?>
                   class="form-range" disabled>
            <output class="range-output">
                <?php echo escapeHtml($field_config['default'] ?? '0'); ?>
            </output>
            
            <?php break; case 'textarea': ?>
            <textarea name="<?php echo escapeHtml($field['name']); ?>"
                      rows="<?php echo intval($field_config['rows'] ?? 4); ?>"
                      placeholder="<?php echo escapeHtml($field_config['placeholder'] ?? ''); ?>"
                      <?php echo !empty($field['required']) ? 'required' : ''; ?>
                      <?php if (!empty($field_config['maxlength'])): ?>maxlength="<?php echo intval($field_config['maxlength']); ?>"<?php endif; ?>
                      class="form-textarea" disabled></textarea>
            
            <?php break; case 'select': ?>
            <select name="<?php echo escapeHtml($field['name']); ?><?php echo !empty($field_config['multiple']) ? '[]' : ''; ?>"
                    <?php echo !empty($field['required']) ? 'required' : ''; ?>
                    <?php echo !empty($field_config['multiple']) ? 'multiple' : ''; ?>
                    class="form-select" disabled>
                
                <?php if (empty($field_config['multiple'])): ?>
                <option value=""><?php echo escapeHtml($field_config['placeholder'] ?? '请选择...'); ?></option>
                <?php endif; ?>
                
                <?php if (!empty($field_config['options'])): ?>
                    <?php foreach ($field_config['options'] as $option): ?>
                    <option value="<?php echo escapeHtml($option); ?>">
                        <?php echo escapeHtml($option); ?>
                    </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            
            <?php break; case 'radio': ?>
            <div class="radio-group">
                <?php if (!empty($field_config['options'])): ?>
                    <?php foreach ($field_config['options'] as $i => $option): ?>
                    <label class="radio-label">
                        <input type="radio" 
                               name="<?php echo escapeHtml($field['name']); ?>"
                               value="<?php echo escapeHtml($option); ?>"
                               <?php echo !empty($field['required']) ? 'required' : ''; ?>
                               class="form-radio" disabled>
                        <span class="radio-text"><?php echo escapeHtml($option); ?></span>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php break; case 'checkbox': ?>
            <div class="checkbox-group">
                <?php if (!empty($field_config['options'])): ?>
                    <?php foreach ($field_config['options'] as $i => $option): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" 
                               name="<?php echo escapeHtml($field['name']); ?>[]"
                               value="<?php echo escapeHtml($option); ?>"
                               class="form-checkbox" disabled>
                        <span class="checkbox-text"><?php echo escapeHtml($option); ?></span>
                    </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php break; case 'file': ?>
            <input type="file" 
                   name="<?php echo escapeHtml($field['name']); ?><?php echo !empty($field_config['multiple']) ? '[]' : ''; ?>"
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   <?php echo !empty($field_config['multiple']) ? 'multiple' : ''; ?>
                   <?php if (!empty($field_config['accept'])): ?>accept="<?php echo escapeHtml($field_config['accept']); ?>"<?php endif; ?>
                   class="form-file" disabled>
            
            <?php if (!empty($field_config['max_size'])): ?>
            <div class="file-info">最大文件大小: <?php echo formatBytes($field_config['max_size']); ?></div>
            <?php endif; ?>
            
            <?php break; case 'date': ?>
            <input type="date" 
                   name="<?php echo escapeHtml($field['name']); ?>"
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   <?php if (!empty($field_config['min'])): ?>min="<?php echo escapeHtml($field_config['min']); ?>"<?php endif; ?>
                   <?php if (!empty($field_config['max'])): ?>max="<?php echo escapeHtml($field_config['max']); ?>"<?php endif; ?>
                   class="form-date" disabled>
            
            <?php break; case 'datetime': ?>
            <input type="datetime-local" 
                   name="<?php echo escapeHtml($field['name']); ?>"
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   <?php if (!empty($field_config['min'])): ?>min="<?php echo escapeHtml($field_config['min']); ?>"<?php endif; ?>
                   <?php if (!empty($field_config['max'])): ?>max="<?php echo escapeHtml($field_config['max']); ?>"<?php endif; ?>
                   class="form-datetime" disabled>
            
            <?php break; case 'time': ?>
            <input type="time" 
                   name="<?php echo escapeHtml($field['name']); ?>"
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   class="form-time" disabled>
            
            <?php break; case 'color': ?>
            <input type="color" 
                   name="<?php echo escapeHtml($field['name']); ?>"
                   value="<?php echo escapeHtml($field_config['default'] ?? '#000000'); ?>"
                   <?php echo !empty($field['required']) ? 'required' : ''; ?>
                   class="form-color" disabled>
            
            <?php break; case 'hidden': ?>
            <input type="hidden" 
                   name="<?php echo escapeHtml($field['name']); ?>"
                   value="<?php echo escapeHtml($field_config['value'] ?? ''); ?>">
            <div class="hidden-field-info">隐藏字段: <?php echo escapeHtml($field['name']); ?></div>
            
            <?php break; endswitch; ?>
            
            <?php if (!empty($field_config['help_text'])): ?>
            <div class="field-help"><?php echo escapeHtml($field_config['help_text']); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-form">
            <p>表单中没有字段。请从左侧添加字段。</p>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" disabled>
                <?php echo escapeHtml($form_data['submit_text'] ?? '提交'); ?>
            </button>
            <button type="reset" class="btn btn-default" disabled>重置</button>
        </div>
    </form>
</div>

<style>
.uforms-preview {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.preview-notice {
    background: #e3f2fd;
    border: 1px solid #2196f3;
    color: #1976d2;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 20px;
    text-align: center;
}

.preview-notice i {
    margin-right: 5px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group.required .form-label {
    font-weight: 600;
}

.required-mark {
    color: #e74c3c;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-input,
.form-textarea,
.form-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    background-color: #f5f5f5;
}

.radio-group,
.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.radio-label,
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: not-allowed;
}

.form-file {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #f5f5f5;
}

.file-info {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.form-range {
    width: 100%;
    margin: 10px 0;
}

.range-output {
    display: inline-block;
    padding: 2px 8px;
    background: #e9ecef;
    border-radius: 3px;
    font-size: 12px;
    margin-left: 10px;
}

.field-help {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
    font-style: italic;
}

.hidden-field-info {
    font-size: 12px;
    color: #999;
    font-style: italic;
    padding: 5px;
    background: #f8f9fa;
    border-radius: 3px;
}

.form-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: not-allowed;
    font-size: 14px;
    margin-right: 10px;
}

.btn-primary {
    background: #007cba;
    color: white;
}

.btn-default {
    background: #f8f9fa;
    color: #333;
    border: 1px solid #ddd;
}

.empty-form {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}
</style>
