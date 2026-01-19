<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

use RuntimeException;

final class SchemaSyncPlanner
{
    public function defsEqual(array $a, array $b): bool
    {
        $ja = json_encode($a, JSON_UNESCAPED_SLASHES);
        $jb = json_encode($b, JSON_UNESCAPED_SLASHES);

        if ($ja === false || $jb === false) {
            return false;
        }

        return hash('sha256', $ja) === hash('sha256', $jb);
    }

    /**
     * @return array{renames:list<array{from:string,to:string}>, adds:list<array<string,mixed>>}
     */
    public function buildUpdateOps(array $approvedDef, array $desiredDef): array
    {
        $approvedCols = $approvedDef['columns'] ?? [];
        $desiredCols = $desiredDef['columns'] ?? [];

        if (!is_array($approvedCols) || !is_array($desiredCols)) {
            return ['renames' => [], 'adds' => []];
        }

        $approvedSet = [];
        foreach ($approvedCols as $c) {
            if (!is_array($c) || !isset($c['name'])) {
                continue;
            }
            $n = trim((string) $c['name']);
            if ($n !== '') {
                $approvedSet[$n] = true;
            }
        }

        $desiredByName = [];
        foreach ($desiredCols as $c) {
            if (!is_array($c) || !isset($c['name'])) {
                continue;
            }
            $n = trim((string) $c['name']);
            if ($n !== '') {
                $desiredByName[$n] = $c;
            }
        }

        $renames = [];
        $was = $desiredDef['was'] ?? [];
        if (!is_array($was)) {
            $was = [];
        }

        foreach ($was as $to => $fromList) {
            if (!is_string($to)) {
                continue;
            }
            $to = trim($to);
            if ($to === '') {
                continue;
            }

            if (isset($approvedSet[$to])) {
                continue;
            }

            if (!is_array($fromList)) {
                continue;
            }

            $matches = [];
            foreach ($fromList as $from) {
                if (!is_string($from)) {
                    continue;
                }
                $from = trim($from);
                if ($from === '') {
                    continue;
                }
                if (isset($approvedSet[$from])) {
                    $matches[] = $from;
                }
            }

            $matches = array_values(array_unique($matches));

            if (count($matches) > 1) {
                throw new RuntimeException(
                    "Ambiguous was() for '{$to}': multiple legacy columns exist: " . implode(', ', $matches)
                );
            }

            if (count($matches) === 1) {
                $from = $matches[0];

                if (!isset($desiredByName[$to])) {
                    throw new RuntimeException("was() target '{$to}' must exist as a desired column");
                }

                $renames[] = ['from' => $from, 'to' => $to];

                unset($approvedSet[$from]);
                $approvedSet[$to] = true;
            }
        }

        $adds = [];
        foreach ($desiredByName as $name => $col) {
            if (!isset($approvedSet[$name])) {
                $adds[] = $col;
            }
        }

        usort(
            $adds,
            fn (array $a, array $b): int => ((string) ($a['name'] ?? '')) <=> ((string) ($b['name'] ?? ''))
        );

        return [
            'renames' => $renames,
            'adds' => $adds,
        ];
    }
}
