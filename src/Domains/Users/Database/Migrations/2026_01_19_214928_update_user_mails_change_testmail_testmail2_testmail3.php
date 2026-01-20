<?php
declare(strict_types=1);

namespace Domains\Users\Database\Migrations;

use PDO;
use Lilly\Database\Schema\Blueprint;
use Lilly\Database\Schema\Schema;

return function (PDO $pdo): void {
    $schema = new Schema($pdo);

    $schema->table('user_mails', function (Blueprint $t): void {
        $t->change('testmail')->type('string:255')->default('Yeah!!');
        $t->change('testmail2')->unique(true);
        $t->change('testmail3')->type('string:255')->nullable(true);
    });
};
