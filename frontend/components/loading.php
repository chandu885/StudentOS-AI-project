<?php
// frontend/components/loading.php
?>
<div id="globalLoadingOverlay" style="position: fixed; inset: 0; background: rgba(11, 16, 32, 0.8); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column; gap: 16px;">
    <div style="width: 48px; height: 48px; border: 4px solid var(--border-color); border-top-color: var(--primary); border-radius: 50%;" class="animate-spin"></div>
    <div style="color: var(--text-primary); font-weight: 500; font-size: 14px;" id="globalLoadingText">Processing request...</div>
</div>

<script>
function showLoading(text = 'Processing request...') {
    const overlay = document.getElementById('globalLoadingOverlay');
    const label = document.getElementById('globalLoadingText');
    if (label) label.textContent = text;
    if (overlay) overlay.style.display = 'flex';
}

function hideLoading() {
    const overlay = document.getElementById('globalLoadingOverlay');
    if (overlay) overlay.style.display = 'none';
}
</script>
