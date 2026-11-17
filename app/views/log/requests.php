<?php /** @var array $list @var int $total @var int $page @var int $pages @var array $filter @var array $softwares */ ?>
<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">请求日志</h2>
            <p class="section-desc">共 <?= (int)$total ?> 条</p>
        </div>
    </div>
    <form class="filter-bar panel" method="get">
        <input type="hidden" name="r" value="log/requests">
        <input type="text" name="card_id" value="<?= \App\Core\Util::e($filter['card_id']) ?>" placeholder="卡号">
        <select name="software_id">
            <option value="">全部软件</option>
            <?php foreach ($softwares as $sw): ?>
                <option value="<?= $sw['id'] ?>" <?= (string)$filter['software_id'] === (string)$sw['id'] ? 'selected' : '' ?>><?= \App\Core\Util::e($sw['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="result">
            <option value="">全部结果</option>
            <option value="1" <?= (string)$filter['result'] === '1' ? 'selected' : '' ?>>成功</option>
            <option value="0" <?= (string)$filter['result'] === '0' ? 'selected' : '' ?>>失败</option>
        </select>
        <input type="date" name="start" value="<?= \App\Core\Util::e($filter['start']) ?>">
        <input type="date" name="end" value="<?= \App\Core\Util::e($filter['end']) ?>">
        <button class="btn btn-ghost" type="submit">筛选</button>
    </form>
    <div class="panel table-wrap">
        <table class="table table-dense">
            <thead>
            <tr><th>时间</th><th>卡号</th><th>软件</th><th>设备</th><th>动作</th><th>扣次</th><th>结果</th><th>IP</th></tr>
            </thead>
            <tbody>
            <?php if (!$list): ?>
                <tr><td colspan="8" class="empty">暂无日志</td></tr>
            <?php else: foreach ($list as $log): ?>
                <tr>
                    <td class="mono dim"><?= \App\Core\Util::date($log['created_at']) ?></td>
                    <td class="mono"><?= \App\Core\Util::e($log['card_id']) ?></td>
                    <td><?= \App\Core\Util::e($log['software_identifier']) ?></td>
                    <td class="mono ellipsis" title="<?= \App\Core\Util::e($log['device_id']) ?>"><?= \App\Core\Util::e($log['device_id']) ?></td>
                    <td><code class="tag-code"><?= \App\Core\Util::e($log['action']) ?></code></td>
                    <td class="mono"><?= $log['action'] === 'ticket' && $log['result'] == 1 ? (int)$log['remain_before'] . '→' . (int)$log['remain_after'] : '—' ?></td>
                    <td>
                        <?php if ((int)$log['result'] === 1): ?>
                            <span class="status"><i class="dot dot-on"></i>成功</span>
                        <?php else: ?>
                            <span class="status"><i class="dot dot-err"></i><?= (int)$log['error_code'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="mono dim"><?= \App\Core\Util::e($log['ip']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a class="<?= $i === $page ? 'is-current' : '' ?>" href="index.php?r=log/requests&page=<?= $i ?>&card_id=<?= urlencode($filter['card_id']) ?>&software_id=<?= urlencode($filter['software_id']) ?>&result=<?= urlencode($filter['result']) ?>&start=<?= urlencode($filter['start']) ?>&end=<?= urlencode($filter['end']) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>
