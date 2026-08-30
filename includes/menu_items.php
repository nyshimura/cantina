<?php
// includes/menu_items.php

// Define all navigation items centrally
$navItems = [
    ['perm' => 'canViewDashboard', 'url' => 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'layout-grid'],
    ['perm' => 'canManageSettings', 'url' => 'settings.php', 'label' => 'Configurações', 'icon' => 'settings', 'mobileLabel' => 'Config.'],
    ['perm' => 'canManageFinancial', 'url' => 'financial.php', 'label' => 'Financeiro', 'icon' => 'dollar-sign', 'mobileLabel' => 'Financ.'],
    ['perm' => 'canManageStudents', 'url' => 'students.php', 'label' => 'Alunos', 'icon' => 'graduation-cap'],
    ['perm' => 'canManageParents', 'url' => 'parents.php', 'label' => 'Responsáveis', 'icon' => 'users'],
    ['perm' => 'canManageSettings', 'url' => 'schedules.php', 'label' => 'Horários e Turmas', 'icon' => 'clock', 'mobileLabel' => 'Turmas'],
    ['perm' => 'canManagePreOrders', 'url' => 'kitchen_dashboard.php', 'label' => 'Cozinha (Preparo)', 'icon' => 'chef-hat', 'mobileLabel' => 'Cozinha'],
    ['perm' => 'canManagePreOrders', 'url' => 'room_orders.php', 'label' => 'Coleta na Sala', 'icon' => 'tablet-smartphone', 'mobileLabel' => 'Coleta'],
    ['perm' => 'canManagePreOrders', 'url' => 'dispatch.php', 'label' => 'Painel de Entrega (TV)', 'icon' => 'monitor-speaker', 'mobileLabel' => 'TV'],
    ['perm' => 'canManageTags', 'url' => 'tags.php', 'label' => 'Tags NFC', 'icon' => 'rss'],
    ['perm' => 'canManageTeam', 'url' => 'team.php', 'label' => 'Equipe', 'icon' => 'shield-check'],
    ['perm' => 'canViewLogs', 'url' => 'logs.php', 'label' => 'Auditoria', 'icon' => 'file-text']
];
