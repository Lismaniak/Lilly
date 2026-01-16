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

  Domain/             # Business domains (Users, Teams, Billing, etc.)
  UI/                 # Cross-domain and shared UI components
```

---

## Domain structure

Each domain is a **bounded context** and contains everything related to that domain.

```
Domain/<DomainName>/
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
Domain/Users/Models/
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

```
Domain/Users/Repositories/
  UserRepository.php
```

Repositories define **how domain data is persisted and queried**.

### Repository rules

* Repositories encapsulate **all persistence logic**
* Services MUST NOT:

  * write SQL
  * use query builders directly
  * talk to the database
* Repositories:

  * return domain models or DTOs
  * expose domain-specific queries
  * hide storage details (SQL, ORM, etc.)

### Example responsibilities

* `findById(UserId $id)`
* `findByEmail(Email $email)`
* `save(User $user)`
* `delete(User $user)`

Repositories are **explicit and predictable**, enabling:

* consistent data access
* easier testing
* safer refactors
* future persistence changes without touching services

---

## Migrations

```
Domain/Users/Migrations/
  2026_01_16_000001_create_users.php
```

* Domain-scoped migrations
* Generated from model field definitions
* Loaded automatically by the framework

---

## Policies & Gates (Domain middleware)

```
Domain/Users/Policies/
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
Domain/Users/Validators/
  CreateUserValidator.php
```

* Optional domain-level validation helpers
* Used by services for business rule validation
* Not tied to HTTP or UI

---

## Services (Use cases)

```
Domain/Users/Services/
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

### DTO conventions (mandatory)

* `*Data.php` → service command input DTO
* `*Query.php` → service query input DTO
* `*Result.php` → service output DTO

DTOs:

* immutable
* typed
* no business logic
* no framework dependencies

---

## Domain Routes

```
Domain/Users/Routes/
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
Domain/Users/Components/AddUserButton/
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
| Service Data   | `Domain/*/Services/*Data.php`     | Service input     |
| Service Result | `Domain/*/Services/*Result.php`   | Service output    |

### DTO rules

* `readonly`
* immutable
* typed
* validation rules live **inside the DTO**
* no framework or DB access

---

## Testing rules

* Every **repository** has tests
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
