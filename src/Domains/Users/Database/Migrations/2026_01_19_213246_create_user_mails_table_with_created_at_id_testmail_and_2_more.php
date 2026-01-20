<?php
declare(strict_types=1);

namespace Domains\Users\Database\Migrations;

use PDO;
use Lilly\Database\Schema\Blueprint;
use Lilly\Database\Schema\Schema;

return function (PDO $pdo): void {
    $schema = new Schema($pdo);

    $schema->create('user_mails', function (Blueprint $t): void {
        $t->timestamp('created_at');
        $t->id('id');
        $t->string('testmail', 255);
        $t->timestamp('updated_at')->nullable();
        $t->unsignedBigInteger('user_id');

        $t->foreignKey('user_id', 'id', 'users', 'cascade');
    });
};
