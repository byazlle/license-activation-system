<?php /** @var string $error @var bool $installed @var string $csrf */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>登录 · 授权管理</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-head">
            <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
            <h1 class="auth-title">授权管理</h1>
            <p class="auth-sub">卡密发放与激活校验</p>
        </div>
        <?php if (!empty($installed)): ?>
            <div class="notice notice-ok">安装完成，请登录</div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="notice notice-err"><?= \App\Core\Util::e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="index.php?r=admin/login" class="auth-form">
            <input type="hidden" name="csrf" value="<?= \App\Core\Util::e($csrf) ?>">
            <label class="field">
                <span class="field-label">用户名</span>
                <input type="text" name="username" autocomplete="username" required autofocus>
            </label>
            <label class="field">
                <span class="field-label">密码</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="btn btn-primary btn-block">登录</button>
        </form>
    </div>
    <p class="auth-foot">软件激活与授权管理系统</p>
</div>
</body>
</html>
