# Units of Work

Domain driven design emphasises the importance of the domain model and the business logic that is encapsulated within,
over infrastructure concerns. As your domain model grows in complexity, one of the challenges you will face is ensuring
that all operations are executed in a consistent and reliable manner.

Commands that mutate the state of your bounded context must be _atomic_ - they must either succeed or fail as a whole.
This is where the concept of a _unit of work_ comes in.

A unit of work is a design pattern that defines the boundary when a transaction starts and ends. It ensures that
all operations within that transaction succeed or fail together. This helps achieve data consistency, particularly when
your domain model has no concept of how it is persisted.

## Scenario

Consider the following scenario. Our domain models the attendees at an in-person event as having one-to-many tickets.
When we want to cancel a ticket, we do this via the attendee aggregate root that contains the ticket entities. This is
because our domain logic says that state of the attendee may change when the ticket is cancelled.

Our command handler might look like this:

```php
namespace App\Modules\EventManagement\Application\UseCases;

use App\Modules\EventManagement\Api\Input\CancelAttendeeTicketCommand;
use App\Modules\EventManagement\Application\Ports\Persistence\AttendeeRepository;
use CloudCreativity\Modules\Toolkit\Results\Result;

final readonly class CancelAttendeeTicketHandler
{
    public function __construct(
        private AttendeeRepository $attendees,
    ) {
    }

    public function handle(CancelAttendeeTicketCommand $command): Result
    {
        $attendee = $this->attendees->findOrFail($command->attendeeId);

        // dispatches an "attendee ticket cancelled" domain event
        // which may cause other parts of the domain to react
        $attendee->cancelTicket(
            $command->ticketId,
            $command->reason,
        );

        // must persist the aggregate root and entity changes
        // but we have no knowledge of how this is done
        $this->attendees->update($attendee);

        return Result::ok();
    }
}
```

### Problem

This implementation poses a number of issues:

1. When the business action - _cancel ticket_ - is performed, the aggregate root and the relevant ticket entity is
   updated to the new state (as defined by the business logic).
2. As its state is settled, the aggregate root emits a domain event so that other parts of the domain can react to
   the change.
3. The domain event is correctly dispatched when the aggregate root is in a new consistent state. However, at this point
   the changes have not been persisted. This means side-effects - including other changes in our infrastructure layer -
   will occur _before_ the ticket cancellation is actually persisted.
4. As the persistence layer is correctly abstracted behind a driven port the command handler has no knowledge
   of how the changes are persisted. Will the repository do a single write of the new data, or perform multiple
   writes to persist the updated state?
5. If the persistence fails, the side-effects of the domain event will have already occurred. This could lead to
   inconsistencies in the domain. For example, if we publish an integration event as a side-effect of the domain event,
   that integration event would be published even though the persistence failed.

So is the handler implemented correctly? Should we move the domain event dispatching out of the aggregate root
so that we can modify the aggregate root, persist the changes, then dispatch the event?

The answer is that the handler is correctly implemented. As we are modelling the business domain, it is correct that the
aggregate root handles both the state mutation and emits the domain event. This makes the aggregate root descriptive
of the business logic and emitting the domain event is part of this logic.

Ultimately the problem we're facing is that the domain is correctly modelled, but the sequencing of operations is
not correct _from an application perspective_.

### Solution

As we prioritise the domain model over application concerns, we will leave the domain model as-is. Instead, we need
to introduce an application concern that can orchestrate the sequence of operations in the correct order from its
perspective.

This is where the unit of work comes in. By declaring a start and end to a transaction, operations can be atomic
and sequenced in the correct order.

Given the above example, the correct order from the application perspective is:

1. Unit of work begins by starting a transaction.
2. The aggregate root state change occurs and the domain event is emitted by the aggregate.
3. The domain event dispatching is deferred until later in the unit of work. This means side effects via event listeners
   will be triggered later.
4. The aggregate root is persisted via the repository port. Internally within the adapter this may result in multiple
   database writes, that are within the transaction.
5. With the persistence changes successful made (but not yet committed), the deferred domain event is dispatched.
6. Side effects of the domain event are triggered, within the transaction boundary. Any side effects that affect the
   persistence layer via driven ports now occur _after_ the aggregate changes were persisted.
7. The transaction is committed by closing the unit of work. The state changes to the aggregate root and the side
   effects are persisted together. They are atomic.

All this is achieved by this package by combining the following:

- **Unit of work** - begins, commits and rolls back a transaction. This is a driven port in the application layer, with
  the adapter implemented by the infrastructure layer. This allows you to implement a transaction for whatever database
  solution you are using.
- **Unit of work manager** - an application concern that handles the lifecycle of the unit of work, e.g. deferring
  operations for later execution.
- **Unit of work aware domain event dispatcher** - coordinates domain event dispatching with the unit of work manager.
- **Unit of work middleware** - that ensure command handlers and integration event consumers are executed in a unit of
  work.

:::info
The manager is high-level abstraction (provided by this package and part of the application layer) that manages the
lifecycle of the unit of work. The unit of work itself is a low-level abstraction (part of the infrastructure layer)
that actually starts, commits and rolls back the transaction.
:::

Our previous example can be updated to add a unit of work that wraps the command handler execution via middleware:

```php
namespace App\Modules\EventManagement\Application\UseCases;

use App\Modules\EventManagement\Api\Input\CancelAttendeeTicketCommand;
use App\Modules\EventManagement\Application\Ports\Persistence\AttendeeRepository;
use CloudCreativity\Modules\Application\Bus\Middleware\ExecuteInUnitOfWork;
use CloudCreativity\Modules\Toolkit\Results\Result;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;

#[Through(ExecuteInUnitOfWork::class)]
final readonly class CancelAttendeeTicketHandler
{
    public function __construct(
        private AttendeeRepository $attendees,
    ) {
    }

    public function handle(CancelAttendeeTicketCommand $command): Result
    {
        $attendee = $this->attendees->findOrFail($command->attendeeId);

        $attendee->cancelTicket(
            $command->ticketId,
            $command->reason,
        );

        $this->attendees->update($attendee);

        return Result::ok();
    }
}
```

## Unit of Work

To implement a unit of work, you need an adapter in your infrastructure layer that implements our `UnitOfWork`
interface.

```php
namespace CloudCreativity\Modules\Contracts\Application\Ports;

use Closure;

interface UnitOfWork
{
    /**
     * Is a unit of work currently active?
     *
     * Returns true when there is an open transaction that work will be committed or rolled back with, e.g. while a
     * callback passed to `execute()` is running. Implementations MAY also return true for a transaction that was not
     * started by this unit of work, if they cannot distinguish it.
     */
    public function active(): bool;

    /**
     * Is there no unit of work currently active?
     *
     * Returns the inverse of `active()`.
     */
    public function inactive(): bool;

    /**
     * Execute the callback in a transaction.
     *
     * @template TReturn
     * @param Closure(): TReturn $callback
     * @param int<1, max> $attempts
     * @return TReturn
     */
    public function execute(Closure $callback, int $attempts = 1): mixed;
}
```

This allows you to plug our unit of work manager into any database solution you are using. For example, an adapter
implementation for Laravel could look like this:

```php
namespace App\Modules\Infrastructure;

use Closure;
use CloudCreativity\Modules\Contracts\Application\Ports\UnitOfWork;
use Illuminate\Database\ConnectionInterface;

final readonly class IlluminateUnitOfWork implements UnitOfWork
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function active(): bool
    {
        return $this->db->transactionLevel() > 0;
    }

    public function inactive(): bool
    {
        return !$this->active();
    }

    public function execute(Closure $callback, int $attempts = 1): mixed
    {
        return $this->db->transaction(
            static fn () => $callback(),
            $attempts,
        );
    }
}
```

## Unit of Work Manager

The unit of work manager is an application layer concern. It handles the complexities of the unit of work lifecycle.
When combined with the unit of work aware domain event dispatcher, it ensures domain event dispatching and side
effects (via listeners) are coordinated within the unit of work.

The adapter just requires your concrete unit of work implementation:

```php
use CloudCreativity\Modules\Application\UnitOfWork\UnitOfWorkManager;
use CloudCreativity\Modules\Contracts\Application\Ports\UnitOfWork;
use CloudCreativity\Modules\Contracts\Application\Ports\ExceptionReporter;

$manager = new UnitOfWorkManager(
    db: $container->get(UnitOfWork::class),
    reporter: $container->get(ExceptionReporter::class),
);
```

:::info
The second constructor argument of the unit of work manager is
an [exception reporter.](../infrastructure/exception-reporting) This is useful if the unit of work manager is handling
multiple commit attempts. It allows it to report any exceptions that occur prior to a re-attempt.

Only failures that occur before the unit of work commits are re-attempted. Once the unit of work has committed, an
exception thrown by an after commit callback is not re-attempted, as this would execute the committed work again.
:::

### Singleton Instance

One really important point to note is that you **must** use a singleton instance of the unit of work manager for the
duration of the unit of work.

The same instance must be injected both into the domain event dispatcher, plus the middleware that wraps command
handlers and integration event consumers.

:::tip
Middleware is documented in the relevant chapters for [commands](../application/commands#middleware)
and [inbound integration events.](../application/integration-events#inbound-middleware)
:::

### Deferring Work

Use our unit of work aware domain event dispatcher to defer domain events to before the unit of work is committed.
Coordination of when domain event listeners are triggered can be controlled via interfaces on the event listeners - as
described later in this chapter.

If you have other use-cases for deferring work, use the following method on the manager:

```php
$manager->beforeCommit(function (): void {
    // some deferred work.
});
```

It is also possible to defer work to after the transaction is committed:

```php
$manager->afterCommit(function (): void {
    // some deferred work.
});
```

As the transaction has already been committed, a failing after commit callback does not prevent the remaining after
commit callbacks from being executed. Once they have all been executed, if exactly one of them failed, its exception is
thrown. If more than one failed, an `AfterCommitCallbacksFailed` exception is thrown instead. Its `exceptions()` method
returns all the exceptions in the order they were thrown, and its previous exception is the first one.

#### After Commit Queue

After commit callbacks are executed as a FIFO (first in, first out) queue. If an after commit callback registers
another after commit callback, it is added to the end of the queue, rather than being executed immediately.

An after commit callback can also start a new unit of work, as the previous unit of work has already committed. For
example, it might dispatch a command whose handler executes in a unit of work. The new unit of work executes its before
commit callbacks as normal. However, its after commit callbacks are added to the end of the same queue. So they are
executed after the callback that started the new unit of work has returned.

```php
$manager->execute(function () use ($manager): void {
    // executed first
    $manager->afterCommit(function () use ($manager): void {
        // starts and commits a new unit of work...
        $manager->execute(function () use ($manager): void {
            // ...whose after commit callback is added to the end of the queue: executed third.
            $manager->afterCommit(function (): void {
                // some deferred work.
            });
        });
    });

    // executed second
    $manager->afterCommit(function (): void {
        // some deferred work.
    });
});
```

As the new unit of work's after commit callbacks are queued, any failure in them is thrown by the outermost call to
`execute()` along with any other after commit failures. It is not thrown by the call that started the new unit of work.

:::warning
While the queue is FIFO, your after commit callbacks should **never** rely on the order they are executed in, or on
any knowledge of other after commit callbacks. Each callback should be an independent side effect of the committed
work. If one piece of work depends on another, it should be coordinated explicitly - for example, by having one piece
of work trigger the next, rather than relying on the order of the queue.
:::

Starting a unit of work while another is still executing (i.e. before it has committed) is not allowed, and will throw
an exception. Likewise, an after commit callback cannot register a before commit callback, as the unit of work has
already committed - unless it does so within a new unit of work.

## Deferring Domain Events

When using the unit of work pattern, you **must** use the unit of work aware domain event dispatcher. This coordinates
the dispatching of domain events with the unit of work manager.

The unit of work manager is injected into the domain event dispatcher via its constructor. This is covered in
the [domain event dispatching chapter](../application/domain-events), which also describes how to
configure listeners.

For the purposes of this chapter, we'll focus on how the domain event dispatcher works with the unit of work manager.

### Event Dispatch

When the dispatcher is asked to dispatch a domain event (i.e. from an aggregate root or entity), it will defer the event
dispatching to before the unit of work commits. This means all listeners to that domain event are also deferred.

For clarity, this means that **by default domain events and listeners occur before the unit of work commits**, i.e. any
side-effects will occur _within_ the unit of work's transaction boundary.

### Immediate Dispatch

Sometimes you may have side effects for domain events that need to occur immediately rather than being deferred. When
using a unit of work pattern this should not be your default approach - but we recognise that sometimes it is
unavoidable.

To trigger those side effects immediately, you need to indicate that the domain event should not be deferred when it is
emitted. Implement the `OccursImmediately` interface on the domain event:

```php
namespace App\Modules\EventManagement\Domain\Events;

use CloudCreativity\Modules\Contracts\Domain\Events\DomainEvent;
use CloudCreativity\Modules\Contracts\Domain\Events\OccursImmediately;

final readonly class AttendeeTicketCancelled implements
    DomainEvent,
    OccursImmediately
{
    // ...
}
```

:::warning
If you find yourself reaching for immediate dispatch for most or all of your domain events, you may need to reconsider
the domain design and/or whether a unit of work pattern can actually be followed.
:::

This immediate dispatch of the domain events allows listeners to be triggered immediately. However, what happens if some
listeners can actually be deferred? Indicate this by implementing the `DispatchBeforeCommit` interface on the listener:

```php
namespace App\Modules\EventManagement\Application\Orchestration\Listeners;

use CloudCreativity\Modules\Contracts\Application\UnitOfWork\DispatchBeforeCommit;

final readonly class QueueAttendeeCancellationEmail implements
    DispatchBeforeCommit
{
    // ...
}
```

:::tip
For scenarios where you have to immediately dispatch events, it is advisable to defer as many listeners as possible.
This helps you stick closely to the unit of work pattern, with the minimal amount of side effects incurred
immediately.
:::

### After Commit Listeners

As explained, the default behaviour is to defer domain events and their listeners to before the unit of work commits.
However, you may have some listeners that need to run after the unit of work commits.

To indicate that a listener should be deferred to after the unit of work commits, implement the `DispatchAfterCommit`
interface:

```php
namespace App\Modules\EventManagement\Application\Orchestration\Listeners;

use CloudCreativity\Modules\Contracts\Application\UnitOfWork\DispatchAfterCommit;

final readonly class QueueAttendeeCancellationEmail implements
    DispatchAfterCommit
{
    // ...
}
```

:::warning
If any work pushed to after the unit of work has committed fails, it is not possible to rollback the transaction. This
means that the domain's new state will be committed, but your side effect has not occurred. This could lead to data
inconsistencies.

Use an [Outbox pattern](../infrastructure/outbox) to ensure data consistency.
:::

## Using Unit of Works

The unit of work pattern can be implemented on command handlers and integration event consumers.
There is a consistent approach to all - use a middleware that wraps the execution in a unit of work.

As already noted, ensure this middleware is injected with your singleton unit of work manager instance.

:::tip
Ensure the unit of work middleware is the last middleware to be executed before the handler.
This means you should implement it on the handler itself, rather than adding it as bus middleware.
:::

### Command Handlers

Use the `ExecuteInUnitOfWork` middleware to wrap command handlers in a unit of work:

```php
namespace App\Modules\EventManagement\Api\Input\CancelAttendeeTicket;

use App\Modules\EventManagement\Application\Ports\Persistence\AttendeeRepository;
use CloudCreativity\Modules\Application\Bus\Middleware\ExecuteInUnitOfWork;
use CloudCreativity\Modules\Toolkit\Results\Result;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;

#[Through(ExecuteInUnitOfWork::class)]
final readonly class CancelAttendeeTicketHandler
{
    public function __construct(
        private AttendeeRepository $attendees,
    ) {
    }

    public function handle(CancelAttendeeTicketCommand $command): Result
    {
        $attendee = $this->attendees->findOrFail($command->attendeeId);

        $attendee->cancelTicket(
            $command->ticketId,
            $command->reason,
        );

        $this->attendees->update($attendee);

        return Result::ok();
    }
}
```

### Integration Event Handlers

As explained in the [integration events chapter](../application/integration-events#strategies), there are several
strategies that
can be used to handle inbound events.

If you dispatch a command as a result of the inbound event, you do not need to worry about the unit of work
on the inbound event handler. Instead, the unit of work should be implemented by the command handler.

However, an alternative approach is to map the inbound integration event to a domain event. If using this approach, you
will need to wrap the dispatch of the domain event in a unit of work. This ensures side effects are properly
orchestrated by the unit of work manager and are atomic.

This can be achieved via the `ExecuteInUnitOfWork` middleware on the inbound event handler:

```php
namespace App\Modules\EventManagement\Application\UseCases\Events;

use App\Modules\EventManagement\Domain\Events\DomainEventDispatcher;
use App\Modules\EventManagement\Domain\Events\SalesAtEventDidChange;
use App\Modules\Ordering\Api\Output\V1\Events\OrderWasFulfilled;
use CloudCreativity\Modules\Application\Bus\Middleware\ExecuteInUnitOfWork;
use CloudCreativity\Modules\Contracts\Bus\DispatchThroughMiddleware;
use CloudCreativity\Modules\Toolkit\Pipeline\Through;

#[Through(ExecuteInUnitOfWork::class)]
final readonly class OrderWasFulfilledHandler
{
    public function __construct(
        private DomainEventDispatcher $domainEvents,
    ) {
    }

    public function handle(OrderWasFulfilled $event): void
    {
        $this->domainEvents->dispatch(new SalesAtEventDidChange(
            eventId: $event->eventId,
        ));
    }
}
```

## Testing

We provide a fake unit of work that can be used in your tests. This is the
`CloudCreativity\Modules\Testing\FakeUnitOfWork` class.

This is a fully working implementation that will fake starting and committing transactions. It will also re-attempt the
transaction if it fails and the number of attempts is greater than one.

If you need to check the sequence of what happened in the unit of work, this can be done via the `$sequence` property on
the fake unit of work. For example, if you wanted to check that the unit of work was attempted twice and succeeded on
the second attempt:

```php
use CloudCreativity\Modules\Application\UnitOfWork\UnitOfWorkManager;
use CloudCreativity\Modules\Testing\FakeUnitOfWork;

$unitOfWork = new FakeUnitOfWork();
$manager = new UnitOfWorkManager($unitOfWork);

// execute work that uses the unit of work manager, with two attempts

$this->assertSame(2, $unitOfWork->executions);
$this->assertSame(2, $unitOfWork->attempts);
$this->assertSame(1, $unitOfWork->commits);
$this->assertSame(1, $unitOfWork->rollbacks);
$this->assertSame([
    'execute:1/attempt:1',
    'execute:1/rollback:1',
    'execute:2/attempt:1',
    'execute:2/commit:1',
], $unitOfWork->sequence);
```

Each entry in the sequence is in the format `execute:<execution>/<event>:<attempt>`. The execution is the number of the
call to the fake's `execute()` method, and the attempt is the number of the attempt within that call.

Notice that the two attempts are recorded as two executions, each with a single attempt. This is because the unit of
work manager handles re-attempts itself, executing the fake unit of work once per attempt. The attempt number will
only be greater than one if you call the fake directly with more than one attempt. The `executions`, `attempts`,
`commits` and `rollbacks` properties are all counted across every call to the fake.

A new unit of work started by an after commit callback is also recorded as a separate execution. For example, if the
work in the above example also started a new unit of work from an after commit callback, the sequence would continue
with `execute:3/attempt:1` and `execute:3/commit:1`.

The fake also implements the `active()` and `inactive()` methods. It is active while the callback passed to
`execute()` is running, including on each retry, and inactive before and after. To match the unit of work manager,
which does not support nested units of work, the fake throws a `RuntimeException` if `execute()` is called while it is
already active. As the fake is inactive once it has committed, an after commit callback can start a new unit of work,
in the same way as with a real unit of work.
