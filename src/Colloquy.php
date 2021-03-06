<?php

namespace Colloquy;

use Colloquy\Drivers\DriverInterface;
use Colloquy\Exceptions\ContextAlreadyExistsException;
use Colloquy\Exceptions\UserDefinedContextNotFoundException;

class Colloquy
{
    public const PREFIX = 'Colloquy';

    /** @type ColloquyBinding[] */
    protected static $bindings = [];

    /** @type DriverInterface */
    protected $driver;

    /** @type string[] */
    protected static $contextsToBeRemoved = [];

    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    public static function getBoundContext(string $contextName, $object): ColloquyContext
    {
        $binding = self::$bindings[$contextName];

        return new ColloquyContext(
            $binding->getIdentifierResolver()->get($object),
            new self($binding->getDriver())
        );
    }

    public static function bind(string $contextName, IdentifierResolverInterface $identifierResolver, DriverInterface $driver): void
    {
        self::$bindings[$contextName] = new ColloquyBinding($identifierResolver, $driver);
    }

    public static function makeSelfFromBinding(string $contextName): self
    {
        return new self(self::$bindings[$contextName]->getDriver());
    }

    public static function doesContextBindingExist(string $contextName): bool
    {
        return array_key_exists($contextName, self::$bindings);
    }

    public static function addContextToBeRemoved(ColloquyContext $context): void
    {
        array_push(static::$contextsToBeRemoved, $context->getIdentifier());
    }

    public static function shouldBeRemoved(ColloquyContext $context): bool
    {
        return in_array($context->getIdentifier(), self::$contextsToBeRemoved);
    }

    public function contextExists(string $contextName, $object): bool
    {
        return $this->driver->exists(self::$bindings[$contextName]->getIdentifierResolver()->get($object));
    }

    public static function createContextFromBinding(string $contextName, $object): void
    {
        if (!Colloquy::doesContextBindingExist($contextName)) {
            throw new UserDefinedContextNotFoundException($contextName);
        }

        $binding = self::$bindings[$contextName];
        $colloquy = new self($binding->getDriver());
        $colloquy->begin($binding->getIdentifierResolver()->get($object));
    }

    public function begin(string $identifier): ColloquyContext
    {
        if ($this->driver->exists($identifier)) {
            throw new ContextAlreadyExistsException($identifier);
        }

        $this->driver->create($identifier);

        return new ColloquyContext($identifier, $this);
    }

    public function context(string $identifier): ColloquyContext
    {
        if (!$this->driver->exists($identifier)) {
            return $this->begin($identifier);
        }

        return new ColloquyContext($identifier, $this);
    }

    public static function removeContext(ColloquyContext $context): void
    {
        $context->end();

        unset(self::$contextsToBeRemoved[$context->getIdentifier()]);
    }

    public function end(string $identifier): void
    {
        $this->driver->remove($identifier);
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }
}
