<?php
/**
 * frontend/components/header.php - StudentOS AI Universal Dashboard Header Component
 *
 * This component provides a standardized, unified document head and dashboard layout structure
 * across all user portals (Student, Faculty, Admin, Super Admin).
 *
 * Usage in dashboard pages:
 * -------------------------------------------------------------
 *   <?php
 *   $pageTitle = 'My Dashboard - StudentOS AI';
 *   $pageHeading = 'Coursework & Assignments'; // optional
 *   $pageSubtitle = 'Manage and submit your coursework'; // optional
 *   include_once __DIR__ . '/../components/header.php';
 *   ?>
 *   <!-- Your page-specific content here -->
 *   </div> <!-- /dashboard-content -->
 *   <?php include_once __DIR__ . '/../components/footer.php'; ?>
 *   </main>
 *   </div> <!-- /dashboard-layout -->
 *   </body>
 *   </html>
 *
 * Available configuration variables:
 * - $pageTitle        : string  - Text for browser tab <title> (default: 'StudentOS AI - Academic Operating System')
 * - $pageHeading      : string  - Optional title rendered inside standard .page-header banner
 * - $pageSubtitle     : string  - Optional subtitle rendered beneath $pageHeading
 * - $pageActions      : string  - Optional HTML for buttons rendered in header actions area
 * - $breadcrumbs      : array   - Optional breadcrumb links: [['label' => 'Home', 'url' => '/'], ['label' => 'Current']]
 * - $bodyClass        : string  - Additional CSS classes on <body> tag (default: '')
 * - $extraCss         : array   - Additional stylesheet paths or URLs to include in <head>
 * - $extraStyles      : string  - Raw CSS rules to inject into an inline <style> block
 * - $extraHead        : string  - Raw HTML/tags to inject directly before </head>
 * - $openLayout       : bool    - Whether to render <div class="dashboard-layout"> (default: true)
 * - $includeSidebar   : bool    - Whether to include sidebar.php (default: true)
 * - $includeNavbar    : bool    - Whether to include navbar.php (default: true)
 * - $openContent      : bool    - Whether to open <div class="dashboard-content"> (default: true)
 * - $headOnly         : bool    - If true, only renders <!DOCTYPE> through </head> without opening <body> (default: false)
 * - $renderAlerts     : bool    - If true, renders $successMsg and $errorMsg automatically if set (default: true)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load core helpers if not already loaded
if (!function_exists('url')) {
    $helpersFile = __DIR__ . '/../includes/helpers.php';
    if (file_exists($helpersFile)) {
        require_once $helpersFile;
    }
}

// Configuration defaults
$pageTitle       = $pageTitle ?? 'StudentOS AI - Academic Operating System';
$bodyClass       = $bodyClass ?? '';
$openLayout      = $openLayout ?? true;
$includeSidebar  = $includeSidebar ?? true;
$includeNavbar   = $includeNavbar ?? true;
$openContent     = $openContent ?? true;
$headOnly        = $headOnly ?? false;
$renderAlerts    = $renderAlerts ?? false;
$extraCss        = (array)($extraCss ?? []);
$extraStyles     = $extraStyles ?? '';
$extraHead       = $extraHead ?? '';
$pageHeading     = $pageHeading ?? '';
$pageSubtitle    = $pageSubtitle ?? '';
$pageActions     = $pageActions ?? '';
$breadcrumbs     = $breadcrumbs ?? [];

// Helper to resolve asset paths across any folder depth
if (!function_exists('resolveAssetUrl')) {
    function resolveAssetUrl($path) {
        if (empty($path)) return '';
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        if (function_exists('url')) {
            return url($path);
        }
        return '../' . ltrim($path, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="StudentOS AI — Intelligent Academic Management and Operating System">
    
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Google Font Resources (Inter, Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- External CDN Resources (Font Awesome 6, Normalize.css) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">

    <!-- Core Application Stylesheets -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/variables.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/reset.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/global.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/components.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl('/assets/css/responsive.css')); ?>">

    <!-- Additional Custom CSS files -->
    <?php foreach ($extraCss as $cssHref): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars(resolveAssetUrl($cssHref)); ?>">
    <?php endforeach; ?>

    <!-- Page Specific Inline Styles -->
    <?php if (!empty($extraStyles)): ?>
        <style>
            <?php echo $extraStyles; ?>
        </style>
    <?php endif; ?>

    <!-- Additional Head Injections -->
    <?php if (!empty($extraHead)): ?>
        <?php echo $extraHead; ?>
    <?php endif; ?>
</head>

<?php if ($headOnly) { return; } ?>

<body class="<?php echo htmlspecialchars($bodyClass); ?>">

<?php if ($openLayout): ?>
    <?php if ($includeNavbar): ?>
        <?php include_once __DIR__ . '/navbar.php'; ?>
    <?php endif; ?>

    <div class="dashboard-layout">
        <?php if ($includeSidebar): ?>
            <?php include_once __DIR__ . '/sidebar.php'; ?>
        <?php endif; ?>

        <main class="dashboard-main">
            <?php if ($openContent): ?>
                <div class="dashboard-content">

                    <!-- Breadcrumbs (if provided) -->
                    <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
                        <nav class="breadcrumb-nav" aria-label="Breadcrumb" style="margin-bottom: 16px; font-size: 13px; color: var(--text-muted); display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <?php foreach ($breadcrumbs as $idx => $crumb): ?>
                                <?php if ($idx > 0): ?>
                                    <span style="opacity: 0.5;">/</span>
                                <?php endif; ?>
                                <?php if (!empty($crumb['url']) && $idx < count($breadcrumbs) - 1): ?>
                                    <a href="<?php echo htmlspecialchars(resolveAssetUrl($crumb['url'])); ?>" style="color: var(--text-secondary); text-decoration: none;">
                                        <?php echo htmlspecialchars($crumb['label']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-primary); font-weight: 500;">
                                        <?php echo htmlspecialchars($crumb['label']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </nav>
                    <?php endif; ?>

                    <!-- Standard Page Heading (if provided) -->
                    <?php if (!empty($pageHeading)): ?>
                        <div class="page-header">
                            <div>
                                <h1><?php echo htmlspecialchars($pageHeading); ?></h1>
                                <?php if (!empty($pageSubtitle)): ?>
                                    <p class="page-subtitle"><?php echo htmlspecialchars($pageSubtitle); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($pageActions)): ?>
                                <div class="header-actions">
                                    <?php echo $pageActions; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Automated Flash Alert Messages -->
                    <?php if ($renderAlerts): ?>
                        <?php if (!empty($successMsg)): ?>
                            <div class="alert alert-success" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); padding: 14px 18px; border-radius: var(--radius-md);">
                                <i class="fas fa-check-circle" style="font-size: 18px;"></i>
                                <div><?php echo htmlspecialchars($successMsg); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errorMsg)): ?>
                            <div class="alert alert-error" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); padding: 14px 18px; border-radius: var(--radius-md);">
                                <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
                                <div><?php echo htmlspecialchars($errorMsg); ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
            <?php endif; ?>
<?php endif; ?>
