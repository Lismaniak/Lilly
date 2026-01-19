<?php
declare(strict_types=1);

namespace Domains\Users\Schema;

/**
 * Domain schema definition.
 *
 * Used by CLI tooling (migrations scaffolding, introspection).
 */
final class UsersSchema
{
    public static function table(): string
    {
        return 'users';
    }

    public static function primaryKey(): string
    {
        return 'id';
    }

    public static function idType(): string
    {
        return 'int';
    }

    public static function ownedTables(): array
    {
        return [
            'users_emails',
            'users_names',
        ];
    }

    public static function foreignKeys(): array
    {
        return [
            [
                'table' => 'users_emails',
                'column' => 'user_id',
                'references' => ['table' => 'users', 'column' => 'id'],
                'onDelete' => 'cascade',
            ],
            [
                'table' => 'users_names',
                'column' => 'user_id',
                'references' => ['table' => 'users', 'column' => 'id'],
                'onDelete' => 'cascade',
            ]
        ];
    }
}
