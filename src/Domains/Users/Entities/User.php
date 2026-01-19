<?php
declare(strict_types=1);

namespace Domains\Users\Entities;

use Domains\Users\Repositories\UsersCommandRepository;
use Domains\Users\Repositories\UsersQueryRepository;
use Domains\Users\Schema\UsersSchema;
use Lilly\Orm\ActiveRecord;

final class User extends ActiveRecord
{
    public function __construct(
        public readonly int $id
    ) {}

    protected static function queryRepositoryFqcn(): string
    {
        return UsersQueryRepository::class;
    }

    protected static function commandRepositoryFqcn(): string
    {
        return UsersCommandRepository::class;
    }

    public static function fromRow(array $row): static
    {
        return new self(
            id: (int) $row[UsersSchema::primaryKey()]
        );
    }
}
