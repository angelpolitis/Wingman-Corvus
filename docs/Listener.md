# Listener

`Wingman\Corvus\Listener`

`Listener` is a fluent builder for subscribing to signals on a bus. Listeners are not activated by a pattern-matching rules until a `when*`, `once*`, `latest`, or `replay` registration method is called; only from that point does the listener receive matching signals.

Multiple registration calls may be chained on the same listener, each producing an independent `SignalRule`.

---

## Creating a Listener

### `Listener::create(): static`

Creates a new listener bound to the default bus.

```php
$listener = Listener::create();
```

### `Listener::for(object ...$targets): static`

Creates a new listener pre-loaded with one or more target objects. The listener will only activate for emissions that include at least one of these targets.

```php
$listener = Listener::for($user, $account);
```

---

## Signal Registration

Each registration method adds a `SignalRule` to the listener and calls `Bus::register()` to wire the listener into the routing tree.

### `when(string ...$signals): static`

Activates when **any** of the listed patterns matches an emitted signal.

```php
Listener::create()->when("user.created", "user.updated")->do(...);
```

### `whenAny(string ...$signals): static`

Alias for `when()`.

### `whenAll(string ...$signals): static`

Activates when **all** of the listed patterns have each been matched (by one or more signals, across separate emissions, in any order).

```php
// Activate only after both "service.a.ready" AND "service.b.ready" have fired.
Listener::create()->whenAll("service.a.ready", "service.b.ready")->do(...);
```

Pattern matches are accumulated across emissions. Once all patterns are satisfied, handlers run and the accumulation resets.

### `once(string ...$signals): static`

Identical to `when()` but activates a maximum of one time.

### `onceAny(string ...$signals): static`

Alias for `once()`.

### `onceAll(string ...$signals): static`

Identical to `whenAll()` but activates a maximum of one time.

### `latest(string ...$signals): static`

Registers the listener and immediately activates it with the single most recent historical emission matching any of the given patterns, if one exists. Continues listening for future emissions.

```php
// Immediately receive the last "config.loaded", then get future ones too.
Listener::create()->latest("config.loaded")->do(fn ($e) => applyConfig($e));
```

### `replay(int $n, string ...$signals): static`

Registers the listener and immediately activates it for each of the last `$n` historical emissions (in chronological order) matching any of the given patterns. Continues listening for future emissions.

```php
// Process the last 10 "audit.event" emissions immediately, then listen for new ones.
Listener::create()->replay(10, "audit.event")->do(fn ($e) => processAudit($e));
```

---

## Handlers

### `do(callable ...$callbacks): static`

Appends one or more handler callbacks to the listener. Handlers are executed in the order they were added. Each receives a `HandlerExecution` object.

```php
Listener::create()
    ->when("order.placed")
    ->do(
        fn ($e) => sendConfirmationEmail($e->target),
        fn ($e) => updateInventory($e->payload[0]),
    );
```

---

## Predicates

Predicates are evaluated before handlers run. All predicates registered across multiple calls must pass together (AND logic between calls). Within a single call, `if()` uses OR and `ifAll()` uses AND.

### `if(callable ...$predicates): static`

At least one predicate must return `true`.

### `ifAny(callable ...$predicates): static`

Alias for `if()`.

### `ifAll(callable ...$predicates): static`

All predicates must return `true`.

```php
Listener::create()
    ->when("payment.received")
    ->if(fn ($target) => $target->isActive())
    ->ifAll(fn ($target) => $target->accountBalance >= 0)
    ->do(fn ($e) => processPayment($e));
```

Each predicate receives the current target object (or `null` when no targets are involved) as its only argument.

---

## Targets

### `for(object ...$targets): static` *(static factory)*

The static `for()` factory creates a listener pre-loaded with targets. You may also chain it as part of a builder, but the static form exists for symmetry with `Emitter::for()`.

When a listener has registered targets, it only activates for targets that appear in both the emission and the listener's own target list. When no targets are registered, the listener activates for all targets in the emission (or for targetless emissions).

---

## Activation Cap

### `cap(int $maxTimes): static`

Sets the maximum number of times the listener may be activated across all its signal rules combined. After reaching the cap, `canBeActivated()` returns `false` and the listener is silently skipped on future emissions.

`$maxTimes` must be ≥ 0; throws `InvalidCapException` otherwise.

```php
// Activate at most 3 times across all rules.
Listener::create()->cap(3)->when("notification.pushed")->do(...);
```

---

## Priority

### `priority(int $priority): static`

Sets the dispatch priority. Listeners with higher values are activated before listeners with lower values. Defaults to `0`.

```php
Listener::create()->priority(100)->when("request.received")->do(fn ($e) => authenticate($e));
Listener::create()->priority(10)->when("request.received")->do(fn ($e) => logRequest($e));
```

---

## Name

### `named(string $name): static`

Assigns a name to the listener so it can later be looked up via `Bus::getListener(string $name)`.

```php
Listener::create()
    ->named("session-cleaner")
    ->when("user.logged-out")
    ->do(fn ($e) => $e->target->clearSession());

Bus::get()->getListener("session-cleaner"); // returns the Listener
```

---

## Tags / Groups

### `tag(string ...$tags): static`

Assigns one or more tags to the listener. Tags are deduplicated; calling `tag()` multiple times accumulates rather than replaces. Tags are used for bulk deregistration via `Bus::deregisterGroup()`.

```php
Listener::create()
    ->tag("module:payments", "lifecycle:auth")
    ->when("payment.**")
    ->do(fn ($e) => handlePayment($e));

Bus::get()->deregisterGroup("module:payments");
```

---

## Bus Selection

### `useBus(string $bus): static`

Directs the listener to register on the named bus instead of the default one. Must be called **before** any registration method.

```php
Listener::create()->useBus("api")->when("request.**")->do(...);
```

---

## Deregistration

### `deregister(): static`

Removes the listener from its bus entirely, cleaning up all patterns, group memberships, name entries, and cached matches.

```php
$listener->deregister();
```

---

## Introspection

| Method | Return type | Description |
|--------|-------------|-------------|
| `getId()` | `int` | Stable, process-scoped unique ID for this listener. |
| `getName()` | `?string` | The name assigned via `named()`, or `null`. |
| `getPriority()` | `int` | The listener's dispatch priority. |
| `getTags()` | `string[]` | All tags assigned to this listener. |
| `getHandlers()` | `HandlerCollection` | The registered handler callbacks. |
| `getSignalRuleset()` | `SignalRuleset` | All signal rules. |
| `getTargets()` | `TargetCollection` | The registered target objects. |
| `getTimesActivated()` | `int` | How many times the listener has been activated. |
| `canBeActivated()` | `bool` | Whether the activation cap, if set, has been reached. |
| `hasCap()` | `bool` | Whether a cap has been set. |
| `hasPredicates()` | `bool` | Whether any predicates are registered. |
| `isPropagationStopped()` | `bool` | Whether a handler stopped propagation during the most recent activation. |

---

## HandlerExecution

Every handler callback receives a single `HandlerExecution` argument with the following properties:

| Property | Type | Description |
|----------|------|-------------|
| `$signals` | `SignalCollection` | All matched signals that triggered this rule. |
| `$target` | `?object` | The current target object, or `null`. |
| `$payload` | `array` | Data passed via `Emitter::with()`. |
| `$handler` | `Handler` | The handler being executed. |
| `$date` | `DateTimeImmutable` | The timestamp of this handler activation. |

And the following methods:

| Method | Description |
|--------|-------------|
| `stopPropagation()` | Prevents any subsequent handlers or listeners from running in the current emit cycle. |
| `isPropagationStopped()` | Returns `true` if `stopPropagation()` has been called on this execution. |
