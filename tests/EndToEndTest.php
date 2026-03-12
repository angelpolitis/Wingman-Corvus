<?php
    /*/
     * Project Name:    Wingman — Corvus — End-to-End Tests
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
     * End-to-end integration tests for Corvus.
     *
     * Exercises full dispatch cycles that cross multiple components: targeted vs
     * targetless emission, single- and multi-level wildcard matching, the complete
     * when() → emit() → handler loop, composed predicate chains on both the
     * emitter and listener side, mixed priority + stop-propagation, and deferred
     * queue flush in a realistic "service-ready" pattern.
     *
     * Each test resets the bus in setUp() so test cases are fully isolated.
     *
     * @package Wingman\Corvus\Tests
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    #[Group("e2e")]
    #[Tags("integration", "e2e")]
    class EndToEndTest extends Test {

        protected function setUp () : void {
            Bus::reset();
        }

        // ─────────────────────────────────────────────────────────────────────
        // Target scoping
        // ─────────────────────────────────────────────────────────────────────

        #[Define(
            name: "Targeted emission with mismatched targets does not activate listener",
            description: "When the listener and emitter targets are different objects, the listener must remain silent."
        )]
        public function testTargetedEmissionWithMismatchedTargetsDoesNotActivate () : void {
            $hit = 0;

            $listenerTarget = new class { public string $name = "Alice"; };
            $emitterTarget  = new class { public string $name = "Bob"; };

            Listener::for($listenerTarget)->when("test.signal")->do(function () use (&$hit) { $hit++; });
            Emitter::for($emitterTarget)->emit("test.signal");

            $this->assertEquals(0, $hit, "Listener must not activate when emitter and listener targets differ.");
        }

        #[Define(
            name: "Targeted emission with matching target activates listener",
            description: "When the emitter and listener share the same target object, the listener must fire."
        )]
        public function testTargetedEmissionWithMatchingTargetActivatesListener () : void {
            $hit = 0;

            $target = new class { public string $name = "Alice"; };

            Listener::for($target)->when("test.signal")->do(function () use (&$hit) { $hit++; });
            Emitter::for($target)->emit("test.signal");

            $this->assertEquals(1, $hit, "Listener must activate exactly once when targets match.");
        }

        // ─────────────────────────────────────────────────────────────────────
        // Wildcard dispatch
        // ─────────────────────────────────────────────────────────────────────

        #[Define(
            name: "Single-level wildcard listener fires for matching target",
            description: "'test.*' must match 'test.signal' when both sides share the same target."
        )]
        public function testSingleLevelWildcardListenerMatchingTarget () : void {
            $hit = 0;

            $target = new class { public string $name = "Alice"; };

            Listener::for($target)->when("test.*")->do(function () use (&$hit) { $hit++; });
            Emitter::for($target)->emit("test.signal");

            $this->assertEquals(1, $hit, "Wildcard listener must fire for a matching target.");
        }

        #[Define(
            name: "Single-level wildcard listener does not fire for mismatched target",
            description: "'test.*' must not activate when the listener and emitter reference different objects."
        )]
        public function testSingleLevelWildcardListenerMismatchedTarget () : void {
            $hit = 0;

            $listenerTarget = new class {};
            $emitterTarget  = new class {};

            Listener::for($listenerTarget)->when("test.*")->do(function () use (&$hit) { $hit++; });
            Emitter::for($emitterTarget)->emit("test.signal");

            $this->assertEquals(0, $hit, "Wildcard listener must not activate when targets differ.");
        }

        #[Define(
            name: "Wildcard emitter pattern matches a literal listener pattern",
            description: "Emitting 'test.*' must match a listener registered for the literal 'test.signal'."
        )]
        public function testWildcardEmitterPatternMatchesLiteralListenerPattern () : void {
            $hit = 0;

            $target = new class {};

            Listener::for($target)->when("test.signal")->do(function () use (&$hit) { $hit++; });
            Emitter::for($target)->emit("test.*");

            $this->assertEquals(1, $hit, "A wildcard emitter pattern must match listeners with a literal-overlap pattern.");
        }

        #[Define(
            name: "Multi-level wildcard listener fires for deep descendants",
            description: "'events.**' must dispatch to a handler for 'events.user.profile.updated'."
        )]
        public function testMultiLevelWildcardListenerFiresForDeepDescendant () : void {
            $hit = 0;
            Listener::create()->when("events.**")->do(function () use (&$hit) { $hit++; });

            Emitter::create()->emit("events.user.profile.updated");

            $this->assertEquals(1, $hit, "Multi-level wildcard must match deeply nested signals.");
        }

        // ─────────────────────────────────────────────────────────────────────
        // Composed predicate chains
        // ─────────────────────────────────────────────────────────────────────

        #[Define(
            name: "Emitter predicate passes + listener predicate fails blocks handler",
            description: "If the emitter predicate passes but the listener predicate fails, the handler must not run."
        )]
        public function testCombinedPredicatesEmitterPassListenerFail () : void {
            $hit = 0;
            Listener::create()
                ->when("combo.signal")
                ->if(fn () => false)
                ->do(function () use (&$hit) { $hit++; });

            Emitter::create()->if(fn () => true)->emit("combo.signal");

            $this->assertEquals(0, $hit, "If the listener predicate fails the handler must not run.");
        }

        #[Define(
            name: "Both emitter and listener predicates passing activates handler",
            description: "Both emitter and listener predicates returning true must lead to handler activation."
        )]
        public function testCombinedPredicatesBothPassActivateHandler () : void {
            $hit = 0;
            Listener::create()
                ->when("combo.signal")
                ->if(fn () => true)
                ->do(function () use (&$hit) { $hit++; });

            Emitter::create()->if(fn () => true)->emit("combo.signal");

            $this->assertEquals(1, $hit, "Handler must fire when both emitter and listener predicates pass.");
        }

        // ─────────────────────────────────────────────────────────────────────
        // Realistic end-to-end patterns
        // ─────────────────────────────────────────────────────────────────────

        #[Define(
            name: "Deferred queue enables an all-services-ready startup pattern",
            description: "Two services defer their ready signals; flushing must activate the startup listener exactly once."
        )]
        public function testDeferredQueueAllServicesReadyStartupPattern () : void {
            $started = 0;
            Listener::create()->whenAll("service.a.ready", "service.b.ready")->do(function () use (&$started) { $started++; });

            $bus = Bus::get();
            $bus->defer(Emitter::create(), "service.a.ready");
            $bus->defer(Emitter::create(), "service.b.ready");
            $bus->flush();

            $this->assertEquals(1, $started, "The startup listener must fire exactly once after both services signal readiness.");
        }

        #[Define(
            name: "once() listener self-deregisters after first activation",
            description: "A once() listener must not activate a second time even if the signal is re-emitted."
        )]
        public function testOnceListenerSelfDeregistersAfterFirstActivation () : void {
            $hit = 0;
            Listener::create()->once("init.done")->do(function () use (&$hit) { $hit++; });

            for ($i = 0; $i < 5; $i++) {
                Emitter::create()->emit("init.done");
            }

            $this->assertEquals(1, $hit, "A once() listener must fire exactly once no matter how many times the signal is emitted.");
        }

        #[Define(
            name: "Priority gate stops subsequent processing via stopPropagation",
            description: "A high-priority listener that stops propagation must prevent low-priority listeners from running."
        )]
        public function testPriorityAuthGateStopsSubsequentProcessing () : void {
            $processed = false;

            Listener::create()->priority(100)->when("http.request")->do(fn ($e) => $e->stopPropagation());
            Listener::create()->priority(1)->when("http.request")->do(function () use (&$processed) { $processed = true; });

            Emitter::create()->emit("http.request");

            $this->assertFalse($processed, "Downstream processing must be blocked when the auth gate stops propagation.");
        }

        #[Define(
            name: "History replay enables late-joining subscriber pattern",
            description: "A listener registered after emission must still receive the event via latest()."
        )]
        public function testHistoryReplayMakesLateSubscriberReceiveEvent () : void {
            Emitter::create()->with(["db_host" => "localhost"])->emit("config.loaded");

            $received = null;
            Listener::create()->latest("config.loaded")->do(function (HandlerExecution $e) use (&$received) {
                $received = $e->payload[0] ?? null;
            });

            $this->assertEquals(["db_host" => "localhost"], $received, "Late subscriber must receive the original payload via latest().");
        }

        #[Define(
            name: "Middleware logging wrapper observes every dispatch cycle",
            description: "A middleware that logs signal names must collect one entry per emitted signal."
        )]
        public function testMiddlewareLoggingWrapperObservesEveryDispatch () : void {
            $log = [];

            Bus::get()->pipe(function ($emission, callable $next) use (&$log) {
                $log[] = $emission->signal->name;
                $next($emission);
            });

            Emitter::create()->emit("app.started");
            Emitter::create()->emit("app.ready");

            $this->assertEquals(["app.started", "app.ready"], $log, "Middleware must record each dispatched signal in order.");
        }
    }
?>
