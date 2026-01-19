<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Metadata;

use Lilly\Database\Orm\Attributes\Column;
use Lilly\Database\Orm\Attributes\Table;
use ReflectionClass;
use RuntimeException;

final class MetadataFactory
{
    /** @var array<class-string, EntityMetadata> */
    private array $cache = [];

    /**
     * @param class-string $entityClass
     */
    public function for(string $entityClass): EntityMetadata
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        $rc = new ReflectionClass($entityClass);

        $tableAttrs = $rc->getAttributes(Table::class);
        if ($tableAttrs === []) {
            throw new RuntimeException("Entity {$entityClass} missing #[Table('...')]");
        }

        /** @var Table $table */
        $table = $tableAttrs[0]->newInstance();

        $propertyToColumn = [];
        $columnToProperty = [];

        $primaryProperty = null;
        $primaryColumn = null;
        $primaryAuto = false;

        foreach ($rc->getProperties() as $rp) {
            $attrs = $rp->getAttributes(Column::class);
            if ($attrs === []) {
                continue;
            }

            /** @var Column $col */
            $col = $attrs[0]->newInstance();

            $propertyToColumn[$rp->getName()] = $col->name;
            $columnToProperty[$col->name] = $rp->getName();

            if ($col->primary) {
                if ($primaryProperty !== null) {
                    throw new RuntimeException("Entity {$entityClass} has multiple primary columns");
                }
                $primaryProperty = $rp->getName();
                $primaryColumn = $col->name;
                $primaryAuto = $col->autoIncrement;
            }
        }

        if ($primaryProperty === null || $primaryColumn === null) {
            throw new RuntimeException("Entity {$entityClass} missing a primary #[Column(..., primary: true)]");
        }

        return $this->cache[$entityClass] = new EntityMetadata(
            class: $entityClass,
            table: $table->name,
            primaryProperty: $primaryProperty,
            primaryColumn: $primaryColumn,
            primaryAutoIncrement: $primaryAuto,
            propertyToColumn: $propertyToColumn,
            columnToProperty: $columnToProperty,
        );
    }
}
