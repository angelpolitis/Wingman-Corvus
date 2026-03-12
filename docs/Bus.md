# Bus

`Wingman\Corvus\Bus`

The `Bus` is the central registry that connects emitters to listeners. It stores the listener routing tree, emission history, middleware pipeline, deferred queue, and forwarding rules. Multiple named buses can coexist in a single process.

---

## Obtaining a Bus Instance

### `Bus::get(?string $name = null) : static`

Returns the bus with the given name, creating it if it does not already exist. When `$name` is `null`, the default bus (`"default"`) is used.

```php
$bus = Bus::get();          // default bus
$bus = Bus::get("api");     // named bus
```

### `Bus::exists(?string $name = null) : bool`

Returns `true` if a bus with the given name is in the static registry.

### `new Bus(?string $name = null)`

Constructs a new bus. If `$name` is provided the bus registers itself in the static cache; a subsequent `Bus::get($name)` call returns it.

Throws `BusAlreadyExistsException` if a bus with that name already exists.

### `Bus::set(string $name, Bus $bus) : void`

Inserts an externally constructed bus into the registry under `$name`. Useful when extending `Bus` and managing its registration manually.

### `Bus::remove(?string $name = null) : void`

Removes a bus from the registry. Does not destroy the object — any existing references remain valid.

### `Bus::reset() : void`

Clears the entire registry. Primarily useful in test tear-down.

---

## Listener Management

### `register(Listener $listener, string[] $patterns) : void`

Registers a listener under the given patterns. Called internally by `Listener::when()`, `once()`, etc. You rarely need to call this directly.

### `deregister(Listener $listener) : void`

Removes a listener from all its patterns, the priority map, the name map, all group memberships, and the match cache.

### `deregisterGroup(string $tag) : static`

Deregisters every listener currently tagged with `$tag` in one call. Returns `$this` for fluency.

```php
Bus::get()->deregisterGroup("module:analytics");
```

### `getListener(string $name) : ?Listener`

Returns the `Listener` registered under the given name, or `null`.

### `getGroup(string $tag) : ListenerCollection`

Returns all listeners currently tagged with `$tag`.

### `getListeners() : ListenerCollection`

Returns all registered listeners.

---

## Emitter Management

### `registerEmitter(Emitter $emitter) : int`

Registers an emitter and returns its stable ID. Called internally by `Emitter::emit()`.

### `getEmitters() : EmitterCollection`

Returns all registered emitters.

---

## Signal Dispatch

### `dispatchEmission(Emission $emission, array &$activatedRules) : bool`

Runs the emission through the middleware pipeline and then dispatches to all matching listeners in priority order. Increments and decrements an internal depth counter; throws `CircularEmissionException` if `MAX_EMIT_DEPTH` (32) is exceeded. Returns `true` if propagation was stopped.

After local dispatch, any matching bridge forwarding rules are applied.

### `findListeners(string $pattern) : ListenerCollection`

Returns all listeners whose registered patterns match the given signal pattern, sorted by descending priority. Results are cached per pattern.

### `findMatchingNodes(string $pattern) : NodeList`

Low-level BFS traversal that returns the raw tree nodes matching a pattern. Prefer `findListeners()` for typical use.

---

## History

### `addToHistory(Emission $emission) : void`

Appends an emission to the history and the per-signal index. If a history cap is set and it is exceeded, the oldest entries are trimmed.

### `getHistory() : EmissionCollection`

Returns all stored emissions in chronological order.

### `getHistoryPerSignal() : array<string, EmissionCollection>`

Returns the entire per-signal history map.

### `getSignalHistory(string $signal) : EmissionCollection`

Returns all stored emissions for a specific literal signal name.

### `getSignalLastEmission(string $signal) : ?Emission`

Returns the most recent emission for a specific literal signal name, or `null` if the signal has never been emitted.

### `hasBeenEmitted(string $signal) : bool`

Returns `true` if the signal has been emitted at least once.

### `findEmissions(string $pattern) : EmissionCollection`

Scans the full history and returns all emissions whose signal name matches `$pattern` (wildcards supported).

### `withHistoryLimit(int $limit) : static`

Caps the number of emissions retained in history. When the cap is reached, the oldest emission is discarded. If the existing history is already larger than `$limit`, it is trimmed immediately.

`$limit` must be at least 1; throws `InvalidCapException` otherwise.

```php
Bus::get()->withHistoryLimit(1000);
```

---

## Middleware Pipeline

### `pipe(callable $middleware) : static`

Appends a middleware callable to the dispatch pipeline. Each middleware has the signature:

```php
fn (Emission $emission, callable $next) : void
```

The middleware must call `$next($emission)` to continue the chain. If it does not, no listeners are activated for that emission.

Middleware is applied to every emission dispatched through `dispatchEmission()`. Multiple calls build a chain where the first registered wraps the outermost layer. See [Middleware.md](Middleware.md) for details.

---

## Deferred Emissions

### `defer(Emitter $emitter, array|string ...$signalPatterns) : string`

Enqueues an emission for later dispatch. Returns a unique string ID for the queued entry.

```php
$id = Bus::get()->defer($emitter, "report.ready");
```

### `tagDeferred(string $id, string ...$tags) : static`

Assigns one or more tags to a queued entry, enabling selective flush or cancel operations.

Throws `DeferredNotFoundException` if the ID does not exist.

### `cancel(string $id) : static`

Removes a single queued entry by ID.

Throws `DeferredNotFoundException` if the ID does not exist.

### `cancelGroup(string $tag) : static`

Removes all queued entries that carry the given tag.

### `flush(?string $tag = null) : void`

Dispatches queued entries. When `$tag` is `null` the entire queue is dispatched and cleared. When `$tag` is provided, only entries carrying that tag are dispatched and removed; other entries remain.

### `hasPending(?string $tag = null) : bool`

Returns `true` if there is at least one queued entry (optionally filtered by tag).

### `getPendingCount(?string $tag = null) : int`

Returns the number of queued entries (optionally filtered by tag).

---

## Bus Bridging

### `forward(string $pattern, string $targetBus) : static`

Registers a forwarding rule: every emission on this bus whose signal name matches `$pattern` is also dispatched to the bus named `$targetBus` after all local listeners have run (unless propagation was stopped).

Throws `BusNotFoundException` if the target bus does not exist at the time `forward()` is called.

```php
Bus::get()->forward("audit.**", "audit");
```

### `unforward(string $pattern, ?string $targetBus = null) : static`

Removes a forwarding rule. If `$targetBus` is `null`, all forwarding targets for `$pattern` are removed.

---

## Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `DEFAULT_NAME` | `"default"` | The name used when no name is provided to `Bus::get()`. |
| `MAX_EMIT_DEPTH` | `32` | Maximum dispatch recursion depth before `CircularEmissionException` is thrown. |

---

## Identifiers

### `getNextSignalId() : int`

Returns a monotonically increasing integer ID for a new signal. Called internally by `Emitter::createSignalEmission()`. Each bus maintains its own independent counter.
