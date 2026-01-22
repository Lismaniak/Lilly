<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeCrossComponentCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:cross:make');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scaffold a cross-domain component in src/App/CrossComponents')
            ->addArgument('name', InputArgument::REQUIRED, 'Component name, e.g. InviteUserToTeam')
            ->addArgument(
                'domains',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Existing domain names, e.g. Users Teams (any order)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->normalizeName((string) $input->getArgument('name'));
        $rawDomains = $input->getArgument('domains');

        if ($name === '' || !is_array($rawDomains) || $rawDomains === []) {
            $output->writeln('<error>Usage: shape:cross:make <Name> <DomainA> <DomainB> [...DomainN]</error>');
            return Command::FAILURE;
        }

        $domains = [];
        foreach ($rawDomains as $raw) {
            if (!is_string($raw)) {
                continue;
            }

            $domain = $this->normalizeDomainName($raw);
            if ($domain === '') {
                $output->writeln("<error>Invalid domain name:</error> {$raw}");
                return Command::FAILURE;
            }

            if (!$this->domainExists($domain)) {
                $output->writeln("<error>Domain does not exist:</error> {$domain} (expected folder src/Domains/{$domain})");
                return Command::FAILURE;
            }

            $domains[$domain] = true;
        }

        if (count($domains) < 2) {
            $output->writeln('<error>CrossComponent requires at least 2 existing domains</error>');
            return Command::FAILURE;
        }

        $domainNames = array_keys($domains);            // PascalCase, ex Users, Teams
        $domainKeys = array_map('strtolower', $domainNames);
        sort($domainKeys);                              // alphabetical keys: teams, users

        $group = $this->signatureNamespaceGroup($domainKeys); // TeamsUsers

        $root = "{$this->projectRoot}/src/App/CrossComponents/{$group}/{$name}";

        if (is_dir($root)) {
            $output->writeln("<error>CrossComponent already exists:</error> {$group}/{$name}");
            return Command::FAILURE;
        }

        $dirs = [
            '',
            'Routes',
            'Actions',
            'Assets',
            'Tests',
        ];

        foreach ($dirs as $dir) {
            $this->mkdir($root . ($dir ? "/{$dir}" : ''), $output);
        }

        $this->write("{$root}/Component.php", $this->componentStub($group, $name, $domainKeys), $output);
        $this->write("{$root}/Props.php", $this->propsStub($group, $name), $output);

        $this->write("{$root}/Routes/web.php", $this->routesStub($domainKeys), $output);
        $this->write("{$root}/Routes/api.php", $this->routesStub($domainKeys), $output);
        $this->write("{$root}/Routes/components.php", $this->routesStub($domainKeys), $output);

        $this->write("{$root}/Actions/{$name}.php", $this->actionStub($group, $name), $output);
        $this->write("{$root}/Actions/{$name}Input.php", $this->inputStub($group, $name), $output);

        $this->write("{$root}/Assets/component.ts", "// {$name} hydration\n", $output);
        $this->write("{$root}/Assets/component.css", "/* {$name} styles */\n", $output);

        $output->writeln("<info>Created cross component:</info> {$group}/{$name}");
        $output->writeln("<info>Namespace:</info> App\\CrossComponents\\{$group}\\{$name}");
        return Command::SUCCESS;
    }

    private function domainExists(string $domain): bool
    {
        return is_dir("{$this->projectRoot}/src/Domains/{$domain}");
    }

    private function normalizeDomainName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        return $name !== '' ? ucfirst($name) : '';
    }

    private function normalizeName(string $raw): string
    {
        $raw = preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '';
        return $raw !== '' ? ucfirst($raw) : '';
    }

    /**
     * @param list<string> $domainKeys lowercase keys, sorted
     */
    private function signatureNamespaceGroup(array $domainKeys): string
    {
        $parts = array_map(
            static fn (string $k): string => ucfirst($k),
            $domainKeys
        );

        return implode('', $parts); // TeamsUsers
    }

    private function mkdir(string $path, OutputInterface $output): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            $output->writeln(" + dir  " . $this->rel($path));
        }
    }

    private function write(string $path, string $contents, OutputInterface $output): void
    {
        file_put_contents($path, $contents);
        $output->writeln(" + file " . $this->rel($path));
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }

    /**
     * @param list<string> $domainKeys
     */
    private function componentStub(string $group, string $name, array $domainKeys): string
    {
        $keys = implode(', ', $domainKeys);

        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\CrossComponents\\{$group}\\{$name};\n\nfinal class Component\n{\n    // CrossComponent domains: {$keys}\n}\n";
    }

    private function propsStub(string $group, string $name): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\CrossComponents\\{$group}\\{$name};\n\nfinal readonly class Props\n{\n    public function __construct()\n    {\n    }\n}\n";
    }

    /**
     * @param list<string> $domainKeys
     */
    private function routesStub(array $domainKeys): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Http\\CrossDomainRouter;\n\nreturn function (CrossDomainRouter \$router): void {\n};\n";
    }

    private function actionStub(string $group, string $name): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\CrossComponents\\{$group}\\{$name}\\Actions;\n\nfinal class {$name}\n{\n    // orchestrates cross-domain action\n}\n";
    }

    private function inputStub(string $group, string $name): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\CrossComponents\\{$group}\\{$name}\\Actions;\n\nfinal readonly class {$name}Input\n{\n    public function __construct()\n    {\n    }\n}\n";
    }
}
