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
        $nameRaw = (string) $input->getArgument('name');
        $domain = $this->normalizeDomainName($nameRaw);

        $domainRoot = $this->projectRoot . '/src/Domains/' . $domain;

        if (is_dir($domainRoot)) {
            $output->writeln("<error>Domain already exists:</error> {$domain}");
            return Command::FAILURE;
        }

        $this->mkdir($domainRoot, $output);

        $dirs = [
            'Models',
            'Repositories/Queries',
            'Repositories/Commands',
            'Migrations',
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

        $this->writeFile(
            $domainRoot . '/Routes/web.php',
            "<?php\ndeclare(strict_types=1);\n\n// Domain routes for {$domain} (web)\n",
            $output
        );

        $this->writeFile(
            $domainRoot . '/Routes/api.php',
            "<?php\ndeclare(strict_types=1);\n\n// Domain routes for {$domain} (api)\n",
            $output
        );

        $this->writeFile(
            $domainRoot . '/Routes/components.php',
            "<?php\ndeclare(strict_types=1);\n\n// Optional: component route overrides for {$domain}\n",
            $output
        );

        $domainKey = strtolower($domain);

        $this->writeFile(
            $domainRoot . "/Policies/{$domain}Policy.php",
            $this->policyStub($domain, $domainKey),
            $output
        );

        $this->writeFile(
            $domainRoot . "/Repositories/Queries/{$domain}QueryRepository.php",
            $this->queryRepoStub($domain, $domainKey),
            $output
        );

        $this->writeFile(
            $domainRoot . "/Repositories/Commands/{$domain}CommandRepository.php",
            $this->commandRepoStub($domain, $domainKey),
            $output
        );

        $output->writeln("<info>Created domain:</info> {$domain}");
        return Command::SUCCESS;
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? $name;

        if ($name === '') {
            return 'Domain';
        }

        return ucfirst($name);
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

    private function policyStub(string $domain, string $domainKey): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domain\\{$domain}\\Policies;\n\nuse Lilly\\Http\\Request;\nuse Lilly\\Security\\DomainPolicy;\nuse Lilly\\Security\\PolicyDecision;\n\nfinal class {$domain}Policy implements DomainPolicy\n{\n    public function domain(): string\n    {\n        return '{$domainKey}';\n    }\n\n    public function authorize(Request \$request): PolicyDecision\n    {\n        return PolicyDecision::allow();\n    }\n}\n";
    }

    private function queryRepoStub(string $domain, string $domainKey): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domain\\{$domain}\\Repositories\\Queries;\n\nfinal class {$domain}QueryRepository\n{\n    // Read-only repository for domain '{$domainKey}'\n    // Only SELECT operations allowed\n}\n";
    }

    private function commandRepoStub(string $domain, string $domainKey): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace Domain\\{$domain}\\Repositories\\Commands;\n\nfinal class {$domain}CommandRepository\n{\n    // Write repository for domain '{$domainKey}'\n    // Only INSERT/UPDATE/DELETE operations allowed\n}\n";
    }
}
