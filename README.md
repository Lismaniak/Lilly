Below is a **clean rewrite**, fully aligned with your **current reality**:

* `UI` is gone
* `App` owns components and cross-components
* Routing, CLI scaffolding, and Kernel behavior are consistent
* No meta formatting, just normal Markdown that pastes cleanly

You can **replace the entire README.md with this**.

---

# Lilly Framework – Architecture & Conventions

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

Notes:

* `Domains/` contains all business logic.
* `App/` is allowed to depend on domain services.
* Domain code must never depend on `App/`.

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

* A domain owns its data, rules, and permissions.
* Domain code may NOT depend on `App/`.
* Services may depend on repositories.
* Domain policies are enforced before domain code executes.
* Services are the only place where repositories are used.

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
* No queries or side effects

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
Services/Commands  -> CommandRepositories (QueryRepositories allowed for checks)
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

## Migrations

```
Domains/Users/Migrations/
  2026_01_16_000001_create_users.php
```

* Domain-scoped migrations
* One domain per migration set
* Loaded automatically by the framework

---

## Policies and gates (domain middleware)

```
Domains/Users/Policies/
  UsersPolicy.php
  Gates/
    CanCreateUser.php
    CanInviteUser.php
```

* Policies act as mandatory middleware.
* No domain code executes unless its policy passes.
* Gates are fine-grained permission checks.
* Enforced automatically by the Kernel.

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
    CreateUserData.php
    CreateUserResult.php

  Queries/
    GetUserProfileService.php
    GetUserProfileQuery.php
    GetUserProfileResult.php
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

---

## DTO conventions (mandatory)

DTOs are used everywhere to enforce boundaries and intent.

### DTO types

| Type           | Location                          | Purpose           |
| -------------- | --------------------------------- | ----------------- |
| Props          | `Components/*/Props.php`          | GET configuration |
| Action Input   | `Components/*/Actions/*Input.php` | POST body         |
| Service Data   | `Domains/*/Services/*Data.php`    | Command input     |
| Service Query  | `Domains/*/Services/*Query.php`   | Read intent       |
| Service Result | `Domains/*/Services/*Result.php`  | Service output    |

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

## Command Data DTO (`*Data.php`)

Location:

```
Domains/*/Services/Commands/*Data.php
```

Purpose: represents an intent to change state.

Examples:

* Create a user
* Update an email
* Invite a member
* Delete a team

Characteristics:

* Causes side effects
* Used only by command services
* May fail due to business rules
* Usually returns a `*Result.php`

---

## Query DTO (`*Query.php`)

Location:

```
Domains/*/Services/Queries/*Query.php
```

Purpose: represents an intent to read state.

Characteristics:

* Read-only
* No side effects
* Safe to repeat
* Safe to cache

---

## Why command data and query DTOs are different

They represent fundamentally different intents.

| Aspect              | Command Data | Query      |
| ------------------- | ------------ | ---------- |
| Purpose             | Change state | Read state |
| Side effects        | Yes          | No         |
| Writes data         | Yes          | No         |
| Safe to cache       | No           | Yes        |
| Causes transactions | Often        | Never      |

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

Examples:

* Labels
* Redirect URLs
* Feature flags
* IDs needed to render data

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

### Cross-domain routes

Cross-domain components live in `App/CrossComponents`.

```
App/CrossComponents/<signature>/<Component>/
  Routes/
    web.php
    api.php
    components.php
```

Signature rules:

* Lowercase
* Alphabetical
* Joined with `+`
* Example: `teams+users`

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
App/CrossComponents/teams+users/InviteUserToTeam/
```

Component ID convention:

```
cross.teams+users.invite-user-to-team
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
