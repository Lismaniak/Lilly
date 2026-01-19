<?php
declare(strict_types=1);

namespace Domains\Users\Entities;

use Lilly\Database\Orm\Attributes\Table;
use Lilly\Database\Orm\Attributes\Column;

#[Table('users')]
final class Users
{
    #[Column('id', primary: true, autoIncrement: true)]
    public ?int $id = null;
}
