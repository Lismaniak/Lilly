# Lilly Framework – Architecture and Conventions

Lilly is an opinionated PHP 8.3+ framework that enforces a consistent, domain-driven structure for every application built on it. It ships with a CLI for scaffolding and schema syncing, favors explicit boundaries, and keeps read/write paths separated by design.

---

## Quick start

### Requirements

* PHP 8.3+
* Composer
* A database (SQLite by default, MySQL via Docker)

### Local setup (SQLite)

```
cp .env.example .env
composer install
php -S 0.0.0.0:8000 -t public
```

The example `.env` config uses SQLite at `var/lilly.sqlite`. Update values as needed.

### Local setup (Docker + MySQL)

```
cp .env.example .env
docker compose up --build
```

This starts:

* PHP at http://localhost:8000
* MySQL at localhost:3306 with the credentials in `docker-compose.yml`

For Docker, set your `.env` to the MySQL configuration:

```
APP_ENV=local
APP_DEBUG=1

DB_CONNECTION=mysql
DB_DATABASE=lilly
DB_SANDBOX_DATABASE=lilly_sandbox
DB_HOST=mysql
DB_PORT=3306
DB_USERNAME=lilly
DB_PASSWORD=lilly
```

### CLI usage

Use the bundled CLI for scaffolding and schema sync:

```
php lilly-cli list
```

---

This document defines the enforced folder structure, component model, repository discipline, routing conventions, and security flow used in Lilly.

The goal of Lilly is architectural uniformity:

* Every application looks the same
* Every feature is implemented in the same pattern
* Only business logic differs

This is enforced by scaffolding, strict conventions, and structural rules.

---

## Core philosophy

Lilly is opinionated by design.

* Structure beats flexibility
* Boundaries are explicit and enforced
* Read and write paths are separated
* Authorization is mandatory and automatic
* Escape hatches are explicit, never implicit

Only the business logic should differ.

---

## High-level structure

```
src/
  Lilly/              # Framework core
  Domains/            # Business domains (Users, Teams, Billing, etc.)
  App/                # Application layer (components, cross-components)
```

Rules:

* `Domains/` contains all business logic
* `App/` may depend on domain services
* Domain code must never depend on `App/`

---

## Domains (bounded contexts)

Each domain is a fully isolated bounded context.

```
Domains/<DomainName>/
  Models/
  Repositories/
    Queries/
    Commands/
  Migrations/
  Policies/
    Gates/
  Validators/
  Services/
    Commands/
    Queries/
  Routes/
  Components/
  Tests/
```

### Domain rules

* A domain owns its data, rules, and permissions
* Domain code may NOT depend on `App/`
* Services may depend on repositories
* Domain policies are enforced before domain code executes
* Services are the only place where repositories are used

---

## Models

```
Domains/Users/Models/
  User.php
  UserFields.php
  UserRelations.php
```

### Responsibilities

**User.php**

* Domain model
* Represents business state
* No workflows
* No persistence logic
* Small helper behavior only

**UserFields.php**

* Field definitions
* Types, defaults, constraints
* Used for migrations and validation

**UserRelations.php**

* Domain relations (hasMany, belongsTo, etc.)
* No queries
* No side effects

---

## Repositories (persistence boundary)

Repositories are split to enforce strict separation between reads and writes.

```
Domains/Users/Repositories/
  Queries/
    UserQueryRepository.php
  Commands/
    UserCommandRepository.php
```

### Repository rules (strict)

**Query repositories**

* SELECT and read operations only
* Must never modify state

**Command repositories**

* INSERT, UPDATE, DELETE only
* Must never return arbitrary query results

**All repositories**

* Encapsulate all persistence logic
* Hide SQL, ORM, or storage details
* Return domain models or DTOs
* Contain no business logic

Services must NOT:

* Write SQL
* Use query builders directly
* Talk to the database directly

---

## Service to repository usage rules (enforced)

```
Services/Queries   -> QueryRepositories only
Services/Commands  -> CommandRepositories
                      (QueryRepositories allowed for checks)
```

**Query services**

* Read-only
* No side effects
* Safe to cache
* May only depend on query repositories

**Command services**

* Perform mutations
* May depend on:

  * Query repositories (lookups, checks)
  * Command repositories (writes)

This enables mechanical enforcement and a consistent mental model.

---

## Command service base class

All command services extend `Lilly\Services\CommandService`.

Behavior:

* `handle()` validates the DTO types before and after execution.
* `execute()` contains the mutation logic and returns a `ResultDto`.
* Optional guards (`expectedDataClass()` and `expectedResultClass()`) enforce concrete DTO types and throw when mismatched.

Example:

```
readonly class CreateUserData implements CommandDataDto
{
    public function __construct(public string $name)
    {
        $data = ArrayValidator::map(
            ['name' => $name],
            ['name' => ['required', 'string', 'max:255', 'non_empty']]
        );

        $this->name = $data['name'];
    }
}

readonly class CreateUserResult implements ResultDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $createdAt,
        public string $updatedAt
    ) {
        ArrayValidator::map(
            [
                'id' => $id,
                'name' => $name,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ],
            [
                'id' => ['required', 'int'],
                'name' => ['required', 'string', 'max:255'],
                'created_at' => ['required', 'string'],
                'updated_at' => ['required', 'string'],
            ]
        );
    }
}

final class CreateUserService extends CommandService
{
    protected function execute(CommandDataDto $data): ResultDto
    {
        // mutation logic
    }

    protected function expectedDataClass(): ?string
    {
        return CreateUserData::class;
    }

    protected function expectedResultClass(): ?string
    {
        return CreateUserResult::class;
    }
}
```

---

## Migrations

```
Domains/Users/Migrations/
  2026_01_16_000001_create_users.php
```

* Domain-scoped migrations
* One domain per migration set
* Loaded automatically by the framework

---

## Schema sync (table blueprints)

Lilly can generate migrations from domain table blueprints and keep an approved manifest in sync.

### Blueprint location and structure

```
Domains/<Domain>/Database/Tables/*Table.php
```

Each table class must implement:

* `public static function name(): string`
* `public static function define(Blueprint $t): void`
* Optional: `public static function foreignKeys(): array`

### Example table blueprint

```php
<?php
declare(strict_types=1);

namespace Domains\Users\Database\Tables;

use Lilly\Database\Schema\Blueprint;

final class UsersTable
{
    public static function name(): string
    {
        return 'users';
    }

    public static function define(Blueprint $t): void
    {
        $t->id();
        $t->unsignedBigInteger('team_id')->nullable();
        $t->uuid('uuid')->unique();
        $t->string('email')->unique();
        $t->string('full_name')->was('name');
        $t->timestamps();
    }

    public static function foreignKeys(): array
    {
        return [
            [
                'column' => 'team_id',
                'references' => 'id',
                'on' => 'teams',
                'onDelete' => 'cascade',
            ],
        ];
    }
}
```

### Supported column types

* `id()`
* `string($name, $length = 255)`
* `text()`
* `int()`
* `boolean()`
* `timestamp()`
* `datetime()`
* `date()`
* `unsignedBigInteger()`
* `bigInteger()`
* `uuid()`
* `json()`

### Rename safety (`was()`)

Use `->was()` on a column to mark legacy names so sync will emit a safe rename instead of drop+add.

```
$t->string('display_name')->was(['name', 'full_name']);
```

### Sync lifecycle

1. Define or update table blueprints in `Database/Tables`.
2. Run `db:sync` to generate a pending plan in `Database/Migrations/.pending/<hash>`.
3. Review the generated migrations and plan.
4. Run `db:sync:apply <Domain> [hash]` to:

   * Sandbox-run the plan
   * Apply migrations to the real DB
   * Promote files into `Database/Migrations/`
   * Write `schema.manifest.json` as the approved baseline

5. If needed, discard a plan with `db:sync:discard <Domain> <hash>`.

### Destructive drops

`db:sync` will refuse to generate migrations that drop tables or columns unless you pass:

```
db:sync <Domain> --allow-drop
```

This is an explicit safety check.

---

## Policies and gates (domain middleware)

```
Domains/Users/Policies/
  UsersPolicy.php
  Gates/
    CanCreateUser.php
    CanInviteUser.php
```

* Policies act as mandatory middleware
* No domain code executes unless its policy passes
* Gates are fine-grained permission checks
* Enforced automatically by the Kernel

---

## Validators

```
Domains/Users/Validators/
  CreateUserValidator.php
```

* Optional domain-level validation helpers
* Used by services for business rule validation
* Not tied to HTTP or `App/`

---

## Services (use cases)

```
Domains/Users/Services/
  Commands/
    CreateUserService.php

  Queries/
    GetUserProfileService.php
```

### Service rules

* One service equals one use case
* Services contain business workflows
* Services:

  * Accept DTOs
  * Call repositories
  * Enforce business rules
* Services do NOT:

  * Depend on HTTP
  * Depend on `App/`
  * Perform authorization manually
  * Return domain models directly

Authorization is enforced before services run.

Command service files define the command data and result DTOs alongside the service class.

---

## DTO conventions (mandatory)

DTOs are used everywhere to enforce boundaries and intent.

### DTO types

| Type           | Location                          | Purpose           |
| -------------- | --------------------------------- | ----------------- |
| Props          | `Components/*/Props.php`          | GET configuration |
| Action Input   | `Components/*/Actions/*Input.php` | POST body         |
| Service Data   | `Domains/*/Services/Commands/*Service.php` | Command input     |
| Service Query  | `Domains/*/Services/Queries/*Service.php`  | Read intent       |
| Service Result | `Domains/*/Services/Commands/*Service.php` and `Domains/*/Services/Queries/*Service.php` | Service output    |

### DTO rules

* `readonly`
* Immutable
* Typed
* Validation rules live inside the DTO
* No framework or database access

---

## Why different DTO types exist

Lilly distinguishes between:

* Action Input DTOs
* Service Command Data DTOs
* Service Query DTOs
* Props DTOs

The difference is intent, not syntax.

This separation enforces boundaries and prevents accidental coupling.

---

## Command Data DTO (`*Data`)

Location:

```
Domains/*/Services/Commands/*Service.php
```

Purpose: represents an intent to change state.

Characteristics:

* Causes side effects
* Used only by command services
* May fail due to business rules
* Usually returns a `*Result` DTO defined in the same file

---

## Query DTO (`*Query`)

Location:

```
Domains/*/Services/Queries/*Service.php
```

Purpose: represents an intent to read state.

Characteristics:

* Read-only
* No side effects
* Safe to repeat
* Safe to cache

---

## Query service structure (generated)

Generated query services live in a single file that holds the query DTO, result DTO, and service class. This keeps the read intent, validation, and execution co-located.

```
namespace Domains/<Domain>/Services/Queries;

use Lilly\Dto\QueryDto;
use Lilly\Dto\ResultDto;
use Lilly\Services\QueryService;
use Lilly\Validation\ArrayValidator;

readonly class <Name>Query implements QueryDto
{
    public function __construct(/* inputs */)
    {
        $data = ArrayValidator::map(
            [
                // input mapping
            ],
            [
                // query validation rules
            ]
        );

        // assign validated data
    }
}

readonly class <Name>Result implements ResultDto
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = ArrayValidator::mapListWithSchema($items, [
            // result item schema + validation rules
        ]);
    }

    /**
     * @var list<array<string, mixed>>
     */
    public array $items;
}

final class <Name>Service extends QueryService
{
    protected function execute(QueryDto $query): ResultDto
    {
        return new <Name>Result();
    }
}
```

### Query DTO validation

* Validation rules live inside the query DTO constructor.
* `ArrayValidator::map()` is used to:
  * enforce required fields
  * validate types and constraints
  * normalize values before assignment

### Result DTO validation

* Result data is validated in the result DTO constructor.
* `ArrayValidator::mapListWithSchema()` is used to:
  * shape each item
  * validate field rules
  * enforce stable output structure

---

## Action Input DTO (`*Input.php`)

Location:

```
App/**/Actions/*Input.php
```

Purpose: represents raw input coming from HTTP.

Responsibilities:

* Parse request data
* Validate shape
* Normalize values

Validation includes:

* Required fields
* String length
* Format checks

Validation excludes:

* Uniqueness checks
* Permission checks
* Cross-entity business rules

Action Input DTOs protect the domain from the application layer.

---

## Props DTO (`Props.php`)

Location:

```
App/**/Props.php
```

Purpose: represents component configuration, not user input.

Props are setup, not interaction.

---

## Request lifecycle

### POST (mutation)

```
HTTP Request
  -> Action Input DTO
  -> Service Command Data
  -> Command Service
  -> Repositories
```

### GET (read)

```
HTTP Request
  -> Props DTO
  -> Query DTO
  -> Query Service
  -> Repositories
```

Each step removes a dependency.

---

## Routing

### Domain routes

```
Domains/<Domain>/Routes/
  web.php
  api.php
  components.php
```

Each route file must return:

```
function (DomainRouter $router): void
```

Domain policies are enforced automatically.

---

## Cross-domain routes

Cross-domain components live in `App/CrossComponents`.

```
App/CrossComponents/<Group>/<Component>/
  Routes/
    web.php
    api.php
    components.php
```

Group rules:

* Group is a PascalCase concatenation of involved domain names
* Domain names are lowercased, sorted alphabetically, then capitalized
* Example: domains `Teams` and `Users` become `TeamsUsers`

Routes are registered with `CrossDomainRouter` and enforce all involved domain policies.

---

## Components

### Single-domain components

```
Domains/Users/Components/AddUserButton/
  Component.php
  Props.php
  Actions/
  View/
  Assets/
  Tests/
```

Rules:

* Must touch exactly one domain
* Domain inferred from folder location
* Domain policy enforced automatically

Component ID convention:

```
users.add-user-button
```

---

### Cross-domain components

```
App/CrossComponents/TeamsUsers/InviteUserToTeam/
```

Component ID convention:

```
cross.teamsusers.invite-user-to-team
```

---

## Security and authorization flow

Per request:

1. Kernel matches route
2. Domain policies are resolved and enforced
3. Route-declared gates are enforced
4. Route handler executes

If any policy or gate denies, the request returns 403.

If a domain policy class is missing, the application fails fast.

---

## Testing rules

* Every query repository has tests
* Every command repository has tests
* Every command service has a test
* Every component has:

  * Render test
  * Action test (if applicable)
* No duplicated business logic tests in components

---

## CLI tooling

Available commands:

```
shape:domain:make <Domain>
shape:domain:remove <Domain>

shape:cross:make <Name> <DomainA> <DomainB> [...DomainN]
shape:cross:remove <Name>

shape:gate:make <Domain> <GateClass>
shape:gate:remove <Domain> <GateClass>

db:sync [Domain] [--allow-drop]
db:sync:lint [Domain]
db:sync:apply <Domain> [hash]
db:sync:discard <Domain> <hash>
```

Scaffolding is the only supported way to create structure.

---

## Final note

If something feels strict, that is intentional.

Lilly optimizes for:

* Predictability
* Safety
* Long-term maintainability
* Zero architectural drift

Only the business logic should differ.
