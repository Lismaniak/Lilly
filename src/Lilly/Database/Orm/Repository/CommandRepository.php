<?php
declare(strict_types=1);

namespace Lilly\Database\Orm\Repository;

use RuntimeException;

abstract class CommandRepository extends RepositoryBase
{
    public function save(object $entity): void
    {
        $m = $this->orm->meta->for($this->entityClass);
        $pkProp = $m->primaryProperty;

        $row = $this->persister->toRow($entity);

        $pkVal = $entity->{$pkProp};
        $isInsert = $pkVal === null || $pkVal === 0 || $pkVal === '';

        if ($isInsert) {
            if ($m->primaryAutoIncrement) {
                unset($row[$m->primaryColumn]);
            }

            $newId = $this->orm->execInsert(
                $this->orm->qb->insert($m->table, $row)
            );

            if ($m->primaryAutoIncrement) {
                $entity->{$pkProp} = $newId;
            }

            return;
        }

        unset($row[$m->primaryColumn]);

        $q = $this->orm->qb
            ->update($m->table, $row)
            ->where($m->primaryColumn, '=', $pkVal)
            ->build();

        $this->orm->execUpdate($q);
    }

    public function delete(object $entity): void
    {
        $m = $this->orm->meta->for($this->entityClass);
        $pkProp = $m->primaryProperty;

        $pkVal = $entity->{$pkProp};

        if ($pkVal === null || $pkVal === 0 || $pkVal === '') {
            throw new RuntimeException('Cannot delete entity without primary key value');
        }

        $q = $this->orm->qb
            ->delete($m->table)
            ->where($m->primaryColumn, '=', $pkVal)
            ->build();

        $this->orm->execDelete($q);
    }
}
