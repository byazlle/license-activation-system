<?php /** @var string $currentUrl @var string|null $result @var string $error @var string $csrf */ ?>
<section class="section section-narrow">
    <div class="section-head">
        <div>
            <h2 class="section-title">地址转换</h2>
            <p class="section-desc">更换服务器或域名后重签 K 码，软件无需更新</p>
        </div>
    </div>
    <form method="post" class="panel form-panel">
        <input type="hidden" name="csrf" value="<?= \App\Core\Util::e($csrf) ?>">
        <label class="field">
            <span class="field-label">当前服务器地址</span>
            <input type="text" name="new_url" value="<?= \App\Core\Util::e($currentUrl) ?>" placeholder="https://license.example.com/api" required>
            <span class="field-hint">该地址将写入新 K 码；保存后也成为后续发卡的默认地址</span>
        </label>
        <label class="field">
            <span class="field-label">旧 K 码</span>
            <textarea name="kcode" rows="5" class="mono" placeholder="粘贴旧 K 码" required></textarea>
        </label>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">转换</button>
        </div>
    </form>

    <?php if (!empty($error)): ?>
        <div class="notice notice-err mt"><?= \App\Core\Util::e($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($result)): ?>
        <div class="panel result-panel mt">
            <div class="result-head">
                <div>
                    <h3 class="result-title">新 K 码</h3>
                    <p class="result-meta">卡号与密钥未变，仅服务器地址已更新</p>
                </div>
                <button type="button" id="copyK" class="btn btn-ghost">复制</button>
            </div>
            <div class="kcode-list"><div class="kcode-row"><code class="kcode" id="newK"><?= \App\Core\Util::e($result) ?></code></div></div>
        </div>
        <script>
        document.getElementById('copyK').addEventListener('click', function () {
            var ta = document.createElement('textarea');
            ta.value = document.getElementById('newK').textContent;
            document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
            this.textContent = '已复制';
        });
        </script>
    <?php endif; ?>
</section>
