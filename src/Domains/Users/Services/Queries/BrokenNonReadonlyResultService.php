<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;

final class BrokenNonReadonlyResultService extends QueryService
{
    public function run(): ResultDto
    {
        return $this->handle(new ValidUsersQueryForNonReadonlyResultQuery(1));
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new NonReadonlyUsersResult('bad');
    }
}

readonly class ValidUsersQueryForNonReadonlyResultQuery implements QueryDto
{
    public function __construct(public int $id)
    {
    }
}

class NonReadonlyUsersResult implements ResultDto
{
    public function __construct(public string $message)
    {
    }
}
