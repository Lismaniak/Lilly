<?php
declare(strict_types=1);

namespace Domains\Users\Repositories;

use Domains\Users\Schema\UsersSchema;
use Lilly\Database\Repositories\AbstractRepository;

final class UsersQueryRepository extends AbstractRepository
{
    protected function table(): string
    {
        return UsersSchema::table();
    }

    protected function primaryKey(): string
    {
        return UsersSchema::primaryKey();
    }

    // <methods>

    // </methods>
}
