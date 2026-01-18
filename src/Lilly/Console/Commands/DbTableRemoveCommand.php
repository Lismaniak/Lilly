<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DbTableRemoveCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('db:table:remove');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scaffold a drop-table migration for a domain-owned table')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('table', InputArgument::REQUIRED, 'Table name, e.g. user_emails')
            ->addOption('owned', null, InputOption::VALUE_NONE, 'Use Domains/<Domain>/Migrations/owned/<table>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));
        $table = $this->normalizeTableName((string) $input->getArgument('table'));
        $owned = (bool) $input->getOption('owned');

        if ($domain === '' || $table === '') {
            $output->writeln('<error>Usage: db:table:remove <Domain> <table> [--owned]</error>');
            $output->writeln('<comment>Example: db:table:remove Users user_emails --owned</comment>');
            return Command::FAILURE;
        }

        $domainRoot = "{$this->projectRoot}/src/Domains/{$domain}";
        if (!is_dir($domainRoot)) {
            $output->writeln("<error>Domain does not exist:</error> {$domain} (expected src/Domains/{$domain})");
            return Command::FAILURE;
        }

        $baseMigrationsDir = "{$domainRoot}/Migrations";
        $tableDir = $owned
            ? "{$baseMigrationsDir}/owned/{$table}"
            : "{$baseMigrationsDir}/{$table}";

        if (!is_dir($tableDir)) {
            mkdir($tableDir, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($tableDir));
        }

        $create = glob($tableDir . '/*_create_' . $table . '.php');
        if ($create === false || count($create) === 0) {
            $output->writeln("<error>No create-table migration found for table '{$table}'.</error>");
            $output->writeln("<comment>Run: db:table:make {$domain} {$table}" . ($owned ? ' --owned' : '') . "</comment>");
            return Command::FAILURE;
        }

        $stamp = gmdate('Y_m_d_His');
        $file = "{$stamp}_remove_{$table}.php";
        $path = "{$tableDir}/{$file}";

        if (is_file($path)) {
            $output->writeln("<error>Migration already exists:</error> {$this->rel($path)}");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($table));
        $output->writeln(' + file ' . $this->rel($path));
        $output->writeln("<info>Created migration:</info> {$domain}/" . ($owned ? "owned/{$table}" : $table) . "/{$file}");

        return Command::SUCCESS;
    }

    private function stub(string $table): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Database\\Schema\\Schema;\n\nreturn function (PDO \$pdo): void {\n    \$schema = new Schema(\$pdo);\n\n    \$schema->dropIfExists('{$table}');\n};\n";
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function normalizeTableName(string $raw): string
    {
        $raw = trim($raw);
        $raw = strtolower($raw);

        $raw = preg_replace('/[^a-z0-9]+/', '_', $raw) ?? '';
        $raw = preg_replace('/_+/', '_', $raw) ?? '';
        $raw = trim($raw, '_');

        return $raw;
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
