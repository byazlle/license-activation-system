<?php /** @var array $list */ ?>
<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">操作日志</h2>
            <p class="section-desc">后台关键动作</p>
        </div>
    </div>
    <div class="panel table-wrap">
        <table class="table table-dense">
            <thead><tr><th>时间</th><th>操作人</th><th>动作</th><th>对象</th><th>详情</th><th>IP</th></tr></thead>
            <tbody>
            <?php if (!$list): ?>
                <tr><td colspan="6" class="empty">暂无记录</td></tr>
            <?php else: foreach ($list as $log): ?>
                <tr>
                    <td class="mono dim"><?= \App\Core\Util::date($log['created_at']) ?></td>
                    <td><?= \App\Core\Util::e($log['admin_name']) ?></td>
                    <td><code class="tag-code"><?= \App\Core\Util::e($log['action']) ?></code></td>
                    <td class="mono"><?= \App\Core\Util::e($log['target']) ?></td>
                    <td class="mono dim ellipsis"><?= \App\Core\Util::e($log['detail']) ?></td>
                    <td class="mono dim"><?= \App\Core\Util::e($log['ip']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</section>
