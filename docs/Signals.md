# Signals & Patterns

## Signal Names

A signal is identified by a **dot-separated name** such as:

```
user.created
app.billing.invoice.generated
service.cache.miss
```

Each dot-separated segment is called a **component**. Signal names should be lowercase, descriptive, and form a logical hierarchy from broad to specific, reading left to right.

---

## Wildcard Patterns

Patterns are used by the `Listener` registration methods and `Bus::findEmissions()`. They support two wildcards.

### `*` — Single-level wildcard

Matches **exactly one** component at its position.

| Pattern | Matches | Does not match |
|---------|---------|----------------|
| `user.*` | `user.created`, `user.deleted` | `user.profile.updated` |
| `*.created` | `user.created`, `order.created` | `user.profile.created` |
| `app.*.error` | `app.db.error`, `app.cache.error` | `app.error`, `app.db.query.error` |

### `**` — Multi-level wildcard

Matches **zero or more** components at its position, including the rest of the hierarchy.

| Pattern | Matches | Does not match |
|---------|---------|----------------|
| `user.**` | `user.created`, `user.profile.updated`, `user.profile.avatar.changed` | |
| `**.error` | `error`, `app.error`, `app.db.error`, `app.db.query.error` | |
| `app.**.error` | `app.error`, `app.db.error`, `app.db.query.error` | `user.error` |
| `**` | Everything | |

> `**` placed at any position anchors to that depth and consumes all remaining or preceding components. A single `**` listener effectively subscribes to all signals on the bus.

---

## Matching Semantics

Corvus uses a **state-machine BFS traversal** of an internal segment tree built from registered listener patterns. When a signal is emitted:

1. The signal name is split on `.` into segments.
2. The BFS walks the tree, branching for `*` (single-level), `**` (sticky multi-level), and literal matches simultaneously.
3. A **sticky flag** carried by `**` states allows the wildcard to consume arbitrarily many segments before the pattern is exhausted.
4. All tree nodes reached at the end of the segment sequence are collected; their listener ID sets are unioned and priority-sorted.

This means a single emission may activate many listeners — one per matching registered pattern.

---

## Overlap Detection

`PatternAnalyser::isOverlap(string $a, string $b): bool` returns `true` if the two patterns can match at least one common signal. It is used internally by the match cache invalidation logic.

Examples:

| Pattern A | Pattern B | Overlaps? |
|-----------|-----------|-----------|
| `user.*` | `user.created` | Yes |
| `user.*` | `order.*` | No |
| `app.**` | `app.db.query.error` | Yes |
| `a.b.c` | `a.b.d` | No |

---

## Pattern Normalisation

Patterns are normalised before use:

- String inputs are accepted as-is.
- Array inputs are flattened and deduplicated.
- Nested arrays (e.g. `[["a.b", "a.c"], "a.d"]`) are unwrapped.

This is done transparently by `PatternAnalyser::normalisePatterns()` and you never need to call it directly.

---

## Analysing a Signal

`PatternAnalyser::analyse(string $pattern): array` returns `[?string $namespace, string $type]`:

- `$type` is the last dot-separated segment.
- `$namespace` is everything before the last segment, or `null` for a single-segment signal.

Example: `analyse("app.user.created")` → `["app.user", "created"]`.
