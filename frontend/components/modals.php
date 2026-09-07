<?php
// frontend/components/modals.php
?>
<!-- Generic Confirmation Modal -->
<div class="modal-backdrop" id="confirmModal">
    <div class="modal-card" style="max-width: 440px;">
        <div class="modal-header">
            <h3 id="confirmModalTitle">Confirm Action</h3>
            <button class="modal-close" onclick="closeModal('confirmModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p id="confirmModalMessage" style="color: var(--text-secondary); font-size: 14px;">Are you sure you want to proceed with this action?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('confirmModal')">Cancel</button>
            <button class="btn btn-danger" id="confirmModalBtn">Confirm</button>
        </div>
    </div>
</div>

<script>
let confirmCallback = null;
function showConfirmDialog(title, message, callback, btnText = 'Confirm', btnClass = 'btn-danger') {
    document.getElementById('confirmModalTitle').textContent = title;
    document.getElementById('confirmModalMessage').textContent = message;
    const btn = document.getElementById('confirmModalBtn');
    btn.textContent = btnText;
    btn.className = `btn ${btnClass}`;
    confirmCallback = callback;
    btn.onclick = function() {
        if (confirmCallback) confirmCallback();
        closeModal('confirmModal');
    };
    openModal('confirmModal');
}
</script>
