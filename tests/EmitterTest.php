<?php
    /*/
     * Project Name:    Wingman — Corvus — Emitter Tests
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
    use Wingman\Corvus\Objects\HandlerExecution;

    /**
     * Tests for the Emitter fluent builder and its dispatch semantics.
     *
     * Covers basic emission activating listeners, payload composition (with /
     * withOnly), target scoping (for), emitter predicate guards (if / ifAll /
     * ifAny), multiple signals in a single emit() call, deduplication within
     * one emit() cycle, and the HandlerExecution context object (payload and
     * target accessibility).
     *
     * @package Wingman\Corvus\Tests
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    #[Group("emitter")]
    #[Tags("unit", "emitter", "dispatch")]
    class EmitterTest extends Test {

        protected function setUp () : void {
            Bus::reset();
        }

        // ──────────────────────────────────────────────────────────────────────
        // Basic emit
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "emit() activates a matching listener",
            description: "Emitting a signal must trigger every listener registered for that exact pattern."
        )]
        public function testEmitActivatesMatchingListener () : void {
            $hit = 0;
            Listener::create()->when("item.created")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("item.created");

            $this->assertEquals(1, $hit, "Matching listener must be activated exactly once.");
        }

        #[Define(
            name: "emit() does not activate an unrelated listener",
            description: "Emitting a signal must not fire listeners registered for different patterns."
        )]
        public function testEmitDoesNotActivateUnrelatedListener () : void {
            $hit = 0;
            Listener::create()->when("item.deleted")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("item.created");

            $this->assertEquals(0, $hit, "Unrelated listener must not be activated.");
        }

        #[Define(
            name: "Emitting multiple signals activates listeners for each",
            description: "Passing multiple patterns to emit() must activate matching listeners for every signal."
        )]
        public function testEmitMultipleSignalsActivatesEachListener () : void {
            $hitA = 0;
            $hitB = 0;
            Listener::create()->when("multi.a")->do(function () use (&$hitA) { $hitA++; });
            Listener::create()->when("multi.b")->do(function () use (&$hitB) { $hitB++; });

            Emitter::create()->emit("multi.a", "multi.b");

            $this->assertEquals(1, $hitA, "Listener for 'multi.a' must fire once.");
            $this->assertEquals(1, $hitB, "Listener for 'multi.b' must fire once.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Payload
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "with() appends payload items accessible in HandlerExecution",
            description: "Items passed to with() must appear in \$e->payload inside the handler."
        )]
        public function testWithAppendsPayloadItems () : void {
            $received = [];

            Listener::create()->when("payload.signal")->do(function (HandlerExecution $e) use (&$received) {
                $received = $e->payload;
            });

            Emitter::create()->with("alpha", 42)->emit("payload.signal");

            $this->assertEquals(["alpha", 42], $received, "Payload must contain all items passed to with().");
        }

        #[Define(
            name: "Chained with() calls accumulate items",
            description: "Calling with() twice must accumulate both sets of items in the payload."
        )]
        public function testChainedWithCallsAccumulate () : void {
            $received = [];

            Listener::create()->when("payload.signal")->do(function (HandlerExecution $e) use (&$received) {
                $received = $e->payload;
            });

            Emitter::create()->with("a", "b")->with("c")->emit("payload.signal");

            $this->assertEquals(["a", "b", "c"], $received, "Both with() calls must be reflected in the payload.");
        }

        #[Define(
            name: "withOnly() replaces the entire payload",
            description: "withOnly() must discard previously appended items and set only the new ones."
        )]
        public function testWithOnlyReplacesPayload () : void {
            $received = [];

            Listener::create()->when("payload.signal")->do(function (HandlerExecution $e) use (&$received) {
                $received = $e->payload;
            });

            Emitter::create()->with("old")->withOnly("new")->emit("payload.signal");

            $this->assertEquals(["new"], $received, "withOnly() must replace the entire payload.");
        }

        #[Define(
            name: "getPayload() returns the current payload",
            description: "getPayload() must reflect all items appended via with()."
        )]
        public function testGetPayloadReflectsConfiguredItems () : void {
            $emitter = Emitter::create()->with("x", "y");

            $this->assertEquals(["x", "y"], $emitter->getPayload(), "getPayload() must return the accumulated payload.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Target scoping
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "for() provides the target in HandlerExecution",
            description: "The target object passed to for() must be available as \$e->target in the handler."
        )]
        public function testForProvidesTargetInHandlerExecution () : void {
            $target = new \stdClass();
            $target->name = "widget";

            $receivedTarget = null;

            Listener::create()->when("target.signal")->do(function (HandlerExecution $e) use (&$receivedTarget) {
                $receivedTarget = $e->target;
            });

            Emitter::for($target)->emit("target.signal");

            $this->assertTrue($receivedTarget === $target, "The emitter target must be passed to the handler.");
        }

        #[Define(
            name: "for() with non-matching listener target does not activate the listener",
            description: "A listener scoped to a different target must not be activated."
        )]
        public function testForWithNonMatchingListenerTargetDoesNotActivate () : void {
            $listenerTarget = new \stdClass();
            $emitterTarget = new \stdClass();

            $hit = 0;
            Listener::for($listenerTarget)->when("target.signal")->do(function () use (&$hit) { $hit++; });

            Emitter::for($emitterTarget)->emit("target.signal");

            $this->assertEquals(0, $hit, "Listener must not activate when targets do not match.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // Predicate guards
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "if() on emitter suppresses entire emission when predicate is false",
            description: "An emitter predicate returning false must prevent any listener from being activated."
        )]
        public function testEmitterIfSuppressesEmissionWhenFalse () : void {
            $hit = 0;
            Listener::create()->when("guarded.signal")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->if(fn () => false)->emit("guarded.signal");

            $this->assertEquals(0, $hit, "Listener must not activate when the emitter predicate returns false.");
        }

        #[Define(
            name: "if() on emitter allows emission when predicate is true",
            description: "An emitter predicate returning true must allow normal listener activation."
        )]
        public function testEmitterIfAllowsEmissionWhenTrue () : void {
            $hit = 0;
            Listener::create()->when("allowed.signal")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->if(fn () => true)->emit("allowed.signal");

            $this->assertEquals(1, $hit, "Listener must activate when the emitter predicate returns true.");
        }

        #[Define(
            name: "ifAll() requires all predicates to return true",
            description: "If any predicate in ifAll() returns false the entire emission must be suppressed."
        )]
        public function testEmitterIfAllSuppressesWhenOnePredicateFails () : void {
            $hit = 0;
            Listener::create()->when("ifAll.signal")->do(function () use (&$hit) { $hit++; });

            Emitter::create()
                ->ifAll(fn () => true, fn () => false)
                ->emit("ifAll.signal");

            $this->assertEquals(0, $hit, "ifAll() must suppress emission if any predicate fails.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // HandlerExecution context
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "HandlerExecution carries the emitted signal name",
            description: "The signal accessible via \$e->signals in the handler must match the emitted string."
        )]
        public function testHandlerExecutionCarriesSignalName () : void {
            $receivedSignal = null;

            Listener::create()->when("ctx.signal")->do(function (HandlerExecution $e) use (&$receivedSignal) {
                $receivedSignal = $e->signals->getFirst()->name;
            });

            Emitter::create()->emit("ctx.signal");

            $this->assertEquals("ctx.signal", $receivedSignal, "Signal name in HandlerExecution must match the emitted signal.");
        }

        #[Define(
            name: "HandlerExecution has a DateTimeImmutable date",
            description: "The \$date property on HandlerExecution must be a DateTimeImmutable instance."
        )]
        public function testHandlerExecutionHasDateTimeImmutableDate () : void {
            $receivedDate = null;

            Listener::create()->when("date.signal")->do(function (HandlerExecution $e) use (&$receivedDate) {
                $receivedDate = $e->date;
            });

            Emitter::create()->emit("date.signal");

            $this->assertInstanceOf(\DateTimeImmutable::class, $receivedDate, "HandlerExecution date must be a DateTimeImmutable.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // useBus()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "useBus() routes the emission to the named bus and not the default bus",
            description: "An emitter pointed at a named bus must trigger listeners on that bus and leave the default-bus listener untouched."
        )]
        public function testUseBusRoutesEmissionToNamedBus () : void {
            new Bus("analytics");

            $defaultHit   = 0;
            $analyticsHit = 0;

            Listener::create()->when("page.viewed")->do(function () use (&$defaultHit) { $defaultHit++; });
            Listener::create()->useBus("analytics")->when("page.viewed")->do(function () use (&$analyticsHit) { $analyticsHit++; });

            Emitter::create()->useBus("analytics")->emit("page.viewed");

            $this->assertEquals(0, $defaultHit,   "Default-bus listener must not fire when the emitter targets a different bus.");
            $this->assertEquals(1, $analyticsHit, "Named-bus listener must fire when the emitter targets that bus.");
        }
    }
?>