<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['title']); ?> - <?php echo Helper::options()->title; ?></title>
    <link rel="stylesheet" href="<?php echo Helper::options()->pluginUrl; ?>/Uforms/assets/css/uforms.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .form-title {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .form-description {
            margin: 0;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .form-body {
            padding: 40px;
        }
        
        .form-footer {
            background: #f8f9fa;
            padding: 20px 40px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .powered-by {
            font-size: 12px;
            color: #666;
        }
        
        .powered-by a {
            color: #3788d8;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .form-body {
                padding: 30px 20px;
            }
            
            .form-footer {
                padding: 20px;
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
    
    <?php if (!empty($config['custom_css'])): ?>
    <style><?php echo $config['custom_css']; ?></style>
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <div class="form-header">
            <h1 class="form-title"><?php echo htmlspecialchars($form['title']); ?></h1>
            <?php if (!empty($form['description'])): ?>
            <p class="form-description"><?php echo nl2br(htmlspecialchars($form['description'])); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="form-body">
            <?php echo UformsFront::renderFormHTML($form, $fields, $config, $settings, 'standalone'); ?>
        </div>
        
        <div class="form-footer">
            <div class="form-stats">
                <span class="submissions-count">
                    <i class="icon-users"></i> 
                    已有 <?php echo $form['submit_count']; ?> 人提交
                </span>
            </div>
            
            <div class="powered-by">
                Powered by <a href="https://github.com/typecho/typecho" target="_blank">Typecho</a> & 
                <a href="#" target="_blank">Uforms</a>
            </div>
        </div>
    </div>
    
    <script src="<?php echo Helper::options()->pluginUrl; ?>/Uforms/assets/js/uforms.js"></script>
    
    <?php if (!empty($settings['enable_analytics'])): ?>
    <script>
        // 表单访问统计
        fetch('<?php echo Helper::options()->adminUrl; ?>extending.php?panel=Uforms%2Fajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'track_view',
                form_id: '<?php echo $form['id']; ?>'
            })
        });
    </script>
    <?php endif; ?>
    
    <?php if (!empty($settings['enable_recaptcha']) && !empty($settings['recaptcha_site_key'])): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</body>
</html>
