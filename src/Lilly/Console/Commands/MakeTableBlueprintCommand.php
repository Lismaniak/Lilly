<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeTableBlueprintCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:domain:make:blueprint');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scaffold a domain table blueprint (Domains/<Domain>/Database/Tables/*Table.php)')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users')
            ->addArgument('name', InputArgument::REQUIRED, 'Blueprint name, e.g. Users or UserEmails');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));
        $name = $this->normalizeClassName((string) $input->getArgument('name'));

        if ($domain === '' || $name === '') {
            $output->writeln('<error>Usage: shape:table:make <Domain> <Name></error>');
            $output->writeln('<comment>Examples:</comment>');
            $output->writeln('<comment>  shape:table:make Users Users</comment>');
            $output->writeln('<comment>  shape:table:make Users UserEmails</comment>');
            return Command::FAILURE;
        }

        $domainRoot = "{$this->projectRoot}/src/Domains/{$domain}";
        if (!is_dir($domainRoot)) {
            $output->writeln("<error>Domain does not exist:</error> {$domain} (expected src/Domains/{$domain})");
            return Command::FAILURE;
        }

        $tablesDir = "{$domainRoot}/Database/Tables";
        $this->mkdir($tablesDir, $output);

        $class = "{$name}Table";
        $path = "{$tablesDir}/{$class}.php";

        if (is_file($path)) {
            $output->writeln('<error>Blueprint already exists:</error> ' . $this->rel($path));
            return Command::FAILURE;
        }

        $tableName = $this->inferTableNameFromEntityOrName($domain, $name);

        file_put_contents($path, $this->stub($domain, $class, $tableName));
        $output->writeln(' + file ' . $this->rel($path));

        return Command::SUCCESS;
    }

    private function stub(string $domain, string $class, string $tableName): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Database\\Tables;\n\nuse Lilly\\Database\\Schema\\Blueprint;\n\nfinal class {$class}\n{\n    public static function name(): string\n    {\n        return '{$tableName}';\n    }\n\n    public static function define(Blueprint \$t): void\n    {\n        \$t->id();\n        \$t->timestamps();\n    }\n\n    /**\n     * Optional FK definitions for tooling.\n     * Return format matches your Schema foreign key compiler expectations.\n     */\n    public static function foreignKeys(): array\n    {\n        return [];\n    }\n}\n";
    }

    private function inferTableNameFromEntityOrName(string $domain, string $name): string
    {
        $entityFqcn = "Domains\\{$domain}\\Entities\\{$name}";

        if (class_exists($entityFqcn)) {
            try {
                $meta = (new \Lilly\Database\Orm\Metadata\MetadataFactory())->for($entityFqcn);
                return $meta->table;
            } catch (\Throwable) {
                // fall through to inferred name
            }
        }

        return $this->classToTable($name);
    }

    private function classToTable(string $class): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $class) ?? '';
        $snake = strtolower($snake);
        $snake = preg_replace('/_+/', '_', $snake) ?? '';
        return trim($snake, '_');
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function normalizeClassName(string $name): string
    {
        $name = trim($name);

        // split on non-alphanumeric, then StudlyCase
        $parts = preg_split('/[^A-Za-z0-9]+/', $name) ?: [];

        $class = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $class .= ucfirst(strtolower($part));
        }

        return $class;
    }

    private function mkdir(string $path, OutputInterface $output): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($path));
        }
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
