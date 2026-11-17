<?php /** @var array $list @var string $csrf */ ?>
<section class="section section-narrow">
    <div class="section-head">
        <div>
            <h2 class="section-title">管理员</h2>
            <p class="section-desc">后台账号管理</p>
        </div>
        <a class="link" href="index.php?r=admin/password">修改我的密码</a>
    </div>
    <div class="panel table-wrap">
        <table class="table">
            <thead><tr><th>用户名</th><th>角色</th><th>状态</th><th>最后登录</th><th class="right">操作</th></tr></thead>
            <tbody>
            <?php foreach ($list as $a): ?>
                <tr>
                    <td class="strong"><?= \App\Core\Util::e($a['username']) ?></td>
                    <td><?= (int)$a['role'] === 1 ? '超级管理员' : '管理员' ?></td>
                    <td><span class="status"><i class="dot <?= (int)$a['status'] === 1 ? 'dot-on' : 'dot-off' ?>"></i><?= (int)$a['status'] === 1 ? '启用' : '停用' ?></span></td>
                    <td class="mono dim"><?= \App\Core\Util::date($a['last_login_at']) ?></td>
                    <td class="right ops"><a href="javascript:;" data-del="<?= $a['id'] ?>">删除</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <form id="addForm" class="panel form-panel mt">
        <input type="hidden" name="csrf" value="<?= \App\Core\Util::e($csrf) ?>">
        <p class="note-title">新增管理员</p>
        <div class="form-grid">
            <label class="field"><span class="field-label">用户名</span><input type="text" name="username" required></label>
            <label class="field"><span class="field-label">密码</span><input type="password" name="password" required minlength="6"></label>
            <label class="field"><span class="field-label">角色</span><select name="role"><option value="2">管理员</option><option value="1">超级管理员</option></select></label>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">添加</button></div>
        <p id="addMsg" class="hint"></p>
    </form>
</section>
<script>
var CSRF = '<?= \App\Core\Util::e($csrf) ?>';
document.querySelectorAll('[data-del]').forEach(function (el) {
    el.addEventListener('click', function () {
        if (!confirm('确认删除该管理员？')) return;
        var fd = new FormData(); fd.append('csrf', CSRF); fd.append('id', el.dataset.del);
        fetch('index.php?r=admin/remove', {method: 'POST', body: fd}).then(function(r){return r.json();}).then(function(j){alert(j.msg); if(j.code===0) location.reload();});
    });
});
document.getElementById('addForm').addEventListener('submit', function (e) {
    e.preventDefault();
    fetch('index.php?r=admin/add', {method: 'POST', body: new FormData(this)}).then(function(r){return r.json();}).then(function(j){
        document.getElementById('addMsg').textContent = j.msg;
        document.getElementById('addMsg').className = 'hint ' + (j.code===0?'hint-ok':'hint-err');
        if (j.code===0) setTimeout(function(){location.reload();}, 400);
    });
});
</script>
