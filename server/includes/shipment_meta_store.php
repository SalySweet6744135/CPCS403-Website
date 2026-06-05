<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: server/includes/shipment_meta_store.php
 * Purpose: Shipment metadata store — JSON overlay for weight and ETA fields
 */
function shipment_meta_json_path(): string
{
    return dirname(__DIR__) . '/data/shipment_tracking_meta.json';
}

/** @return array<string, array<string, mixed>> */
function shipment_meta_json_load(): array
{
    $path = shipment_meta_json_path();
    if (!is_readable($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path) ?: '{}', true);

    return is_array($data) ? $data : [];
}

/** @param array<string, array<string, mixed>> $data */
function shipment_meta_json_save(array $data): void
{
    $path = shipment_meta_json_path();
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

/**
 * @return array<string, array{weight_kg: ?string, estimated_delivery: ?string}>
 */
function shipment_meta_load_all(): array
{
    $out  = [];
    $file = shipment_meta_json_load();

    foreach ($file as $id => $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[(string) $id] = [
            'weight_kg'          => isset($row['weight_kg']) && $row['weight_kg'] !== ''
                ? (string) $row['weight_kg'] : null,
            'estimated_delivery' => isset($row['estimated_delivery']) && $row['estimated_delivery'] !== ''
                ? (string) $row['estimated_delivery'] : null,
            'status'             => isset($row['status']) && $row['status'] !== ''
                ? (string) $row['status'] : null,
        ];
    }

    return $out;
}

/**
 * @param array{weight_kg?: ?string, estimated_delivery?: ?string} $fields
 */
function shipment_meta_upsert(
    string $trackingmoreId,
    string $trackingNumber,
    string $courierCode,
    array $fields
): void {
    if ($trackingmoreId === '') {
        return;
    }

    $existing = shipment_meta_load_all();
    $weight   = array_key_exists('weight_kg', $fields)
        ? $fields['weight_kg']
        : ($existing[$trackingmoreId]['weight_kg'] ?? null);
    $eta      = array_key_exists('estimated_delivery', $fields)
        ? $fields['estimated_delivery']
        : ($existing[$trackingmoreId]['estimated_delivery'] ?? null);
    $status   = array_key_exists('status', $fields)
        ? $fields['status']
        : ($existing[$trackingmoreId]['status'] ?? null);

    $all = shipment_meta_json_load();
    $all[$trackingmoreId] = [
        'tracking_number'    => $trackingNumber,
        'courier_code'       => $courierCode,
        'weight_kg'          => ($weight !== null && $weight !== '') ? (string) $weight : null,
        'estimated_delivery' => ($eta !== null && $eta !== '') ? (string) $eta : null,
        'status'             => ($status !== null && $status !== '') ? (string) $status : null,
    ];
    shipment_meta_json_save($all);
}

function shipment_meta_remove(string $trackingmoreId): void
{
    if ($trackingmoreId === '') {
        return;
    }
    $all = shipment_meta_json_load();
    if (!isset($all[$trackingmoreId])) {
        return;
    }
    unset($all[$trackingmoreId]);
    shipment_meta_json_save($all);
}

/** @param array<int, array<string, mixed>> $shipments */
function shipment_meta_merge_list(array $shipments): array
{
    $meta = shipment_meta_load_all();
    if ($meta === []) {
        return $shipments;
    }

    foreach ($shipments as &$s) {
        $id = (string) ($s['id'] ?? '');
        if ($id === '' || !isset($meta[$id])) {
            continue;
        }
        $m = $meta[$id];
        if ($m['weight_kg'] !== null) {
            $s['weight_kg'] = $m['weight_kg'];
        }
        if ($m['estimated_delivery'] !== null) {
            $s['estimated_delivery'] = $m['estimated_delivery'];
        }
        if ($m['status'] !== null) {
            $s['status'] = $m['status'];
        }
    }
    unset($s);

    return $shipments;
}
