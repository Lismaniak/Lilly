<?php
declare(strict_types=1);

namespace Domains\Users\Database\Tables;

use Lilly\Database\Schema\Blueprint;

final class UsersEmailTable
{
    public static function name(): string
    {
        return 'users_email';
    }

    public static function define(Blueprint $t): void
    {
        $t->id(); // BIGINT
        $t->unsignedBigInteger('user_id'); // MUST match users.id
        $t->string('email')->unique();
        $t->timestamps();
    }

    /**
     * Optional FK definitions for tooling.
     * Return format matches your Schema foreign key compiler expectations.
     */
    public static function foreignKeys(): array
    {
        return [
            [
                'column' => 'user_id',
                'references' => [
                    'table' => 'users',
                    'column' => 'id',
                ],
                'onDelete' => 'cascade',
                'onUpdate' => 'cascade',
            ],
        ];
    }
}
