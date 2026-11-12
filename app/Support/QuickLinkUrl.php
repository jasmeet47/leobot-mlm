<?php

namespace App\Support;

final class QuickLinkUrl
{
    public static function isSafe(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && $host !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['fragment'])
            && $host !== 'localhost'
            && ! str_ends_with($host, '.localhost')
            && ! filter_var(trim($host, '[]'), FILTER_VALIDATE_IP);
    }
}
