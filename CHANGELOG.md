# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] — Unreleased

Initial release of Corvus, the hierarchical pattern-matched event bus for the Wingman framework.

### Added

**Core architecture**
- `Bus` — central signal registry with a static named-bus pool. Manages the segment tree, listener/emitter registries, emission history, middleware pipeline, deferred queue, and bridge forwarding rules.
- `Listener` — fluent builder for subscribing to signals with support for wildcard patterns, match-all semantics, one-shot activation, historical replay, priority ordering, activation caps, predicate guards, and listener tagging for bulk lifecycle management.
- `Emitter` — fluent builder for firing signals onto a bus with typed payloads, target scoping, and predicate guards.
- `SignalRule` — encapsulates a single registered pattern set together with its `SignalMatchType` and optional activation cap.
- `PatternAnalyser` — utility for parsing, matching, and comparing dot-namespaced signal patterns, including `*` (single-level) and `**` (multi-level) wildcards.

**Signal routing**
- Dot-namespaced signal hierarchy (`user.profile.updated`, etc.).
- `*` single-level wildcard and `**` sticky multi-level wildcard.
- BFS segment-tree traversal for O(depth) path lookups.
- Per-bus match result cache with per-pattern invalidation on listener registration or removal.

**Signal-match types** (`Enums/SignalMatchType`)
- `MATCH_ANY` — activate on any matching signal.
- `MATCH_ALL` — activate only when all patterns in the rule have each been matched (across separate emissions).
- `MATCH_LATEST` — register, immediately replay the single most recent matching historical emission, then listen for future ones.
- `MATCH_REPLAY` — register, immediately replay the last N matching historical emissions in chronological order, then listen for future ones.

**Listener features**
- Fluent registration methods: `when()`, `whenAny()`, `whenAll()`, `once()`, `onceAny()`, `onceAll()`, `latest()`, `replay()`.
- Multiple independent `SignalRule`s per listener instance.
- Per-listener activation cap (`Listener::cap()`).
- Configurable dispatch priority (`Listener::priority()`).
- Named listeners retrievable from the bus by name (`Listener::named()`).
- Listener tagging for group deregistration (`Listener::tag()`, `Bus::deregisterGroup()`).
- Target-scoped listeners: only activate for emissions carrying matching target objects.
- Predicate guards: `if()`, `ifAny()`, `ifAll()`.
- Programmatic deregistration (`Listener::deregister()`).

**Emitter features**
- Payload composition: `Emitter::with()` (append) and `Emitter::withOnly()` (replace).
- Target-scoped emissions (`Emitter::for()`).
- Predicate guards: `if()`, `ifAny()`, `ifAll()`.
- Multiple signals per `emit()` call with automatic flattening.
- Listener + rule deduplication within a single `emit()` call.

**Dispatch cycle**
- `HandlerExecution` context object passed to every handler, carrying `$signals`, `$target`, `$payload`, `$handler`, and `$date`.
- Stop-propagation support: `HandlerExecution::stopPropagation()` halts further handler and listener activation within the current cycle and prevents bridge forwarding.
- Handler error recovery: any `Throwable` thrown by a handler is wrapped in `HandlerException` and re-thrown, preserving the original exception as `getPrevious()`.

**Middleware pipeline**
- Composable pipeline via `Bus::pipe(callable)`.
- Middleware signature: `fn(Emission $emission, callable $next): void`.
- Outermost-first execution order; suppression of dispatch possible by not calling `$next`.

**Emission history**
- Every emission is recorded in a per-bus chronological history and a per-signal index.
- History cap via `Bus::withHistoryLimit(int)` with immediate trim on overflow.
- Query methods: `getHistory()`, `getHistoryPerSignal()`, `getSignalHistory()`, `getSignalLastEmission()`, `hasBeenEmitted()`, `findEmissions()`.

**Deferred emission queue**
- `Bus::defer(Emitter, ...patterns)` enqueues an emission and returns a unique string ID.
- `Bus::tagDeferred(string $id, string ...$tags)` marks a queued entry for selective flush/cancel.
- `Bus::cancel(string $id)` removes a specific entry by ID.
- `Bus::cancelGroup(string $tag)` removes all entries carrying a tag.
- `Bus::flush(?string $tag)` dispatches and clears all (or tag-filtered) queued entries.
- `Bus::hasPending(?string $tag)` and `Bus::getPendingCount(?string $tag)` for queue inspection.

**Bus bridging**
- `Bus::forward(string $pattern, string $targetBus)` forwards matching emissions to another named bus after local dispatch.
- `Bus::unforward(string $pattern, ?string $targetBus)` removes forwarding rules.

**Circular emission guard**
- Per-bus emission depth counter with `Bus::MAX_EMIT_DEPTH = 32`.
- `CircularEmissionException` thrown (not PHP stack overflow) if the limit is exceeded.
- Depth counter decremented in a `finally` block for correctness.

**Stable identifiers**
- `HasId` trait providing per-class monotonic ID counter via late-static binding.
- `Identifiable` interface declaring `getId(): int`.
- `Bus`, `Listener`, and `Emitter` implement `Identifiable`.

**Collections**
- Eight typed collection classes extending `TypedCollection`: `EmissionCollection`, `EmitterCollection`, `HandlerCollection`, `ListenerCollection`, `PredicateCollection`, `SignalCollection`, `SignalRuleset`, `TargetCollection`.

**Exception hierarchy**
- `Exception` marker interface covering all Corvus exceptions.
- `BusAlreadyExistsException` — duplicate named bus construction.
- `BusNotFoundException` — forwarding target bus not found.
- `CircularEmissionException` — maximum emit depth exceeded; exposes `getSignal()`, `getDepth()`.
- `DeferredNotFoundException` — operation on unknown deferred ID; exposes `getDeferredId()`.
- `HandlerException` — handler callback threw; wraps the original `\Throwable`; exposes `getSignal()`.
- `InvalidCapException` — negative or zero cap or limit value; exposes `getCap()`.
- `InvalidPatternException` — reserved for future pattern validation; exposes `getPattern()`.

**Documentation**
- `README.md` — overview, quick-start, key concepts, and links to detailed docs.
- `docs/Signals.md` — signal naming, wildcard semantics, pattern matching specification.
- `docs/Bus.md` — full `Bus` API reference.
- `docs/Listener.md` — full `Listener` API reference, including `HandlerExecution` reference.
- `docs/Emitter.md` — full `Emitter` API reference, including `Emission` and `Signal` value-object reference.
- `docs/Middleware.md` — middleware pipeline, stop-propagation, handler error recovery, circular emission guard.
- `docs/Exceptions.md` — full exception hierarchy with per-class context methods.
