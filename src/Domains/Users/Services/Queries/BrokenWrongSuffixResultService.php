<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;

final class BrokenWrongSuffixResultService extends QueryService
{
    public function run(): ResultDto
    {
        return $this->handle(new ValidUsersQueryForWrongSuffixResultQuery(1));
    }

    protected function execute(QueryDto $query): ResultDto
    {
        return new UsersOutput('bad');
    }
}

readonly class ValidUsersQueryForWrongSuffixResultQuery implements QueryDto
{
    public function __construct(public int $id)
    {
    }
}

readonly class UsersOutput implements ResultDto
{
    public function __construct(public string $message)
    {
    }
}
