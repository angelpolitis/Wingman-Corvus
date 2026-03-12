<?php
    /*/
     * Project Name:    Wingman — Corvus — Bus Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 12 2026
    /*/

    # Use the Corvus.Tests namespace.
    namespace Wingman\Corvus\Tests;

    # Import the following classes to the current scope.
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Attributes\Tags;
    use Wingman\Argus\Test;
    use Wingman\Corvus\Bus;
    use Wingman\Corvus\Emitter;
    use Wingman\Corvus\Listener;
    use Wingman\Corvus\Exceptions\BusAlreadyExistsException;
    use Wingman\Corvus\Exceptions\BusNotFoundException;
    use Wingman\Corvus\Exceptions\CircularEmissionException;
    use Wingman\Corvus\Exceptions\DeferredNotFoundException;
    use Wingman\Corvus\Exceptions\HandlerException;
    use Wingman\Corvus\Exceptions\InvalidCapException;

    /**
     * Tests for the Bus class.
     *
     * Covers the static registry (get / exists / set / remove / reset), named-bus
     * construction exceptions, emission history and history cap, deferred emission
     * queue (defer / tag / cancel / flush / hasPending / getPendingCount),
     * middleware pipeline, listener group teardown, bus bridging (forward /
     * unforward), and the circular-emission guard.
     *
     * @package Wingman\Corvus\Tests
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    #[Group("bus")]
    #[Tags("unit", "bus", "registry", "history", "deferred", "middleware", "bridging")]
    class BusTest extends Test {

        protected function setUp () : void {
            Bus::reset();
        }

        // ──────────────────────────────────────────────────────────────────────
        // Registry
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "get() returns the same default instance on repeated calls",
            description: "Bus::get() must be idempotent for the default bus."
        )]
        public function testGetReturnsSameDefaultInstance () : void {
            $a = Bus::get();
            $b = Bus::get();

            $this->assertTrue($a === $b, "Repeated calls to Bus::get() must return the same instance.");
        }

        #[Define(
            name: "get('name') creates and caches a named bus",
            description: "After calling Bus::get('api'), Bus::exists('api') must return true."
        )]
        public function testGetCreatesAndCachesNamedBus () : void {
            Bus::get("api");

            $this->assertTrue(Bus::exists("api"), "A named bus must be cached after the first get() call.");
        }

        #[Define(
            name: "exists() returns false before bus is created",
            description: "Bus::exists('new') must return false before the bus is accessed."
        )]
        public function testExistsReturnsFalseBeforeCreation () : void {
            $this->assertFalse(Bus::exists("new"), "A bus must not exist before it is created.");
        }

        #[Define(
            name: "new Bus() with duplicate name throws BusAlreadyExistsException",
            description: "Constructing two buses with the same name must throw on the second attempt."
        )]
        public function testDuplicateNamedBusThrows () : void {
            new Bus("dup");

            $this->assertThrows(
                BusAlreadyExistsException::class,
                fn () => new Bus("dup"),
                "Constructing a bus with an already-registered name must throw BusAlreadyExistsException."
            );
        }
        
        #[Define(
            name: "remove() unregisters the named bus",
            description: "After Bus::remove('api'), Bus::exists('api') must return false."
        )]
        public function testRemoveUnregistersNamedBus () : void {
            Bus::get("api");
            Bus::remove("api");

            $this->assertFalse(Bus::exists("api"), "Removed bus must no longer exist in the registry.");
        }

        #[Define(
            name: "reset() clears all registrations",
            description: "After Bus::reset(), every previously registered bus must be gone."
        )]
        public function testResetClearsAllRegistrations () : void {
            Bus::get("a");
            Bus::get("b");
            Bus::reset();

            $this->assertFalse(Bus::exists("a"), "Bus 'a' must be gone after reset.");
            $this->assertFalse(Bus::exists("b"), "Bus 'b' must be gone after reset.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // History
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "History is recorded after emit",
            description: "Emitting a signal must add one entry to the bus history."
        )]
        public function testHistoryIsRecordedAfterEmit () : void {
            Emitter::create()->emit("user.created");

            $this->assertEquals(1, Bus::get()->getHistory()->getSize(), "One emission must be in history.");
        }

        #[Define(
            name: "hasBeenEmitted returns true for an emitted signal",
            description: "hasBeenEmitted() must return true after that signal has been dispatched."
        )]
        public function testHasBeenEmittedReturnsTrueAfterEmit () : void {
            Emitter::create()->emit("user.created");

            $this->assertTrue(Bus::get()->hasBeenEmitted("user.created"), "hasBeenEmitted() must return true after emission.");
        }

        #[Define(
            name: "hasBeenEmitted returns false for never-emitted signal",
            description: "hasBeenEmitted() must return false for a signal that was never dispatched."
        )]
        public function testHasBeenEmittedReturnsFalseWhenNeverEmitted () : void {
            $this->assertFalse(Bus::get()->hasBeenEmitted("user.created"), "A never-emitted signal must not be in history.");
        }

        #[Define(
            name: "getSignalLastEmission returns the most recent emission",
            description: "After two emissions of the same signal, getSignalLastEmission must return the second."
        )]
        public function testGetSignalLastEmissionReturnsLatest () : void {
            Emitter::create()->emit("ping");
            Emitter::create()->emit("ping");

            $bus = Bus::get();
            $history = $bus->getSignalHistory("ping");
            $last = $bus->getSignalLastEmission("ping");

            $this->assertEquals(2, $history->getSize(), "Both emissions must be in the per-signal history.");
            $this->assertEquals($last->signal->name, "ping", "Last emission must carry the correct signal name.");
        }

        #[Define(
            name: "findEmissions matches wildcard patterns",
            description: "findEmissions('user.*') must return only emissions whose signal name matches the pattern."
        )]
        public function testFindEmissionsMatchesWildcardPatterns () : void {
            Emitter::create()->emit("user.created");
            Emitter::create()->emit("order.placed");

            $results = Bus::get()->findEmissions("user.*");

            $this->assertEquals(1, $results->getSize(), "Only the 'user.created' emission must be found.");
        }

        #[Define(
            name: "withHistoryLimit(0) throws InvalidCapException",
            description: "A history limit of zero must not be accepted."
        )]
        public function testWithHistoryLimitZeroThrows () : void {
            $this->assertThrows(
                InvalidCapException::class,
                fn () => Bus::get()->withHistoryLimit(0),
                "A zero history limit must throw InvalidCapException."
            );
        }

        #[Define(
            name: "withHistoryLimit caps and trims history",
            description: "Setting a cap of 2 and emitting 3 signals must leave only the 2 most recent entries."
        )]
        public function testWithHistoryLimitCapsHistory () : void {
            $bus = Bus::get()->withHistoryLimit(2);

            Emitter::create()->emit("a.one");
            Emitter::create()->emit("a.two");
            Emitter::create()->emit("a.three");

            $history = $bus->getHistory();

            $this->assertEquals(2, $history->getSize(), "History must be capped at 2 entries.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Listener management
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "getListeners grows when a listener is registered",
            description: "After registering a listener, getListeners() must return a non-empty collection."
        )]
        public function testGetListenersGrowsOnRegistration () : void {
            Listener::create()->when("x.y")->do(fn ($e) => null);

            $this->assertEquals(1, Bus::get()->getListeners()->getSize(), "One listener must be returned.");
        }

        #[Define(
            name: "getListener returns a named listener",
            description: "A listener registered with named() must be retrievable by name."
        )]
        public function testGetListenerReturnsNamedListener () : void {
            Listener::create()->named("my-listener")->when("x.y")->do(fn ($e) => null);

            $found = Bus::get()->getListener("my-listener");

            $this->assertNotNull($found, "getListener() must return the named listener.");
        }

        #[Define(
            name: "deregisterGroup removes all listeners with the given tag",
            description: "Every listener tagged with the group tag must be removed; untagged listeners must remain."
        )]
        public function testDeregisterGroupRemovesTaggedListeners () : void {
            Listener::create()->tag("module:a")->when("x.y")->do(fn ($e) => null);
            Listener::create()->tag("module:a")->when("x.z")->do(fn ($e) => null);
            Listener::create()->when("x.w")->do(fn ($e) => null);

            Bus::get()->deregisterGroup("module:a");

            $this->assertEquals(1, Bus::get()->getListeners()->getSize(), "Only the untagged listener must remain.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Deferred emission queue
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "defer() returns a non-empty string ID",
            description: "The deferred entry ID must be a non-empty string."
        )]
        public function testDeferReturnsStringId () : void {
            $id = Bus::get()->defer(Emitter::create(), "deferred.signal");

            $this->assertNotEmpty($id, "defer() must return a non-empty string ID.");
        }

        #[Define(
            name: "hasPending returns true after defer",
            description: "The queue must be non-empty immediately after deferring an entry."
        )]
        public function testHasPendingReturnsTrueAfterDefer () : void {
            Bus::get()->defer(Emitter::create(), "deferred.signal");

            $this->assertTrue(Bus::get()->hasPending(), "hasPending() must return true after defer.");
        }

        #[Define(
            name: "getPendingCount reflects the number of queued entries",
            description: "Deferring two entries must result in a pending count of 2."
        )]
        public function testGetPendingCountReflectsQueue () : void {
            $bus = Bus::get();
            $bus->defer(Emitter::create(), "a.one");
            $bus->defer(Emitter::create(), "a.two");

            $this->assertEquals(2, $bus->getPendingCount(), "Pending count must match the number of deferred entries.");
        }

        #[Define(
            name: "flush() dispatches and clears all queued entries",
            description: "After flush(), the queue must be empty and the listener must have been activated."
        )]
        public function testFlushDispatchesAndClearsQueue () : void {
            $hit = 0;
            Listener::create()->when("deferred.signal")->do(function () use (&$hit) { $hit++; });

            $bus = Bus::get();
            $bus->defer(Emitter::create(), "deferred.signal");
            $bus->flush();

            $this->assertEquals(1, $hit, "The deferred listener must have been activated exactly once.");
            $this->assertFalse($bus->hasPending(), "Queue must be empty after flush.");
        }

        #[Define(
            name: "cancel() removes a specific entry",
            description: "Cancelling an entry must remove it so the queue has one fewer item."
        )]
        public function testCancelRemovesSpecificEntry () : void {
            $bus = Bus::get();
            $id = $bus->defer(Emitter::create(), "deferred.signal");
            $bus->cancel($id);

            $this->assertFalse($bus->hasPending(), "Queue must be empty after cancelling the only entry.");
        }

        #[Define(
            name: "cancel() throws DeferredNotFoundException for unknown ID",
            description: "Passing a non-existent ID to cancel() must throw DeferredNotFoundException."
        )]
        public function testCancelThrowsForUnknownId () : void {
            $this->assertThrows(
                DeferredNotFoundException::class,
                fn () => Bus::get()->cancel("no-such-id"),
                "cancel() with an unknown ID must throw DeferredNotFoundException."
            );
        }

        #[Define(
            name: "tagDeferred + cancelGroup removes only tagged entries",
            description: "cancelGroup() must remove all entries carrying the given tag and leave others intact."
        )]
        public function testTagDeferredAndCancelGroupRemovesOnlyTagged () : void {
            $bus = Bus::get();
            $id1 = $bus->defer(Emitter::create(), "a.one");
            $bus->defer(Emitter::create(), "a.two");
            $bus->tagDeferred($id1, "to-cancel");
            $bus->cancelGroup("to-cancel");

            $this->assertEquals(1, $bus->getPendingCount(), "Only the untagged entry must remain.");
        }

        #[Define(
            name: "flush(tag) dispatches only tagged entries",
            description: "flush('reports') must activate only tagged listeners; untagged entries remain in the queue."
        )]
        public function testFlushTagDispatchesOnlyTaggedEntries () : void {
            $hit = 0;
            Listener::create()->when("tagged.signal")->do(function () use (&$hit) { $hit++; });

            $bus = Bus::get();
            $taggedId = $bus->defer(Emitter::create(), "tagged.signal");
            $bus->defer(Emitter::create(), "other.signal");
            $bus->tagDeferred($taggedId, "reports");
            $bus->flush("reports");

            $this->assertEquals(1, $hit, "Only the tagged emission must have been dispatched.");
            $this->assertEquals(1, $bus->getPendingCount(), "The untagged entry must remain pending.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Middleware pipeline
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "pipe() middleware is invoked on every emit",
            description: "A registered middleware callable must be called once per emitted signal."
        )]
        public function testMiddlewareIsInvokedOnEveryEmit () : void {
            $calls = 0;

            Bus::get()->pipe(function ($emission, callable $next) use (&$calls) {
                $calls++;
                $next($emission);
            });

            Emitter::create()->emit("a.signal");
            Emitter::create()->emit("b.signal");

            $this->assertEquals(2, $calls, "Middleware must be called once per emitted signal.");
        }

        #[Define(
            name: "Middleware suppressing next() prevents listener activation",
            description: "A middleware that does not call next() must prevent listeners from being activated."
        )]
        public function testMiddlewareSuppressingNextPreventsListeners () : void {
            $hit = 0;
            Listener::create()->when("blocked.signal")->do(function () use (&$hit) { $hit++; });

            Bus::get()->pipe(function ($emission, callable $next) {
                // intentionally do not call $next
            });

            Emitter::create()->emit("blocked.signal");

            $this->assertEquals(0, $hit, "Suppressing the middleware chain must prevent listener activation.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Bus bridging
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "forward() routes matching emissions to the target bus",
            description: "A signal matching the forwarding pattern must activate a listener on the target bus."
        )]
        public function testForwardRoutesMatchingEmissionsToTargetBus () : void {
            $hit = 0;
            $targetBus = Bus::get("audit");
            Listener::create()->useBus("audit")->when("audit.**")->do(function () use (&$hit) { $hit++; });

            Bus::get()->forward("audit.**", "audit");
            Emitter::create()->emit("audit.user.login");

            $this->assertEquals(1, $hit, "The forwarded signal must activate the listener on the audit bus.");
        }

        #[Define(
            name: "forward() to non-existent bus throws BusNotFoundException",
            description: "Forwarding to a bus name that is not registered must throw BusNotFoundException."
        )]
        public function testForwardToNonExistentBusThrows () : void {
            $this->assertThrows(
                BusNotFoundException::class,
                fn () => Bus::get()->forward("**", "ghost"),
                "Forwarding to a non-existent bus must throw BusNotFoundException."
            );
        }

        #[Define(
            name: "unforward() removes the forwarding rule",
            description: "After unforward(), emissions must no longer be routed to the target bus."
        )]
        public function testUnforwardRemovesForwardingRule () : void {
            $hit = 0;
            Bus::get("audit2");
            Listener::create()->useBus("audit2")->when("**")->do(function () use (&$hit) { $hit++; });

            Bus::get()->forward("**", "audit2");
            Bus::get()->unforward("**", "audit2");
            Emitter::create()->emit("anything");

            $this->assertEquals(0, $hit, "Unforwarded signals must not activate listeners on the previously targeted bus.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Circular emission guard
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "CircularEmissionException is thrown when MAX_EMIT_DEPTH is exceeded",
            description: "A handler that re-emits to its own signal must trigger the circular emission guard."
        )]
        public function testCircularEmissionExceptionThrownOnRecursion () : void {
            Listener::create()->when("recursive.signal")->do(function ($e) {
                Emitter::create()->emit("recursive.signal");
            });

            $this->assertThrows(
                CircularEmissionException::class,
                fn () => Emitter::create()->emit("recursive.signal"),
                "A self-referential emit chain must throw CircularEmissionException."
            );
        }

        // ──────────────────────────────────────────────────────────────────────
        // Handler exceptions
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "Exception thrown in handler is wrapped in HandlerException",
            description: "Any Throwable from a handler must be re-thrown as HandlerException with the original as getPrevious()."
        )]
        public function testHandlerExceptionWrapsOriginalThrowable () : void {
            Listener::create()->when("bad.handler")->do(function () {
                throw new \RuntimeException("original error");
            });

            $caught = null;

            try {
                Emitter::create()->emit("bad.handler");
            } catch (HandlerException $e) {
                $caught = $e;
            }

            $this->assertNotNull($caught, "A HandlerException must be thrown.");
            $this->assertInstanceOf(\RuntimeException::class, $caught->getPrevious(), "The original exception must be wrapped.");
            $this->assertEquals("bad.handler", $caught->getSignal(), "getSignal() must return the signal name.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Query methods
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "findListeners() returns listeners whose patterns overlap the given signal",
            description: "A listener registered for 'user.*' must appear in the result of findListeners('user.created')."
        )]
        public function testFindListenersReturnsMatchingListeners () : void {
            $listener = Listener::create()->when("user.*")->do(fn () => null);

            $found = Bus::get()->findListeners("user.created");

            $this->assertEquals(1, $found->getSize(), "findListeners() must return 1 matching listener.");
            $this->assertTrue($found->getFirst() === $listener, "The returned listener must be the one registered.");
        }

        #[Define(
            name: "findListeners() does not return listeners with non-overlapping patterns",
            description: "A listener registered for 'order.*' must not appear in findListeners('user.created')."
        )]
        public function testFindListenersExcludesNonMatchingListeners () : void {
            Listener::create()->when("order.*")->do(fn () => null);

            $found = Bus::get()->findListeners("user.created");

            $this->assertEquals(0, $found->getSize(), "findListeners() must not return unrelated listeners.");
        }

        #[Define(
            name: "findMatchingNodes() returns tree nodes that match the pattern",
            description: "Registering a listener under 'app.**' must yield at least one node from findMatchingNodes('app.module.loaded')."
        )]
        public function testFindMatchingNodesReturnsTreeNodes () : void {
            Listener::create()->when("app.**")->do(fn () => null);

            $nodes = Bus::get()->findMatchingNodes("app.module.loaded");

            $this->assertTrue(count($nodes) > 0, "findMatchingNodes() must return at least one node for a matching pattern.");
        }

        #[Define(
            name: "getHistoryPerSignal() returns a map keyed by signal name",
            description: "Emitting 'log.info' twice and 'log.error' once must produce a map with two keys and the correct entry counts."
        )]
        public function testGetHistoryPerSignalReturnsKeyedMap () : void {
            Emitter::create()->emit("log.info");
            Emitter::create()->emit("log.info");
            Emitter::create()->emit("log.error");

            $map = Bus::get()->getHistoryPerSignal();

            $this->assertArrayHasKey("log.info", $map, "History map must contain a 'log.info' key.");
            $this->assertArrayHasKey("log.error", $map, "History map must contain a 'log.error' key.");
            $this->assertCount(2, $map["log.info"], "'log.info' must have 2 history entries.");
            $this->assertCount(1, $map["log.error"], "'log.error' must have 1 history entry.");
        }

        #[Define(
            name: "getGroup() returns all listeners carrying the specified tag",
            description: "Two listeners tagged 'auth' and one untagged listener must yield a group of size 2."
        )]
        public function testGetGroupReturnsTaggedListeners () : void {
            Listener::create()->tag("auth")->when("req.in")->do(fn () => null);
            Listener::create()->tag("auth")->when("req.in")->do(fn () => null);
            Listener::create()->when("req.in")->do(fn () => null);

            $group = Bus::get()->getGroup("auth");

            $this->assertEquals(2, $group->getSize(), "getGroup('auth') must return exactly 2 listeners.");
        }

        #[Define(
            name: "getEmitters() grows as emitters emit signals",
            description: "After one emitter calls emit(), getEmitters() must report at least 1 registered emitter."
        )]
        public function testGetEmittersGrowsOnEmit () : void {
            $emitter = Emitter::create();
            $emitter->emit("something.happened");

            $this->assertTrue(Bus::get()->getEmitters()->getSize() >= 1, "getEmitters() must include emitters that have emitted signals.");
        }
    }
?>