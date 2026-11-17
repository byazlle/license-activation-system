<?php /** @var string $error */ ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>安装 · 授权管理</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">
<div class="auth-wrap auth-wrap-wide">
    <div class="auth-card">
        <div class="auth-head">
            <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
            <h1 class="auth-title">初始化系统</h1>
            <p class="auth-sub">填写数据库信息，系统将自动建表并生成密钥</p>
        </div>
        <?php if (!empty($error)): ?>
            <div class="notice notice-err"><?= \App\Core\Util::e($error) ?></div>
        <?php endif; ?>
        <form id="installForm" method="post" action="index.php?r=install/run" class="auth-form">
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">数据库主机</span>
                    <input type="text" name="host" value="127.0.0.1" required>
                </label>
                <label class="field">
                    <span class="field-label">端口</span>
                    <input type="text" name="port" value="3306" required>
                </label>
                <label class="field">
                    <span class="field-label">数据库名</span>
                    <input type="text" name="name" placeholder="需提前创建" required>
                </label>
                <label class="field">
                    <span class="field-label">表前缀</span>
                    <input type="text" name="prefix" value="lc_" required>
                </label>
                <label class="field">
                    <span class="field-label">数据库用户</span>
                    <input type="text" name="user" required>
                </label>
                <label class="field">
                    <span class="field-label">数据库密码</span>
                    <input type="password" name="pass">
                </label>
            </div>
            <div class="divider"></div>
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">管理员用户名</span>
                    <input type="text" name="admin_user" required>
                </label>
                <label class="field">
                    <span class="field-label">管理员密码</span>
                    <input type="password" name="admin_pass" required>
                </label>
            </div>
            <div class="form-actions">
                <button type="button" id="testBtn" class="btn btn-ghost">测试连接</button>
                <button type="submit" class="btn btn-primary">安装</button>
            </div>
            <p id="testResult" class="hint"></p>
        </form>
    </div>
    <p class="auth-foot">安装后请在服务器上确认 keys/ 与 config/ 不可通过网页访问</p>
</div>
<script>
document.getElementById('testBtn').addEventListener('click', function () {
    var f = document.getElementById('installForm');
    var d = new FormData(f);
    var out = document.getElementById('testResult');
    out.textContent = '正在测试…';
    fetch('index.php?r=install/test', {method: 'POST', body: d})
        .then(function (r) { return r.json(); })
        .then(function (j) { out.textContent = j.msg; out.className = 'hint ' + (j.ok ? 'hint-ok' : 'hint-err'); })
        .catch(function () { out.textContent = '请求失败'; out.className = 'hint hint-err'; });
});
</script>
</body>
</html>
