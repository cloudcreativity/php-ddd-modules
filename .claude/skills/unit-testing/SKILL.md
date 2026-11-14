---
name: unit-testing
description: Use when writing or changing PHPUnit tests under tests/ — file layout, naming, attributes, mocks vs stubs vs the shipped Testing fakes, assertions, exceptions, fixtures for abstract classes, and PHPStan-compliant test types.
---

# Skill: Unit Testing

Write tests for individual classes and methods in this PHP DDD library. Unit tests should be focused on testing a
single unit of behavior in isolation, without relying on external dependencies.

## When to use

Use this skill when:

- Writing a new unit test for a class or method
- Adding test coverage to an existing class or method
- Updating unit tests after a class or method changes

The same practices apply to the `Integration` suite. Put a test in `tests/Unit` when it exercises one class with its
collaborators replaced by stubs, mocks or fakes. Put it in `tests/Integration` when it exercises several real classes
wired together, e.g. a dispatcher autowiring handlers and middleware from a PSR container and running the full
pipeline.

## File Layout

- Tests mirror `src/`: the test for `src/Bus/Foo.php` is `tests/Unit/Bus/FooTest.php`, in namespace
  `CloudCreativity\Modules\Tests\Unit\Bus`. File names must end in `Test.php`.
- Every file starts with the MIT copyright header used in existing files, then `declare(strict_types=1);`.
- Test classes are `final` and extend `PHPUnit\Framework\TestCase`.

```php
<?php

/*
 * Copyright 2026 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\Modules\Tests\Unit\Bus;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FooTest extends TestCase
{
    #[Test]
    public function it_does_something(): void
    {
        // ...
    }
}
```

## Best practices

### Test Method Naming

Name test methods in snake case, starting with `it_` and then describing the behavior under test, e.g.
`it_returns_a_failed_result_when_validation_fails()`.

Because these names don't start with `test`, PHPUnit only runs them if they have the `#[Test]` attribute. A method
without it is skipped with no error or warning, so **every `it_` method must have `#[Test]`.**

### Use PHPUnit Attributes

Always use PHPUnit attributes (`#[Test]`, `#[DataProvider]`, etc.) and never docblock annotations such as `@test` or
`@dataProvider`. Docblock metadata is deprecated in PHPUnit 11 and removed in PHPUnit 12.

Data providers must be `public static` and need a precise return type for PHPStan (level 10 also covers `tests/`):

```php
/**
 * @return array<string, array{string, bool}>
 */
public static function valueProvider(): array
{
    return [
        'empty' => ['', false],
        'non-empty' => ['foo', true],
    ];
}

#[Test]
#[DataProvider('valueProvider')]
public function it_checks_the_value(string $value, bool $expected): void
{
    // ...
}
```

### Tear Down

Whenever any properties are set on the test class in the `setUp()` method, **always** unset them in the `tearDown()`
method, then call the parent:

```php
protected function tearDown(): void
{
    unset($this->property1, $this->property2);
    parent::tearDown();
}
```

### Arrange, Act, Assert (AAA) Pattern

Follow the Arrange, Act, Assert (AAA) pattern when writing unit tests. This pattern helps to structure tests in a clear
and consistent way. For example:

```php
#[Test]
public function it_does_something(): void
{
    // Arrange
    $objectUnderTest = new MyClass();
    // Act
    $result = $objectUnderTest->someMethod();
    // Assert
    $this->assertSame('expected value', $result);
}
```

Never write a test that mixes the Arrange, Act, and Assert steps together, or does AAA more than once in a test.

Expectations are the one exception to the order: `expectException()`, `expectExceptionMessage()` and mock
`expects(...)` calls are assertions, but they must be set up *before* the act step. Put them at the end of the arrange
step, just before the act.

> The `// Arrange` etc comments are for illustrative purposes only; do not add to tests.

### Be Thorough

Ensure all public methods on the class under test are covered by unit tests. This includes edge cases and error
conditions. Aim for high code coverage, but prioritize meaningful tests that cover important behavior over achieving
100% coverage.

When a unit throws exceptions, write tests to assert that the correct exceptions are thrown under the expected
conditions. For example:

```php
#[Test]
public function it_throws_exception_when_invalid(): void
{
    $objectUnderTest = new MyClass();

    $this->expectException(ContractException::class);
    $this->expectExceptionMessage('Invalid input provided.');

    $objectUnderTest->someMethod('invalid input');
}
```

Always use `expectException()` and `expectExceptionMessage()` to assert both the correct exception class and message is
thrown. Asserting the message ensures the exception is thrown for the expected reason, rather than a different error
condition.

Precondition checks made with `CloudCreativity\Modules\Toolkit\Contracts::assert()` throw
`CloudCreativity\Modules\Toolkit\ContractException`. They are part of the class's public behavior and should be
tested. Internal invariants checked with PHP's native `assert()` throw `AssertionError`, and only because the tests run
with `zend.assertions=1`. They guard against programming errors, so don't write tests for them unless asked.

### Asserting Values

Use `assertSame()` for scalars, arrays of scalars, and when the exact same object instance is expected (e.g. a
collaborator that is passed through unchanged).

When asserting value objects and DTOs (e.g. `Result`, `Error`, identifiers), use `assertEquals()` to compare the
expected and actual objects. This compares the properties of the objects rather than their references:

```php
$expected = new MyValueObject('value');
$actual = $objectUnderTest->getValueObject();
$this->assertEquals($expected, $actual);
```

This is preferred over testing individual properties of a value object, as it ensures the entire value object is
correct and reduces the chance of missing an important property in the assertion.

Where a value object implements an `equals(self $other): bool` method (e.g. the identifiers in `Toolkit/Identifiers`),
use PHPUnit's `assertObjectEquals()` and `assertObjectNotEquals()` to compare the objects using their `equals()`
method:

```php
$this->assertObjectEquals($expected, $actual);
```

### Test Doubles

Isolate the unit under test by replacing its collaborators with test doubles. In order of preference:

1. **Real instances** of value objects, DTOs and other simple concrete classes. Never mock or stub these. Every class
   in this library is `final` (enforced by pint), so concrete classes can't be mocked anyway.
2. **The shipped fakes** in `src/Testing/` for the driven ports and PSR interfaces they implement: `FakeContainer`,
   `FakeQueue`, `FakeUnitOfWork`, `FakeDomainEventDispatcher`, `FakeOutboundEventPublisher`, `FakeExceptionReporter`
   and `FakeLogger`. They record what was done to them, so you can assert on their state after the act step.
3. **Stubs** (`createStub()`) for interfaces that only need to exist or return values, e.g. a `Command`, `Query` or
   `IntegrationEvent` message passed through the code under test.
4. **Mocks** (`createMock()`) only when the test asserts how a collaborator is called. Always configure them with
   `expects(...)`. A mock with no expectations should be a stub: PHPUnit 12 raises a notice for it, and this repo fails
   on notices.

Mock and stub the interfaces in `src/Contracts/`, not the concrete classes. Type mock properties with a native
intersection type so PHPStan knows about both APIs:

```php
private Validator&MockObject $validator;

protected function setUp(): void
{
    parent::setUp();
    $this->validator = $this->createMock(Validator::class);
}

#[Test]
public function it_validates_the_command(): void
{
    $command = $this->createStub(Command::class);

    $this->validator
        ->expects($this->once())
        ->method('validate')
        ->with($this->identicalTo($command))
        ->willReturn(new ListOfErrors());

    // act and assert
}
```

### Testing Abstract Classes

Many classes in this library (dispatchers, handler containers, middleware) are `abstract` and meant to be extended by
consumers. Test them through a subclass:

- **Anonymous class** in the test (or in `setUp()`), when the subclass is small and only used by that test class:

  ```php
  $this->middleware = new class ($this->validator) extends ValidateMessage {
      protected function rules(): iterable
      {
          return ['foo', 'bar'];
      }
  };
  ```

- **Fixture class** next to the test (e.g. `tests/Unit/Domain/TestEntity.php`), when the subclass is larger, needs PHP
  attributes such as `#[WithCommand]` or `#[Through]` (which are read by reflection), or is shared across test
  classes. Fixtures shared across suites live in the root of `tests/` (e.g. `tests/TestBackedEnum.php`). Fixture file
  names must not end in `Test.php`.

### Test Sub-Headings

When a test class is long, use sub-headings in comments to group the tests for each method of the class under test.
This makes the test class easier to read and navigate. For example:

```php
// -------------------------------------------------------------------------
// dispatch()
// -------------------------------------------------------------------------

#[Test]
public function it_dispatches_a_command(): void
```

### Arrange State Directly

Build the object under test directly in the state the test needs. Don't get it there by calling its other public
methods, because then the test also depends on those methods working, and it breaks the AAA pattern. For entities and
aggregates it also makes it harder to assert which domain events are, or are not, dispatched.

Incorrect - a state transition as part of arrange:

```php
#[Test]
public function it_cannot_be_locked_when_already_locked(): void
{
    $entity = $this->makeEntity();
    $entity->lock();

    $this->expectException(ContractException::class);
    $this->expectExceptionMessage('Entity is already locked.');

    $entity->lock();
}
```

Correct - the entity is constructed in the state ready for the test:

```php
#[Test]
public function it_cannot_be_locked_when_already_locked(): void
{
    $entity = $this->makeEntity(locked: true);

    $this->expectException(ContractException::class);
    $this->expectExceptionMessage('Entity is already locked.');

    $entity->lock();
}
```

## Before Finishing

Run these and fix any failures:

```bash
vendor/bin/phpunit tests/Unit/Path/To/FooTest.php   # the test(s) you wrote
composer static                                     # phpstan level 10, includes tests/
composer style:fix                                  # pint
```
