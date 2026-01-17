```md
# Lilly Framework – Architecture & Conventions

Lilly is an **opinionated PHP meta-framework** focused on **architectural uniformity**, **strict boundaries**, and **mechanical enforcement** of patterns.

It is not a library of helpers.
It is a framework that **forces structure**.

The goal is simple:

* every application looks the same
* every feature follows the same flow
* only business logic differs

This is enforced through:
* scaffolding
* conventions
* runtime checks
* zero “magic”

---

## Core principles

* Structure beats flexibility
* Read and write paths are separated
* UI never leaks into the domain
* Domains own rules and permissions
* Authorization is mandatory and automatic
* Escape hatches are explicit

---

## High-level structure

```

src/
Lilly/              # Framework core
Domains/            # Business domains (Users, Teams, Billing, ...)
UI/                 # Non-domain UI components
App/                # Application-specific glue (cross-components)

```

---

## Domains (bounded contexts)

Each domain is a **fully isolated bounded context**.

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
Queries/
Commands/
Routes/
Components/
Tests/

```

### Domain rules (strict)

* A domain owns:
  * its data
  * its rules
  * its permissions
* Domain code:
  * MUST NOT depend on UI
  * MUST NOT depend on other domains
* Authorization:
  * is enforced automatically
  * cannot be skipped
* Repositories:
  * are the only persistence boundary
* Services:
  * are the only place where workflows live

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
* Domain state
* Small helper behavior only
* No workflows
* No persistence logic

**UserFields.php**
* Field definitions
* Types, defaults, constraints
* Used for migrations and validation

**UserRelations.php**
* Domain relationships
* No queries or side effects

---

## Repositories (persistence boundary)

Repositories are split to **enforce read/write discipline**.

```

Domains/Users/Repositories/
Queries/
UserQueryRepository.php
Commands/
UserCommandRepository.php

```

### Repository rules

**Query repositories**
* SELECT only
* No mutations
* No side effects

**Command repositories**
* INSERT / UPDATE / DELETE only
* No arbitrary reads

**All repositories**
* Hide SQL / ORM details
* Return models or DTOs
* Contain zero business logic

Services must never:
* write SQL
* use query builders directly
* access the database

---

## Services (use cases)

```

Domains/Users/Services/
Queries/
GetUserProfileService.php
GetUserProfileQuery.php
GetUserProfileResult.php

Commands/
CreateUserService.php
CreateUserData.php
CreateUserResult.php

```

### Service rules

* One service = one use case
* Services:
  * accept DTOs
  * orchestrate workflows
  * call repositories
* Services do NOT:
  * depend on HTTP
  * depend on UI
  * perform authorization manually
  * return domain models

Authorization is enforced **before** services run.

---

## DTOs (mandatory)

DTOs exist to **cut dependencies** and **clarify intent**.

### DTO types

| Type           | Location                                  | Purpose |
|----------------|-------------------------------------------|---------|
| Props          | `Components/*/Props.php`                  | GET configuration |
| Action Input   | `Components/*/Actions/*Input.php`         | POST input |
| Service Data   | `Domains/*/Services/*Data.php`            | Command intent |
| Service Query  | `Domains/*/Services/*Query.php`           | Read intent |
| Service Result | `Domains/*/Services/*Result.php`          | Output |

### Global DTO rules

* `readonly`
* immutable
* typed
* validation lives inside the DTO
* no framework access
* no database access

---

## Command Data DTO (`*Data.php`)

Represents an **intent to change state**.

Examples:
* create user
* update email
* invite member

Characteristics:
* causes side effects
* used only by command services
* may fail due to business rules
* usually returns a `*Result.php`

---

## Query DTO (`*Query.php`)

Represents an **intent to read state**.

Characteristics:
* read-only
* no side effects
* safe to cache
* safe to repeat

---

## Why commands and queries are separate

They represent **different guarantees**.

| Aspect       | Command | Query |
|-------------|---------|-------|
| Mutates     | Yes     | No    |
| Cacheable   | No      | Yes   |
| Side effects| Yes     | No    |
| Transactions| Often   | Never |

This enables:
* read-only DB connections
* static enforcement
* safer caching
* simpler mental models

---

## Action Input DTO (`*Input.php`)

Location:
```

Components/*/Actions/*Input.php

```

Purpose:
* represent raw HTTP input
* validate shape
* normalize values

Includes:
* required fields
* formats
* basic constraints

Excludes:
* uniqueness checks
* permission checks
* cross-entity rules

Action inputs protect the domain from the UI.

---

## Props DTO (`Props.php`)

Purpose:
* component configuration
* not user input

Examples:
* labels
* IDs
* flags
* redirect targets

Props are **not interaction**.
They are **setup**.

---

## Request lifecycle

### POST (mutation)

```

HTTP request
↓
Action Input DTO
↓
Service Command Data
↓
Command Service
↓
Repositories

```

### GET (read)

```

HTTP request
↓
Props DTO
↓
Query DTO
↓
Query Service
↓
Repositories

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

Each file:
* must return a callable
* receives a `DomainRouter`
* automatically enforces domain policies

---

### Cross-domain routes

Cross-domain components live in:

```

src/App/CrossComponents/
teams+users/
InviteUserToTeam/

```

Rules:
* folder name is the domain signature
* lowercase
* alphabetical
* joined with `+`

Routes use `CrossDomainRouter` and enforce **all involved domain policies**.

---

## Components (islands)

### Single-domain component

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
* touches exactly one domain
* domain inferred from folder
* policy enforced automatically

Component ID:
```

users.add-user-button

```

---

### Cross-domain component

```

App/CrossComponents/teams+users/InviteUserToTeam/

```

Component ID:
```

cross.teams+users.invite-user-to-team

```

---

## Component parts

**Component.php**
* entry point
* wiring only
* no business logic

**Actions/**
* one class per POST action

**View/**
* server-rendered HTML
* no services
* no side effects

**Assets/**
* optional hydration
* scoped
* no globals

---

## Security model

### Domain policies

Each domain must define:

```

Domains/<Domain>/Policies/<Domain>Policy.php

```

Rules:
* mandatory
* auto-discovered
* enforced on every request

If missing, the application fails at runtime.

---

### Gates

```

Domains/<Domain>/Policies/Gates/*.php

```

* fine-grained permissions
* referenced by name on routes
* enforced after domain policies

---

## Testing rules

* Every repository has tests
* Every command service has tests
* Every component has:
  * render test
  * action test
* No duplicated business logic tests

---

## CLI tooling

Available commands:

```

shape:domain:make <Domain>
shape:domain:remove <Domain>

shape:cross:make <Component> <DomainA> <DomainB> [...]
shape:cross:remove <Component>

```

Scaffolding is the only supported way to create structure.

---

## Philosophy

Lilly is intentionally strict.

You trade:
* flexibility
for
* consistency
* predictability
* safer refactors
* faster onboarding

If something feels rigid, that is by design.

Only the business logic should differ.
```

---
