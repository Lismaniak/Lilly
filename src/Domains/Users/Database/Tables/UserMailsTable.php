<?php
declare(strict_types=1);

namespace Domains\Users\Database\Tables;

use Lilly\Database\Schema\Blueprint;

final class UserMailsTable
{
    public static function name(): string
    {
        return 'user_mails';
    }

    public static function define(Blueprint $t): void
    {
        $t->id();
        $t->string('testmail');
        $t->timestamps();
    }

    /**
     * Optional FK definitions for tooling.
     * Return format matches your Schema foreign key compiler expectations.
     */
    public static function foreignKeys(): array
    {
        return [];
    }
}
