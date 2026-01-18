<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Lilly\Config\Config;
use Lilly\Database\ConnectionFactory;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class DbFlushCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly Config $config,
    ) {
        parent::__construct('db:flush');
    }

    protected function configure(): void
    {
        $this->setDescription('DANGER: Drop all tables in the configured database (fresh start)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $question = new ConfirmationQuestion(
            "This will permanently DELETE ALL TABLES in your configured database. Continue? (y/N) ",
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Aborted.</comment>');
            return Command::SUCCESS;
        }

        $pdo = (new ConnectionFactory(config: $this->config, projectRoot: $this->projectRoot))->pdo();
        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        try {
            match ($driver) {
                'sqlite' => $this->flushSqlite($pdo, $output),
                'mysql' => $this->flushMysql($pdo, $output),
                default => throw new RuntimeException("Unsupported driver '{$driver}'"),
            };
        } catch (\Throwable $e) {
            $output->writeln('<error>Flush failed:</error> ' . $e->getMessage());
            return Command::FAILURE;
        }

        $output->writeln('<info>Database flushed.</info>');
        return Command::SUCCESS;
    }

    private function flushSqlite(PDO $pdo, OutputInterface $output): void
    {
        $pdo->exec('PRAGMA foreign_keys = OFF;');

        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        $rows = $stmt ? $stmt->fetchAll() : [];

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['name']) || !is_string($row['name'])) {
                continue;
            }

            $table = $row['name'];
            $pdo->exec('DROP TABLE IF EXISTS ' . $this->qiSqlite($table));
            $output->writeln(' - drop ' . $table);
        }

        $pdo->exec('PRAGMA foreign_keys = ON;');
    }

    private function flushMysql(PDO $pdo, OutputInterface $output): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');

        $stmt = $pdo->query("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE()");
        $rows = $stmt ? $stmt->fetchAll() : [];

        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['name']) || !is_string($row['name'])) {
                continue;
            }

            $table = $row['name'];
            $pdo->exec('DROP TABLE IF EXISTS ' . $this->qiMysql($table));
            $output->writeln(' - drop ' . $table);
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function qiSqlite(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    private function qiMysql(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
