# Exceptions

All exceptions thrown by Corvus implement the `Wingman\Corvus\Interfaces\Exception` marker interface. You can catch that interface to handle any Corvus error without enumerating individual classes:

```php
use Wingman\Corvus\Intefaces\Exception as CorvusException;

try {
    // ...
} catch (CorvusException $e) {
    // handles any Corvus exception
}
```

---

## Hierarchy

```
\Throwable
├── \RuntimeException
│   ├── Wingman\Corvus\Exceptions\BusAlreadyExistsException
│   ├── Wingman\Corvus\Exceptions\BusNotFoundException
│   ├── Wingman\Corvus\Exceptions\CircularEmissionException
│   ├── Wingman\Corvus\Exceptions\DeferredNotFoundException
│   └── Wingman\Corvus\Exceptions\HandlerException
└── \InvalidArgumentException
    ├── Wingman\Corvus\Exceptions\InvalidCapException
    └── Wingman\Corvus\Exceptions\InvalidPatternException
```

All of the above also implement `Wingman\Corvus\Intefaces\Exception`.

---

## Reference

### `BusAlreadyExistsException`

**Thrown by:** `Bus::__construct()`

Raised when constructing a named bus whose name is already present in the static registry.

```php
$bus = new Bus("api"); // fine
$bus = new Bus("api"); // throws BusAlreadyExistsException
```

---

### `BusNotFoundException`

**Thrown by:** `Bus::forward()`

Raised when calling `Bus::forward()` with a target bus name that does not yet exist.

```php
Bus::get()->forward("audit.**", "audit"); // throws if Bus::get("audit") has not been created yet
```

---

### `CircularEmissionException`

**Thrown by:** `Bus::dispatchEmission()`

Raised when the dispatch recursion depth exceeds `Bus::MAX_EMIT_DEPTH` (32). Indicates a circular emission chain: a handler is (directly or indirectly) emitting a signal that re-enters the same dispatch path.

Additional context methods:

| Method | Return type | Description |
|--------|-------------|-------------|
| `getSignal()` | `string` | The signal name at which the recursion limit was hit. |
| `getDepth()` | `int` | The depth at which the limit was exceeded. |

---

### `DeferredNotFoundException`

**Thrown by:** `Bus::cancel()`, `Bus::tagDeferred()`

Raised when attempting to operate on a deferred emission ID that does not exist in the queue. This typically means the entry was already flushed, cancelled, or the ID was mistyped.

Additional context methods:

| Method | Return type | Description |
|--------|-------------|-------------|
| `getDeferredId()` | `string` | The ID that was not found. |

---

### `HandlerException`

**Thrown by:** `Bus::dispatchEmissionCore()` — propagates out of `Emitter::emit()`

Raised when a handler callback throws any `\Throwable`. The original throwable is always stored as the previous exception.

```php
catch (HandlerException $e) {
    $e->getSignal();    // string — the signal being dispatched
    $e->getPrevious();  // \Throwable — the original exception from the handler
}
```

Additional context methods:

| Method | Return type | Description |
|--------|-------------|-------------|
| `getSignal()` | `string` | The signal name being dispatched when the error occurred. |

---

### `InvalidCapException`

**Thrown by:** `Listener::cap()`, `SignalRule::__construct()`, `Bus::withHistoryLimit()`

Raised when a cap or limit value is outside the acceptable range (negative for activation caps; less than 1 for history limits).

```php
catch (InvalidCapException $e) {
    $e->getCap(); // int — the invalid value that was provided
}
```

Additional context methods:

| Method | Return type | Description |
|--------|-------------|-------------|
| `getCap()` | `int` | The invalid cap value that was provided. |

---

### `InvalidPatternException`

**Thrown by:** *(reserved for `PatternAnalyser` validation)*

Reserved for future use when signal pattern validation is enforced at the point of registration. Raised when a signal pattern string is syntactically invalid (e.g. an empty string or illegal characters).

```php
catch (InvalidPatternException $e) {
    $e->getPattern(); // string — the invalid pattern
}
```

Additional context methods:

| Method | Return type | Description |
|--------|-------------|-------------|
| `getPattern()` | `string` | The invalid pattern that was provided. |
