# Middleware & Propagation

## Middleware Pipeline

Corvus supports a composable middleware pipeline that wraps every emission dispatched through `Bus::dispatchEmission()`. Middleware is registered with `Bus::pipe()`.

### Signature

```php
fn (Wingman\Corvus\Objects\Emission $emission, callable $next) : void
```

A middleware **must** call `$next($emission)` to continue the chain. If it does not, listener dispatch is suppressed for that emission.

### Registering Middleware

```php
Bus::get()->pipe(function (Emission $emission, callable $next) : void {
    // before dispatch
    $next($emission);
    // after dispatch
});
```

### Execution Order

Given three calls `pipe(A)`, `pipe(B)`, `pipe(C)`, the chain executes:

```
A → B → C → [listeners]
```

- `A` runs first (outermost); `C` runs innermost, directly before listeners.
- Code before `$next($emission)` runs on the way in.
- Code after `$next($emission)` runs on the way out, in reverse order.

### Examples

**Logging:**

```php
Bus::get()->pipe(function (Emission $emission, callable $next) : void {
    $start = hrtime(true);
    $next($emission);
    $elapsed = (hrtime(true) - $start) / 1e6;
    error_log(sprintf("[Corvus] %s dispatched in %.2fms", $emission->signal->name, $elapsed));
});
```

**Guarding an entire bus:**

```php
Bus::get("admin")->pipe(function (Emission $emission, callable $next) : void {
    if (!$authContext->isAdmin()) {
        return; // suppress without calling $next
    }
    $next($emission);
});
```

**Enriching payload:**

```php
Bus::get()->pipe(function (Emission $emission, callable $next) : void {
    // Middleware cannot mutate immutable Emissions, but it can wrap context
    // available to listeners via a shared request-scoped object in the payload.
    $next($emission);
});
```

> Because `Emission` properties are `readonly`, payload enrichment is best done before emission (in the `Emitter::with()` call) rather than in middleware. Middleware is best suited for cross-cutting concerns: logging, metrics, access control, and tracing.

---

## Stop Propagation

A handler can interrupt the dispatch cycle by calling `$e->stopPropagation()` on its `HandlerExecution` argument. When this is called:

1. The current handler finishes executing.
2. No further handlers on the same listener are invoked.
3. No further listeners are activated for the current emission.
4. If multiple signal patterns were passed to `Emitter::emit()`, emission of remaining patterns is also aborted.
5. Bridge forwarding to other buses does **not** occur for the stopped emission.

The stopped state is available after activation via `Listener::isPropagationStopped()`.

### Example

```php
// Authentication gate — placed at high priority to run first.
Listener::create()
    ->priority(100)
    ->when("api.request.**")
    ->do(function (HandlerExecution $e) {
        if (!authenticate($e->target)) {
            $e->stopPropagation();
            respond(401);
        }
    });
```

---

## Handler Error Recovery

If a handler callback throws any `Throwable`, Corvus wraps it in a `HandlerException` and re-throws from `Emitter::emit()`. The original exception is accessible via `HandlerException::getPrevious()`, and the signal name that was being dispatched is accessible via `HandlerException::getSignal()`.

```php
use Wingman\Corvus\Exceptions\HandlerException;

try {
    Emitter::create()->emit("payment.process");
} catch (HandlerException $e) {
    logger()->critical("Handler failure on signal: " . $e->getSignal(), [
        "exception" => $e->getPrevious(),
    ]);
}
```

---

## Circular Emission Guard

Corvus tracks the current dispatch recursion depth per bus instance. If a handler emits a signal back onto the same bus (directly or through a chain), the depth counter increments on each recursive entry. When the depth reaches `Bus::MAX_EMIT_DEPTH` (default: `32`), a `CircularEmissionException` is thrown rather than overflowing the call stack.

```php
use Wingman\Corvus\Exceptions\CircularEmissionException;

try {
    Emitter::create()->emit("a.triggered");
} catch (CircularEmissionException $e) {
    echo "Circular emission detected for '{$e->getSignal()}' at depth {$e->getDepth()}";
}
```

The depth counter is decremented in a `finally` block, ensuring it is always accurate even when an exception is thrown mid-dispatch.
