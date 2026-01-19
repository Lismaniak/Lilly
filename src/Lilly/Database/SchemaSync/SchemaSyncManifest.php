<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

use Lilly\Database\Schema\Blueprint;
use RuntimeException;

final class SchemaSyncManifest
{
    public function __construct(
        private readonly string $projectRoot
    ) {}

    /**
     * @param list<array{class: string, table: string}> $tables
     * @return array<string, mixed>
     */
    public function buildDesiredManifest(string $domain, array $tables): array
    {
        $tablesMap = [];
        foreach ($tables as $t) {
            $table = $t['table'];
            $class = $t['class'];

            $tablesMap[$table] = [
                'class' => $class,
                'def' => $this->tableDefinition($table, $class),
            ];
        }
        ksort($tablesMap);

        $normalized = [
            'domain' => $domain,
            'tables' => $tablesMap,
        ];

        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to json encode manifest');
        }

        $hash = hash('sha256', $json);

        return [
            'domain' => $domain,
            'generated_at' => gmdate('c'),
            'schema_hash' => $hash,
            'tables' => $tablesMap,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function readApprovedManifest(string $domain): array
    {
        $path = "{$this->projectRoot}/src/Domains/{$domain}/Database/schema.manifest.json";
        if (!is_file($path)) {
            return ['tables' => []];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return ['tables' => []];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['tables' => []];
        }

        if (!isset($decoded['tables']) || !is_array($decoded['tables'])) {
            $decoded['tables'] = [];
        }

        return $decoded;
    }

    /**
     * @return array{table:string, columns:list<array<string,mixed>>, was:array<string,list<string>>}
     */
    private function tableDefinition(string $tableName, string $tableClass): array
    {
        $bp = new Blueprint($tableName, 'create');

        try {
            $tableClass::define($bp);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "Failed to build blueprint for {$tableClass}: " . $e->getMessage(),
                previous: $e
            );
        }

        $cols = [];
        foreach ($bp->columns() as $c) {
            $cols[] = [
                'name' => $c->name,
                'type' => $c->type,
                'nullable' => $c->nullable,
                'unique' => $c->unique,
                'primary' => $c->primary,
                'auto_increment' => $c->autoIncrement,
                'default' => $c->default,
            ];
        }

        $cols = $this->normalizeColumnsForManifest($cols);

        return [
            'table' => $tableName,
            'columns' => $cols,
            'was' => $this->normalizeWasMap($bp->was()),
        ];
    }

    /**
     * @param array<string, list<string>> $was
     * @return array<string, list<string>>
     */
    private function normalizeWasMap(array $was): array
    {
        $out = [];
        foreach ($was as $to => $fromList) {
            if (!is_string($to)) {
                continue;
            }
            $to = trim($to);
            if ($to === '') {
                continue;
            }

            $clean = [];
            foreach ($fromList as $from) {
                if (!is_string($from)) {
                    continue;
                }
                $from = trim($from);
                if ($from === '' || $from === $to) {
                    continue;
                }
                $clean[] = $from;
            }

            $clean = array_values(array_unique($clean));
            if ($clean === []) {
                continue;
            }

            $out[$to] = $clean;
        }

        ksort($out);
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $cols
     * @return list<array<string,mixed>>
     */
    private function normalizeColumnsForManifest(array $cols): array
    {
        usort($cols, function (array $a, array $b): int {
            $an = isset($a['name']) ? (string) $a['name'] : '';
            $bn = isset($b['name']) ? (string) $b['name'] : '';
            return $an <=> $bn;
        });

        $out = [];
        foreach ($cols as $c) {
            $name = isset($c['name']) ? trim((string) $c['name']) : '';
            if ($name === '') {
                continue;
            }

            $out[] = [
                'name' => $name,
                'type' => isset($c['type']) ? (string) $c['type'] : '',
                'nullable' => (bool) ($c['nullable'] ?? false),
                'unique' => (bool) ($c['unique'] ?? false),
                'primary' => (bool) ($c['primary'] ?? false),
                'auto_increment' => (bool) ($c['auto_increment'] ?? false),
                'default' => $c['default'] ?? null,
            ];
        }

        return $out;
    }
}
