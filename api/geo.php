<?php
/**
 * TCC RRS – Geographic cascade API (PSGC)
 *
 * GET api/geo.php?level=regions                 → all regions
 * GET api/geo.php?parent=<regionCode>           → provinces (or cities when the
 *                                                 region has none, e.g. NCR)
 * GET api/geo.php?parent=<provinceCode>         → cities / municipalities
 * GET api/geo.php?parent=<cityCode>             → barangays
 *
 * Returns: { "kind": "provinces|cities|barangays", "items": [{code,name,label}] }
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$treeFile = __DIR__ . '/../assets/data/psgc.json';
if (!is_file($treeFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'geo data unavailable']);
    exit;
}
$tree = json_decode(file_get_contents($treeFile), true);

$level  = $_GET['level']  ?? '';
$parent = $_GET['parent'] ?? '';

function regLabel(array $reg): string {
    return ($reg['rn'] ?? '') . ' (' . $reg['n'] . ')';
}

if ($level === 'regions') {
    $items = [];
    foreach ($tree as $code => $reg) {
        $items[] = ['code' => $code, 'name' => $reg['n'], 'label' => regLabel($reg)];
    }
    echo json_encode(['kind' => 'regions', 'items' => $items]);
    exit;
}

if ($parent === '' || !isset($tree) ) {
    http_response_code(400);
    echo json_encode(['error' => 'missing parent']);
    exit;
}

// Region → provinces, or cities when the region has no provinces
if (isset($tree[$parent])) {
    $reg = $tree[$parent];
    if (!empty($reg['p'])) {
        $items = [];
        foreach ($reg['p'] as $code => $p) {
            $items[] = ['code' => $code, 'name' => $p['n'], 'label' => $p['n']];
        }
        echo json_encode(['kind' => 'provinces', 'items' => $items]);
    } else {
        $items = [];
        foreach ($reg['c'] as $code => $c) {
            $items[] = ['code' => $code, 'name' => $c['n'], 'label' => $c['n']];
        }
        echo json_encode(['kind' => 'cities', 'items' => $items]);
    }
    exit;
}

// Province → cities / municipalities
foreach ($tree as $reg) {
    foreach ($reg['p'] as $pCode => $p) {
        if ((string) $pCode === $parent) {
            $items = [];
            foreach ($p['c'] as $code => $c) {
                $items[] = ['code' => $code, 'name' => $c['n'], 'label' => $c['n']];
            }
            echo json_encode(['kind' => 'cities', 'items' => $items]);
            exit;
        }
    }
}

// City / municipality → barangays
foreach ($tree as $reg) {
    foreach ($reg['p'] as $p) {
        foreach ($p['c'] as $cCode => $c) {
            if ((string) $cCode === $parent) {
                $items = [];
                foreach ($c['b'] as $code => $name) {
                    $items[] = ['code' => $code, 'name' => $name, 'label' => $name];
                }
                echo json_encode(['kind' => 'barangays', 'items' => $items]);
                exit;
            }
        }
    }
    foreach ($reg['c'] as $cCode => $c) {
        if ((string) $cCode === $parent) {
            $items = [];
            foreach ($c['b'] as $code => $name) {
                $items[] = ['code' => $code, 'name' => $name, 'label' => $name];
            }
            echo json_encode(['kind' => 'barangays', 'items' => $items]);
            exit;
        }
    }
}

http_response_code(404);
echo json_encode(['error' => 'parent not found']);
