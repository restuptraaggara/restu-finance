<?php

namespace App\Services;

use Illuminate\Http\Request;

class LanService
{
    /**
     * Regex untuk memeriksa apakah sebuah IP adalah private IPv4:
     * - 10.0.0.0 - 10.255.255.255
     * - 172.16.0.0 - 172.31.255.255
     * - 192.168.0.0 - 192.168.255.255
     */
    protected const PRIVATE_IPV4_REGEX = '/^(?:10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(?:1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3})$/';

    /**
     * Memeriksa apakah sebuah IP merupakan private IPv4 yang valid (bukan loopback/APIPA).
     */
    public function isPrivateIPv4(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if ($ip === '127.0.0.1' || str_starts_with($ip, '169.254.') || str_starts_with($ip, '0.')) {
            return false;
        }

        return (bool) preg_match(self::PRIVATE_IPV4_REGEX, $ip);
    }

    /**
     * Mengumpulkan semua IP private yang terdeteksi pada mesin host.
     */
    public function getLanIps(?Request $request = null): array
    {
        $ips = [];

        // 1. Cek dari host hostname lookup (standar PHP)
        $hostname = gethostname();
        if ($hostname) {
            $hostIps = @gethostbynamel($hostname);
            if (is_array($hostIps)) {
                foreach ($hostIps as $ip) {
                    if ($this->isPrivateIPv4($ip)) {
                        $ips[] = $ip;
                    }
                }
            }
        }

        // 2. Cek SERVER_ADDR jika ada
        if ($request && $request->server('SERVER_ADDR')) {
            $serverIp = $request->server('SERVER_ADDR');
            if ($this->isPrivateIPv4($serverIp)) {
                $ips[] = $serverIp;
            }
        }

        // 3. Cek request host jika client mengakses langsung via IP LAN
        if ($request) {
            $reqHost = $request->getHost();
            if ($this->isPrivateIPv4($reqHost)) {
                $ips[] = $reqHost;
            }
        }

        // 4. Fallback socket lookup jika belum ada IP terdeteksi
        if (empty($ips) && function_exists('socket_create')) {
            try {
                $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                if ($sock) {
                    @socket_connect($sock, '8.8.8.8', 53);
                    @socket_getsockname($sock, $sockIp);
                    @socket_close($sock);
                    if ($sockIp && $this->isPrivateIPv4($sockIp)) {
                        $ips[] = $sockIp;
                    }
                }
            } catch (\Throwable) {}
        }

        $unique = array_values(array_unique($ips));

        // Prioritaskan 192.168.x.x > 10.x.x.x > 172.x.x.x
        usort($unique, function ($a, $b) {
            $scoreA = str_starts_with($a, '192.168.') ? 3 : (str_starts_with($a, '10.') ? 2 : 1);
            $scoreB = str_starts_with($b, '192.168.') ? 3 : (str_starts_with($b, '10.') ? 2 : 1);
            return $scoreB <=> $scoreA;
        });

        return $unique;
    }

    /**
     * Mendapatkan IP LAN utama yang paling direkomendasikan.
     */
    public function getPrimaryLanIp(?Request $request = null): ?string
    {
        $ips = $this->getLanIps($request);
        return $ips[0] ?? null;
    }

    /**
     * Mendapatkan port aplikasi yang sedang aktif.
     */
    public function getPort(?Request $request = null): int
    {
        if ($request) {
            $port = (int) $request->getPort();
            if ($port > 0) {
                return $port;
            }
        }

        $serverPort = (int) ($_SERVER['SERVER_PORT'] ?? 8000);
        return $serverPort > 0 ? $serverPort : 8000;
    }

    /**
     * Mendapatkan skema HTTP / HTTPS.
     */
    public function getScheme(?Request $request = null): string
    {
        if ($request) {
            return $request->getScheme();
        }
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    /**
     * Mendapatkan payload informasi lengkap LAN Share.
     */
    public function getLanInfo(?Request $request = null): array
    {
        $lanIps = $this->getLanIps($request);
        $primaryIp = $lanIps[0] ?? null;
        $port = $this->getPort($request);
        $scheme = $this->getScheme($request);

        $isAvailable = !empty($primaryIp);
        $portSuffix = ($port === 80 && $scheme === 'http') || ($port === 443 && $scheme === 'https') ? '' : ":{$port}";
        $shareUrl = $isAvailable ? "{$scheme}://{$primaryIp}{$portSuffix}" : null;

        return [
            'available' => $isAvailable,
            'ip' => $primaryIp,
            'port' => $port,
            'scheme' => $scheme,
            'share_url' => $shareUrl,
            'all_ips' => $lanIps,
            'message' => $isAvailable
                ? 'LAN Share URL siap digunakan.'
                : 'LAN address tidak dapat dideteksi. Pastikan server terhubung ke jaringan lokal.',
        ];
    }
}
