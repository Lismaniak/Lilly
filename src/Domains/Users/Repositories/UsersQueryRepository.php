<?php
declare(strict_types=1);

namespace Domains\Users\Repositories;

use Domains\Users\Entities\Users;
use Lilly\Database\Orm\Orm;
use Lilly\Database\Orm\Repository\QueryRepository;

final class UsersQueryRepository extends QueryRepository
{
    public function __construct(Orm $orm)
    {
        parent::__construct($orm, Users::class);
    }
}
