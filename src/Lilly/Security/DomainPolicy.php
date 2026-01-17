<?php
// src/Lilly/Security/DomainPolicy.php

declare(strict_types=1);

namespace Lilly\Security;

use Lilly\Http\Request;

interface DomainPolicy
{
    public function domain(): string;

    public function authorize(Request $request): PolicyDecision;
}