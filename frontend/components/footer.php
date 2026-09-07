<?php
// frontend/components/footer.php
?>
<footer class="dashboard-footer" style="padding: 24px; border-top: 1px solid var(--border-color); background: var(--bg-secondary); margin-top: auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; font-size: var(--font-size-xs); color: var(--text-muted);">
    <div style="display: flex; align-items: center; gap: 16px;">
        <span>&copy; <?php echo date('Y'); ?> <strong>StudentOS AI</strong>. All rights reserved.</span>
        <span style="display: inline-flex; align-items: center; gap: 6px; background: rgba(34, 197, 94, 0.1); color: var(--success); padding: 2px 8px; border-radius: var(--radius-full); font-weight: 500;">
            <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--success);"></span> All Systems Operational
        </span>
    </div>
    <div style="display: flex; gap: 16px;">
        <a href="#" style="color: var(--text-secondary);">Privacy Policy</a>
        <a href="#" style="color: var(--text-secondary);">Terms of Service</a>
        <a href="#" style="color: var(--text-secondary);">Support</a>
        <span>v1.0.0</span>
    </div>
</footer>
