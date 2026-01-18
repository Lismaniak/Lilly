<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            ->setDescription('Scaffold a create-table migration for a domain')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));

        if ($domain === '') {
            $output->writeln('<error>Usage: db:table:make <Domain></error>');
            return Command::FAILURE;
        }

        $domainRoot = "{$this->projectRoot}/src/Domains/{$domain}";
        if (!is_dir($domainRoot)) {
            $output->writeln("<error>Domain does not exist:</error> {$domain} (expected src/Domains/{$domain})");
            return Command::FAILURE;
        }

        $migrationsDir = "{$domainRoot}/Migrations";
        $existing = glob($migrationsDir . '/*_create_' . strtolower($domain) . '.php');
        if ($existing !== false && count($existing) > 0) {
            $output->writeln(
                "<error>Create-table migration already exists for domain {$domain}.</error>"
            );
            $output->writeln(
                "<comment>Use db:table:update {$domain} instead.</comment>"
            );
            return Command::FAILURE;
        }

        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($migrationsDir));
        }

        $table = strtolower($domain);
        $stamp = gmdate('Y_m_d_His');
        $file = "{$stamp}_create_{$table}.php";

        $path = "{$migrationsDir}/{$file}";
        if (is_file($path)) {
            $output->writeln("<error>Migration already exists:</error> {$this->rel($path)}");
            return Command::FAILURE;
        }

        file_put_contents($path, $this->stub($table));
        $output->writeln(' + file ' . $this->rel($path));
        $output->writeln("<info>Created migration:</info> {$domain}/{$file}");

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

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
