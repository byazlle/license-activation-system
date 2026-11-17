<?php /** @var array|null $software @var string $csrf */ ?>
<?php
$s = $software;
$rawCfg = $s ? $s['default_config'] : '';
$cfg = json_decode($rawCfg, true);
if (!is_array($cfg)) {
    $cfg = array();
}
$ai = isset($cfg['ai']) && is_array($cfg['ai']) ? $cfg['ai'] : array();
$extraType = 'text';
$extraContent = '';
if (isset($cfg['json'])) {
    $extraType = 'json';
    $extraContent = json_encode($cfg['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} elseif (isset($cfg['script'])) {
    $extraType = 'script';
    $extraContent = (string)$cfg['script'];
} elseif (isset($cfg['text'])) {
    $extraType = 'text';
    $extraContent = (string)$cfg['text'];
}
?>
<section class="section section-narrow">
    <div class="section-head">
        <div>
            <h2 class="section-title"><?= $s ? '编辑软件' : '新增软件' ?></h2>
            <p class="section-desc">配置授权的默认值，以及下发到客户端的 AI 与额外配置</p>
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

        <div class="switch-row">
            <label class="switch">
                <input type="checkbox" id="aiToggle" value="1" <?= $ai ? 'checked' : '' ?>>
                <span>下发 AI 配置</span>
            </label>
            <span class="field-hint">开启后随业务包下发接口地址、API Key、模型名称；客户端解密后本地直连</span>
        </div>
        <div id="aiBox" class="subpanel" <?= $ai ? '' : 'hidden' ?>>
            <div class="form-grid">
                <label class="field field-full">
                    <span class="field-label">接口地址</span>
                    <input type="text" id="aiEndpoint" value="<?= \App\Core\Util::e(isset($ai['endpoint']) ? $ai['endpoint'] : '') ?>" placeholder="https://api.example.com/v1/chat/completions">
                </label>
                <label class="field field-full">
                    <span class="field-label">API Key</span>
                    <input type="text" id="aiKey" class="mono" value="<?= \App\Core\Util::e(isset($ai['key']) ? $ai['key'] : '') ?>" placeholder="sk-...">
                </label>
                <label class="field field-full">
                    <span class="field-label">模型名称</span>
                    <div class="inline-row">
                        <input type="text" id="aiModel" list="modelOptions" value="<?= \App\Core\Util::e(isset($ai['model']) ? $ai['model'] : '') ?>" placeholder="gpt-4o-mini">
                        <button type="button" id="fetchModels" class="btn btn-ghost btn-sm">获取模型</button>
                    </div>
                    <datalist id="modelOptions"></datalist>
                    <span class="field-hint" id="modelHint">按接口地址与 API Key 请求可选模型；失败可手动填写</span>
                </label>
            </div>
        </div>

        <div class="divider"></div>

        <div class="switch-row">
            <label class="switch">
                <input type="checkbox" id="extraToggle" value="1" <?= $extraContent !== '' ? 'checked' : '' ?>>
                <span>下发额外配置</span>
            </label>
            <span class="field-hint">自定义文本、JSON 参数，或配置文件 / 脚本内容</span>
        </div>
        <div id="extraBox" class="subpanel" <?= $extraContent !== '' ? '' : 'hidden' ?>>
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">类型</span>
                    <select id="extraType">
                        <option value="text" <?= $extraType === 'text' ? 'selected' : '' ?>>自定义文本（text）</option>
                        <option value="json" <?= $extraType === 'json' ? 'selected' : '' ?>>JSON 参数（json）</option>
                        <option value="script" <?= $extraType === 'script' ? 'selected' : '' ?>>配置文件 / 脚本（script）</option>
                    </select>
                </label>
                <div class="field field-full">
                    <span class="field-label">内容</span>
                    <textarea id="extraContent" rows="8" class="mono" placeholder="选择类型后填写内容"><?= \App\Core\Util::e($extraContent) ?></textarea>
                    <div class="chip-row">
                        <button type="button" class="btn btn-ghost btn-sm" data-tpl="text">文本模板</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-tpl="json">JSON 模板</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-tpl="script">脚本模板</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="cfg-head">
            <div class="tabs">
                <button type="button" class="tab is-current" data-tab="visual">可视化配置</button>
                <button type="button" class="tab" data-tab="source">JSON 源码</button>
            </div>
            <button type="button" id="fillTemplate" class="btn btn-ghost btn-sm">套用完整模板</button>
        </div>

        <div id="visualPane" class="cfg-visual">
            <p class="field-hint">上方表单即可视化编辑入口，修改会实时写入下方 JSON。未开放的字段不会被改动。</p>
            <pre class="cfg-preview mono" id="cfgPreview"></pre>
        </div>

        <div id="sourcePane" class="cfg-source" hidden>
            <label class="field">
                <span class="field-label">默认下发配置（JSON）</span>
                <textarea name="default_config" id="cfgText" rows="14" class="mono" placeholder='{"ai":{"endpoint":"...","key":"sk-xxx","model":"gpt-4o-mini"}}'><?= \App\Core\Util::e($rawCfg !== '' ? $rawCfg : "{\n\n}") ?></textarea>
                <span class="field-hint">下发时整体加密；客户端解密后可读 <code>ai</code> / <code>text</code> / <code>json</code> / <code>script</code></span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
        </div>
        <p id="formMsg" class="hint"></p>
    </form>
</section>
<script>
var CSRF = '<?= \App\Core\Util::e($csrf) ?>';
var modeSel = document.getElementById('modeSel');
var cfgText = document.getElementById('cfgText');
var cfgPreview = document.getElementById('cfgPreview');
var aiToggle = document.getElementById('aiToggle');
var extraToggle = document.getElementById('extraToggle');

function toggleMode() {
    document.getElementById('daysField').style.display = modeSel.value === 'time' ? '' : 'none';
    document.getElementById('timesField').style.display = modeSel.value === 'times' ? '' : 'none';
}
modeSel.addEventListener('change', toggleMode); toggleMode();

// ---- 配置读写 ----
function readCfg() {
    var t = cfgText.value.trim();
    if (t === '') return {};
    try { return JSON.parse(t); } catch (e) { return null; }
}
function writeCfg(obj) {
    cfgText.value = JSON.stringify(obj, null, 2);
    cfgPreview.textContent = cfgText.value;
}
function refreshPreview() {
    var o = readCfg();
    cfgPreview.textContent = o ? JSON.stringify(o, null, 2) : '（当前 JSON 无法解析，请切换到「JSON 源码」修正）';
}

// ---- 可视化 -> JSON ----
function syncFromVisual() {
    var o = readCfg();
    if (!o) return; // JSON 不合法时不覆盖，避免吞掉用户手写内容

    if (aiToggle.checked) {
        var ai = (o.ai && typeof o.ai === 'object') ? o.ai : {};
        var ep = document.getElementById('aiEndpoint').value.trim();
        var k = document.getElementById('aiKey').value.trim();
        var m = document.getElementById('aiModel').value.trim();
        if (ep) ai.endpoint = ep; else delete ai.endpoint;
        if (k) ai.key = k; else delete ai.key;
        if (m) ai.model = m; else delete ai.model;
        if (Object.keys(ai).length) { o.ai = ai; } else { delete o.ai; }
    } else {
        delete o.ai;
    }

    if (extraToggle.checked) {
        var type = document.getElementById('extraType').value;
        var content = document.getElementById('extraContent').value;
        delete o.text; delete o.json; delete o.script;
        if (type === 'json') {
            var parsed = null;
            try { parsed = JSON.parse(content); } catch (e) { parsed = null; }
            if (parsed !== null) o.json = parsed;
        } else if (type === 'script') {
            o.script = content;
        } else {
            o.text = content;
        }
    } else {
        delete o.text; delete o.json; delete o.script;
    }
    writeCfg(o);
}

['aiEndpoint', 'aiKey', 'aiModel', 'extraContent'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', syncFromVisual);
});
document.getElementById('extraType').addEventListener('change', syncFromVisual);
[[aiToggle, 'aiBox'], [extraToggle, 'extraBox']].forEach(function (pair) {
    pair[0].addEventListener('change', function () {
        document.getElementById(pair[1]).hidden = !pair[0].checked;
        syncFromVisual();
    });
});

// ---- 标签页 ----
document.querySelectorAll('.tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.tab').forEach(function (t) { t.classList.remove('is-current'); });
        tab.classList.add('is-current');
        var isVisual = tab.dataset.tab === 'visual';
        document.getElementById('visualPane').hidden = !isVisual;
        document.getElementById('sourcePane').hidden = isVisual;
        if (isVisual) { refreshPreview(); } else { cfgText.focus(); }
    });
});
cfgText.addEventListener('input', refreshPreview);
refreshPreview();

// ---- 模板 ----
var TPL = {
    text: '欢迎使用本软件，如需帮助请联系客服。',
    json: '{\n  "temperature": 0.7,\n  "max_tokens": 2048\n}',
    script: '{\n  "feature_flag": true,\n  "endpoint": "https://example.com"\n}'
};
document.querySelectorAll('[data-tpl]').forEach(function (b) {
    b.addEventListener('click', function () {
        var type = b.dataset.tpl;
        document.getElementById('extraType').value = type;
        var ta = document.getElementById('extraContent');
        if (!ta.value.trim() || confirm('覆盖当前内容？')) ta.value = TPL[type];
        extraToggle.checked = true;
        document.getElementById('extraBox').hidden = false;
        syncFromVisual();
    });
});
document.getElementById('fillTemplate').addEventListener('click', function () {
    if (!confirm('将用完整模板覆盖当前配置，继续？')) return;
    var tpl = {
        ai: {endpoint: 'https://api.example.com/v1/chat/completions', key: 'sk-xxx', model: 'gpt-4o-mini'}
    };
    cfgText.value = JSON.stringify(tpl, null, 2);
    aiToggle.checked = true;
    document.getElementById('aiBox').hidden = false;
    document.getElementById('aiEndpoint').value = tpl.ai.endpoint;
    document.getElementById('aiKey').value = tpl.ai.key;
    document.getElementById('aiModel').value = tpl.ai.model;
    document.querySelector('.tab[data-tab=visual]').click();
    refreshPreview();
});

// ---- 拉取模型 ----
document.getElementById('fetchModels').addEventListener('click', function () {
    var btn = this, hint = document.getElementById('modelHint');
    var ep = document.getElementById('aiEndpoint').value.trim();
    var key = document.getElementById('aiKey').value.trim();
    if (!ep || !key) { hint.textContent = '请先填写接口地址与 API Key'; return; }
    btn.disabled = true; btn.textContent = '获取中…';
    var fd = new FormData();
    fd.append('csrf', CSRF); fd.append('endpoint', ep); fd.append('key', key);
    fetch('index.php?r=software/models', {method: 'POST', body: fd})
        .then(function (r) { return r.json(); })
        .then(function (j) {
            btn.disabled = false; btn.textContent = '获取模型';
            if (j.code !== 0) { hint.textContent = j.msg; return; }
            var dl = document.getElementById('modelOptions');
            dl.innerHTML = '';
            j.data.models.forEach(function (m) { var o = document.createElement('option'); o.value = m; dl.appendChild(o); });
            hint.textContent = '已获取 ' + j.data.models.length + ' 个模型，可下拉选择';
        });
});

// ---- 提交 ----
document.getElementById('swForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var out = document.getElementById('formMsg');
    if (!readCfg()) {
        out.textContent = 'JSON 配置格式不正确，请先修正';
        out.className = 'hint hint-err';
        document.querySelector('.tab[data-tab=source]').click();
        return;
    }
    syncFromVisual();
    fetch('index.php?r=software/save', {method: 'POST', body: new FormData(this)})
        .then(function (r) { return r.json(); })
        .then(function (j) {
            out.textContent = j.msg;
            out.className = 'hint ' + (j.code === 0 ? 'hint-ok' : 'hint-err');
            if (j.code === 0) { setTimeout(function () { location.href = 'index.php?r=software/index'; }, 500); }
        });
});
syncFromVisual();
</script>
