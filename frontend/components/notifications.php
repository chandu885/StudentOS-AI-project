<?php
// frontend/components/notifications.php
?>
<div id="toast-container" class="toast-container"></div>
<?php
// Render session flash alerts if any
if (!empty($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        showToast("<?php echo addslashes($flash['message']); ?>", "<?php echo addslashes($flash['type'] ?? 'info'); ?>");
    });
    </script>
    <?php
}
?>
