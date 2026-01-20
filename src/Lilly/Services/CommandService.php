<?php
declare(strict_types=1);

namespace Lilly\Services;

use Lilly\Dto\CommandDataDto;
use Lilly\Dto\DtoGuard;
use Lilly\Dto\ResultDto;

abstract class CommandService
{
    final public function handle(CommandDataDto $data): ResultDto
    {
        DtoGuard::assertCommandDataDto($data);

        $result = $this->execute($data);

        DtoGuard::assertResultDto($result);

        return $result;
    }

    abstract protected function execute(CommandDataDto $data): ResultDto;
}
