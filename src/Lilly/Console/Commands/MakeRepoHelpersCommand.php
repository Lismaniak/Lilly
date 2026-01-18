<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeRepoHelpersCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:domain:helpers');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Add standard repository helper methods')
            ->addArgument('domain', InputArgument::REQUIRED, 'Domain name, e.g. Users');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('domain'));

        if ($domain === '') {
            $output->writeln('<error>Invalid domain name</error>');
            return Command::FAILURE;
        }

        $schemaFqcn = "Domains\\{$domain}\\Schema\\{$domain}Schema";
        if (!class_exists($schemaFqcn)) {
            $output->writeln("<error>Domain schema not found:</error> {$schemaFqcn}");
            return Command::FAILURE;
        }

        $entityFqcn = "Domains\\{$domain}\\Entities\\{$this->singularize($domain)}";
        if (!class_exists($entityFqcn)) {
            $output->writeln("<error>Domain entity not found:</error> {$entityFqcn}");
            return Command::FAILURE;
        }

        $idType = $schemaFqcn::idType();
        $entityShort = $this->singularize($domain);

        $this->appendHelpers(
            "{$this->projectRoot}/src/Domains/{$domain}/Repositories/{$domain}QueryRepository.php",
            $this->queryHelpersStub($entityShort, $idType),
            ['findById', 'existsById', 'findAll', 'count'],
            $output
        );

        $this->appendHelpers(
            "{$this->projectRoot}/src/Domains/{$domain}/Repositories/{$domain}CommandRepository.php",
            $this->commandHelpersStub($idType),
            ['deleteById', 'deleteAll'],
            $output
        );

        $output->writeln("<info>Repository helpers added for:</info> {$domain}");
        return Command::SUCCESS;
    }

    private function appendHelpers(
        string $path,
        string $stub,
        array $methods,
        OutputInterface $output
    ): void {
        if (!is_file($path)) {
            $output->writeln("<error>Repository not found:</error> {$this->rel($path)}");
            return;
        }

        $src = file_get_contents($path);
        if ($src === false) {
            return;
        }

        foreach ($methods as $method) {
            if (str_contains($src, "function {$method}(")) {
                $output->writeln(" = skip {$method} (already exists)");
                return;
            }
        }

        if (!str_contains($src, '// <methods>')) {
            $output->writeln("<error>Missing // <methods> region in {$this->rel($path)}</error>");
            return;
        }

        $src = str_replace(
            "// </methods>",
            rtrim($stub) . "\n\n    // </methods>",
            $src
        );

        file_put_contents($path, $src);
        $output->writeln(" + file {$this->rel($path)}");
    }

    private function queryHelpersStub(string $entityShort, string $idType): string
    {
        return
            "\n" .
            "    public function findById({$idType} \$id): ?{$entityShort}\n" .
            "    {\n" .
            "        \$row = \$this->findRowById(\$id);\n" .
            "        if (\$row === null) {\n" .
            "            return null;\n" .
            "        }\n\n" .
            "        return {$entityShort}::fromRow(\$row);\n" .
            "    }\n\n" .

            "    public function existsById({$idType} \$id): bool\n" .
            "    {\n" .
            "        return \$this->rowExistsById(\$id);\n" .
            "    }\n\n" .

            "    public function findAll(): array\n" .
            "    {\n" .
            "        \$sql = 'SELECT * FROM ' . \$this->qi(\$this->table());\n" .
            "        \$stmt = \$this->pdo->query(\$sql);\n" .
            "        \$rows = \$stmt->fetchAll(\\PDO::FETCH_ASSOC);\n\n" .
            "        return array_map(fn(array \$row) => {$entityShort}::fromRow(\$row), \$rows);\n" .
            "    }\n\n" .

            "    public function count(): int\n" .
            "    {\n" .
            "        \$sql = 'SELECT COUNT(*) FROM ' . \$this->qi(\$this->table());\n" .
            "        return (int) \$this->pdo->query(\$sql)->fetchColumn();\n" .
            "    }\n";
    }

    private function commandHelpersStub(string $idType): string
    {
        return
            "\n" .
            "    public function deleteById({$idType} \$id): void\n" .
            "    {\n" .
            "        \$this->deleteRowById(\$id);\n" .
            "    }\n\n" .

            "    public function deleteAll(): void\n" .
            "    {\n" .
            "        \$sql = 'DELETE FROM ' . \$this->qi(\$this->table());\n" .
            "        \$this->pdo->exec(\$sql);\n" .
            "    }\n";
    }

    private function singularize(string $domain): string
    {
        $lower = strtolower($domain);
        if (str_ends_with($lower, 's')) {
            return substr($domain, 0, -1);
        }

        return $domain;
    }

    private function normalizeDomainName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9]/', '', trim($name)) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }
}
