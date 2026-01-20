<?php
declare(strict_types=1);

namespace Lilly\Services;

use Lilly\Dto\DtoGuard;
use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;

abstract class QueryService
{
    final public function handle(QueryDto $query): ResultDto
    {
        DtoGuard::assertQueryDto($query);

        $result = $this->execute($query);

        DtoGuard::assertResultDto($result);

        return $result;
    }

    abstract protected function execute(QueryDto $query): ResultDto;
}
