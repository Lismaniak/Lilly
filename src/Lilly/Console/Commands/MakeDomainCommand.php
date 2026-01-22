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
            'Controllers',
            'Entities',
            'Repositories',
            'Database/Tables',
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
            $domainRoot . "/Entities/{$domain}.php",
            $this->entityStub($domain, $domainKey),
            $output
        );

        $this->writeFile(
            $domainRoot . "/Controllers/{$domain}Controller.php",
            $this->controllerStub($domain),
            $output
        );

        $this->writeFile(
            $domainRoot . "/Database/Tables/{$domain}Table.php",
            $this->tableStub($domain, $domainKey),
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
            $this->queryRepoStub($domain),
            $output
        );

        $this->writeFile(
            $domainRoot . "/Repositories/{$domain}CommandRepository.php",
            $this->commandRepoStub($domain),
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
            $output->writeln(' + dir  ' . $this->rel($path));
        }
    }

    private function writeFile(string $path, string $contents, OutputInterface $output): void
    {
        file_put_contents($path, $contents);
        $output->writeln(' + file ' . $this->rel($path));
    }

    private function rel(string $absPath): string
    {
        return ltrim(str_replace($this->projectRoot, '', $absPath), '/');
    }

    private function routesWebStub(string $domain): string
    {
        $domainKey = strtolower($domain);

        return "<?php\ndeclare(strict_types=1);\n\nuse Domains\\{$domain}\\Controllers\\{$domain}Controller;\nuse Lilly\\Http\\DomainRouter;\nuse Lilly\\Http\\Response;\n\nreturn function (DomainRouter \$router): void {\n    \$controller = new {$domain}Controller();\n\n    \$router->get('/{$domainKey}/health', fn (): Response => \$controller->health());\n};\n";
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

    private function queryRepoStub(string $domain): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Repositories;\n\nuse Domains\\{$domain}\\Entities\\{$domain};\nuse Lilly\\Database\\Orm\\Orm;\nuse Lilly\\Database\\Orm\\Repository\\QueryRepository;\n\nfinal class {$domain}QueryRepository extends QueryRepository\n{\n    public function __construct(Orm \$orm)\n    {\n        parent::__construct(\$orm, {$domain}::class);\n    }\n}\n";
    }

    private function commandRepoStub(string $domain): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Repositories;\n\nuse Domains\\{$domain}\\Entities\\{$domain};\nuse Lilly\\Database\\Orm\\Orm;\nuse Lilly\\Database\\Orm\\Repository\\CommandRepository;\n\nfinal class {$domain}CommandRepository extends CommandRepository\n{\n    public function __construct(Orm \$orm)\n    {\n        parent::__construct(\$orm, {$domain}::class);\n    }\n}\n";
    }

    private function entityStub(string $domain, string $domainKey): string
    {
        $table = $domainKey;

        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Entities;\n\nuse Lilly\\Database\\Orm\\Attributes\\Table;\nuse Lilly\\Database\\Orm\\Attributes\\Column;\n\n#[Table('{$table}')]\nfinal class {$domain}\n{\n    #[Column('id', primary: true, autoIncrement: true)]\n    public ?int \$id = null;\n}\n";
    }

    private function controllerStub(string $domain): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Controllers;\n\nuse Lilly\\Http\\Response;\n\nfinal class {$domain}Controller\n{\n    public function health(): Response\n    {\n        return Response::json(['ok' => true]);\n    }\n}\n";
    }

    private function tableStub(string $domain, string $domainKey): string
    {
        $table = $domainKey;

        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domains\\{$domain}\\Database\\Tables;\n\nuse Lilly\\Database\\Schema\\Blueprint;\n\nfinal class {$domain}Table\n{\n    public static function name(): string\n    {\n        return '{$table}';\n    }\n\n    public static function define(Blueprint \$t): void\n    {\n        \$t->id();\n        \$t->timestamps();\n    }\n}\n";
    }
}
