<?php
    /*/
     * Project Name:    Wingman — Corvus — Listener Tests
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
    use Wingman\Corvus\Exceptions\InvalidCapException;

    /**
     * Tests for the Listener fluent builder and its dispatch semantics.
     *
     * Covers basic when() activation, signal targeting, whenAll() all-required
     * semantics, once() one-shot behaviour, latest() and replay() historical-
     * replay modes, activation caps, priority ordering, naming, tagging,
     * stop-propagation, predicate guards, target scoping, deregistration, and
     * the getTimesActivated() counter.
     *
     * @package Wingman\Corvus\Tests
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    #[Group("listener")]
    #[Tags("unit", "listener", "dispatch")]
    class ListenerTest extends Test {

        protected function setUp () : void {
            Bus::reset();
        }

        // ──────────────────────────────────────────────────────────────────────
        // Basic activation
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "when() activates listener on matching signal",
            description: "A when() listener must be activated when a matching signal is emitted."
        )]
        public function testWhenActivatesOnMatchingSignal () : void {
            $hit = 0;
            Listener::create()->when("user.created")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("user.created");

            $this->assertEquals(1, $hit, "The listener must be activated exactly once.");
        }

        #[Define(
            name: "when() does not activate for a non-matching signal",
            description: "A listener registered for 'user.created' must not fire for 'order.placed'."
        )]
        public function testWhenDoesNotActivateForNonMatchingSignal () : void {
            $hit = 0;
            Listener::create()->when("user.created")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("order.placed");

            $this->assertEquals(0, $hit, "The listener must not be activated for a non-matching signal.");
        }

        #[Define(
            name: "whenAny() is equivalent to when()",
            description: "whenAny() with multiple patterns must activate on any individual match."
        )]
        public function testWhenAnyActivatesOnAnyMatchingPattern () : void {
            $hit = 0;
            Listener::create()->whenAny("user.created", "order.placed")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("user.created");
            Emitter::create()->emit("order.placed");

            $this->assertEquals(2, $hit, "The listener must activate for each matching signal.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // once() / onceAll()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "once() fires the listener exactly one time",
            description: "After the first activation a once() listener must remain silent."
        )]
        public function testOnceFiresExactlyOnce () : void {
            $hit = 0;
            Listener::create()->once("user.created")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("user.created");
            Emitter::create()->emit("user.created");
            Emitter::create()->emit("user.created");

            $this->assertEquals(1, $hit, "A once() listener must fire exactly once.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // whenAll()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "whenAll() does not activate until all patterns are satisfied",
            description: "Only after both 'a.ready' and 'b.ready' have been emitted must the listener fire."
        )]
        public function testWhenAllWaitsForAllPatterns () : void {
            $hit = 0;
            Listener::create()->whenAll("a.ready", "b.ready")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("a.ready");
            $this->assertEquals(0, $hit, "Listener must not fire after only the first pattern.");

            Emitter::create()->emit("b.ready");
            $this->assertEquals(1, $hit, "Listener must fire once all patterns are satisfied.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // latest() / replay()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "latest() immediately replays the most recent historical emission",
            description: "latest() registered after an emission must activate with the stored emission immediately."
        )]
        public function testLatestReplaysImmediately () : void {
            Emitter::create()->emit("config.loaded");

            $replayed = 0;
            Listener::create()->latest("config.loaded")->do(function () use (&$replayed) { $replayed++; });

            $this->assertEquals(1, $replayed, "latest() must replay the historical emission at registration time.");
        }

        #[Define(
            name: "latest() does not replay when no prior emission exists",
            description: "latest() registered before any emission must not fire at registration time."
        )]
        public function testLatestDoesNotReplayWhenNoPriorEmission () : void {
            $replayed = 0;
            Listener::create()->latest("config.loaded")->do(function () use (&$replayed) { $replayed++; });

            $this->assertEquals(0, $replayed, "latest() must not replay when no prior emission exists.");
        }

        #[Define(
            name: "replay(n) immediately replays the last n historical emissions",
            description: "After 3 emissions, replay(2) must activate the handler exactly twice at registration."
        )]
        public function testReplayReplaysLastNEmissions () : void {
            Emitter::create()->emit("audit.event");
            Emitter::create()->emit("audit.event");
            Emitter::create()->emit("audit.event");

            $replayed = 0;
            Listener::create()->replay(2, "audit.event")->do(function () use (&$replayed) { $replayed++; });

            $this->assertEquals(2, $replayed, "replay(2) must replay exactly the last 2 historical emissions.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Activation cap
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "cap() limits the number of activations",
            description: "A listener with cap(2) must fire at most twice regardless of how many signals match."
        )]
        public function testCapLimitsActivations () : void {
            $hit = 0;
            Listener::create()->cap(2)->when("capped.signal")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("capped.signal");
            Emitter::create()->emit("capped.signal");
            Emitter::create()->emit("capped.signal");

            $this->assertEquals(2, $hit, "Activation must be capped at 2.");
        }

        #[Define(
            name: "cap(-1) throws InvalidCapException",
            description: "A negative cap must not be accepted."
        )]
        public function testNegativeCapThrows () : void {
            $this->assertThrows(
                InvalidCapException::class,
                fn () => Listener::create()->cap(-1),
                "A negative cap must throw InvalidCapException."
            );
        }

        // ──────────────────────────────────────────────────────────────────────
        // Priority
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "Higher-priority listener runs before lower-priority listener",
            description: "When two listeners share a signal, the one with the higher priority must execute first."
        )]
        public function testHigherPriorityListenerRunsFirst () : void {
            $order = [];
            Listener::create()->priority(1)->when("ordered.event")->do(function () use (&$order) { $order[] = "low"; });
            Listener::create()->priority(10)->when("ordered.event")->do(function () use (&$order) { $order[] = "high"; });

            Emitter::create()->emit("ordered.event");

            $this->assertEquals(["high", "low"], $order, "High-priority listener must run before low-priority listener.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Name
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "named() registers the listener under a retrievable name",
            description: "After calling named(), Bus::getListener() must return the same instance."
        )]
        public function testNamedAllowsLookupByName () : void {
            $listener = Listener::create()->named("my-service-listener")->when("x.y")->do(fn ($e) => null);

            $found = Bus::get()->getListener("my-service-listener");

            $this->assertTrue($found === $listener, "getListener() must return the named instance.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Tags / groups
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "tag() assigns tags visible via getTags()",
            description: "Tags passed to tag() must be retrievable from getTags()."
        )]
        public function testTagAssignsTagsToListener () : void {
            $listener = Listener::create()->tag("module:a", "lifecycle:auth")->when("x.y")->do(fn ($e) => null);

            $this->assertContains("module:a", $listener->getTags(), "First tag must be in the list.");
            $this->assertContains("lifecycle:auth", $listener->getTags(), "Second tag must be in the list.");
        }

        #[Define(
            name: "Multiple tag() calls accumulate rather than replace",
            description: "Calling tag() twice must produce a union of all provided tags."
        )]
        public function testMultipleTagCallsAccumulate () : void {
            $listener = Listener::create()->tag("a")->tag("b")->when("x.y")->do(fn ($e) => null);

            $this->assertContains("a", $listener->getTags(), "First tag must be present.");
            $this->assertContains("b", $listener->getTags(), "Second tag must be present.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Stop propagation
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "stopPropagation() prevents subsequent handlers from running",
            description: "When the first handler stops propagation, the second handler must not run."
        )]
        public function testStopPropagationPreventsSubsequentHandlers () : void {
            $secondRan = false;

            Listener::create()
                ->priority(10)
                ->when("guarded.event")
                ->do(
                    fn ($e) => $e->stopPropagation(),
                    function () use (&$secondRan) { $secondRan = true; }
                );

            Emitter::create()->emit("guarded.event");

            $this->assertFalse($secondRan, "The second handler must not run after stopPropagation().");
        }

        #[Define(
            name: "stopPropagation() prevents lower-priority listeners from running",
            description: "When a high-priority listener stops propagation, a lower-priority listener must not fire."
        )]
        public function testStopPropagationPreventsLowerPriorityListeners () : void {
            $lowHit = false;

            Listener::create()->priority(100)->when("guarded2.event")->do(fn ($e) => $e->stopPropagation());
            Listener::create()->priority(1)->when("guarded2.event")->do(function () use (&$lowHit) { $lowHit = true; });

            Emitter::create()->emit("guarded2.event");

            $this->assertFalse($lowHit, "Lower-priority listener must not run after propagation is stopped.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Predicates
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "if() prevents activation when predicate returns false",
            description: "A listener whose if() predicate returns false must not fire."
        )]
        public function testIfPredicatePreventsActivationWhenFalse () : void {
            $hit = 0;
            Listener::create()
                ->when("guarded.signal")
                ->if(fn () => false)
                ->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("guarded.signal");

            $this->assertEquals(0, $hit, "Listener must not activate when the predicate returns false.");
        }

        #[Define(
            name: "if() allows activation when predicate returns true",
            description: "A listener whose if() predicate returns true must fire normally."
        )]
        public function testIfPredicateAllowsActivationWhenTrue () : void {
            $hit = 0;
            Listener::create()
                ->when("allowed.signal")
                ->if(fn () => true)
                ->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("allowed.signal");

            $this->assertEquals(1, $hit, "Listener must activate when the predicate returns true.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Deregistration
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "deregister() removes the listener from the bus",
            description: "After deregister(), the listener must not fire and the bus must not list it."
        )]
        public function testDeregisterRemovesListenerFromBus () : void {
            $hit = 0;
            $listener = Listener::create()->when("x.signal")->do(function () use (&$hit) { $hit++; });

            $listener->deregister();
            Emitter::create()->emit("x.signal");

            $this->assertEquals(0, $hit, "A deregistered listener must not activate.");
            $this->assertEquals(0, Bus::get()->getListeners()->getSize(), "Bus must report no listeners after deregistration.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // getTimesActivated()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "getTimesActivated() increments on each successful activation",
            description: "Emitting the signal three times must yield a times-activated count of 3."
        )]
        public function testGetTimesActivatedIncrementsOnActivation () : void {
            $listener = Listener::create()->when("counted.signal")->do(fn ($e) => null);

            Emitter::create()->emit("counted.signal");
            Emitter::create()->emit("counted.signal");
            Emitter::create()->emit("counted.signal");

            $this->assertEquals(3, $listener->getTimesActivated(), "Activation count must equal the number of matching emissions.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // onceAll() / onceAny()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "onceAll() fires exactly once after every listed signal has been emitted",
            description: "Must not fire after only the first signal, fire once when all have been emitted, and not fire again afterwards."
        )]
        public function testOnceAllFiresWhenAllSignalsEmitted () : void {
            $hit = 0;
            Listener::create()->onceAll("ready.a", "ready.b")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("ready.a");
            $this->assertEquals(0, $hit, "Must not fire after only the first signal.");

            Emitter::create()->emit("ready.b");
            $this->assertEquals(1, $hit, "Must fire exactly once when all signals have been emitted.");

            Emitter::create()->emit("ready.a");
            Emitter::create()->emit("ready.b");
            $this->assertEquals(1, $hit, "Must not fire again after cap is reached.");
        }

        #[Define(
            name: "onceAny() fires on the first matching signal and does not fire again",
            description: "Must fire once when either signal is emitted and ignore all subsequent emissions."
        )]
        public function testOnceAnyFiresOnFirstMatchingSignal () : void {
            $hit = 0;
            Listener::create()->onceAny("signal.x", "signal.y")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("signal.x");
            $this->assertEquals(1, $hit, "Must fire once on the first matching emission.");

            Emitter::create()->emit("signal.y");
            $this->assertEquals(1, $hit, "Must not fire a second time.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // useBus()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "useBus() routes the listener to the named bus and not the default bus",
            description: "A listener pointed at a named bus must activate on that bus and remain silent when the default bus dispatches."
        )]
        public function testUseBusRoutesListenerToNamedBus () : void {
            $hit = 0;
            new Bus("events");
            Listener::create()->useBus("events")->when("msg.sent")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("msg.sent");
            $this->assertEquals(0, $hit, "Default-bus emission must not activate a listener on a different bus.");

            Emitter::create()->useBus("events")->emit("msg.sent");
            $this->assertEquals(1, $hit, "Named-bus emission must activate the listener on that bus.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Accessor methods
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "getName() returns the name assigned via named()",
            description: "A listener created with named('my-listener') must return 'my-listener' from getName()."
        )]
        public function testGetNameReturnsAssignedName () : void {
            $listener = Listener::create()->named("my-listener")->when("x")->do(fn () => null);

            $this->assertEquals("my-listener", $listener->getName(), "getName() must return the name passed to named().");
        }

        #[Define(
            name: "getPriority() returns the priority assigned via priority()",
            description: "A listener created with priority(42) must return 42 from getPriority()."
        )]
        public function testGetPriorityReturnsAssignedPriority () : void {
            $listener = Listener::create()->priority(42)->when("x")->do(fn () => null);

            $this->assertEquals(42, $listener->getPriority(), "getPriority() must return the value passed to priority().");
        }

        #[Define(
            name: "getTags() returns all tags assigned via tag()",
            description: "A listener tagged with 'alpha' and 'beta' must return both from getTags()."
        )]
        public function testGetTagsReturnsAssignedTags () : void {
            $listener = Listener::create()->tag("alpha", "beta")->when("x")->do(fn () => null);

            $tags = $listener->getTags();

            $this->assertContains("alpha", $tags, "getTags() must include 'alpha'.");
            $this->assertContains("beta", $tags, "getTags() must include 'beta'.");
            $this->assertCount(2, $tags, "getTags() must return exactly 2 tags.");
        }

        #[Define(
            name: "hasCap() returns false by default and true after cap() is called",
            description: "An uncapped listener must return false from hasCap(); setting a cap must flip it to true."
        )]
        public function testHasCapReflectsCapState () : void {
            $uncapped = Listener::create()->when("x")->do(fn () => null);
            $capped   = Listener::create()->cap(3)->when("x")->do(fn () => null);

            $this->assertFalse($uncapped->hasCap(), "hasCap() must be false for a listener without a cap.");
            $this->assertTrue($capped->hasCap(),    "hasCap() must be true for a listener with cap(3).");
        }

        #[Define(
            name: "canBeActivated() returns false after the listener cap is exhausted",
            description: "A listener with cap(1) must return false from canBeActivated() after it has fired once."
        )]
        public function testCanBeActivatedReturnsFalseAfterCapReached () : void {
            $listener = Listener::create()->cap(1)->when("once.signal")->do(fn () => null);

            $this->assertTrue($listener->canBeActivated(), "canBeActivated() must be true before any emission.");

            Emitter::create()->emit("once.signal");

            $this->assertFalse($listener->canBeActivated(), "canBeActivated() must be false after the cap is exhausted.");
        }
    }
?>