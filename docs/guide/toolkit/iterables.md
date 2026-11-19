# Iterables

Domain code often needs to work with collections of things - for example, a list of tickets held by an attendee. Using
plain PHP arrays for this is problematic. An array type-hint does not describe what the array contains, and it gives you
nowhere to put business logic that relates to the collection.

The better approach is to write iterable classes that are specific to your domain. As described in
the [Value Objects chapter](../domain/value-objects), these classes can then encapsulate business logic specific to the
collection.

This package provides interfaces and traits that remove the boilerplate of writing these iterable classes. The
interfaces are in the `CloudCreativity\Modules\Contracts\Toolkit\Iterables` namespace, and the traits that implement
them are in the `CloudCreativity\Modules\Toolkit\Iterables` namespace.

| Interface      | Trait            | Use for                                         |
|----------------|------------------|-------------------------------------------------|
| `ListIterator` | `IsList`         | A list of values, that may be empty.            |
| `NonEmptyList` | `IsNonEmptyList` | A list of values, that must never be empty.     |
| `KeyedSet`     | `IsKeyedSet`     | A set of values that are keyed by a string.     |
| `LazyList`     | `IsLazyList`     | A list of values that are loaded when iterated. |

The traits store the values in a private `$stack` property (or a `$source` closure for lazy lists). Your class is
responsible for setting this property, typically in its constructor. This means your class controls exactly what types
of values can be added to it.

## Lists

A list is a zero-indexed collection of values. Implement the `ListIterator` interface using the `IsList` trait:

```php
namespace App\Modules\EventManagement\Domain;

use CloudCreativity\Modules\Contracts\Toolkit\Iterables\ListIterator;
use CloudCreativity\Modules\Toolkit\Iterables\IsList;

/**
 * @implements ListIterator<Ticket>
 */
final class ListOfTickets implements ListIterator
{
    /** @use IsList<Ticket> */
    use IsList;

    public function __construct(Ticket ...$tickets)
    {
        $this->stack = array_values($tickets);
    }
}
```

The list can then be used as follows:

```php
$tickets = new ListOfTickets($ticket1, $ticket2);

foreach ($tickets as $ticket) {
    // ...
}

count($tickets); // 2
$tickets->all(); // [$ticket1, $ticket2]
$tickets->isEmpty(); // false
$tickets->isNotEmpty(); // true
```

:::tip
Using a variadic constructor argument (`Ticket ...$tickets`) ensures every value in the list is of the correct type. We
use `array_values()` because PHP allows variadic arguments to be passed with string keys (via named arguments or
unpacking an array with string keys), and the list must be zero-indexed.
:::

## Non-Empty Lists

A non-empty list is a list that must always contain at least one value. This is useful for enforcing an invariant - for
example, if an attendee must always have at least one ticket. Implement the `NonEmptyList` interface using the
`IsNonEmptyList` trait:

```php
namespace App\Modules\EventManagement\Domain;

use CloudCreativity\Modules\Contracts\Toolkit\Iterables\NonEmptyList;
use CloudCreativity\Modules\Toolkit\Iterables\IsNonEmptyList;

/**
 * @implements NonEmptyList<Ticket>
 */
final class NonEmptyListOfTickets implements NonEmptyList
{
    /** @use IsNonEmptyList<Ticket> */
    use IsNonEmptyList;

    public function __construct(Ticket $ticket, Ticket ...$tickets)
    {
        $this->stack = [$ticket, ...array_values($tickets)];
    }
}
```

Requiring the first value as a separate constructor argument means the list can never be constructed empty. As the list
can never be empty, the `NonEmptyList` interface does not have `isEmpty()` or `isNotEmpty()` methods.

## Keyed Sets

A keyed set is a collection of values that are keyed by a string. Implement the `KeyedSet` interface using the
`IsKeyedSet` trait. Your class is responsible for determining the key for each value:

```php
namespace App\Modules\EventManagement\Domain;

use CloudCreativity\Modules\Contracts\Toolkit\Iterables\KeyedSet;
use CloudCreativity\Modules\Toolkit\Iterables\IsKeyedSet;

/**
 * @implements KeyedSet<Ticket>
 */
final class TicketsByReference implements KeyedSet
{
    /** @use IsKeyedSet<Ticket> */
    use IsKeyedSet;

    public function __construct(Ticket ...$tickets)
    {
        foreach ($tickets as $ticket) {
            $this->stack[$ticket->reference] = $ticket;
        }
    }

    public function get(string $reference): ?Ticket
    {
        return $this->stack[$reference] ?? null;
    }
}
```

Keyed sets have the same `all()`, `count()`, `isEmpty()` and `isNotEmpty()` methods as lists. When iterating over the
set, the keys are the string keys:

```php
foreach ($tickets as $reference => $ticket) {
    // ...
}
```

:::tip
The `KeyedSetOfErrors` class described in the [Results chapter](./results#keyed-error-sets) is an example of a keyed
set.
:::

## Lazy Lists

A lazy list does not hold its values in memory. Instead, it is given a closure that returns a generator, and the values
are only loaded when the list is iterated over. This is useful for large collections - for example, when iterating over
the results of a database query.

Implement the `LazyList` interface using the `IsLazyList` trait. Set the `$source` property to a closure that returns a
generator:

```php
namespace App\Modules\EventManagement\Application\Ports\Persistence;

use Closure;
use CloudCreativity\Modules\Contracts\Toolkit\Iterables\LazyList;
use CloudCreativity\Modules\Toolkit\Iterables\IsLazyList;
use Generator;

/**
 * @implements LazyList<TicketState>
 */
final class LazyListOfTicketStates implements LazyList
{
    /** @use IsLazyList<TicketState> */
    use IsLazyList;

    /**
     * @param Closure(): Generator<TicketState> $source
     */
    public function __construct(Closure $source)
    {
        $this->source = $source;
    }
}
```

The persistence adapter in the infrastructure layer can then provide a generator that loads the values on demand. For
example, using a Laravel lazy collection:

```php
$states = new LazyListOfTicketStates(function () use ($attendeeId) {
    foreach (TicketModel::where('attendee_id', $attendeeId)->lazy() as $model) {
        yield $this->mapper->toTicketState($model);
    }
});
```

The closure is invoked each time the list is iterated over. As the values are never held in memory, a lazy list does not
implement `Countable`. If you need all the values, use the `all()` method - but be aware that this eagerly loads every
value into an array.
