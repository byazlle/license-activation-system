<?php /** @var array $list @var array $softwares @var array $filter @var int $page @var int $pages @var int $total @var string $csrf */ ?>
<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">卡密列表</h2>
            <p class="section-desc">共 <?= (int)$total ?> 张</p>
        </div>
        <a class="btn btn-primary" href="index.php?r=card/create">生成卡密</a>
    </div>
    <form class="filter-bar panel" method="get">
        <input type="hidden" name="r" value="card/index">
        <input type="text" name="keyword" value="<?= \App\Core\Util::e($filter['keyword']) ?>" placeholder="卡号或备注">
        <select name="software_id">
            <option value="">全部软件</option>
            <?php foreach ($softwares as $sw): ?>
                <option value="<?= $sw['id'] ?>" <?= (string)$filter['software_id'] === (string)$sw['id'] ? 'selected' : '' ?>><?= \App\Core\Util::e($sw['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">全部状态</option>
            <option value="1" <?= (string)$filter['status'] === '1' ? 'selected' : '' ?>>启用</option>
            <option value="0" <?= (string)$filter['status'] === '0' ? 'selected' : '' ?>>停用</option>
        </select>
        <button class="btn btn-ghost" type="submit">筛选</button>
    </form>
    <div class="panel table-wrap">
        <table class="table">
            <thead>
            <tr><th>卡号</th><th>模式</th><th>到期 / 剩余</th><th>已用</th><th>状态</th><th class="right">操作</th></tr>
            </thead>
            <tbody>
            <?php if (!$list): ?>
                <tr><td colspan="6" class="empty">没有符合条件的卡密</td></tr>
            <?php else: foreach ($list as $card): ?>
                <tr>
                    <td class="mono strong"><?= \App\Core\Util::e($card['card_id']) ?></td>
                    <td><?= $card['mode'] === 'times' ? '次数型' : '时效型' ?></td>
                    <td class="mono">
                        <?php if ($card['mode'] === 'time'): ?>
                            <?= \App\Core\Util::date($card['expire_at']) ?>
                        <?php else: ?>
                            <?= (int)$card['remain'] ?> / <?= (int)$card['total_times'] ?>
                        <?php endif; ?>
                    </td>
                    <td class="mono"><?= (int)$card['used'] ?></td>
                    <td>
                        <span class="status"><i class="dot <?= (int)$card['status'] === 1 ? 'dot-on' : 'dot-off' ?>"></i><?= (int)$card['status'] === 1 ? '启用' : '停用' ?></span>
                    </td>
                    <td class="right ops">
                        <a href="index.php?r=card/detail&id=<?= $card['id'] ?>">详情</a>
                        <a href="javascript:;" data-extend="<?= $card['id'] ?>">延期</a>
                        <a href="javascript:;" data-times="<?= $card['id'] ?>">改次数</a>
                        <a href="javascript:;" data-status="<?= $card['id'] ?>" data-val="<?= (int)$card['status'] === 1 ? 0 : 1 ?>"><?= (int)$card['status'] === 1 ? '停用' : '启用' ?></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a class="<?= $i === $page ? 'is-current' : '' ?>" href="index.php?r=card/index&page=<?= $i ?>&keyword=<?= urlencode($filter['keyword']) ?>&software_id=<?= urlencode($filter['software_id']) ?>&status=<?= urlencode($filter['status']) ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>
<script>
var CSRF = '<?= \App\Core\Util::e($csrf) ?>';
function post(url, d, cb) {
    d.csrf = CSRF; var fd = new FormData();
    Object.keys(d).forEach(function (k) { fd.append(k, d[k]); });
    fetch(url, {method: 'POST', body: fd}).then(function (r) { return r.json(); }).then(function (j) { alert(j.msg); if (j.code === 0) { location.reload(); } });
}
document.querySelectorAll('[data-status]').forEach(function (el) {
    el.addEventListener('click', function () { post('index.php?r=card/status', {id: el.dataset.status, status: el.dataset.val}); });
});
document.querySelectorAll('[data-extend]').forEach(function (el) {
    el.addEventListener('click', function () {
        var days = prompt('延期天数', '30');
        if (days && +days > 0) post('index.php?r=card/extend', {id: el.dataset.extend, days: days});
    });
});
document.querySelectorAll('[data-times]').forEach(function (el) {
    el.addEventListener('click', function () {
        var n = prompt('新的剩余次数', '0');
        if (n !== null && +n >= 0) post('index.php?r=card/setTimes', {id: el.dataset.times, remain: n});
    });
});
</script>
