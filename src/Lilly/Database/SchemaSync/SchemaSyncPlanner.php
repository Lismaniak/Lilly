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
     * @return array{
     *   drops:list<string>,
     *   renames:list<array{from:string,to:string}>,
     *   adds:list<array<string,mixed>>,
     *   foreign_keys_adds:list<array{column:string,references:string,on:string,onDelete:string|null}>
     * }
     */
    public function buildUpdateOps(array $approvedDef, array $desiredDef): array
    {
        $approvedCols = $approvedDef['columns'] ?? [];
        $desiredCols = $desiredDef['columns'] ?? [];

        if (!is_array($approvedCols) || !is_array($desiredCols)) {
            return ['drops' => [], 'renames' => [], 'adds' => [], 'foreign_keys_adds' => []];
        }

        $approvedSet = [];     // name => true
        $approvedNames = [];   // ordered list for stable output
        foreach ($approvedCols as $c) {
            if (!is_array($c) || !isset($c['name'])) {
                continue;
            }
            $n = trim((string) $c['name']);
            if ($n === '') {
                continue;
            }
            if (!isset($approvedSet[$n])) {
                $approvedSet[$n] = true;
                $approvedNames[] = $n;
            }
        }

        $desiredByName = []; // name => colDef
        foreach ($desiredCols as $c) {
            if (!is_array($c) || !isset($c['name'])) {
                continue;
            }
            $n = trim((string) $c['name']);
            if ($n === '') {
                continue;
            }
            $desiredByName[$n] = $c;
        }

        $desiredSet = [];
        foreach ($desiredByName as $n => $_c) {
            $desiredSet[$n] = true;
        }

        $renames = [];
        $protected = []; // columns participating in rename, never drop in same plan

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
                continue; // target already exists in approved
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

                $protected[$from] = true;
                $protected[$to] = true;

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
            static fn (array $a, array $b): int => ((string) ($a['name'] ?? '')) <=> ((string) ($b['name'] ?? ''))
        );

        $drops = [];
        foreach ($approvedNames as $n) {
            if (isset($desiredSet[$n])) {
                continue; // still desired
            }
            if (isset($protected[$n])) {
                continue; // involved in rename
            }
            $drops[] = $n;
        }

        sort($drops);

        $foreignKeyAdds = $this->planForeignKeyAdds($approvedDef, $desiredDef);

        return [
            'drops' => $drops,
            'renames' => $renames,
            'adds' => $adds,
            'foreign_keys_adds' => $foreignKeyAdds,
        ];
    }

    /**
     * @return list<array{column:string,references:string,on:string,onDelete:string|null}>
     */
    private function planForeignKeyAdds(array $approvedDef, array $desiredDef): array
    {
        $approvedFks = $approvedDef['foreign_keys'] ?? [];
        $desiredFks = $desiredDef['foreign_keys'] ?? [];

        if (!is_array($approvedFks)) {
            $approvedFks = [];
        }
        if (!is_array($desiredFks)) {
            $desiredFks = [];
        }

        $fkKey = static function (array $fk): string {
            $column = trim((string) ($fk['column'] ?? ''));
            $references = trim((string) ($fk['references'] ?? ''));
            $on = trim((string) ($fk['on'] ?? ''));
            $onDelete = array_key_exists('onDelete', $fk)
                ? (is_string($fk['onDelete']) ? trim($fk['onDelete']) : null)
                : null;

            if ($onDelete === '') {
                $onDelete = null;
            }

            return "{$column}|{$references}|{$on}|".($onDelete ?? '');
        };

        $approvedMap = [];
        foreach ($approvedFks as $fk) {
            if (!is_array($fk)) {
                continue;
            }
            $approvedMap[$fkKey($fk)] = $fk;
        }

        $desiredMap = [];
        foreach ($desiredFks as $fk) {
            if (!is_array($fk)) {
                continue;
            }
            $desiredMap[$fkKey($fk)] = $fk;
        }

        $adds = [];
        foreach ($desiredMap as $k => $fk) {
            if (!isset($approvedMap[$k])) {
                $adds[] = $fk;
            }
        }

        $removals = [];
        foreach ($approvedMap as $k => $_fk) {
            if (!isset($desiredMap[$k])) {
                $removals[] = $k;
            }
        }

        if ($removals !== []) {
            throw new RuntimeException(
                "Foreign key removals/changes are not supported by schema sync yet. " .
                "Create a manual migration that drops/recreates constraints. " .
                "Missing keys: " . implode(', ', $removals)
            );
        }

        usort(
            $adds,
            static fn (array $a, array $b): int => ((string) ($a['column'] ?? '') . '|' . (string) ($a['on'] ?? ''))
                <=> ((string) ($b['column'] ?? '') . '|' . (string) ($b['on'] ?? ''))
        );

        return $adds;
    }
}
