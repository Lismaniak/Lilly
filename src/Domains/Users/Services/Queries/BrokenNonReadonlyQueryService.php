<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;

final class BrokenNonReadonlyQueryService extends QueryService
{
    public function run(): ResultDto
    {
        return $this->handle(new NonReadonlyUsersQuery(1));
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new BrokenNonReadonlyQueryResult('ok');
    }
}

class NonReadonlyUsersQuery implements QueryDto
{
    public function __construct(public int $id)
    {
    }
}

readonly class BrokenNonReadonlyQueryResult implements ResultDto
{
    public function __construct(public string $message)
    {
    }
}
