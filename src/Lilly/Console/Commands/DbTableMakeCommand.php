<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DbTableMakeCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('db:table:make');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scaffold a create-table migration for a domain table or an owned table')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('table', InputArgument::OPTIONAL, 'Optional. If omitted, defaults to the domain table (users)')
            ->addOption('owned', null, InputOption::VALUE_NONE, 'Create under Domains/<Domain>/Migrations/owned/<table>');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));
        $owned = (bool) $input->getOption('owned');

        $tableArg = $input->getArgument('table');
        $tableRaw = is_string($tableArg) ? $tableArg : '';

        $table = $tableRaw !== ''
            ? $this->normalizeTableName($tableRaw)
            : $this->defaultDomainTableName($domain);

        if ($domain === '' || $table === '') {
            $output->writeln('<error>Usage: db:table:make <Domain> [table] [--owned]</error>');
            $output->writeln('<comment>Examples:</comment>');
            $output->writeln('<comment>  db:table:make Users</comment>');
            $output->writeln('<comment>  db:table:make Users accounts</comment>');
            $output->writeln('<comment>  db:table:make Users user_emails --owned</comment>');
            return Command::FAILURE;
        }

        if ($owned && $tableRaw === '') {
            $output->writeln('<error>When using --owned you must provide a table name.</error>');
            $output->writeln('<comment>Example: db:table:make Users user_emails --owned</comment>');
            return Command::FAILURE;
        }

        $domainRoot = "{$this->projectRoot}/src/Domains/{$domain}";
        if (!is_dir($domainRoot)) {
            $output->writeln("<error>Domain does not exist:</error> {$domain} (expected src/Domains/{$domain})");
            return Command::FAILURE;
        }

        $baseMigrationsDir = "{$domainRoot}/Migrations";

        if (!is_dir($baseMigrationsDir)) {
            mkdir($baseMigrationsDir, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($baseMigrationsDir));
        }

        $tableDir = $owned
            ? "{$baseMigrationsDir}/owned/{$table}"
            : $baseMigrationsDir; // domain table migrations live directly here

        if (!is_dir($tableDir)) {
            mkdir($tableDir, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($tableDir));
        }

        // Folder already tells the table name, so filenames do not repeat it.
        $existingCreate = $owned
            ? glob($tableDir . '/*_create.php')
            : glob($tableDir . '/*_create.php');

        if ($existingCreate !== false && count($existingCreate) > 0) {
            $output->writeln("<error>Create-table migration already exists for table '{$table}'.</error>");
            $output->writeln("<comment>Use: db:table:update {$domain}" . ($tableRaw !== '' ? " {$table}" : '') . ($owned ? ' --owned' : '') . "</comment>");
            return Command::FAILURE;
        }

        $stamp = gmdate('Y_m_d_His');
        $file = "{$stamp}_create.php";
        $path = "{$tableDir}/{$file}";

        if (is_file($path)) {
            $output->writeln("<error>Migration already exists:</error> {$this->rel($path)}");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($table));
        $output->writeln(' + file ' . $this->rel($path));

        $folderLabel = $owned ? "owned/{$table}" : $table;
        $output->writeln("<info>Created migration:</info> {$domain}/{$folderLabel}/{$file}");

        return Command::SUCCESS;
    }

    private function stub(string $table): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Database\\Schema\\Schema;\nuse Lilly\\Database\\Schema\\Blueprint;\n\nreturn function (PDO \$pdo): void {\n    \$schema = new Schema(\$pdo);\n\n    \$schema->create('{$table}', function (Blueprint \$t): void {\n        \$t->id();\n        \$t->timestamps();\n    });\n};\n";
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function defaultDomainTableName(string $domain): string
    {
        // Users -> users, TeamMembers -> team_members (still one domain table)
        return $this->normalizeTableName($domain);
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
