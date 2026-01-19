<?php
declare(strict_types=1);

namespace Lilly\Database\Orm;

use Lilly\Database\Orm\Metadata\MetadataFactory;

final class EntityPersister
{
    public function __construct(
        private readonly MetadataFactory $meta
    ) {}

    /**
     * @param object $entity
     * @return array<string, mixed> column => value
     */
    public function toRow(object $entity): array
    {
        $m = $this->meta->for($entity::class);

        $out = [];
        foreach ($m->propertyToColumn as $prop => $col) {
            $out[$col] = $entity->{$prop};
        }

        return $out;
    }
}
