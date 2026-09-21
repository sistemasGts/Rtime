<?php
/**
 * Gestionar Módulos - Helpers
 * Funciones para construir y renderizar árbol de módulos
 */

function buildModuleTree(array $modules): array {
    $lookup = [];
    foreach ($modules as $module) {
        $module['children'] = [];
        $lookup[(int)$module['mod_id']] = $module;
    }

    $tree = [];
    foreach ($lookup as $id => &$module) {
        $parentId = $module['mod_padre'] !== null ? (int)$module['mod_padre'] : 0;
        if ($parentId === 0 || !isset($lookup[$parentId])) {
            $tree[$id] = &$module;
        } else {
            $lookup[$parentId]['children'][$id] = &$module;
        }
    }
    return $tree;
}

function renderModuleTree(array $items, array $permissions): void {
    echo '<ul class="module-tree">';
    foreach ($items as $item) {
        $checked = in_array((int)$item['mod_id'], $permissions, true) ? 'checked' : '';
        $safeName = htmlspecialchars($item['mod_nombre']);
        echo '<li class="module-node">';
        echo '<label><input type="checkbox" class="module-checkbox" name="permisos[]" value="' . (int)$item['mod_id'] . '" ' . $checked . '> ' . $safeName;
        if (!empty($item['mod_url'])) {
            echo ' <span class="module-url">(' . htmlspecialchars($item['mod_url']) . ')</span>';
        }
        echo '</label>';
        if (!empty($item['children'])) {
            renderModuleTree($item['children'], $permissions);
        }
        echo '</li>';
    }
    echo '</ul>';
}
