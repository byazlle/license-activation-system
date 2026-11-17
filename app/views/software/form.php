<?php /** @var array|null $software @var string $csrf */ ?>
<?php $s = $software; ?>
<section class="section section-narrow">
    <div class="section-head">
        <div>
            <h2 class="section-title"><?= $s ? '编辑软件' : '新增软件' ?></h2>
            <p class="section-desc">配置软件的授权默认值与下发内容</p>
        </div>
        <a class="link" href="index.php?r=software/index">返回列表</a>
    </div>
    <form id="swForm" class="panel form-panel">
        <input type="hidden" name="csrf" value="<?= \App\Core\Util::e($csrf) ?>">
        <?php if ($s): ?><input type="hidden" name="id" value="<?= $s['id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <label class="field">
                <span class="field-label">软件名称</span>
                <input type="text" name="name" value="<?= \App\Core\Util::e($s ? $s['name'] : '') ?>" required>
            </label>
            <label class="field">
                <span class="field-label">软件标识</span>
                <input type="text" name="identifier" value="<?= \App\Core\Util::e($s ? $s['identifier'] : '') ?>" <?= $s ? 'readonly' : '' ?> placeholder="英文与下划线" required>
                <?php if ($s): ?><span class="field-hint">创建后不可修改，K 码已绑定该标识</span><?php endif; ?>
            </label>
            <label class="field">
                <span class="field-label">默认授权模式</span>
                <select name="default_mode" id="modeSel">
                    <option value="time" <?= ($s && $s['default_mode'] === 'time') || !$s ? 'selected' : '' ?>>时效型</option>
                    <option value="times" <?= $s && $s['default_mode'] === 'times' ? 'selected' : '' ?>>次数型</option>
                </select>
            </label>
            <label class="field" id="daysField">
                <span class="field-label">默认天数</span>
                <input type="number" name="default_days" min="1" value="<?= \App\Core\Util::e($s ? $s['default_days'] : 30) ?>">
            </label>
            <label class="field" id="timesField">
                <span class="field-label">默认次数</span>
                <input type="number" name="default_times" min="1" value="<?= \App\Core\Util::e($s ? $s['default_times'] : 100) ?>">
            </label>
        </div>
        <div class="divider"></div>
        <label class="field">
            <span class="field-label">默认下发配置（JSON）</span>
            <textarea name="default_config" rows="10" class="mono" placeholder='{"ai":{"endpoint":"https://api.example.com/v1/chat/completions","key":"sk-xxx","model":"gpt-4o-mini"},"text":"欢迎使用","json":{"temperature":0.7}}'><?= \App\Core\Util::e($s ? $s['default_config'] : '') ?></textarea>
            <span class="field-hint">包含 AI 配置、自定义文本、JSON 参数；下发时整体加密，软件解密后直连 AI</span>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
        </div>
        <p id="formMsg" class="hint"></p>
    </form>
</section>
<script>
var modeSel = document.getElementById('modeSel');
function toggleMode() {
    document.getElementById('daysField').style.display = modeSel.value === 'time' ? '' : 'none';
    document.getElementById('timesField').style.display = modeSel.value === 'times' ? '' : 'none';
}
modeSel.addEventListener('change', toggleMode); toggleMode();
document.getElementById('swForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(this);
    var out = document.getElementById('formMsg');
    fetch('index.php?r=software/save', {method: 'POST', body: fd}).then(function (r) { return r.json(); })
        .then(function (j) {
            out.textContent = j.msg;
            out.className = 'hint ' + (j.code === 0 ? 'hint-ok' : 'hint-err');
            if (j.code === 0) setTimeout(function () { location.href = 'index.php?r=software/index'; }, 400);
        });
});
</script>
