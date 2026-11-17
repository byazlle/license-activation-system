<?php /** @var array $card @var array $stats */ ?>
<section class="section section-narrow">
    <div class="section-head">
        <div>
            <h2 class="section-title">卡详情</h2>
            <p class="section-desc mono"><?= \App\Core\Util::e($card['card_id']) ?></p>
        </div>
        <a class="link" href="index.php?r=card/index">返回列表</a>
    </div>
    <div class="panel split-panel">
        <div class="split-col"><span class="split-num"><?= (int)$stats['total'] ?></span><span class="split-label">请求总数</span></div>
        <div class="split-col"><span class="split-num"><?= (int)$stats['success'] ?></span><span class="split-label">成功</span></div>
        <div class="split-col"><span class="split-num"><?= (int)$stats['activate'] ?></span><span class="split-label">激活</span></div>
        <div class="split-col"><span class="split-num"><?= (int)$stats['ticket'] ?></span><span class="split-label">消费</span></div>
    </div>
    <div class="panel table-wrap mt">
        <table class="table key-value">
            <tbody>
            <tr><th>授权模式</th><td><?= $card['mode'] === 'times' ? '次数型' : '时效型' ?></td></tr>
            <tr><th>状态</th><td><span class="status"><i class="dot <?= (int)$card['status'] === 1 ? 'dot-on' : 'dot-off' ?>"></i><?= (int)$card['status'] === 1 ? '启用' : '停用' ?></span></td></tr>
            <tr><th>到期时间</th><td class="mono"><?= $card['mode'] === 'time' ? \App\Core\Util::date($card['expire_at']) : '—' ?></td></tr>
            <tr><th>剩余 / 总次数</th><td class="mono"><?= $card['mode'] === 'times' ? (int)$card['remain'] . ' / ' . (int)$card['total_times'] : '—' ?></td></tr>
            <tr><th>已消费</th><td class="mono"><?= (int)$card['used'] ?></td></tr>
            <tr><th>绑定设备</th><td class="mono"><?= \App\Core\Util::e($card['device_id'] ?: '—') ?></td></tr>
            <tr><th>首次激活</th><td class="mono"><?= \App\Core\Util::date($card['activated_at']) ?></td></tr>
            <tr><th>最近活跃</th><td class="mono"><?= \App\Core\Util::date($stats['last']) ?></td></tr>
            <tr><th>备注</th><td><?= \App\Core\Util::e($card['remark'] ?: '—') ?></td></tr>
            </tbody>
        </table>
    </div>
    <p class="hint mt"><a class="link" href="index.php?r=log/requests&card_id=<?= urlencode($card['card_id']) ?>">查看这张卡的请求日志</a></p>
</section>
