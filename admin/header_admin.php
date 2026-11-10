<?php
// Sidebar compartida per les pàgines d'administració
// Requereix que $currentPage estigui definit
if (!isset($currentPage)) $currentPage = '';
$user = getCurrentUser();
$userName = $user ? htmlspecialchars($user['nom']) : 'Admin';
$userInitial = mb_substr($userName, 0, 1);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div style="width: 24px; height: 24px; background: var(--secondary-color); border-radius: 4px;"></div>
        BiblioAdmin
    </div>

    <nav class="sidebar-nav">
        <a href="index.php" class="nav-item <?= $currentPage === 'admin_catalog' ? 'active' : '' ?>">
            <span>📚 Catàleg</span>
        </a>
        <a href="alta_llibre.php" class="nav-item <?= $currentPage === 'admin_alta' ? 'active' : '' ?>">
            <span>➕ Alta Llibre</span>
        </a>

        <hr style="border: none; border-top: 1px solid var(--border-color); margin: 1rem 0;">
        <small style="color: var(--text-muted); padding: 0 1rem; font-size: 0.7rem; text-transform: uppercase;">Àrea d'Usuari</small>

        <a href="../pages/dashboard.php" class="nav-item">
            <span>🏠 El meu espai</span>
        </a>
        <a href="../pages/catalog.php" class="nav-item">
            <span>📖 Catàleg públic</span>
        </a>
        <a href="../pages/profile.php" class="nav-item">
            <span>👤 El meu perfil</span>
        </a>
        <a href="../pages/logout.php" class="nav-item" style="margin-top: auto; color: var(--error-color);">
            <span>🚪 Tancar Sessió</span>
        </a>
    </nav>
</aside>
