<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DbTableUpdateCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('db:table:update');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scaffold an alter-table migration (add columns only, MVP). Domain table by default, owned when [table] is provided.')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('table', InputArgument::OPTIONAL, 'Owned table name, e.g. user_emails (optional)')
            ->addOption('desc', null, InputOption::VALUE_REQUIRED, 'Filename description, e.g. add_verified_at');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));

        $tableArg = $input->getArgument('table');
        $tableRaw = is_string($tableArg) ? trim($tableArg) : '';

        $descOpt = $input->getOption('desc');
        $descRaw = is_string($descOpt) ? trim($descOpt) : '';

        if ($domain === '') {
            $output->writeln('<error>Usage: db:table:update <Domain> [owned_table]</error>');
            $output->writeln('<comment>Examples:</comment>');
            $output->writeln('<comment>  db:table:update Users</comment>');
            $output->writeln('<comment>  db:table:update Users --desc add_email</comment>');
            $output->writeln('<comment>  db:table:update Users user_emails</comment>');
            $output->writeln('<comment>  db:table:update Users user_emails --desc add_verified_at</comment>');
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

        // Domain table update (default)
        if ($tableRaw === '') {
            $table = $this->defaultDomainTableName($domain);
            $desc = $this->normalizeDesc($descRaw);

            $tableDir = $baseMigrationsDir;

            if (!$this->hasCreateMigration($tableDir)) {
                $output->writeln("<error>No create-table migration found for domain table '{$table}'.</error>");
                $output->writeln("<comment>Run: db:table:make {$domain}</comment>");
                return Command::FAILURE;
            }

            return $this->writeUpdateMigration($output, $tableDir, $domain, 'Migrations', $table, $desc);
        }

        // If [table] is provided, we treat it as owned table name.
        // SAFETY RULE: we do NOT create the folder. Owned table must already exist and have a create migration.
        $table = $this->normalizeTableName($tableRaw);
        if ($table === '') {
            $output->writeln('<error>Invalid table name.</error>');
            return Command::FAILURE;
        }

        $desc = $this->normalizeDesc($descRaw);

        $ownedDir = "{$baseMigrationsDir}/owned/{$table}";

        if (!is_dir($ownedDir)) {
            $output->writeln("<error>Owned table folder does not exist:</error> {$this->rel($ownedDir)}");
            $output->writeln("<comment>Create it first with: db:table:make {$domain} {$table}</comment>");
            return Command::FAILURE;
        }

        if (!$this->hasCreateMigration($ownedDir)) {
            $output->writeln("<error>No create-table migration found for owned table '{$table}'.</error>");
            $output->writeln("<comment>Run: db:table:make {$domain} {$table}</comment>");
            return Command::FAILURE;
        }

        return $this->writeUpdateMigration($output, $ownedDir, $domain, "owned/{$table}", $table, $desc);
    }

    private function writeUpdateMigration(
        OutputInterface $output,
        string $dir,
        string $domain,
        string $label,
        string $table,
        string $desc
    ): int {
        $stamp = gmdate('Y_m_d_His');
        $file = $desc !== '' ? "{$stamp}_update_{$desc}.php" : "{$stamp}_update.php";
        $path = "{$dir}/{$file}";

        if (is_file($path)) {
            $output->writeln("<error>Migration already exists:</error> {$this->rel($path)}");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($table));
        $output->writeln(' + file ' . $this->rel($path));
        $output->writeln("<info>Created migration:</info> {$domain}/{$label}/{$file}");

        return Command::SUCCESS;
    }

    private function hasCreateMigration(string $dir): bool
    {
        $create = glob($dir . '/*_create.php');
        return $create !== false && count($create) > 0;
    }

    private function stub(string $table): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Database\\Schema\\Schema;\nuse Lilly\\Database\\Schema\\Blueprint;\n\nreturn function (PDO \$pdo): void {\n    \$schema = new Schema(\$pdo);\n\n    \$schema->table('{$table}', function (Blueprint \$t): void {\n        // \$t->string('example');\n    });\n};\n";
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function defaultDomainTableName(string $domain): string
    {
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

    private function normalizeDesc(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

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
