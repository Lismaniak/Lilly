<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

final class SchemaSyncDiscovery
{
    public function __construct(
        private readonly string $projectRoot
    ) {}

    /**
     * @return list<string>
     */
    public function discoverDomains(): array
    {
        $root = "{$this->projectRoot}/src/Domains";
        if (!is_dir($root)) {
            return [];
        }

        $items = scandir($root);
        if ($items === false) {
            return [];
        }

        $out = [];
        foreach ($items as $d) {
            if ($d === '.' || $d === '..') {
                continue;
            }
            if (str_starts_with($d, '.')) {
                continue;
            }
            if (is_dir("{$root}/{$d}")) {
                $out[] = $d;
            }
        }

        sort($out);
        return $out;
    }

    /**
     * @return list<array{class: string, table: string}>
     */
    public function discoverTableBlueprints(string $domain): array
    {
        $ns = "Domains\\{$domain}\\Database\\Tables\\";
        $dir = "{$this->projectRoot}/src/Domains/{$domain}/Database/Tables";

        $files = glob($dir . '/*Table.php') ?: [];
        sort($files);

        $out = [];

        foreach ($files as $path) {
            $base = basename($path, '.php');
            $fqcn = $ns . $base;

            if (!class_exists($fqcn)) {
                continue;
            }
            if (!method_exists($fqcn, 'name') || !method_exists($fqcn, 'define')) {
                continue;
            }

            $table = trim((string) $fqcn::name());
            if ($table === '') {
                continue;
            }

            $out[] = ['class' => $fqcn, 'table' => $table];
        }

        return $out;
    }
}
