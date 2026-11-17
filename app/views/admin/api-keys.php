<?php /** @var array $list @var array $calls @var array $scopes @var string $csrf */ ?>
<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">API 密钥</h2>
            <p class="section-desc">供 AI / 第三方系统调用开放 API，所有管理功能均可通过密钥调用</p>
        </div>
        <a class="link" href="https://github.com/byazlle/license-activation-system/blob/main/docs/D5-%E5%BC%80%E6%94%BEAPI%E4%B8%8EAI%E9%9B%86%E6%88%90%E6%96%87%E6%A1%A3.md" target="_blank" rel="noopener">接口文档</a>
    </div>

    <div class="panel notice-panel">
        <p class="note-title">调用方式</p>
        <p class="hint">地址：<code class="mono">/openapi/软件/操作</code>（如 <code class="mono">/openapi/software/list</code>），POST JSON。</p>
        <p class="hint">鉴权：<code class="mono">Authorization: Bearer lsk_xxx</code>，也可用 <code class="mono">X-API-Key</code> 请求头。</p>
        <p class="hint">密钥仅以哈希入库，明文只在创建时显示一次；安装时自动生成的明文保存在 <code class="mono">install/initial_api_key.txt</code>。</p>
    </div>

    <div id="newKeyBox" class="panel notice-panel hidden">
        <p class="note-title">新密钥（请立即复制保存，关闭后不再显示）</p>
        <div class="inline-row">
            <code id="newKeyText" class="mono key-plain"></code>
            <button id="copyKey" class="btn btn-sm" type="button">复制</button>
        </div>
    </div>

    <div class="panel table-wrap mt">
        <table class="table">
            <thead><tr><th>名称</th><th>前缀</th><th>权限</th><th>状态</th><th>最后使用</th><th class="right">操作</th></tr></thead>
            <tbody>
            <?php foreach ($list as $k): ?>
                <?php $sc = json_decode($k['scopes'], true); ?>
                <tr>
                    <td class="strong"><?= \App\Core\Util::e($k['name']) ?></td>
                    <td class="mono"><?= \App\Core\Util::e($k['key_prefix']) ?>…</td>
                    <td class="mono dim"><?= \App\Core\Util::e(is_array($sc) ? implode(', ', $sc) : '-') ?></td>
                    <td>
                        <?php if ((int)$k['status'] !== 1): ?>
                            <span class="status"><i class="dot dot-off"></i>已撤销</span>
                        <?php elseif ((int)$k['expires_at'] > 0 && (int)$k['expires_at'] < time()): ?>
                            <span class="status"><i class="dot dot-off"></i>已过期</span>
                        <?php else: ?>
                            <span class="status"><i class="dot dot-on"></i>启用</span>
                        <?php endif; ?>
                    </td>
                    <td class="mono dim"><?= \App\Core\Util::date($k['last_used_at']) ?></td>
                    <td class="right ops">
                        <?php if ((int)$k['status'] === 1): ?>
                            <a href="javascript:;" data-revoke="<?= (int)$k['id'] ?>" data-confirm="确认撤销该密钥？调用会立即失败。">撤销</a>
                        <?php else: ?>
                            <span class="dim">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form id="keyForm" class="panel form-panel mt">
        <input type="hidden" name="csrf" value="<?= \App\Core\Util::e($csrf) ?>">
        <p class="note-title">新建密钥</p>
        <div class="form-grid">
            <label class="field"><span class="field-label">名称</span><input type="text" name="name" placeholder="例如：AI 助手" required></label>
            <label class="field"><span class="field-label">有效期（天）</span><input type="number" name="days" value="0" min="0" max="3650"><span class="hint">0 = 永久</span></label>
        </div>
        <div class="field mt">
            <span class="field-label">权限范围（不勾选 = 全部权限）</span>
            <div class="chip-row">
                <?php foreach ($scopes as $s): ?>
                    <label class="chip"><input type="checkbox" name="scopes[]" value="<?= \App\Core\Util::e($s) ?>"> <?= \App\Core\Util::e($s) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">创建密钥</button></div>
        <p id="keyMsg" class="hint"></p>
    </form>

    <?php if ($calls): ?>
    <div class="panel table-wrap mt">
        <p class="note-title">最近调用</p>
        <table class="table">
            <thead><tr><th>时间</th><th>密钥</th><th>接口</th><th>返回码</th><th>IP</th><th>耗时</th></tr></thead>
            <tbody>
            <?php foreach ($calls as $c): ?>
                <tr>
                    <td class="mono dim"><?= \App\Core\Util::date($c['created_at']) ?></td>
                    <td class="mono"><?= \App\Core\Util::e($c['key_prefix']) ?></td>
                    <td class="mono"><?= \App\Core\Util::e($c['endpoint']) ?></td>
                    <td class="mono <?= (int)$c['code'] === 0 ? 'ok-text' : 'err-text' ?>"><?= (int)$c['code'] ?></td>
                    <td class="mono dim"><?= \App\Core\Util::e($c['ip']) ?></td>
                    <td class="mono dim"><?= (int)$c['cost_ms'] ?>ms</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<script>
var KEY_CSRF = '<?= \App\Core\Util::e($csrf) ?>';
document.querySelectorAll('[data-revoke]').forEach(function (el) {
    el.addEventListener('click', function () {
        if (!confirm('确认撤销该密钥？调用会立即失败。')) return;
        var fd = new FormData(); fd.append('csrf', KEY_CSRF); fd.append('id', el.dataset.revoke);
        fetch('index.php?r=admin/apiKeyRevoke', {method: 'POST', body: fd})
            .then(function (r) { return r.json(); })
            .then(function (j) { alert(j.msg); if (j.code === 0) location.reload(); });
    });
});
document.getElementById('keyForm').addEventListener('submit', function (e) {
    e.preventDefault();
    fetch('index.php?r=admin/apiKeyCreate', {method: 'POST', body: new FormData(this)})
        .then(function (r) { return r.json(); })
        .then(function (j) {
            var msg = document.getElementById('keyMsg');
            msg.textContent = j.msg;
            msg.className = 'hint ' + (j.code === 0 ? 'hint-ok' : 'hint-err');
            if (j.code === 0) {
                document.getElementById('newKeyText').textContent = j.data.key;
                document.getElementById('newKeyBox').classList.remove('hidden');
            }
        });
});
document.getElementById('copyKey').addEventListener('click', function () {
    var text = document.getElementById('newKeyText').textContent;
    if (navigator.clipboard) navigator.clipboard.writeText(text);
    else window.prompt('复制密钥：', text);
});
</script>
