<?php /** @var array $softwares @var string $csrf */ ?>
<section class="section section-narrow">
    <div class="section-head">
        <div>
            <h2 class="section-title">生成卡密</h2>
            <p class="section-desc">批量生成卡号并封装为加密 K 码</p>
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
                        <option value="<?= $sw['id'] ?>" data-mode="<?= $sw['default_mode'] ?>"><?= \App\Core\Util::e($sw['name']) ?> · <?= $sw['default_mode'] === 'times' ? '次数型' : '时效型' ?></option>
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
            <label class="field field-full">
                <span class="field-label">备注</span>
                <input type="text" name="remark" placeholder="可选，用于标记批次来源">
            </label>
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
var modeSel = document.getElementById('modeSel');
function toggleMode() {
    document.getElementById('daysField').style.display = modeSel.value === 'time' ? '' : 'none';
    document.getElementById('timesField').style.display = modeSel.value === 'times' ? '' : 'none';
}
modeSel.addEventListener('change', toggleMode);
document.getElementById('swSel').addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    if (opt.dataset.mode) { modeSel.value = opt.dataset.mode; toggleMode(); }
});
toggleMode();

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
