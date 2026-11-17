<?php /** @var string $msg @var string $error @var string $csrf */ ?>
<section class="section section-narrow">
    <div class="section-head">
        <div>
            <h2 class="section-title">修改密码</h2>
            <p class="section-desc">更新当前账号的登录密码</p>
        </div>
    </div>
    <?php if (!empty($msg)): ?><div class="notice notice-ok"><?= \App\Core\Util::e($msg) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="notice notice-err"><?= \App\Core\Util::e($error) ?></div><?php endif; ?>
    <form method="post" class="panel form-panel">
        <input type="hidden" name="csrf" value="<?= \App\Core\Util::e($csrf) ?>">
        <label class="field"><span class="field-label">旧密码</span><input type="password" name="old" required></label>
        <label class="field"><span class="field-label">新密码</span><input type="password" name="new" required minlength="6"></label>
        <div class="form-actions"><button class="btn btn-primary" type="submit">保存</button></div>
    </form>
</section>
