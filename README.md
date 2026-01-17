# Lilly Framework – Architecture & Conventions

This document describes the **enforced folder structure**, **component model**, **repository usage**, and **DTO conventions** used in Lilly.

The goal of Lilly is **architectural uniformity**:

* every application looks the same
* every feature is implemented in the same pattern
* only business logic differs

This is enforced by **scaffolding**, **strict conventions**, and **structural rules**.

---

## High-level structure

```
src/
  Lilly/              # Framework core (empty for now)
  Domains/             # Business domains (Users, Teams, Billing, etc.)
  UI/                 # Cross-domain and shared UI components
```

---

## Domain structure

Each domain is a **bounded context** and contains everything related to that domain.

```
Domains/<DomainName>/
  Models/
  Repositories/
  Migrations/
  Policies/
  Validators/
  Services/
  Routes/
  Components/
  Tests/
```

### Domain rules

* A domain **owns its data, rules, and permissions**
* Domain code may NOT depend on UI
* UI components may depend on domain services
* Services may depend on repositories
* Domain policies are always enforced before domain code executes
* Services are the **only place** where repositories are used

---

## Models

```
Domains/Users/Models/
  User.php
  UserFields.php
  UserRelations.php
```

* `User.php`

  * Domain model
  * Represents business state
  * No workflows
  * No persistence logic
  * Small helper behavior only

* `UserFields.php`

  * Field definitions
  * Types, defaults, constraints
  * Used for migrations and validation

* `UserRelations.php`

  * Domain relations (hasMany, belongsTo, etc.)

---

## Repositories (Persistence boundary)

Repositories are split to **force architectural discipline** between reads and writes.

```
Domains/Users/Repositories/
  Queries/
    UserQueryRepository.php
  Commands/
    UserCommandRepository.php
```

### Repository rules (strict)

* `*QueryRepository`

  * may only execute **SELECT / read operations**
  * must never modify state

* `*CommandRepository`

  * may only execute **INSERT / UPDATE / DELETE**
  * must never return arbitrary query results

* Repositories:

  * encapsulate **all persistence logic**
  * hide SQL / ORM / storage details
  * return domain models or DTOs
  * contain **no business logic**

* Services MUST NOT:

  * write SQL
  * use query builders directly
  * talk to the database

---

## Service → Repository usage rules (enforced)

```
Services/Queries   → QueryRepositories ONLY
Services/Commands  → CommandRepositories (and QueryRepositories if needed)
```

* Query services:

  * read-only
  * may not cause side effects
  * may only depend on `*QueryRepository`

* Command services:

  * perform mutations
  * may depend on:

    * `*QueryRepository` (for checks, lookups)
    * `*CommandRepository` (for writes)

This split enables:

* mechanical enforcement
* static analysis
* optional read-only DB connections
* consistent mental model

---

## Migrations

```
Domains/Users/Migrations/
  2026_01_16_000001_create_users.php
```

* Domain-scoped migrations
* Generated from model field definitions
* Loaded automatically by the framework

---

## Policies & Gates (Domain middleware)

```
Domains/Users/Policies/
  UsersPolicy.php
  Gates/
    CanCreateUser.php
    CanInviteUser.php
```

* Policies act as **mandatory middleware**
* No domain code executes unless its policy passes
* Gates are fine-grained permission checks
* Enforced on:

  * component GET
  * component POST
  * service execution

---

## Validators

```
Domains/Users/Validators/
  CreateUserValidator.php
```

* Optional domain-level validation helpers
* Used by services for business rule validation
* Not tied to HTTP or UI

---

## Services (Use cases)

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

* One service = one use case
* Services contain business workflows
* Services:

  * accept DTOs
  * call repositories
  * enforce business rules
* Services do NOT:

  * return models directly
  * depend on UI or HTTP
  * perform authorization checks manually (policies handle this)
Below is an **additional section** you can paste into the README.
It fits logically **after the “DTO conventions (mandatory)” section**, because it explains *why* the DTO types exist.

---

## Why different DTO types exist

Lilly deliberately distinguishes between **Action Input DTOs**, **Service Command Data DTOs**, **Service Query DTOs**, and **Props DTOs**.
This separation is intentional and enforces architectural boundaries.

The difference is **intent**, not syntax.

---

## Command Data DTO (`*Data.php`)

Location:

```
Domains/*/Services/Commands/*Data.php
```

### Purpose

Represents an **intent to change state**.

> “I want to do something.”

Examples:

* create a user
* update an email
* invite a member
* delete a team

### Characteristics

* Causes side effects
* Used only by **command services**
* May fail due to business rules
* Always paired with a command service
* Usually returns a `*Result.php`

### Rules

* Immutable
* Typed
* No validation of business rules
* No persistence logic
* No framework or UI dependencies

---

## Query DTO (`*Query.php`)

Location:

```
Domains/*/Services/Queries/*Query.php
```

### Purpose

Represents an **intent to read state**.

> “I want to see something.”

Examples:

* get user profile
* list users
* find team members

### Characteristics

* Must be **read-only**
* Must never cause side effects
* Safe to repeat
* Safe to cache
* Used only by **query services**

### Rules

* Immutable
* Typed
* No persistence logic
* No business rules
* No framework or UI dependencies

---

## Why Command Data and Query DTOs are different

They represent **fundamentally different intents**.

| Aspect                 | Command Data | Query      |
| ---------------------- | ------------ | ---------- |
| Purpose                | Change state | Read state |
| Side effects           | Yes          | No         |
| Writes data            | Yes          | No         |
| Safe to cache          | No           | Yes        |
| Requires authorization | Yes          | Yes        |
| Causes transactions    | Often        | Never      |

Keeping them separate allows:

* mechanical enforcement of read vs write
* optional read-only DB connections
* safer caching strategies
* clearer mental models
* prevention of accidental mutations in queries

---

## Action Input DTO (`*Input.php`)

Location:

```
Components/*/Actions/*Input.php
```

### Purpose

Represents **raw input coming from HTTP**.

> “This is what the user submitted.”

### Responsibilities

* Parse HTTP request data
* Validate **shape**
* Normalize values (trim, lowercase, cast types)

### Characteristics

* Tied to transport (HTTP)
* Tied to UI
* Not reusable across domains
* Not reusable across services

### Validation here includes

* required fields
* string length
* format (email, uuid, etc.)

### Validation here does NOT include

* uniqueness checks
* permission checks
* cross-entity business rules

Action Input DTOs exist to **protect the domain from the UI**.

---

## Props DTO (`Props.php`)

Location:

```
Components/*/Props.php
```

### Purpose

Represents **component configuration**, not user input.

> “How should this component render or behave?”

### Examples

* button label
* redirect URL
* feature flags
* IDs needed to render data

### Characteristics

* Used only for GET rendering
* Immutable
* No side effects
* No persistence
* No business rules

Props are **not** user interaction.
They are **component setup**.

---

## Full lifecycle overview

### POST (mutation)

```
HTTP Request
  ↓
Action Input DTO        (transport shape)
  ↓
Service Command Data    (business intent)
  ↓
Command Service
  ↓
Repositories
```

### GET (read)

```
HTTP Request
  ↓
Props DTO               (component configuration)
  ↓
Query DTO               (read intent)
  ↓
Query Service
  ↓
Repositories
```

Each DTO exists to **cut a dependency**:

* UI never leaks into domain
* transport never leaks into services
* read logic never leaks into write logic

---

## Domain Routes

```
Domains/Users/Routes/
  web.php
  api.php
  components.php
```

* Optional domain-specific routes
* Component routes are handled by the framework
* These files are auto-loaded per domain

---

## Domain Components (Single-domain islands)

```
Domains/Users/Components/AddUserButton/
  Component.php
  Props.php

  Actions/
    CreateUser.php
    CreateUserInput.php

  View/
    view.php
    fragments/

  Assets/
    component.ts
    component.css

  Tests/
    RenderTest.php
    CreateUserActionTest.php
```

### Domain component rules

* Must touch **exactly one domain**
* Domain is inferred from folder location
* Domain policy is enforced automatically
* Component ID is derived from path:

  * `users.add-user-button`

---

## Component parts explained

### Component.php

* Entry point
* Declares hydrate mode
* Maps actions
* Calls domain services
* No business logic

### Props.php (DTO)

* Typed configuration for GET render
* Validation rules live **inside the class**
* Immutable

### Actions/*

* One file per POST action

#### CreateUserInput.php (DTO)

* Typed POST body
* Validation rules **inside the class**
* Shape validation only (no DB checks)

#### CreateUser.php

* Orchestrates action
* Calls domain services
* Maps results to UI responses

### View/

* Server-rendered HTML
* No services
* No validation
* No side effects

### Assets/

* Optional hydration
* Scoped to the island root
* No global selectors

### Tests/

* Integration level
* Rendering works
* Actions return correct responses
* Policies enforced

---

## UI folder (Non-domain components)

```
UI/
  SharedComponents/
  CrossComponents/
```

---

## SharedComponents (0 domains)

```
UI/SharedComponents/ConfirmDialog/
```

* Touches no domain
* Pure UI behavior
* No domain policies

---

## CrossComponents (2+ domains)

```
UI/CrossComponents/teams+users/InviteUserToTeam/
```

### CrossComponent rules

* Folder name is **domain signature**

  * alphabetical
  * lowercase
  * joined with `+`
* Component must touch **exactly those domains**
* Component ID example:

  * `cross.teams+users.invite-user-to-team`

---

## DTO rules (global)

DTOs are used everywhere for consistency.

### Types of DTOs

| Type           | Location                          | Purpose           |
| -------------- | --------------------------------- | ----------------- |
| Props          | `Components/*/Props.php`          | GET configuration |
| Action Input   | `Components/*/Actions/*Input.php` | POST body         |
| Service Data   | `Domains/*/Services/*Data.php`     | Service input     |
| Service Result | `Domains/*/Services/*Result.php`   | Service output    |

### DTO rules

* `readonly`
* immutable
* typed
* validation rules live **inside the DTO**
* no framework or DB access

---

## Testing rules

* Every **query repository** has tests
* Every **command repository** has tests
* Every **command service** has a test
* Every **component** has:

  * render test
  * action test (if it has actions)
* No duplicated business logic tests in components

---

## Core philosophy

Lilly is **opinionated by design**.

* Structure is enforced
* Patterns are mandatory
* Escape hatches are explicit
* Consistency beats flexibility

The result:

* predictable codebases
* fast onboarding
* fewer architectural debates
* safer refactors

Only the business logic should differ.
