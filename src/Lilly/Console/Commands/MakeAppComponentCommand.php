<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeAppComponentCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:app:component:make');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scaffold an app component in src/App/Components')
            ->addArgument('name', InputArgument::REQUIRED, 'Component name, e.g. Ping');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->normalizeName((string) $input->getArgument('name'));

        if ($name === '') {
            $output->writeln('<error>Usage: shape:app:component:make <Name></error>');
            return Command::FAILURE;
        }

        $root = "{$this->projectRoot}/src/App/Components/{$name}";

        if (is_dir($root)) {
            $output->writeln("<error>App component already exists:</error> {$name}");
            return Command::FAILURE;
        }

        $dirs = [
            '',
            'Routes',
            'Actions',
            'View',
            'Assets',
            'Tests',
        ];

        foreach ($dirs as $dir) {
            $this->mkdir($root . ($dir ? "/{$dir}" : ''), $output);
        }

        $this->write("{$root}/Component.php", $this->componentStub($name), $output);
        $this->write("{$root}/Props.php", $this->propsStub($name), $output);

        $this->write("{$root}/Routes/web.php", $this->routesStub(), $output);
        $this->write("{$root}/Routes/api.php", $this->routesStub(), $output);
        $this->write("{$root}/Routes/components.php", $this->routesStub(), $output);

        $this->write("{$root}/Actions/{$name}.php", $this->actionStub($name), $output);
        $this->write("{$root}/Actions/{$name}Input.php", $this->inputStub($name), $output);

        $this->write("{$root}/View/view.php", "<!-- {$name} view -->\n", $output);
        $this->write("{$root}/Assets/component.ts", "// {$name} hydration\n", $output);
        $this->write("{$root}/Assets/component.css", "/* {$name} styles */\n", $output);

        $output->writeln("<info>Created app component:</info> {$name}");
        $output->writeln("<info>Namespace:</info> App\\Components\\{$name}");
        return Command::SUCCESS;
    }

    private function normalizeName(string $raw): string
    {
        $raw = preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '';
        return $raw !== '' ? ucfirst($raw) : '';
    }

    private function mkdir(string $path, OutputInterface $output): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            $output->writeln(' + dir  ' . $this->rel($path));
        }
    }

    private function write(string $path, string $contents, OutputInterface $output): void
    {
        file_put_contents($path, $contents);
        $output->writeln(' + file ' . $this->rel($path));
    }

    private function rel(string $abs): string
    {
        return ltrim(str_replace($this->projectRoot, '', $abs), '/');
    }

    private function componentStub(string $name): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Components\\{$name};\n\nfinal class Component\n{\n}\n";
    }

    private function propsStub(string $name): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Components\\{$name};\n\nfinal readonly class Props\n{\n    public function __construct()\n    {\n    }\n}\n";
    }

    private function routesStub(): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nuse Lilly\\Http\\AppRouter;\n\nreturn function (AppRouter \$router): void {\n};\n";
    }

    private function actionStub(string $name): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Components\\{$name}\\Actions;\n\nfinal class {$name}\n{\n}\n";
    }

    private function inputStub(string $name): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Components\\{$name}\\Actions;\n\nfinal readonly class {$name}Input\n{\n    public function __construct()\n    {\n    }\n}\n";
    }
}
