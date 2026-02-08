<?php
namespace App;

class MacVendorLookup
{
    private static $vendors = null;
    private static $dbPath = __DIR__ . '/../data/mac-vendors.json';

    private static function load(): void
    {
        if (self::$vendors === null) {
            $json = file_get_contents(self::$dbPath);
            self::$vendors = $json ? json_decode($json, true) : [];
        }
    }

    public static function lookup(string $mac): ?string
    {
        self::load();
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
        if (strlen($mac) < 6) return null;

        $prefixes = [
            substr($mac, 0, 9),
            substr($mac, 0, 7),
            substr($mac, 0, 6),
        ];

        foreach ($prefixes as $prefix) {
            if (isset(self::$vendors[$prefix])) {
                return self::$vendors[$prefix];
            }
        }
        return null;
    }

    public static function isRandomizedMac(string $mac): bool
    {
        $mac = preg_replace('/[^A-Fa-f0-9]/', '', $mac);
        if (strlen($mac) < 2) return false;
        $firstByte = hexdec(substr($mac, 0, 2));
        return ($firstByte & 0x02) !== 0;
    }

    public static function getShortName(string $mac): ?string
    {
        if (self::isRandomizedMac($mac)) {
            return 'Private MAC';
        }
        $vendor = self::lookup($mac);
        if (!$vendor) return null;

        $brandMap = [
            'Huawei' => 'Huawei',
            'HUAWEI' => 'Huawei',
            'Samsung' => 'Samsung',
            'SAMSUNG' => 'Samsung',
            'Apple' => 'Apple',
            'Xiaomi' => 'Xiaomi',
            'OPPO' => 'OPPO',
            'Realme' => 'Realme',
            'OnePlus' => 'OnePlus',
            'Vivo' => 'Vivo',
            'Nokia' => 'Nokia',
            'HMD Global' => 'Nokia',
            'Motorola' => 'Motorola',
            'Lenovo' => 'Lenovo',
            'Sony' => 'Sony',
            'LG' => 'LG',
            'Google' => 'Google',
            'Intel' => 'Intel',
            'Dell' => 'Dell',
            'HP ' => 'HP',
            'Hewlett' => 'HP',
            'HEWLETT' => 'HP',
            'Microsoft' => 'Microsoft',
            'TP-Link' => 'TP-Link',
            'TP-LINK' => 'TP-Link',
            'Tenda' => 'Tenda',
            'TENDA' => 'Tenda',
            'D-Link' => 'D-Link',
            'Netgear' => 'Netgear',
            'NETGEAR' => 'Netgear',
            'Cisco' => 'Cisco',
            'MikroTik' => 'MikroTik',
            'Mikrotikls' => 'MikroTik',
            'Routerboard' => 'MikroTik',
            'Ubiquiti' => 'Ubiquiti',
            'ZTE' => 'ZTE',
            'Asus' => 'ASUS',
            'ASUSTek' => 'ASUS',
            'Aruba' => 'Aruba',
            'Ruckus' => 'Ruckus',
            'Cambium' => 'Cambium',
            'Ruijie' => 'Ruijie',
            'Tecno' => 'Tecno',
            'TECNO' => 'Tecno',
            'Infinix' => 'Infinix',
            'INFINIX' => 'Infinix',
            'itel' => 'itel',
            'Itel' => 'itel',
            'Transsion' => 'Transsion',
            'MediaTek' => 'MediaTek',
            'Qualcomm' => 'Qualcomm',
            'Espressif' => 'Espressif',
            'Amazon' => 'Amazon',
            'Roku' => 'Roku',
        ];

        foreach ($brandMap as $needle => $brand) {
            if (stripos($vendor, $needle) !== false) {
                return $brand;
            }
        }

        $short = preg_replace('/\s*(Inc\.?|Corp\.?|Co\.?,?\s*Ltd\.?|Ltd\.?|LLC|GmbH|Electronics|Technology|Communication|Technologies|International|Group)\s*/i', '', $vendor);
        $short = trim($short, ' ,.');
        if (strlen($short) > 20) {
            $short = substr($short, 0, 20);
        }
        return $short ?: $vendor;
    }

    public static function getDeviceIcon(string $vendor): string
    {
        $v = strtolower($vendor);
        if ($v === 'private mac') return 'bi-shield-lock';
        if (in_array($v, ['apple'])) return 'bi-apple';
        if (in_array($v, ['microsoft'])) return 'bi-windows';
        if (in_array($v, ['samsung', 'huawei', 'xiaomi', 'oppo', 'realme', 'oneplus', 'vivo', 'nokia', 'motorola', 'sony', 'lg', 'google', 'tecno', 'infinix', 'itel'])) return 'bi-phone';
        if (in_array($v, ['dell', 'hp', 'lenovo', 'asus', 'intel'])) return 'bi-laptop';
        if (in_array($v, ['tp-link', 'tenda', 'd-link', 'netgear', 'cisco', 'mikrotik', 'ubiquiti', 'aruba', 'ruckus', 'cambium', 'ruijie', 'zte'])) return 'bi-router';
        if (in_array($v, ['amazon', 'roku', 'espressif'])) return 'bi-cast';
        return 'bi-device-hdd';
    }
}
