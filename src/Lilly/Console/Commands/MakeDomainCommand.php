<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeDomainCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:domain:make');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scaffold a new Domain folder structure')
            ->addArgument('name', InputArgument::REQUIRED, 'Domain name, e.g. Users');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $domain = $this->normalizeDomainName((string) $input->getArgument('name'));

        if ($domain === '') {
            $output->writeln('<error>Invalid domain name. Use letters/numbers only, e.g. Users</error>');
            return Command::FAILURE;
        }

        $domainRoot = $this->projectRoot . '/src/Domains/' . $domain;

        if (is_dir($domainRoot)) {
            $output->writeln("<error>Domain already exists:</error> {$domain}");
            return Command::FAILURE;
        }

        $this->mkdir($domainRoot, $output);

        $dirs = [
            'Models',
            'Repositories',
            'Migrations/owned',
            'Policies/Gates',
            'Validators',
            'Services/Commands',
            'Services/Queries',
            'Routes',
            'Components',
            'Tests/Models',
            'Tests/Repositories',
            'Tests/Services',
            'Tests/Policies',
        ];

        foreach ($dirs as $dir) {
            $this->mkdir($domainRoot . '/' . $dir, $output);
        }

        $domainKey = strtolower($domain);

        $this->writeFile(
            $domainRoot . "/Models/{$domain}.php",
            $this->domainModelStub($domain),
            $output
        );

        $this->writeFile(
            $domainRoot . '/Routes/web.php',
            $this->routesWebStub($domain),
            $output
        );

        $this->writeFile(
            $domainRoot . '/Routes/api.php',
            $this->routesApiStub($domain),
            $output
        );

        $this->writeFile(
            $domainRoot . '/Routes/components.php',
            $this->routesComponentsStub($domain),
            $output
        );

        $this->writeFile(
            $domainRoot . "/Policies/{$domain}Policy.php",
            $this->policyStub($domain, $domainKey),
            $output
        );

        $this->writeFile(
            $domainRoot . "/Repositories/{$domain}QueryRepository.php",
            $this->queryRepoStub($domain, $domainKey),
            $output
        );

        $this->writeFile(
            $domainRoot . "/Repositories/{$domain}CommandRepository.php",
            $this->commandRepoStub($domain, $domainKey),
            $output
        );

        $output->writeln("<info>Created domain:</info> {$domain}");
        return Command::SUCCESS;
    }

    private function normalizeDomainName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9]/', '', trim($name)) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function mkdir(string $path, OutputInterface $output): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            $output->writeln(" + dir  {$this->rel($path)}");
        }
    }

    private function writeFile(string $path, string $contents, OutputInterface $output): void
    {
        file_put_contents($path, $contents);
        $output->writeln(" + file {$this->rel($path)}");
    }

    private function rel(string $absPath): string
    {
        return ltrim(str_replace($this->projectRoot, '', $absPath), '/');
    }

    private function routesWebStub(string $domain): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Http\\DomainRouter;\n\nreturn function (DomainRouter \$router): void {\n    // Web routes for {$domain}\n};\n";
    }

    private function routesApiStub(string $domain): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Http\\DomainRouter;\n\nreturn function (DomainRouter \$router): void {\n    // API routes for {$domain}\n};\n";
    }

    private function routesComponentsStub(string $domain): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Http\\DomainRouter;\n\nreturn function (DomainRouter \$router): void {\n    // Component route overrides for {$domain}\n};\n";
    }

    private function policyStub(string $domain, string $domainKey): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Policies;\n\nuse Lilly\\Http\\Request;\nuse Lilly\\Security\\DomainPolicy;\nuse Lilly\\Security\\PolicyDecision;\n\nfinal class {$domain}Policy implements DomainPolicy\n{\n    public function domain(): string\n    {\n        return '{$domainKey}';\n    }\n\n    public function authorize(Request \$request): PolicyDecision\n    {\n        return PolicyDecision::allow();\n    }\n}\n";
    }

    private function queryRepoStub(string $domain, string $domainKey): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Repositories;\n\nuse Domains\\{$domain}\\Models\\{$domain};\nuse Lilly\\Database\\Repositories\\AbstractRepository;\n\nfinal class {$domain}QueryRepository extends AbstractRepository\n{\n    protected function table(): string\n    {\n        return {$domain}::table();\n    }\n\n    protected function primaryKey(): string\n    {\n        return {$domain}::primaryKey();\n    }\n\n    // <methods>\n\n    // </methods>\n}\n";
    }

    private function commandRepoStub(string $domain, string $domainKey): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Repositories;\n\nuse Domains\\{$domain}\\Models\\{$domain};\nuse Lilly\\Database\\Repositories\\AbstractRepository;\n\nfinal class {$domain}CommandRepository extends AbstractRepository\n{\n    protected function table(): string\n    {\n        return {$domain}::table();\n    }\n\n    protected function primaryKey(): string\n    {\n        return {$domain}::primaryKey();\n    }\n\n    // <methods>\n\n    // </methods>\n}\n";
    }

    private function domainModelStub(string $domain): string
    {
        $domainKey = strtolower($domain);
        $singular = str_ends_with($domainKey, 's') ? substr($domainKey, 0, -1) : $domainKey;

        $owned = [
            "{$domainKey}_emails",
            "{$domainKey}_names",
        ];

        $ownedLines = "            '" . implode("',\n            '", $owned) . "',";

        $fkBlocks = [];
        foreach ($owned as $table) {
            $fkBlocks[] =
                "            [\n" .
                "                'table' => '{$table}',\n" .
                "                'column' => '{$singular}_id',\n" .
                "                'references' => ['table' => '{$domainKey}', 'column' => 'id'],\n" .
                "                'onDelete' => 'cascade',\n" .
                "            ]";
        }

        $fkLines = implode(",\n", $fkBlocks);

        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Models;\n\n/**\n * Domain definition model.\n *\n * Used by CLI tooling (migrations scaffolding, introspection).\n */\nfinal class {$domain}\n{\n    public static function table(): string\n    {\n        return '{$domainKey}';\n    }\n\n    public static function primaryKey(): string\n    {\n        return 'id';\n    }\n\n    public static function idType(): string\n    {\n        return 'int';\n    }\n\n    public static function fromRow(array \$row): self\n    {\n        return new self();\n    }\n\n    public static function ownedTables(): array\n    {\n        return [\n{$ownedLines}\n        ];\n    }\n\n    public static function foreignKeys(): array\n    {\n        return [\n{$fkLines}\n        ];\n    }\n}\n";
    }
}
