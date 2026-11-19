# Identifiers

As described in the [Entities and Aggregates](../domain/entities#identifiers) chapter, our domain layer can
type-hint identifiers using an `Identifier` interface. This prevents coupling between the domain layer and the
persistence layer.

:::tip
If a domain entity type-hinted its identifier as an integer, it would be tightly coupled to the persistence layer.
This is because the entity has been written with implicit knowledge that the persistence implementation in the
infrastructure layer uses an auto-incrementing integer identifier.

This coupling is the wrong way round: the infrastructure layer should be coupled to the domain layer, not the other way
round.
:::

## Using Identifiers

The use of this interface does present a problem: how does the infrastructure layer work with a generic
identifier interface when it has implicit knowledge of what _type_ of identifier is used for each entity?

For example, if the `User` entity is persisted with an auto-incrementing integer identifier, how does the user
repository in the persistence layer know that it has received an integer id for the `User` entity?

This package solves this problem by providing concrete implementations for each of the identifier types that could be
received:

- `IntegerId`
- `StringId`
- `Uuid` - plus the version-specific `UuidV4` and `UuidV7`.
- `Guid` - used for polymorphism.

These are described in more detail below. However, the important thing to note is that all these concrete
implementations have a static `from()` method. This can be used to ensure the supplied identifier is of the correct
type. There is also a static `tryFrom()` method, that returns `null` instead of throwing if the identifier is not of
the expected type. This is useful wherever you need to work with identifiers in a way that expects a specific type.

## Integers

Create an integer id as follows:

```php
use CloudCreativity\Modules\Toolkit\Identifiers\IntegerId;

$id = new IntegerId(123);
```

For example, in a repository you might use this as follows:

```php
class MySqlUserRepository implements UserRepository
{
    // ...

    public function create(User $entity): void
    {
        $id = $this->store($this->mapper->toDatabaseRow($entity));

        $entity->setId(new IntegerId($id));
    }
}
```

Use the `IntegerId::from()` method where you receive an identifier and need to ensure it is an integer id:

```php
class MySqlUserRepository implements UserRepository
{
    // ...

    public function update(User $entity): void
    {
        // Here `getId()` returns the identifier interface.
        // So we ensure it is definitely an integer id.
        $id = IntegerId::from($entity->getId());

        $values = $this->mapper->toDatabaseRow($entity);
        $values['id'] = $id->value;

        $this->store($values);
    }
}
```

:::tip
There may be scenarios where you map data from multiple database tables to a single entity. If these tables all use
integer identifiers, there would be no way to distinguish which table the integer identifier belongs to.

In this scenario, you should use a `Guid` instead.
:::

## Strings

Create a string id as follows:

```php
use CloudCreativity\Modules\Toolkit\Identifiers\StringId;

$id = new StringId('123');
```

:::tip
Use the `Uuid` identifier instead of the string identifier if you are using UUIDs.
:::

For example, in a repository you might use this as follows:

```php
class MySqlUserRepository implements UserRepository
{
    // ...

    public function create(User $entity): void
    {
        $id = $this->store($this->mapper->toDatabaseRow($entity));

        $entity->setId(new StringId($id));
    }
}
```

Use the `StringId::from()` method where you receive an identifier and need to ensure it is a string id:

```php
class MySqlUserRepository implements UserRepository
{
    // ...

    public function update(User $entity): void
    {
        // Here `getId()` returns the identifier interface.
        // So we ensure it is definitely a string id.
        $id = StringId::from($entity->getId());

        $values = $this->mapper->toDatabaseRow($entity);
        $values['id'] = $id->value;

        $this->store($values);
    }
}
```

:::tip
There may be scenarios where you map data from multiple database tables to a single entity. If these tables all use
string identifiers, there would be no way to distinguish which table the string identifier belongs to.

In this scenario, you should use a `Guid` instead.
:::

## UUIDs

The `Uuid` identifier class is a wrapper around the `ramsey/uuid` package. For example:

```php
use CloudCreativity\Modules\Toolkit\Identifiers\Uuid;
use Ramsey\Uuid\Uuid as BaseUuid;

$id = new Uuid(BaseUuid::uuid4());
```

If you specifically need a version 4 or version 7 UUID, use the `UuidV4` or `UuidV7` classes. These can be created
via their static `make()` methods, e.g. `UuidV7::make()`. The `Uuid` class also has a static `random()` method that
returns a random `UuidV4`, and a `nil()` method that returns the nil UUID.

We also provide a `UuidFactory` which is a wrapper around the one from the `ramsey/uuid` package. It has the
same methods, but returns UUID identifiers from this package instead. The `uuid4()` and `uuid7()` methods return
`UuidV4` and `UuidV7` instances respectively, while the `from()` method returns the `Uuid` identifier interface.

For example, in a repository you might use this as follows:

```php
use App\Modules\UserManagement\Domain\User;
use CloudCreativity\Modules\Contracts\Toolkit\Identifiers\UuidFactory;

class MySqlUserRepository implements UserRepository
{
    private readonly UuidFactory $uuidFactory;

    // ...

    public function create(User $entity): void
    {
        $id = $this->uuidFactory->uuid4();
        $data = $this->mapper->toDatabaseRow($entity);
        $data['id'] = $id->toString();

        $this->store($data);

        $entity->setId($id);
    }
}
```

:::tip
The default concrete implementation of this factory can be accessed using the static `Uuid::getFactory()` method.
:::

Use the `Uuid::from()` method where you receive an identifier and need to ensure it is a UUID. Or alternatively,
use the `UuidFactory::from()` method.

```php
class MySqlUserRepository implements UserRepository
{
    // ...

    public function update(User $entity): void
    {
        // Here `getId()` returns the identifier interface.
        // So we ensure it is definitely a UUID.
        $id = Uuid::from($entity->getId());

        // or if we had injected the uuid factory:
        $id = $this->uuidFactory->from($entity->getId());

        $values = $this->mapper->toDatabaseRow($entity);
        $values['id'] = $id->toString();

        $this->store($values);
    }
}
```

:::tip
There may be scenarios where you map data from multiple database tables to a single entity. If these tables all use
UUIDs as their primary key, in theory the chance of collision is exceptionally low. However, you may want to guarantee
that you can distinguish the origin of the identifier. In this case, you should use a `Guid` instead.
:::

## GUIDs

The `Guid` identifier is a wrapper around either the `IntegerId`, `StringId` or `Uuid`, but allows you to also store a
_type_ with the wrapped identifier. This is intended for scenarios where you are mapping values from multiple data
sources to a single entity class - and therefore using only an integer, string or UUID does not provide enough context
to understand the source of the entity.

The _type_ you provide to the GUID can be any string or enum you like. Examples would be the fully-qualified class of an
Eloquent model, a database table name, or an enum case.

GUIDs can be created using the static `fromInteger()`, `fromString()` and `fromUuid()` methods. For example:

```php
use CloudCreativity\Modules\Toolkit\Identifiers\Guid;

// $model is an Eloquent model
$guid = Guid::fromInteger($model::class, $model->getKey());
$guid = Guid::fromString($model::class, $model->getKey());
$guid = Guid::fromUuid($model::class, $model->getKey()); // or provide a Ramsey UUID instance

$guid->type === $model::class; // true
```

Use the `Guid::from()` method where you receive an identifier and need to ensure it is a GUID:

```php
class MySqlUserRepository implements UserRepository
{
    // ...

    public function update(User $entity): void
    {
        $guid = Guid::from($entity->getId());

        $values = $this->mapper->toDatabaseRow($entity);
        $values['id'] = $guid->id->value;

        match($guid->type) {
            UserModel::class => $this->storeUser($values),
            LegacyUserModel::class => $this->storeLegacyUser($values),
        };
    }
}
```

If you have a GUID and need to ensure it is of a specific type, use the `assertType()` helper:

```php
$guid = Guid::from($entity->getId());
$guid->assertType(UserModel::class); // throws if not a user model.
```
