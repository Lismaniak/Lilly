<?php
declare(strict_types=1);

namespace Lilly\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeAppPolicyCommand extends Command
{
    public function __construct(
        private readonly string $projectRoot
    ) {
        parent::__construct('shape:app:policy:make');
    }

    protected function configure(): void
    {
        $this->setDescription('Create App policy in src/App/Policies/AppPolicy.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $policiesDir = "{$this->projectRoot}/src/App/Policies";
        $gatesDir = "{$policiesDir}/Gates";
        $policyPath = "{$policiesDir}/AppPolicy.php";

        if (is_file($policyPath)) {
            $output->writeln('<error>AppPolicy already exists:</error> src/App/Policies/AppPolicy.php');
            return Command::FAILURE;
        }

        $this->mkdir($policiesDir, $output);
        $this->mkdir($gatesDir, $output);

        $this->write($policyPath, $this->policyStub(), $output);

        $output->writeln('<info>Created app policy:</info> App\\Policies\\AppPolicy');
        return Command::SUCCESS;
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

    private function policyStub(): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nnamespace App\\Policies;\n\nuse Lilly\\Http\\Request;\nuse Lilly\\Security\\DomainPolicy;\nuse Lilly\\Security\\PolicyDecision;\n\nfinal class AppPolicy implements DomainPolicy\n{\n    public function domain(): string\n    {\n        return 'app';\n    }\n\n    public function authorize(Request \$request): PolicyDecision\n    {\n        return PolicyDecision::allow();\n    }\n}\n";
    }
}
