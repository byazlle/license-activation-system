<?php /** @var array $softwares @var string $csrf @var int $preselect */ ?>
<?php
$defaults = array();
foreach ($softwares as $sw) {
    $cfg = json_decode($sw['default_config'], true);
    $defaults[$sw['id']] = is_array($cfg) ? $cfg : array();
}
?>
<section class="section section-narrow">
    <div class="section-head">
        <div>
            <h2 class="section-title">生成卡密</h2>
            <p class="section-desc">批量生成卡号并封装为加密 K 码；可按卡单独下发 AI 与额外配置</p>
        </div>
        <a class="link" href="index.php?r=card/index">返回列表</a>
    </div>

    <form id="genForm" class="panel form-panel">
        <input type="hidden" name="csrf" value="<?= \App\Core\Util::e($csrf) ?>">

        <div class="form-grid">
            <label class="field field-full">
                <span class="field-label">所属软件</span>
                <select name="software_id" id="swSel" required>
                    <?php if (!$softwares): ?>
                        <option value="">请先创建软件</option>
                    <?php else: foreach ($softwares as $sw): ?>
                        <option value="<?= $sw['id'] ?>"
                                data-mode="<?= $sw['default_mode'] ?>"
                                data-days="<?= (int)$sw['default_days'] ?>"
                                data-times="<?= (int)$sw['default_times'] ?>"
                                <?= (int)$preselect === (int)$sw['id'] ? 'selected' : '' ?>>
                            <?= \App\Core\Util::e($sw['name']) ?> · <?= \App\Core\Util::e($sw['identifier']) ?> · <?= $sw['default_mode'] === 'times' ? '次数型' : '时效型' ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </label>
            <label class="field">
                <span class="field-label">授权模式</span>
                <select name="mode" id="modeSel">
                    <option value="time">时效型</option>
                    <option value="times">次数型</option>
                </select>
            </label>
            <label class="field">
                <span class="field-label">生成数量</span>
                <input type="number" name="count" value="1" min="1" max="500" required>
            </label>
            <label class="field" id="daysField">
                <span class="field-label">有效天数</span>
                <input type="number" name="days" value="30" min="1">
            </label>
            <label class="field" id="timesField">
                <span class="field-label">可用次数</span>
                <input type="number" name="times" value="100" min="1">
            </label>
        </div>

        <div class="divider"></div>

        <div class="switch-row">
            <label class="switch">
                <input type="checkbox" name="remark_enabled" id="remarkToggle" value="1">
                <span>修改备注</span>
            </label>
            <span class="field-hint">不勾选则不写入备注，默认留空</span>
        </div>
        <div id="remarkBox" class="subpanel" hidden>
            <label class="field">
                <span class="field-label">备注内容</span>
                <input type="text" name="remark" id="remarkInput" placeholder="用于标记批次来源，例如：双十一活动" disabled>
            </label>
        </div>

        <div class="divider"></div>

        <div class="switch-row">
            <label class="switch">
                <input type="checkbox" name="ai_enabled" id="aiToggle" value="1">
                <span>下发 AI 配置</span>
            </label>
            <span class="field-hint">仅覆盖接口地址、API Key、模型名称三项的值，配置结构与其他固定字段保持软件默认</span>
        </div>
        <div id="aiBox" class="subpanel" hidden>
            <div class="form-grid">
                <label class="field field-full">
                    <span class="field-label">接口地址</span>
                    <input type="text" name="ai_endpoint" id="aiEndpoint" placeholder="https://api.example.com/v1/chat/completions">
                </label>
                <label class="field field-full">
                    <span class="field-label">API Key</span>
                    <input type="text" name="ai_key" id="aiKey" class="mono" placeholder="sk-...">
                </label>
                <label class="field field-full">
                    <span class="field-label">模型名称</span>
                    <div class="inline-row">
                        <input type="text" name="ai_model" id="aiModel" list="modelOptions" placeholder="gpt-4o-mini">
                        <button type="button" id="fetchModels" class="btn btn-ghost btn-sm">获取模型</button>
                    </div>
                    <datalist id="modelOptions"></datalist>
                    <span class="field-hint" id="modelHint">留空则沿用软件默认；可从接口地址与 Key 拉取可选模型</span>
                </label>
            </div>
            <p class="field-hint" id="aiDefaultHint"></p>
        </div>

        <div class="divider"></div>

        <div class="switch-row">
            <label class="switch">
                <input type="checkbox" name="extra_enabled" id="extraToggle" value="1">
                <span>下发额外配置</span>
            </label>
            <span class="field-hint">自定义文本 / JSON 参数 / 配置文件或脚本内容，随业务包一起加密下发</span>
        </div>
        <div id="extraBox" class="subpanel" hidden>
            <div class="form-grid">
                <label class="field">
                    <span class="field-label">类型</span>
                    <select name="extra_type" id="extraType">
                        <option value="text">自定义文本（text）</option>
                        <option value="json">JSON 参数（json）</option>
                        <option value="script">配置文件 / 脚本（script）</option>
                    </select>
                </label>
                <div class="field field-full">
                    <span class="field-label">内容</span>
                    <textarea name="extra_content" id="extraContent" rows="8" class="mono" placeholder="选择类型后填写内容"></textarea>
                    <div class="chip-row">
                        <button type="button" class="btn btn-ghost btn-sm" data-tpl="text">插入文本模板</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-tpl="json">插入 JSON 模板</button>
                        <button type="button" class="btn btn-ghost btn-sm" data-tpl="script">插入脚本模板</button>
                    </div>
                    <span class="field-hint">JSON 类型需为合法 JSON 对象，保存时会校验</span>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">生成</button>
        </div>
    </form>

    <div id="resultBox" class="panel result-panel" hidden>
        <div class="result-head">
            <div>
                <h3 class="result-title">生成完成</h3>
                <p class="result-meta" id="resultMeta"></p>
            </div>
            <button type="button" id="copyAll" class="btn btn-ghost">复制全部 K 码</button>
        </div>
        <div class="kcode-list" id="kcodeList"></div>
    </div>
</section>
<script>
var DEFAULTS = <?= json_encode($defaults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
var CSRF = '<?= \App\Core\Util::e($csrf) ?>';

var modeSel = document.getElementById('modeSel');
var swSel = document.getElementById('swSel');
var aiEndpoint = document.getElementById('aiEndpoint');
var aiKey = document.getElementById('aiKey');
var aiModel = document.getElementById('aiModel');

function toggleMode() {
    document.getElementById('daysField').style.display = modeSel.value === 'time' ? '' : 'none';
    document.getElementById('timesField').style.display = modeSel.value === 'times' ? '' : 'none';
}
modeSel.addEventListener('change', toggleMode);

// 勾选开关 -> 展开子面板，并启用/禁用内部字段（禁用后不会提交）
function bindToggle(toggleId, boxId) {
    var t = document.getElementById(toggleId);
    var box = document.getElementById(boxId);
    function apply() {
        box.hidden = !t.checked;
        box.querySelectorAll('input,select,textarea').forEach(function (el) {
            if (el.type === 'checkbox') return;
            el.disabled = !t.checked;
        });
    }
    t.addEventListener('change', apply);
    apply();
}
bindToggle('remarkToggle', 'remarkBox');
bindToggle('aiToggle', 'aiBox');
bindToggle('extraToggle', 'extraBox');

// 切换软件：同步默认模式，并把默认 AI 配置展示为占位提示
function applySoftware() {
    var id = swSel.value;
    var opt = swSel.options[swSel.selectedIndex];
    if (opt && opt.dataset.mode) { modeSel.value = opt.dataset.mode; }
    if (opt && opt.dataset.days) { document.querySelector('[name=days]').value = opt.dataset.days; }
    if (opt && opt.dataset.times) { document.querySelector('[name=times]').value = opt.dataset.times; }
    var cfg = DEFAULTS[id] || {};
    var ai = cfg.ai || {};
    aiEndpoint.placeholder = ai.endpoint || 'https://api.example.com/v1/chat/completions';
    aiKey.placeholder = ai.key ? '已配置，留空则沿用' : 'sk-...';
    aiModel.placeholder = ai.model || 'gpt-4o-mini';
    var hint = document.getElementById('aiDefaultHint');
    var parts = [];
    if (ai.endpoint) parts.push('接口 ' + ai.endpoint);
    if (ai.model) parts.push('模型 ' + ai.model);
    if (ai.key) parts.push('Key 已配置');
    hint.textContent = parts.length ? '软件默认：' + parts.join(' · ') : '该软件尚未配置默认 AI 信息';
    toggleMode();
}
swSel.addEventListener('change', applySoftware);
applySoftware();

// 获取模型列表（服务端代请求）
document.getElementById('fetchModels').addEventListener('click', function () {
    var btn = this, hint = document.getElementById('modelHint');
    if (!aiEndpoint.value.trim() || !aiKey.value.trim()) {
        hint.textContent = '请先填写接口地址与 API Key';
        return;
    }
    btn.disabled = true; btn.textContent = '获取中…';
    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('endpoint', aiEndpoint.value.trim());
    fd.append('key', aiKey.value.trim());
    fetch('index.php?r=software/models', {method: 'POST', body: fd})
        .then(function (r) { return r.json(); })
        .then(function (j) {
            btn.disabled = false; btn.textContent = '获取模型';
            if (j.code !== 0) { hint.textContent = j.msg; return; }
            var dl = document.getElementById('modelOptions');
            dl.innerHTML = '';
            j.data.models.forEach(function (m) {
                var o = document.createElement('option');
                o.value = m; dl.appendChild(o);
            });
            hint.textContent = '已获取 ' + j.data.models.length + ' 个模型，可下拉选择或手动输入';
        });
});

var TPL = {
    text: '欢迎使用本软件，如需帮助请联系客服。',
    json: '{\n  "temperature": 0.7,\n  "max_tokens": 2048\n}',
    script: '// 下发到客户端的配置文件内容，原样保留\n{\n  "feature_flag": true,\n  "endpoint": "https://example.com"\n}'
};
document.querySelectorAll('[data-tpl]').forEach(function (b) {
    b.addEventListener('click', function () {
        var type = b.dataset.tpl;
        var ta = document.getElementById('extraContent');
        document.getElementById('extraType').value = type;
        if (!ta.value.trim() || confirm('覆盖当前内容？')) { ta.value = TPL[type]; }
    });
});

document.getElementById('genForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = this.querySelector('button[type=submit]');
    btn.disabled = true; btn.textContent = '生成中…';
    fetch('index.php?r=card/generate', {method: 'POST', body: new FormData(this)})
        .then(function (r) { return r.json(); }).then(function (j) {
            btn.disabled = false; btn.textContent = '生成';
            if (j.code !== 0) { alert(j.msg); return; }
            var box = document.getElementById('resultBox');
            box.hidden = false;
            document.getElementById('resultMeta').textContent = j.data.cards.length + ' 张 · 批次 ' + j.data.batch;
            var list = document.getElementById('kcodeList');
            list.innerHTML = '';
            j.data.kcodes.forEach(function (kc, i) {
                var row = document.createElement('div');
                row.className = 'kcode-row';
                var idx = document.createElement('span');
                idx.className = 'kcode-idx';
                idx.textContent = j.data.cards[i];
                var code = document.createElement('code');
                code.className = 'kcode';
                code.textContent = kc;
                row.appendChild(idx); row.appendChild(code);
                list.appendChild(row);
            });
        });
});
document.getElementById('copyAll').addEventListener('click', function () {
    var codes = Array.prototype.map.call(document.querySelectorAll('.kcode'), function (el) { return el.textContent; });
    var ta = document.createElement('textarea');
    ta.value = codes.join('\n'); document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    this.textContent = '已复制';
    var self = this; setTimeout(function () { self.textContent = '复制全部 K 码'; }, 1500);
});
</script>
