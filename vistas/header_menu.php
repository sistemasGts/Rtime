<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Panel';
$pageSubtitle = $pageSubtitle ?? '';
$headerLinks = $headerLinks ?? [];
$logoutLink = $logoutLink ?? '../proceso/logout.php';

require_once __DIR__ . '/../datos/db.php';
require_once __DIR__ . '/../datos/permisos.php';
require_once __DIR__ . '/../datos/local_scope.php';

$currentScript = rtCurrentScriptBasename();
if ($currentScript !== '' && !rtIsAllowedLocalPage($currentScript)) {
    header('Location: ' . rtBuildWebRedirectUrl($currentScript));
    exit;
}

// Enlaces por defecto que deben mostrarse en el header en todas las páginas.
$defaultHeaderLinks = [
];

function normalizeLink(array $link): array {
    return [
        'href' => basename($link['href'] ?? ''),
        'label' => mb_strtoupper(trim($link['label'] ?? '')),
    ];
}

function linkExists(array $links, array $target): bool {
    foreach ($links as $link) {
        $normalized = normalizeLink($link);
        if ($normalized['href'] !== '' && $normalized['href'] === $target['href']) {
            return true;
        }
        if ($normalized['label'] !== '' && $normalized['label'] === $target['label']) {
            return true;
        }
    }
    return false;
}

function menuContainsLink(array $nodes, array $target): bool {
    foreach ($nodes as $node) {
        $itemHref = basename(trim($node['mod_url'] ?? ''));
        $itemLabel = mb_strtoupper(trim($node['mod_nombre'] ?? ''));
        if ($itemHref !== '' && $itemHref === $target['href']) {
            return true;
        }
        if ($itemLabel !== '' && $itemLabel === $target['label']) {
            return true;
        }
        if (!empty($node['children']) && menuContainsLink($node['children'], $target)) {
            return true;
        }
    }
    return false;
}

function hasPermission($perm) {
    if (empty($perm)) {
        return true;
    }
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($perm, $_SESSION['permissions'], true);
    }
    return true;
}

function getModuleTree(): array {
    global $con2;
    $modules = [];
    $result = $con2->query('SELECT mod_id, mod_padre, mod_nombre, mod_url, mod_icono, mod_orden FROM ad_modulo WHERE mod_estado = 1 ORDER BY mod_padre ASC, mod_orden ASC, mod_nombre ASC');
    if (!$result) {
        return $modules;
    }
    while ($row = $result->fetch_assoc()) {
        $row['children'] = [];
        $row['mod_id'] = (int)$row['mod_id'];
        $row['mod_padre'] = $row['mod_padre'] !== null ? (int)$row['mod_padre'] : 0;
        $modules[$row['mod_id']] = $row;
    }
    $result->free();

    $tree = [];
    foreach ($modules as $id => &$module) {
        if ($module['mod_padre'] > 0 && isset($modules[$module['mod_padre']])) {
            $modules[$module['mod_padre']]['children'][] = &$module;
        } else {
            $tree[] = &$module;
        }
    }
    return $tree;
}

function filterVisibleTree(array &$node, array $allowed): bool {
    $visible = isset($allowed[$node['mod_id']]);
    foreach ($node['children'] as $key => &$child) {
        if (filterVisibleTree($child, $allowed)) {
            $visible = true;
        } else {
            unset($node['children'][$key]);
        }
    }
    return $visible;
}

function renderMenuNode(array $node, bool $isDropdown = false): void {
    $label = htmlspecialchars($node['mod_nombre']);
    $url = trim($node['mod_url'] ?? '');
    $hasChildren = !empty($node['children']);
    $href = $url !== '' ? htmlspecialchars($url) : '#';

    if ($hasChildren) {
        if ($isDropdown) {
            echo '<div class="submenu" style="position:relative;">';
            echo '<a href="' . $href . '">' . $label . ' ▸</a>';
            echo '<div class="submenu-menu" aria-hidden="true">';
            foreach ($node['children'] as $child) {
                renderMenuNode($child, true);
            }
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="dropdown">';
            echo '<a href="' . $href . '">' . $label . ' ▾</a>';
            echo '<div class="dropdown-menu" aria-hidden="true">';
            foreach ($node['children'] as $child) {
                renderMenuNode($child, true);
            }
            echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<a href="' . $href . '">' . $label . '</a>';
    }
}

function renderMenu(array $nodes): void {
    foreach ($nodes as $node) {
        renderMenuNode($node, false);
    }
}

function filterLocalScopeNodes(array $nodes): array {
    $allowedBasenames = [];
    foreach (RT_LOCAL_ALLOWED_PAGES as $page) {
        $allowedBasenames[] = basename($page);
    }

    $filtered = [];
    foreach ($nodes as $node) {
        $currentNode = $node;
        $children = $currentNode['children'] ?? [];
        if (!empty($children)) {
            $children = filterLocalScopeNodes($children);
        }

        $nodeUrl = trim((string)($currentNode['mod_url'] ?? ''));
        $nodeBasename = $nodeUrl !== '' ? basename($nodeUrl) : '';
        $isAllowedNode = in_array($nodeBasename, $allowedBasenames, true);

        if ($isAllowedNode || !empty($children)) {
            $currentNode['children'] = $children;
            $filtered[] = $currentNode;
        }
    }

    return $filtered;
}

$userAllowed = getUserAllowedModules();
if (!empty($userAllowed)) {
    foreach ($defaultHeaderLinks as $dlink) {
        if (!linkExists($headerLinks, normalizeLink($dlink))) {
            $headerLinks[] = $dlink;
        }
    }
}

$moduleTree = getModuleTree();
$visibleModules = [];
foreach ($moduleTree as $node) {
    if (filterVisibleTree($node, $userAllowed)) {
        $visibleModules[] = $node;
    }
}

$visibleModules = filterLocalScopeNodes($visibleModules);
?>
<div class="panel-header">
    <div>
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <?php if ($pageSubtitle !== ''): ?>
            <p><?= htmlspecialchars($pageSubtitle) ?></p>
        <?php endif; ?>
    </div>
    <div class="nav-links">
        <?php if (!empty($visibleModules)): ?>
            <?php renderMenu($visibleModules); ?>
        <?php endif; ?>

        <?php foreach ($headerLinks as $link): ?>
            <?php if (hasPermission($link['perm'] ?? '')): ?>
                <?php if (!menuContainsLink($visibleModules, normalizeLink($link))): ?>
                    <a href="<?= htmlspecialchars($link['href'] ?? '#') ?>"><?= htmlspecialchars($link['label'] ?? 'Ir') ?></a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <a href="<?= htmlspecialchars($logoutLink) ?>" class="btn-danger">Cerrar sesión</a>
    </div>
</div>
<script>
(function() {
    var dropdowns = document.querySelectorAll('.nav-links .dropdown > a');
    dropdowns.forEach(function(toggle) {
        var parent = toggle.parentElement;
        var menu = parent.querySelector('.dropdown-menu');
        if (!menu) return;
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            var isOpen = parent.classList.toggle('open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            menu.style.display = isOpen ? 'block' : 'none';
        });
        menu.addEventListener('click', function(e) { e.stopPropagation(); });
    });

    var submenuToggles = document.querySelectorAll('.nav-links .submenu > a');
    submenuToggles.forEach(function(toggle) {
        var parent = toggle.parentElement;
        var menu = parent.querySelector('.submenu-menu');
        if (!menu) return;
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            var isOpen = parent.classList.toggle('open');
            menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            menu.style.display = isOpen ? 'block' : 'none';
        });
        menu.addEventListener('click', function(e) { e.stopPropagation(); });
    });

    document.addEventListener('click', function(e) {
        document.querySelectorAll('.nav-links .dropdown.open').forEach(function(openDropdown) {
            if (!openDropdown.contains(e.target)) {
                openDropdown.classList.remove('open');
                var menu = openDropdown.querySelector('.dropdown-menu');
                var toggle = openDropdown.querySelector('> a');
                if (menu) {
                    menu.setAttribute('aria-hidden', 'true');
                    menu.style.display = 'none';
                }
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
    });
})();
</script>
