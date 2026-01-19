<?php
declare(strict_types=1);

namespace Domains\Users\Database\Tables;

use Lilly\Database\Schema\Blueprint;

final class UsersTable
{
    public static function name(): string
    {
        return 'users';
    }

    public static function define(Blueprint $t): void
    {
        $t->id();
        $t->timestamps();
    }
}
