<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Reporting;

final class BrowserOpener
{
    public function open(string $path): bool
    {
        $escaped = escapeshellarg($path);

        if (PHP_OS_FAMILY === 'Darwin') {
            exec('open ' . $escaped . ' >/dev/null 2>&1', $out, $code);
            return $code === 0;
        }

        if (PHP_OS_FAMILY === 'Linux') {
            exec('xdg-open ' . $escaped . ' >/dev/null 2>&1', $out, $code);
            return $code === 0;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            exec('start "" ' . $escaped, $out, $code);
            return $code === 0;
        }

        return false;
    }
}
