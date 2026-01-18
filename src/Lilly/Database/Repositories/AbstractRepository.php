<?php
declare(strict_types=1);

namespace Lilly\Database\Repositories;

use PDO;

abstract class AbstractRepository
{
    public function __construct(
        protected readonly PDO $pdo
    ) {}

    abstract protected function table(): string;

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function qi(string $name): string
    {
        // Identifier quoting.
        // Acceptable for sqlite and mysql (non-ANSI mode).
        return '"' . str_replace('"', '""', $name) . '"';
    }

    protected function findRowById(int|string $id): ?array
    {
        $sql =
            'SELECT * FROM ' . $this->qi($this->table()) .
            ' WHERE ' . $this->qi($this->primaryKey()) . ' = :id' .
            ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    protected function rowExistsById(int|string $id): bool
    {
        $sql =
            'SELECT 1 FROM ' . $this->qi($this->table()) .
            ' WHERE ' . $this->qi($this->primaryKey()) . ' = :id' .
            ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    protected function deleteRowById(int|string $id): void
    {
        $sql =
            'DELETE FROM ' . $this->qi($this->table()) .
            ' WHERE ' . $this->qi($this->primaryKey()) . ' = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    }
}
