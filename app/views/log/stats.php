<?php /** @var array $card */ ?>
<section class="section">
    <div class="section-head">
        <div>
            <h2 class="section-title">数据统计</h2>
            <p class="section-desc">卡密库存概览</p>
        </div>
    </div>
    <div class="stats">
        <div class="stat"><span class="stat-label">卡密总数</span><span class="stat-value"><?= (int)$card['total'] ?></span><span class="stat-unit">张</span></div>
        <div class="stat"><span class="stat-label">启用中</span><span class="stat-value"><?= (int)$card['active'] ?></span><span class="stat-unit">张</span></div>
        <div class="stat"><span class="stat-label">时效型</span><span class="stat-value"><?= (int)$card['time_cards'] ?></span><span class="stat-unit">张</span></div>
        <div class="stat"><span class="stat-label">次数型</span><span class="stat-value"><?= (int)$card['times_cards'] ?></span><span class="stat-unit">张</span></div>
        <div class="stat"><span class="stat-label">已过期</span><span class="stat-value"><?= (int)$card['expired'] ?></span><span class="stat-unit">张</span></div>
        <div class="stat"><span class="stat-label">次数耗尽</span><span class="stat-value"><?= (int)$card['exhausted'] ?></span><span class="stat-unit">张</span></div>
    </div>
</section>
