<?php
/** 菜单访问设置（权限由 index.php 路由表统一校验：system:menu / system:menu:*） */

function menu_row($r)
{
    return [
        'id'         => (int) $r['id'],
        'parentId'   => (int) $r['parent_id'],
        'title'      => $r['title'],
        'path'       => $r['path'],
        'permission' => $r['permission'],
        'icon'       => $r['icon'],
        'type'       => $r['type'],
        'sort'       => (int) $r['sort'],
        'visible'    => (int) $r['visible'] === 1,
    ];
}

/** GET /system/menu/list —— 平铺列表（前端组树） */
function api_sysmenu_list()
{
    $rows = q_all('SELECT * FROM sys_menu WHERE is_del = 0 ORDER BY parent_id, sort, id');
    ok(array_map('menu_row', $rows));
}

/** POST /system/menu/add {parentId, title, path, permission?, icon?, sort?, visible?} */
function api_sysmenu_add()
{
    $title = trim((string) param('title', ''));
    $path = trim((string) param('path', ''));
    if ($title === '' || $path === '') err('菜单名称和路由路径为必填');

    $parentId = (int) param('parentId', 0);
    if ($parentId) {
        $parent = q_one('SELECT * FROM sys_menu WHERE id = ? AND is_del = 0', [$parentId]);
        if (!$parent) err('父级菜单不存在');
    }

    $id = q_insert('sys_menu', [
        'parent_id'  => $parentId,
        'title'      => $title,
        'path'       => $path,
        'permission' => (string) param('permission', ''),
        'icon'       => (string) param('icon', ''),
        'type'       => (string) param('type', 'menu') === 'dir' ? 'dir' : 'menu',
        'sort'       => (int) param('sort', 1),
        'visible'    => (int) param('visible', 1) ? 1 : 0,
    ]);
    ok(['id' => $id]);
}

/** POST /system/menu/update {id, title?, path?, permission?, icon?, sort?, visible?, type?} */
function api_sysmenu_update()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM sys_menu WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('菜单不存在');

    $allow = ['title', 'path', 'permission', 'icon', 'type'];
    $data = [];
    foreach ($allow as $key) {
        $v = param($key);
        if ($v !== null) $data[$key] = trim((string) $v);
    }
    if (param('sort') !== null) $data['sort'] = (int) param('sort');
    if (param('visible') !== null) $data['visible'] = (int) param('visible') ? 1 : 0;
    if (isset($data['type']) && !in_array($data['type'], ['dir', 'menu'], true)) {
        unset($data['type']);
    }
    if ($data) q_update('sys_menu', $data, 'id = ?', [$id]);
    ok(null);
}

/** POST /system/menu/visible {id, visible} */
function api_sysmenu_visible()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');
    $row = q_one('SELECT * FROM sys_menu WHERE id = ? AND is_del = 0', [$id]);
    if (!$row) err('菜单不存在');
    $visible = (int) param('visible', 1) ? 1 : 0;
    q_update('sys_menu', ['visible' => $visible], 'id = ?', [$id]);
    ok(null);
}

/** POST /system/menu/delete {id} —— 连同子菜单一起删除 */
function api_sysmenu_delete()
{
    $id = (int) param('id', 0);
    if (!$id) err('缺少参数 id');

    // 递归收集全部子菜单 id
    $ids = [$id];
    $all = q_all('SELECT id, parent_id FROM sys_menu WHERE is_del = 0');
    $children = [];
    foreach ($all as $r) {
        $children[(int) $r['parent_id']][] = (int) $r['id'];
    }
    $walk = function ($pid) use (&$walk, &$ids, $children) {
        foreach ($children[$pid] ?? [] as $cid) {
            $ids[] = $cid;
            $walk($cid);
        }
    };
    $walk($id);

    $marks = implode(',', array_fill(0, count($ids), '?'));
    q_exec("UPDATE sys_menu SET is_del = 1 WHERE id IN ($marks)", $ids);
    ok(['deleted' => count($ids)]);
}
