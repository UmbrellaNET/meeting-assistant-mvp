<?php

namespace App\Support;

use function donatj\UserAgent\parse_user_agent;

class UserAgentInspector
{
    /**
     * @return array{browser: ?string, browser_version: ?string, os: ?string, device_type: ?string}
     */
    public function parse(?string $userAgent): array
    {
        if (! $userAgent) {
            return [
                'browser' => null,
                'browser_version' => null,
                'os' => null,
                'device_type' => null,
            ];
        }

        try {
            $parsed = parse_user_agent($userAgent);
        } catch (\InvalidArgumentException) {
            $parsed = ['browser' => null, 'version' => null, 'platform' => null];
        }

        return [
            'browser' => $parsed['browser'] ?? null,
            'browser_version' => $parsed['version'] ?? null,
            'os' => $parsed['platform'] ?? null,
            'device_type' => $this->deviceType($userAgent),
        ];
    }

    public function deviceType(string $userAgent): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/Mobile|Android|iPhone|iPod|Windows Phone|BlackBerry/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
