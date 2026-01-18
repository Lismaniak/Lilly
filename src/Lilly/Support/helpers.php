<?php
declare(strict_types=1);

if (!function_exists('dump')) {
    function dump(mixed ...$values): void
    {
        echo _lilly_dump_render($values, exitAfter: false);
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        echo _lilly_dump_render($values, exitAfter: true);
        exit(1);
    }
}

function _lilly_dump_render(array $values, bool $exitAfter): string
{
    $isCli = PHP_SAPI === 'cli';
    $out = [];

    foreach ($values as $i => $value) {
        ob_start();
        var_dump($value);
        $dump = trim((string) ob_get_clean());

        $out[] = $dump;
    }

    $body = implode("\n\n", $out);

    if ($isCli) {
        return _lilly_dump_cli($body, $exitAfter);
    }

    return _lilly_dump_http($body, $exitAfter);
}

function _lilly_dump_cli(string $body, bool $exitAfter): string
{
    $yellow = "\033[33m";
    $cyan   = "\033[36m";
    $reset  = "\033[0m";
    $bold   = "\033[1m";

    $header = "{$bold}{$cyan}Lilly dump" . ($exitAfter ? ' (dd)' : '') . "{$reset}";
    $line   = str_repeat('─', 80);

    return <<<CLI

{$header}
{$yellow}{$line}{$reset}
{$body}
{$yellow}{$line}{$reset}

CLI;
}

function _lilly_dump_http(string $body, bool $exitAfter): string
{
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');

    $title = $exitAfter ? 'Lilly dd()' : 'Lilly dump()';

    $escaped = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{$title}</title>
<style>
    body {
        background: #0f172a;
        color: #e5e7eb;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        padding: 24px;
    }
    h1 {
        font-size: 14px;
        margin-bottom: 12px;
        color: #38bdf8;
    }
    pre {
        background: #020617;
        padding: 16px;
        border-radius: 8px;
        overflow-x: auto;
        line-height: 1.4;
        font-size: 13px;
        box-shadow: inset 0 0 0 1px #1e293b;
    }
</style>
</head>
<body>
<h1>{$title}</h1>
<pre>{$escaped}</pre>
</body>
</html>
HTML;
}
