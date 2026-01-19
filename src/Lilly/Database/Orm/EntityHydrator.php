<?php
declare(strict_types=1);

namespace Lilly\Database\Orm;

use Lilly\Database\Orm\Metadata\EntityMetadata;
use Lilly\Database\Orm\Metadata\MetadataFactory;

final class EntityHydrator
{
    public function __construct(
        private readonly MetadataFactory $meta
    ) {}

    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $row
     */
    public function one(string $entityClass, array $row): object
    {
        $m = $this->meta->for($entityClass);
        $e = new $entityClass();

        foreach ($row as $col => $val) {
            $prop = $m->columnToProperty[$col] ?? null;
            if ($prop === null) {
                continue;
            }
            $e->{$prop} = $val;
        }

        return $e;
    }

    /**
     * @param class-string $entityClass
     * @param list<array<string, mixed>> $rows
     * @return list<object>
     */
    public function many(string $entityClass, array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->one($entityClass, $row);
        }
        return $out;
    }
}
