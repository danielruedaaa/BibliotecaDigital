<?php
// Sidebar compartida per totes les pàgines del dashboard
// Requereix que $currentPage estigui definit abans d'incloure
if (!isset($currentPage))
    $currentPage = '';
$user = getCurrentUser();
$userName = $user ? htmlspecialchars($user['nom']) : 'Usuari';
$userInitial = mb_substr($userName, 0, 1);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div style="width: 24px; height: 24px; background: var(--primary-color); border-radius: 4px;"></div>
        BiblioDigital
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <span>El meu espai</span>
        </a>
        <a href="catalog.php" class="nav-item <?= $currentPage === 'catalog' ? 'active' : '' ?>">
            <span>Catàleg</span>
        </a>
        <a href="profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
            <span>El meu perfil</span>
        </a>
        <?php if ($user && $user['rol'] === 'admin'): ?>
            <a href="../admin/index.php" class="nav-item" style="color: var(--secondary-color);">
                <span>⚙️ Panell Admin</span>
            </a>
        <?php endif; ?>
        <a href="logout.php" class="nav-item" style="margin-top: auto; color: var(--error-color);">
            <span>Tancar Sessió</span>
        </a>
    </nav>
</aside>