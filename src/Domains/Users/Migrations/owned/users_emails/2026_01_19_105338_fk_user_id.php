<?php
declare(strict_types=1);

use Lilly\Database\Schema\Blueprint;
use Lilly\Database\Schema\Schema;

return function (PDO $pdo): void {
    $schema = new Schema($pdo);

    $schema->table('users_emails', function (Blueprint $t): void {
        $t->unsignedBigInteger('user_id');

        $fk = $t->foreign('user_id')->references('users', 'id');
        $fk->onDelete('cascade');
    });
};
