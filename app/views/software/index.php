<?php /** @var array $list @var string $csrf */ ?>
<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">软件列表</h2>
            <p class="section-desc">每个软件拥有独立的第二层密钥对</p>
        </div>
        <a class="btn btn-primary" href="index.php?r=software/create">新增软件</a>
    </div>
    <div class="panel table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>名称</th><th>软件标识</th><th>默认模式</th><th>密钥标识</th><th>状态</th><th class="right">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$list): ?>
                <tr><td colspan="6" class="empty">还没有软件，点击右上角新增</td></tr>
            <?php else: foreach ($list as $s): ?>
                <tr>
                    <td class="strong"><?= \App\Core\Util::e($s['name']) ?></td>
                    <td class="mono"><?= \App\Core\Util::e($s['identifier']) ?></td>
                    <td><?= $s['default_mode'] === 'times' ? '次数型' : '时效型' ?></td>
                    <td class="mono dim"><?= \App\Core\Util::e($s['b_key_id']) ?></td>
                    <td>
                        <span class="status"><i class="dot <?= (int)$s['status'] === 1 ? 'dot-on' : 'dot-off' ?>"></i><?= (int)$s['status'] === 1 ? '启用' : '停用' ?></span>
                    </td>
                    <td class="right ops">
                        <a href="index.php?r=software/edit&id=<?= $s['id'] ?>">编辑</a>
                        <a href="index.php?r=software/publicKey&id=<?= $s['id'] ?>">公钥 B</a>
                        <a href="javascript:;" class="op-danger" data-rotate="<?= $s['id'] ?>">轮换密钥</a>
                        <a href="javascript:;" data-status="<?= $s['id'] ?>" data-val="<?= (int)$s['status'] === 1 ? 0 : 1 ?>"><?= (int)$s['status'] === 1 ? '停用' : '启用' ?></a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="panel note-panel">
        <p class="note-title">两把公钥的用途</p>
        <p class="note-text">全局公钥 A 内置于所有软件，用于离线解 K 码；每个软件的公钥 B 随该软件 K 码下发，用于解密业务包。轮换某软件的 B 密钥后，需要重新生成该软件的 K 码。</p>
        <a class="link" href="index.php?r=software/publicA">下载全局公钥 A</a>
    </div>
</section>
<script>
var CSRF = '<?= \App\Core\Util::e($csrf) ?>';
document.querySelectorAll('[data-status]').forEach(function (el) {
    el.addEventListener('click', function () {
        post('index.php?r=software/status', {id: el.dataset.status, status: el.dataset.val});
    });
});
document.querySelectorAll('[data-rotate]').forEach(function (el) {
    el.addEventListener('click', function () {
        if (confirm('轮换后旧 K 码将失效，需要重新生成该软件的 K 码。继续？')) {
            post('index.php?r=software/rotate', {id: el.dataset.rotate});
        }
    });
});
function post(url, data) {
    data.csrf = CSRF;
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    fetch(url, {method: 'POST', body: fd}).then(function (r) { return r.json(); })
        .then(function (j) { alert(j.msg); if (j.code === 0) location.reload(); });
}
</script>
