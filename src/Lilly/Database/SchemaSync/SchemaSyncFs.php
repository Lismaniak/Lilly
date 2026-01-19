<?php
declare(strict_types=1);

namespace Lilly\Database\SchemaSync;

final class SchemaSyncFs
{
    public function __construct(
        private readonly string $projectRoot
    ) {}

    public function mkdir(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    public function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            if (is_dir($full)) {
                $this->deleteDirectory($full);
                continue;
            }
            unlink($full);
        }

        rmdir($path);
    }

    public function rel(string $absPath): string
    {
        return ltrim(str_replace($this->projectRoot, '', $absPath), '/');
    }

    /**
     * @return list<string>
     */
    public function listDirs(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $items = scandir($root);
        if ($items === false) {
            return [];
        }

        $out = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if (str_starts_with($item, '.')) {
                continue;
            }
            $full = $root . '/' . $item;
            if (is_dir($full)) {
                $out[] = $full;
            }
        }

        sort($out);
        return $out;
    }
}
