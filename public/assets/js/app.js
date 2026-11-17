/**
 * 后台通用脚本
 * 只做必要的交互增强，不引入动画与特效。
 */
(function () {
    'use strict';

    // 危险操作二次确认（data-confirm）
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // 表格整行可点击（data-href）
    document.querySelectorAll('tr[data-href]').forEach(function (row) {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function (e) {
            if (e.target.closest('a')) return;
            window.location.href = row.getAttribute('data-href');
        });
    });
})();
