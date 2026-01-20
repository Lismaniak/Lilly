<?php
declare(strict_types=1);

namespace Domains\Users\Services\Queries;

use Lilly\Dto\Dto;

final readonly class UserSummary implements Dto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    /**
     * @return array{id:int, name:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
