<?php
/** @var string $title 页面标题 */
/** @var string $contentHtml 主体内容 */
/** @var string $csrf CSRF 令牌 */
$csrf = isset($csrf) ? $csrf : \App\Core\Auth::csrfToken();
$route = isset($_GET['r']) ? $_GET['r'] : 'dashboard/index';
$nav = array(
    '概览' => array('dashboard/index', 'grid'),
    '软件管理' => array('software/index', 'box'),
    '卡密管理' => array('card/index', 'key'),
    '请求日志' => array('log/requests', 'list'),
    '数据统计' => array('log/stats', 'chart'),
    'API 密钥' => array('admin/apiKeys', 'api'),
    '地址转换' => array('convert/index', 'swap'),
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= \App\Core\Util::e($title) ?> · 授权管理</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
            <span class="brand-name">授权管理</span>
        </div>
        <nav class="nav">
            <?php foreach ($nav as $label => $item): ?>
                <a class="nav-item <?= strpos($route, $item[0]) === 0 ? 'is-active' : '' ?>" href="index.php?r=<?= $item[0] ?>">
                    <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <?php if ($item[1] === 'grid'): ?>
                            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                        <?php elseif ($item[1] === 'box'): ?>
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/>
                        <?php elseif ($item[1] === 'key'): ?>
                            <circle cx="8" cy="15" r="4"/><path d="M10.85 12.15L21 2m-5 5l3 3m-6 0l3 3"/>
                        <?php elseif ($item[1] === 'list'): ?>
                            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
                        <?php elseif ($item[1] === 'chart'): ?>
                            <path d="M3 3v18h18M7 15l4-5 4 3 5-7"/>
                        <?php elseif ($item[1] === 'api'): ?>
                            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3M8 21H5a2 2 0 0 1-2-2v-3m18 0v3a2 2 0 0 1-2 2h-3M9 9h6v6H9z"/>
                        <?php else: ?>
                            <path d="M7 7h14l-3-3m3 3l-3 3M17 17H3l3-3m-3 3l3 3"/>
                        <?php endif; ?>
                    </svg>
                    <span><?= $label ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <div class="account">
                <span class="avatar" aria-hidden="true"><?= \App\Core\Util::e(mb_substr(\App\Core\Auth::name(), 0, 1)) ?></span>
                <div class="account-meta">
                    <span class="account-name"><?= \App\Core\Util::e(\App\Core\Auth::name()) ?></span>
                    <span class="account-role"><?= \App\Core\Auth::isSuper() ? '超级管理员' : '管理员' ?></span>
                </div>
                <a class="signout" href="index.php?r=admin/logout" title="退出">
                    <svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                </a>
            </div>
        </div>
    </aside>
    <main class="main">
        <header class="topbar">
            <h1 class="page-title"><?= \App\Core\Util::e($title) ?></h1>
            <div class="topbar-right">
                <span class="env-tag">内部测试</span>
            </div>
        </header>
        <div class="content">
            <?= $contentHtml ?>
        </div>
    </main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
