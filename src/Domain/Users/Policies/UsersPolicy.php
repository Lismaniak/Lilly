<?php
declare(strict_types=1);

namespace Domain\Users\Policies;

use Lilly\Http\Request;
use Lilly\Security\DomainPolicy;
use Lilly\Security\PolicyDecision;

final class UsersPolicy implements DomainPolicy
{
    public function domain(): string
    {
        return 'users';
    }

    public function authorize(Request $request): PolicyDecision
    {
        return PolicyDecision::allow();
    }
}
