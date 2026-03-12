# Emitter

`Wingman\Corvus\Emitter`

`Emitter` is a fluent builder that fires signals onto a bus. Once configured, `emit()` can be called multiple times; each call performs a fresh dispatch cycle.

---

## Creating an Emitter

### `Emitter::create() : static`

Creates a new emitter bound to the default bus.

```php
$emitter = Emitter::create();
```

### `Emitter::for(object ...$targets) : static`

Creates a new emitter pre-loaded with one or more target objects. Only listeners whose own target list overlaps with, or is a superset of, the emitter's target list will receive the signal.

```php
$emitter = Emitter::for($accountA, $accountB);
```

---

## Emitting Signals

### `emit(array|string ...$signalPatterns) : static`

Fires one or more signals in sequence. Each argument may be a string or an array of strings; nested arrays are flattened.

```php
Emitter::create()->emit("user.created");

Emitter::create()->emit("order.placed", "order.confirmed");

Emitter::create()->emit(["metrics.latency", "metrics.throughput"]);
```

**Dispatch order within a single `emit()` call:**

1. Each signal pattern is iterated in the order provided.
2. For each signal, the bus history is updated, then `Bus::dispatchEmission()` is called.
3. If any handler stops propagation, the loop over signal patterns is aborted.
4. Listeners are deduplicated within a single `emit()` call: the same listener + rule combination cannot fire more than once per call, regardless of how many patterns match it.

---

## Payload

### `with(mixed ...$data) : static`

Appends one or more values to the payload. The payload is forwarded to all handler executions as `$e->payload`.

```php
Emitter::create()
    ->with($user, ["role" => "admin"])
    ->emit("user.created");
```

### `withOnly(mixed ...$data) : static`

Replaces the entire payload with the provided values.

```php
Emitter::create()
    ->withOnly($newData)
    ->emit("data.refreshed");
```

---

## Predicates

Predicates are evaluated before any dispatch occurs. If a predicate fails, the emission is still recorded in history (with an empty target set), but no listeners are activated.

### `if(callable ...$predicates) : static`

At least one predicate must return `true`.

### `ifAny(callable ...$predicates) : static`

Alias for `if()`.

### `ifAll(callable ...$predicates) : static`

All predicates must return `true`.

```php
Emitter::create()
    ->if(fn () => $featureFlag->isEnabled("new-checkout"))
    ->emit("checkout.v2.started");
```

Predicates on an emitter receive `null` if no targets are set, or each target in sequence if targets are set (emission is skipped entirely if no targets survive the filter).

---

## Bus Selection

### `useBus(string $bus) : static`

Directs the emitter to fire on the named bus. Must be configured before calling `emit()`.

```php
Emitter::create()->useBus("api")->emit("request.received");
```

---

## Introspection

| Method | Return type | Description |
|--------|-------------|-------------|
| `getId()` | `int` | Stable, process-scoped unique ID for this emitter. |
| `getPayload()` | `array` | The current payload. |
| `getTargets()` | `TargetCollection` | The registered target objects. |
| `hasPredicates()` | `bool` | Whether any predicates are registered. |

---

## Emission Record

Every call to `emit()` produces one `Emission` value object per signal pattern (even if no listeners are matched or predicates fail). Emissions are stored in the bus history.

| Property | Type | Description |
|----------|------|-------------|
| `$signal` | `Signal` | The signal that was fired. |
| `$payload` | `array` | The payload at the time of emission. |
| `$targets` | `TargetCollection` | The targets that survived predicate filtering. |
| `$date` | `DateTime` | The timestamp of the emission. |
| `$emitterId` | `int` | The stable ID of the emitter that fired this signal. |

The `Signal` object has the following properties:

| Property | Type | Description |
|----------|------|-------------|
| `$id` | `int` | A per-bus sequential integer ID. |
| `$type` | `string` | The last dot-separated component (e.g. `"created"` for `user.created`). |
| `$namespace` | `?string` | Everything before the last component, or `null` for a single-segment signal. |
| `$name` | `string` | The full signal name (e.g. `"user.created"`). |

---

## Deferred Emission

Instead of calling `emit()` directly, you can enqueue the emission for later on the bus:

```php
$id = Bus::get()->defer($emitter, "report.generated");
Bus::get()->flush(); // dispatches all queued emissions
```

See [Bus.md](Bus.md#deferred-emissions) for the full deferred queue API.
