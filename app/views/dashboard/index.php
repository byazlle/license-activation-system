<?php
/** @var array $data */
$d = $data['req'];
$c = $data['card'];
$logs = $data['adminLogs'];
$stats = array(
    array('卡密总数', $c['total'], '张'),
    array('启用中', $c['active'], '张'),
    array('今日请求', $d['today_total'], '次'),
    array('今日成功', $d['today_success'], '次'),
);
?>
<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">概览</h2>
            <p class="section-desc">卡密与请求的当前状态</p>
        </div>
    </div>
    <div class="stats">
        <?php foreach ($stats as $s): ?>
            <div class="stat">
                <span class="stat-label"><?= $s[0] ?></span>
                <span class="stat-value"><?= (int)$s[1] ?></span>
                <span class="stat-unit"><?= $s[2] ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">卡密分布</h2>
            <p class="section-desc">按授权模式与状态拆分</p>
        </div>
    </div>
    <div class="panel split-panel">
        <div class="split-col">
            <span class="split-num"><?= (int)$c['time_cards'] ?></span>
            <span class="split-label">时效型卡</span>
        </div>
        <div class="split-col">
            <span class="split-num"><?= (int)$c['times_cards'] ?></span>
            <span class="split-label">次数型卡</span>
        </div>
        <div class="split-col">
            <span class="split-num"><?= (int)$c['expired'] ?></span>
            <span class="split-label">已过期</span>
        </div>
        <div class="split-col">
            <span class="split-num"><?= (int)$c['exhausted'] ?></span>
            <span class="split-label">次数耗尽</span>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">最近操作</h2>
            <p class="section-desc">后台关键动作记录</p>
        </div>
        <a class="link" href="index.php?r=log/adminLogs">全部记录</a>
    </div>
    <div class="panel table-wrap">
        <table class="table">
            <thead><tr><th>时间</th><th>操作人</th><th>动作</th><th>对象</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="4" class="empty">暂无记录</td></tr>
            <?php else: foreach ($logs as $log): ?>
                <tr>
                    <td class="mono dim"><?= \App\Core\Util::date($log['created_at']) ?></td>
                    <td><?= \App\Core\Util::e($log['admin_name']) ?></td>
                    <td><code class="tag-code"><?= \App\Core\Util::e($log['action']) ?></code></td>
                    <td class="mono"><?= \App\Core\Util::e($log['target']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
