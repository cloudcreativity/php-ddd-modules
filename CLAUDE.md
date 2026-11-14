# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`cloudcreativity/ddd-modules` is a PHP library (PHP ^8.4) providing tooling for writing encapsulated, loosely coupled
DDD bounded contexts ("modules"). It is a library, not an app — there is nothing to run beyond the test/analysis
tooling. User-facing documentation lives in `docs/` (VitePress) and is published to
https://cloudcreativity.github.io/php-ddd-modules.

## Commands

```bash
composer all            # deptrac + phpstan + pint --test + phpunit (same checks CI runs)
composer deptrac        # architectural layer analysis (fails on uncovered dependencies)
composer static         # phpstan, level 10, over src/ and tests/
composer style          # pint --test (check only)
composer style:fix      # pint (apply fixes)
composer test           # phpunit (Unit + Integration suites)

vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit tests/Unit/Bus/PsrPipeContainerTest.php
vendor/bin/phpunit --filter testMethodName

npm run docs:dev        # local VitePress docs server
```

CI runs on PHP 8.4 and 8.5 with `zend.assertions=1` — the code relies on `assert()` for internal invariants, so
assertions are expected to be active in tests. PHPUnit fails on warnings, notices and deprecations.

## Architecture

Namespace root is `CloudCreativity\Modules\` → `src/`. Code is split into layers that mirror the DDD layers a consuming
module is expected to have:

- `Contracts/<Layer>/` — interfaces for each layer. Concrete classes in `src/<Layer>/` implement these, usually
  imported with an `I` alias (e.g. `use ...\Contracts\Bus\CommandHandlerContainer as ICommandHandlerContainer;`).
- `Toolkit/` — layer-agnostic building blocks: `Result`, `Pipeline`, `Iterables`, `Identifiers` (UUID/int/string ids),
  `Contracts` assertions, `Sensitive`, plus global helpers in `Toolkit/functions.php` (autoloaded `files`).
- `Domain/` — entity traits (`IsEntity`, `IsEntityWithNullableId`, `IsEntityWithTypedId`); domain event contracts.
- `Messaging` (contracts only) — `Command`, `Query`, `IntegrationEvent` and their dispatcher interfaces.
- `Bus/` — abstract `CommandDispatcher`, `QueryDispatcher`, `InboundEventDispatcher`, handler containers, bus middleware
  and validation.
- `Application/` — domain event dispatching (deferred / unit-of-work-aware), unit of work manager, application-level
  bus middleware, and driven ports (`Contracts/Application/Ports`: `UnitOfWork`, `Queue`, `OutboundEventPublisher`,
  `ExceptionReporter`).
- `Infrastructure/` — adapters for those ports: queue, outbound event bus, exception reporter, Monolog integration.
- `Testing/` — `Fake*` test doubles shipped for consumers to use in their own tests.

**Layer dependencies are enforced by `deptrac.yaml`.** Domain and Messaging may only depend on Toolkit; Bus on
Messaging/Toolkit/PSR; Application on Bus/Domain/Messaging/Toolkit/PSR; Infrastructure on Application/Bus/Messaging/
Toolkit/PSR. Toolkit depends on nothing (except `ramsey/uuid`). Every class must be covered by a layer — adding a new
top-level namespace or third-party dependency requires updating `deptrac.yaml`.

### Dispatcher wiring pattern

Dispatchers (bus, queue, outbound event bus, domain event dispatcher) are `abstract` classes that consumers extend.
They accept either a PSR `ContainerInterface` or an explicit handler container. When given a PSR container they
autowire from PHP attributes declared on the subclass via reflection — e.g. `#[WithCommand(Cmd::class, Handler::class)]`,
`#[WithQuery]`, `#[WithEvent]`, `#[WithDefault]`, `#[ListenTo]`, and `#[Through([...middleware])]` for pipeline
middleware. Execution goes through `Toolkit\Pipeline` (`PipelineBuilder` + `MiddlewareProcessor`): global pipes first,
then the handler's own middleware, then the handler. Commands and queries return a `Toolkit\Result\Result`.

## Conventions

- Every class is either `final` or `abstract` (enforced by pint's `final_class`); `declare(strict_types=1)` everywhere.
  New source files carry the MIT copyright header used in existing files.
- PHPStan runs at level 10 including tests — use precise generics/phpdoc types (`class-string<T>`, `non-empty-string`,
  `Result<T>`, etc.).
- Tests mirror `src/` under `tests/Unit` and `tests/Integration`; file names end in `Test.php`.
- Record user-facing changes under `## Unreleased` in `CHANGELOG.md` (keepachangelog format; mark breaking changes
  with **BREAKING**). Deprecations are kept for BC and removed in the next major — update `docs/guide/upgrade.md` for
  breaking changes and the relevant `docs/guide/**` page for new features.

## Git Workflow

This repo uses git-flow. `develop` is the 6.x line and the default target for branches and PRs. `next` is the 7.x line
(breaking changes go here). `main` holds tagged releases, and the docs deploy from `main`. Never branch from or target
`main` directly: releases are cut by maintainers through `release/x.y.z` branches.

Branch prefixes: `feature/`, `fix/`, `refactor/`, `build/`, `wip/` (never merged).

Commit messages must follow [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/). Allowed types:
`feat`, `fix`, `refactor`, `build`, `ci`, `docs`, `style`, `perf`, `test`, `wip`. Use an optional scope (e.g.
`build(deps): ...`) and `!` for breaking changes (e.g. `feat!: ...`).

PRs must be rebased onto the target branch (linear history, no merge commits from feature branches) and pass all CI
checks (`composer all`).
