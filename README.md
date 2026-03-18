# Wingman — Corvus

A hierarchical, pattern-matched event bus for PHP 8.1+. Part of the [Wingman](https://github.com/angelpolitis/wingman) framework, but usable as a standalone library.

Corvus lets you emit signals on a bus and subscribe listeners to those signals using expressive dot-namespaced patterns with wildcard support. It supports predicate-filtered dispatch, target-scoped listeners, historical replay, priority ordering, middleware pipelines, deferred queues, listener groups, and cross-bus forwarding.

---

## Requirements

- PHP 8.1 or higher
- `wingman/strux` (for `Node`, `NodeList`, `TypedCollection`)

---

## Installation

```bash
composer require wingman/corvus
```

---

## Quick Start

```php
use Wingman\Corvus\Emitter;
use Wingman\Corvus\Listener;

// Listen for any signal under the "user" namespace.
Listener::create()
    ->when("user.*")
    ->do(function ($e) {
        echo "Signal fired: " . $e->signals->getFirst()->name . PHP_EOL;
    });

// Emit a signal.
Emitter::create()->emit("user.created");
// → Signal fired: user.created
```

No explicit bus registration is needed — both `Listener` and `Emitter` default to the bus named `"default"`, which is automatically created on first use.

---

## Core Concepts

| Concept | Description |
|---------|-------------|
| **Signal** | A dot-namespaced string identifier, e.g. `user.profile.updated` |
| **Pattern** | A signal string that may include wildcards (`*`, `**`) used for matching |
| **Bus** | The central registry that connects emitters to listeners |
| **Emitter** | Fluent builder that fires signals onto a bus |
| **Listener** | Fluent builder that subscribes to signals and runs handlers |
| **Emission** | An immutable record of a single signal dispatch, stored in history |
| **HandlerExecution** | The context object passed to every handler callback |

---

## Signal Patterns

Signals and patterns use **dot notation** as a namespace separator.

| Pattern | Matches |
|---------|---------|
| `user.created` | Exactly `user.created` |
| `user.*` | Any direct child: `user.created`, `user.deleted` — not `user.profile.updated` |
| `user.**` | Any descendant: `user.created`, `user.profile.updated`, `user.profile.avatar.changed` |

See [docs/Signals.md](docs/Signals.md) for the full matching specification.

---

## Listening

```php
use Wingman\Corvus\Listener;

// Any of the listed signals fires the handlers.
Listener::create()
    ->when("order.placed", "order.confirmed")
    ->do(fn ($e) => handleOrder($e));

// All listed signals must have been emitted (in any order) before handlers run.
Listener::create()
    ->whenAll("service.a.ready", "service.b.ready")
    ->do(fn ($e) => bootApplication());

// Fire once then stop.
Listener::create()
    ->once("app.boot")
    ->do(fn ($e) => initialise());

// Replay the last matching historical emission immediately on registration,
// then continue listening for future ones.
Listener::create()
    ->latest("config.loaded")
    ->do(fn ($e) => applyConfig($e->payload[0]));

// Replay the last 5 matching emissions immediately, then listen for future ones.
Listener::create()
    ->replay(5, "audit.event")
    ->do(fn ($e) => processAudit($e));
```

See [docs/Listener.md](docs/Listener.md) for the complete API.

---

## Emitting

```php
use Wingman\Corvus\Emitter;

Emitter::create()
    ->with($user, $metadata)   // append payload items
    ->emit("user.created");

// Emit to specific target objects only.
Emitter::for($tenantA, $tenantB)
    ->emit("billing.invoice.generated");

// Guard emission with a predicate — skips dispatch entirely if it returns false.
Emitter::create()
    ->if(fn () => $featureFlag->isEnabled("new-flow"))
    ->emit("experiment.started");
```

See [docs/Emitter.md](docs/Emitter.md) for the complete API.

---

## Deferred Emissions

```php
$bus = Bus::get();

$id = $bus->defer($emitter, "report.ready");

// Tag it for selective flushing.
$bus->tagDeferred($id, "reports");

// Cancel a specific entry.
$bus->cancel($id);

// Cancel all entries with a tag.
$bus->cancelGroup("reports");

// Flush everything.
$bus->flush();

// Flush only entries tagged "reports".
$bus->flush("reports");
```

---

## Middleware

```php
use Wingman\Corvus\Bus;
use Wingman\Corvus\Objects\Emission;

Bus::get()->pipe(function (Emission $emission, callable $next) : void {
    $start = microtime(true);
    $next($emission);
    $elapsed = microtime(true) - $start;
    error_log("Signal {$emission->signal->name} dispatched in {$elapsed}s");
});
```

Middleware is composable — multiple calls to `pipe()` build a chain where the first registered runs outermost and the last innermost. See [docs/Middleware.md](docs/Middleware.md).

---

## Priority

```php
// Higher number = runs first.
Listener::create()->setPriority(10)->when("app.boot")->do(fn ($e) => loadCache());
Listener::create()->setPriority(1)->when("app.boot")->do(fn ($e) => bootModules());
```

---

## Stop Propagation

A handler can call `$e->stopPropagation()` to prevent any subsequent handlers (or listeners at lower priority) from being activated within the current emit cycle.

```php
Listener::create()
    ->when("request.received")
    ->do(function ($e) {
        if (!authenticate($e)) {
            $e->stopPropagation();
        }
    });
```

---

## Listener Groups

```php
// Tag a listener for later bulk teardown.
Listener::create()
    ->tag("module:payments")
    ->when("payment.**")
    ->do(fn ($e) => handlePayment($e));

// Tear down every listener in the group in one call.
Bus::get()->deregisterGroup("module:payments");
```

---

## Bus Bridging

```php
// Forward all "audit.**" signals from the default bus to a dedicated audit bus.
Bus::get()->forward("audit.**", "audit");

// Later remove the forwarding rule.
Bus::get()->unforward("audit.**", "audit");
```

---

## History & Cap

```php
$bus = Bus::get();

// Cap the history at 500 entries (oldest are dropped when exceeded).
$bus->withHistoryLimit(500);

// Query history.
$bus->getHistory();                      // EmissionCollection (all)
$bus->getSignalHistory("user.created"); // EmissionCollection (per signal)
$bus->getSignalLastEmission("user.created"); // Emission|null
$bus->hasBeenEmitted("user.created");   // bool
$bus->findEmissions("user.*");          // EmissionCollection (pattern match)
```

---

## Named Buses

```php
// Create a named bus (stored in a static registry).
$api = new Bus("api");

// Retrieve it later.
$api = Bus::get("api");

// Direct listeners and emitters to a specific bus.
Listener::create()->useBus("api")->when("request.**")->do(...);
Emitter::create()->useBus("api")->emit("request.received");
```

---

## Named Listeners

```php
Listener::create()
    ->named("user-session-cleaner")
    ->when("user.logged-out")
    ->do(fn ($e) => clearSession($e));

// Retrieve by name later.
$listener = Bus::get()->getListener("user-session-cleaner");
```

---

## Error Handling

Any exception thrown by a handler is wrapped in a `HandlerException` (which carries the original throwable as its `getPrevious()`) and re-thrown from `Emitter::emit()`. Catching `\Wingman\Corvus\Interfaces\Exception` covers every Corvus-specific exception.

```php
use Wingman\Corvus\Exceptions\HandlerException;
use Wingman\Corvus\Interfaces\Exception as CorvusException;

try {
    $emitter->emit("order.placed");
}
catch (HandlerException $e) {
    logger()->error("Handler failed for signal {$e->getSignal()}", [
        "cause" => $e->getPrevious(),
    ]);
}
catch (CorvusException $e) {
    // Any other Corvus error.
}
```

See [docs/Exceptions.md](docs/Exceptions.md) for the full exception hierarchy.

---

## Further Reading

| Document | Contents |
|----------|----------|
| [docs/Signals.md](docs/Signals.md) | Pattern syntax, wildcard semantics, matching rules |
| [docs/Bus.md](docs/Bus.md) | Full Bus API reference |
| [docs/Listener.md](docs/Listener.md) | Full Listener API reference |
| [docs/Emitter.md](docs/Emitter.md) | Full Emitter API reference |
| [docs/Middleware.md](docs/Middleware.md) | Middleware pipeline and stop-propagation |
| [docs/Exceptions.md](docs/Exceptions.md) | Exception hierarchy |


---

## Licence

This project is licensed under the **Mozilla Public License 2.0 (MPL 2.0)**.

Wingman Corvus is part of the **Wingman Framework**, Copyright (c) 2025-2026 Angel Politis.

For the full licence text, please see the [LICENSE](LICENSE) file.
