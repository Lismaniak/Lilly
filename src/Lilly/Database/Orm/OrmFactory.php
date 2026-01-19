<?php
declare(strict_types=1);

namespace Lilly\Database\Orm;

use Lilly\Config\Config;
use Lilly\Database\ConnectionFactory;
use Lilly\Database\Orm\Compiler\MysqlCompiler;
use Lilly\Database\Orm\Compiler\SqliteCompiler;
use RuntimeException;

final class OrmFactory
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly Config $config,
    ) {}

    public function create(): Orm
    {
        $pdo = (new ConnectionFactory(
            config: $this->config,
            projectRoot: $this->projectRoot,
        ))->pdo();

        $driver = strtolower((string) $this->config->dbConnection);

        $compiler = match ($driver) {
            'sqlite' => new SqliteCompiler(),
            'mysql'  => new MysqlCompiler(),
            default  => throw new RuntimeException("Unsupported DB_CONNECTION '{$this->config->dbConnection}'"),
        };

        return new Orm($pdo, $compiler);
    }
}
