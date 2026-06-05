<?php
/*
 * Name: Manar Alharbi, Wareef Alzubaidi, Sama Salloum
 * ID: 2206712, 2207221, 2205679
 * Section: CPCS403
 * Date: 31-05-2026
 * File: server/includes/trackingmore_sdk.php
 * Purpose: TrackingMore SDK loader — manual bootstrap for courier and tracking classes
 */
function trackingmore_load_sdk(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $sdkBase = dirname(__DIR__, 2) . '/trackingmore/trackingmore-sdk-php/src/';
    require_once $sdkBase . 'TrackingMoreException.php';
    require_once $sdkBase . 'ErrorMessages.php';
    require_once $sdkBase . 'Request.php';
    require_once $sdkBase . 'Interfaces/CouriersInterface.php';
    require_once $sdkBase . 'Couriers.php';
    require_once $sdkBase . 'Interfaces/TrackingsInterface.php';
    require_once $sdkBase . 'Trackings.php';
    require_once $sdkBase . 'Interfaces/AirWaybillsInterface.php';
    require_once $sdkBase . 'AirWaybills.php';
    $loaded = true;
}

function trackingmore_trackings(): \TrackingMore\Trackings
{
    trackingmore_load_sdk();
    $key = require dirname(__DIR__) . '/config.php';
    return new \TrackingMore\Trackings($key);
}
