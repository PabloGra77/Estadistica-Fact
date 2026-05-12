<?php
/**
 * Layout principal — incluir al inicio de cada vista protegida.
 * Uso:  $pageTitle = 'Mi Página'; require BASE_PATH.'/views/layout/header.php';
 */
$rolColors = [
    ROL_ADMINISTRADOR => '#60a5fa',
    ROL_FACTURADOR    => '#34d399',
    ROL_EQUIPO_PPL    => '#fbbf24',
    ROL_ESTADISTICO   => '#a78bfa',
];
$rolIcons = [
    ROL_ADMINISTRADOR => '⚙️',
    ROL_FACTURADOR    => '💳',
    ROL_EQUIPO_PPL    => '👥',
    ROL_ESTADISTICO   => '📊',
];
$rolActual = Auth::rol();
$rolColor  = $rolColors[$rolActual] ?? '#94a3b8';
$rolIcon   = $rolIcons[$rolActual]  ?? '👤';
$rolNombre = Auth::rolNombre();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= Security::e($pageTitle ?? APP_NAME) ?></title>
    <meta name="robots" content="noindex,nofollow"/>
    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous"/>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
    <link rel="stylesheet" href="/public/css/app.css"/>
</head>
<body>

<!-- Sidebar -->
<div class="d-flex" id="wrapper">
    <div id="sidebar">
        <div class="sidebar-brand">
            <span class="brand-ppl">PPL</span>
            <span class="brand-sub">IPS Goleman</span>
        </div>
        <nav class="sidebar-nav">
            <a href="/" class="nav-item <?= ($uri === '/') ? 'active' : '' ?>">
                <i class="bi bi-house-door-fill"></i> Inicio
            </a>
            <a href="/pacientes" class="nav-item <?= str_starts_with($uri, '/pacientes') ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Pacientes
            </a>
            <a href="/atenciones" class="nav-item <?= str_starts_with($uri, '/atenciones') ? 'active' : '' ?>">
                <i class="bi bi-calendar2-check-fill"></i> Atenciones
            </a>
            <a href="/soportes" class="nav-item <?= str_starts_with($uri, '/soportes') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text-fill"></i> Soportes
            </a>
            <?php if (Auth::isAdmin()): ?>
            <div class="nav-section">Administración</div>
            <a href="/admin/usuarios" class="nav-item <?= str_starts_with($uri, '/admin') ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i> Usuarios
            </a>
            <?php endif; ?>
        </nav>

        <!-- Info usuario -->
        <div class="user-panel">
            <div class="user-avatar"><?= Security::e(strtoupper(substr(Auth::username(), 0, 1))) ?></div>
            <div class="user-info">
                <div class="user-name"><?= Security::e(Auth::username()) ?></div>
                <div class="user-email"><?= Security::e(Auth::username() . EMAIL_DOMAIN) ?></div>
                <span class="role-badge" style="color:<?= $rolColor ?>;border-color:<?= $rolColor ?>">
                    <?= $rolIcon ?> <?= Security::e($rolNombre) ?>
                </span>
            </div>
            <form method="post" action="/logout" class="logout-form">
                <?= Security::csrfField() ?>
                <button type="submit" class="btn-logout" title="Cerrar sesión">
                    <i class="bi bi-power"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Contenido principal -->
    <div id="content-wrapper">
        <button id="sidebarToggle" title="Menú"><i class="bi bi-list"></i></button>
        <div id="main-content">
