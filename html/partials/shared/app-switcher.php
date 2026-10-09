<?php
/**
 * The application switcher (K31; the design-system's "Header: the application switcher"): in the header's right,
 * before the user menu — the Helpdesk button (when the person holds the Help Desk and this is not it) and the
 * dropdown of every application the person may open, the current one marked, the launcher and (a super-admin) the
 * operating system at the foot. Every link leaves this application, so none is an HTMX swap. Renders nothing
 * standalone or before the kernel answered. Included by html/app.php; $feed may be given (the proofs do).
 */
require_once __DIR__ . '/../../../helpers/os_switcher.php';
$feed = $feed ?? os_my_applications();
if (!is_array($feed) || empty($feed['applications'])) {
    return;
}
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$apps = $feed['applications'];
$helpdesk = null;
foreach ($apps as $a) {
    if (($a['key'] ?? '') === 'helpdesk' && empty($a['current'])) {
        $helpdesk = $a;
    }
}
$item = static function (array $a, ?array $scope = null) use ($e): string {
    $id = 'header-apps-item-' . (int)$a['id'] . ($scope !== null ? '-' . (int)$scope['id'] : '');
    $label = $e($a['name']) . ($scope !== null ? ' <span class="text-muted">· ' . $e($scope['name']) . '</span>' : '');
    $icon = '<i class="' . $e($a['icon'] ?? 'feather-grid') . ' me-2"></i>';
    if (!empty($a['current'])) {            // this application — every row of it, a scoped one's sites too (its own business switcher changes the business)
        return '<span class="dropdown-item active d-flex align-items-center" id="' . $id . '" aria-current="page">' . $icon . '<span>' . $label . '</span></span>';
    }
    return '<a href="' . $e(os_launch_href(($scope ?? $a)['launch_path'])) . '" class="dropdown-item d-flex align-items-center" id="' . $id . '">' . $icon . '<span>' . $label . '</span></a>';
};
?>
<?php if ($helpdesk !== null): ?>
<a href="<?= $e(os_launch_href($helpdesk['launch_path'])) ?>" class="btn btn-sm btn-outline-light me-3 header-helpdesk" id="header-helpdesk-btn" title="Help Desk"><i class="<?= $e($helpdesk['icon'] ?? 'feather-life-buoy') ?> me-1"></i><span class="d-none d-sm-inline">Helpdesk</span></a>
<?php endif; ?>
<div class="dropdown me-3" id="header-apps">
    <a href="#!" class="text-white d-flex align-items-center" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside" aria-expanded="false" id="header-apps-toggle" aria-label="Your applications" title="Your applications"><i class="feather-grid fs-5"></i></a>
    <div class="dropdown-menu dropdown-menu-end shadow-lg header-apps-menu" id="header-apps-menu">
        <h6 class="dropdown-header" id="header-apps-title">Your applications</h6>
        <div class="dropdown-divider"></div>
        <div class="header-apps-list" id="header-apps-list">
        <?php foreach ($apps as $a): ?>
            <?php if (!empty($a['scopes'])): foreach ($a['scopes'] as $s): ?><?= $item($a, $s) ?><?php endforeach; else: ?><?= $item($a) ?><?php endif; ?>
        <?php endforeach; ?>
        </div>
        <div class="dropdown-divider"></div>
        <a href="<?= $e(os_switcher_launcher_url()) ?>" class="dropdown-item d-flex align-items-center" id="header-apps-launcher"><i class="feather-layout me-2"></i><span>All applications</span></a>
        <?php if (!empty($feed['os_url'])): ?><a href="<?= $e($feed['os_url']) ?>" class="dropdown-item d-flex align-items-center" id="header-apps-os"><i class="feather-cpu me-2"></i><span>Operating system</span></a><?php endif; ?>
    </div>
</div>
