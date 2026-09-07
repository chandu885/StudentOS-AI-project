<?php
// frontend/components/cards.php

function renderStatCard($icon, $number, $label, $subtext = '', $color = 'primary') {
    ?>
    <div class="stat-card">
        <div class="stat-icon" style="<?php echo $color !== 'primary' ? 'color: var(--' . $color . '); background: rgba(var(--' . $color . '), 0.1);' : ''; ?>">
            <i class="fas <?php echo htmlspecialchars($icon); ?>"></i>
        </div>
        <div class="stat-content">
            <span class="stat-number"><?php echo htmlspecialchars($number); ?></span>
            <span class="stat-label"><?php echo htmlspecialchars($label); ?></span>
            <?php if ($subtext): ?>
                <span style="font-size: 11px; color: var(--text-muted); margin-top: 2px;"><?php echo htmlspecialchars($subtext); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function renderEmptyState($icon, $title, $description = '', $actionBtn = '') {
    ?>
    <div class="empty-state">
        <i class="fas <?php echo htmlspecialchars($icon); ?>"></i>
        <h4 style="color: var(--text-primary); margin-bottom: 4px;"><?php echo htmlspecialchars($title); ?></h4>
        <?php if ($description): ?>
            <p style="color: var(--text-muted); max-width: 380px; margin: 0 auto;"><?php echo htmlspecialchars($description); ?></p>
        <?php endif; ?>
        <?php if ($actionBtn): ?>
            <div style="margin-top: 16px;"><?php echo $actionBtn; ?></div>
        <?php endif; ?>
    </div>
    <?php
}
