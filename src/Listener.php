<?php
    /**
     * Project Name:    Wingman Corvus - Listener
     * Created by:      Angel Politis
     * Creation Date:   Nov 17 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus namespace.
    namespace Wingman\Corvus;

    # Import the following classes to the current scope.
    use BackedEnum;
    use DateTimeImmutable;
    use Wingman\Corvus\Collections\HandlerCollection;
    use Wingman\Corvus\Collections\PredicateCollection;
    use Wingman\Corvus\Collections\SignalCollection;
    use Wingman\Corvus\Collections\SignalRuleset;
    use Wingman\Corvus\Collections\TargetCollection;
    use Wingman\Corvus\Enums\SignalMatchType;
    use Wingman\Corvus\Exceptions\InvalidCapException;
    use Wingman\Corvus\Interfaces\Identifiable;
    use Wingman\Corvus\Objects\Handler;
    use Wingman\Corvus\Objects\HandlerExecution;
    use Wingman\Corvus\Objects\Predicate;
    use Wingman\Corvus\Objects\Signal;
    use Wingman\Corvus\Objects\SignalRule;
    use Wingman\Corvus\Traits\HasId;

    /**
     * Represents a signal listener.
     * @package Wingman\Corvus
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Listener implements Identifiable {
        use HasId;

        /**
         * The bus (name) of a listener.
         * @var string|null
         */
        protected ?string $bus = null;

        /**
         * The name of a listener.
         * @var string|null
         */
        protected ?string $name = null;

        /**
         * The dispatch priority of a listener. Higher values are activated first.
         * @var int
         */
        protected int $priority = 0;

        /**
         * Whether propagation was stopped during the most recent activation.
         * @var bool
         */
        protected bool $propagationStopped = false;

        /**
         * The group tags assigned to this listener for bulk lifecycle operations.
         * @var string[]
         */
        protected array $tags = [];

        /**
         * The signal ruleset of a listener.
         * @var SignalRuleset
         */
        protected SignalRuleset $signalRuleset;

        /**
         * The handlers of a listener.
         * @var HandlerCollection
         */
        protected HandlerCollection $handlers;

        /**
         * The predicates of a listener.
         * @var PredicateCollection
         */
        protected PredicateCollection $predicates;

        /**
         * The targets of a listener.
         * @var TargetCollection
         */
        protected TargetCollection $targets;

        /**
         * The number of times a listener has been activated.
         * @var int
         */
        protected int $timesActivated = 0;

        /**
         * The maximum number of times a listener can been activated.
         * @var int|null
         */
        protected ?int $maxTimesActivated = null;

        /**
         * The number of a successful matches per signal rule.
         * @var array<int, int>
         */
        protected array $signalRuleActivations = [];

        /**
         * The patterns of a listener that have already been matched.
         * @var array<int, array<string, Signal>>
         */
        protected array $signalRulePatternMatches = [];

        /**
         * A queue of replay tasks (latest or replay-n) stored until handlers are first attached.
         * Each entry: ['type' => 'latest'|'replay', 'rule' => SignalRule, 'bus' => Bus, 'n' => ?int]
         * @var array<int, array>
         */
        protected array $pendingReplays = [];

        /**
         * The debounce window in milliseconds. Zero means no debounce.
         * @var int
         */
        protected int $debounceMs = 0;

        /**
         * The Unix timestamp in milliseconds of the last activation,
         * used to enforce the debounce window.
         * @var int
         */
        protected int $lastActivationTime = 0;

        /**
         * Coerces an array of strings and string-backed enum cases to their string values.
         * @param string|BackedEnum ...$signals The signals to coerce.
         * @return string[] The resolved string values.
         */
        private function coerceSignals (string|BackedEnum ...$signals) : array {
            return array_map(fn ($signal) => $signal instanceof BackedEnum ? $signal->value : $signal, $signals);
        }

        /**
         * Replays up to the last $n historical emissions that match any of the given signal rule's patterns, in chronological order.
         * @param SignalRule $rule A signal rule.
         * @param int $n The maximum number of emissions to replay.
         * @param Bus $bus The bus.
         */
        private function replayFromHistory (SignalRule $rule, int $n, Bus $bus) : void {
            $matchingEmissions = [];

            foreach ($rule->getPatterns() as $pattern) {
                foreach ($bus->findEmissions($pattern)->getAll() as $emission) {
                    $matchingEmissions[$emission->signal->id] = $emission;
                }
            }

            ksort($matchingEmissions);
            $matchingEmissions = array_slice($matchingEmissions, -$n, preserve_keys: true);

            $ruleId = $rule->getId();

            foreach ($matchingEmissions as $emission) {
                $this->signalRulePatternMatches[$ruleId] = [$emission->signal->name => $emission->signal];
                $this->activate($rule, $emission->targets, $emission->payload);
            }
        }

        /**
         * Replays the single most recent historical emission that matches any of the given signal rule's patterns.
         * @param SignalRule $rule A signal rule.
         * @param Bus $bus The bus.
         */
        private function replayLatestFromHistory (SignalRule $rule, Bus $bus) : void {
            $latestEmission = null;

            foreach ($rule->getPatterns() as $pattern) {
                $last = $bus->findEmissions($pattern)->getLast();

                if ($last !== null && ($latestEmission === null || $last->signal->id > $latestEmission->signal->id)) {
                    $latestEmission = $last;
                }
            }

            if ($latestEmission === null) return;

            $ruleId = $rule->getId();
            $this->signalRulePatternMatches[$ruleId] = [$latestEmission->signal->name => $latestEmission->signal];
            $this->activate($rule, $latestEmission->targets, $latestEmission->payload);
        }

        /**
         * Creates a new listener.
         */
        protected function __construct () {
            $this->initialiseId();
            $this->signalRuleset = new SignalRuleset();
            $this->handlers = new HandlerCollection();
            $this->predicates = new PredicateCollection();
            $this->targets = new TargetCollection();
        }

        /**
         * Activates a listener.
         * @param SignalRule $signalRule A signal rule.
         * @param TargetCollection|null $targets The applicable targets, if any.
         * @param array $payload The payload of the emitter.
         * @return static The listener.
         */
        public function activate (SignalRule $signalRule, ?TargetCollection $targets = null, array $payload = []) : bool {
            $this->propagationStopped = false;

            # When multiple predicates are given in sequential calls, all of them must hold.
            $predicate = Predicate::andAll($this->predicates->getAll());

            # If no targets are given, it's assumed there's no target restriction.
            if (!$targets || $targets->getSize() == 0) {
                # If the predicate doesn't pass, then no handlers are fired.
                if (!$predicate(null)) return false;

                # Fetch all emitted signals that fulfill the rule.
                $signals = new SignalCollection(array_values($this->signalRulePatternMatches[$signalRule->getId()]));

                /** @var Handler */
                foreach ($this->handlers as $handler) {
                    $execution = new HandlerExecution($signals, null, $payload, $handler, new DateTimeImmutable());
                    $handler($execution);

                    if ($execution->isPropagationStopped()) {
                        $this->propagationStopped = true;
                        break;
                    }
                }
            }
            else {
                # If the listener has no targets, then consider all given targets applicable,
                # otherwise, only the given targets already registered with the listener apply.
                $actualTargets = $this->targets->getSize() === 0
                    ? $targets
                    : $this->targets->filter(fn ($t) => $targets->has($t));

                # Keep only the targets that pass the predicate.
                $actualTargets = $actualTargets->filter($predicate);

                # Fetch all emitted signals that fulfill the rule.
                $matches = $this->signalRulePatternMatches[$signalRule->getId()];
                $signals = new SignalCollection(array_values($matches));

                /** @var Handler */
                foreach ($this->handlers as $handler) {
                    /** @var object */
                    foreach ($actualTargets as $target) {
                        $execution = new HandlerExecution($signals, $target, $payload, $handler, new DateTimeImmutable());
                        $handler($execution);

                        if ($execution->isPropagationStopped()) {
                            $this->propagationStopped = true;
                            break 2;
                        }
                    }
                }
            }

            $this->timesActivated++;

            return true;
        }

        /**
         * Checks whether a listener can be activated.
         * @return bool Whether the listener can be activated.
         */
        public function canBeActivated () : bool {
            return is_null($this->maxTimesActivated) || $this->timesActivated < $this->maxTimesActivated;
        }
        
        /**
         * Sets a maximum cap for the number of times a listener can be activated.
         * @param int $maxTimes The maximum number of times the listener can be activated.
         * @return static The listener.
         * @throws InvalidCapException If the cap is less than 0.
         */
        public function cap (int $maxTimes) : static {
            if ($maxTimes < 0) {
                throw new InvalidCapException($maxTimes);
            }
            $this->maxTimesActivated = $maxTimes;
            return $this;
        }

        /**
         * Creates a new listener.
         * @return static A new listener.
         */
        public static function create () : static {
            return new static();
        }

        /**
         * Sets the debounce window for this listener. Any activation
         * attempted within $milliseconds of the previous one is silently
         * dropped.
         *
         * @param int $milliseconds The cooldown window in milliseconds.
         * @return static The listener.
         */
        public function debounce (int $milliseconds) : static {
            $this->debounceMs = $milliseconds;
            return $this;
        }

        /**
         * Deregisters the listener from its bus.
         * @return static The listener.
         */
        public function deregister () : static {
            Bus::get($this->bus)->deregister($this);
            return $this;
        }

        /**
         * Creates a new listener with a number of targets.
         * @param object ...$targets The targets.
         * @return static A new listener.
         */
        public static function for (object ...$targets) : static {
            $listener = new static();
            $listener->targets->add(...$targets);
            return $listener;
        }

        /**
         * Specifies a number of callbacks to be run sequentially when a listener is activated.
         * @param callable ...$callbacks The callbacks.
         * @return static The listener.
         */
        public function do (callable ...$callbacks) : static {
            $this->handlers->add(...array_map([Handler::class, "from"], $callbacks));

            if (!empty($this->pendingReplays)) {
                foreach ($this->pendingReplays as $pending) {
                    if ($pending['type'] === 'latest') {
                        $this->replayLatestFromHistory($pending['rule'], $pending['bus']);
                    }
                    else {
                        $this->replayFromHistory($pending['rule'], $pending['n'], $pending['bus']);
                    }
                }

                $this->pendingReplays = [];
            }

            return $this;
        }

        /**
         * Gets the debounce window in milliseconds.
         * @return int The debounce window in milliseconds.
         */
        public function getDebounceMs () : int {
            return $this->debounceMs;
        }

        /**
         * Gets all signal rules of a listener that can be activated by a signal.
         * @param Signal $signal A signal.
         * @return SignalRuleset The rules that can be activated by the signal.
         */
        public function getEligibleSignalRules (Signal $signal) : SignalRuleset {
            $activatedRules = new SignalRuleset();

            /** @var SignalRule */
            foreach ($this->signalRuleset as $rule) {
                $cap = $rule->getCap();
                $ruleId = $rule->getId();
                $activations = $this->signalRuleActivations[$ruleId] ?? 0;

                switch ($rule->getMatchType()) {
                    case SignalMatchType::MATCH_ALL:
                        foreach ($rule->getPatterns() as $pattern) {
                            if (PatternAnalyser::isOverlap($pattern, $signal->name)) {
                                $this->signalRulePatternMatches[$ruleId] ??= [];
                                $this->signalRulePatternMatches[$ruleId][$pattern] = $signal;
                            }
                        }
                        $patternMatches = $this->signalRulePatternMatches[$ruleId] ?? [];
        
                        if (sizeof($patternMatches) === sizeof($rule->getPatterns())) {
                            if (!isset($cap) || $activations < $cap) {
                                $this->signalRuleActivations[$ruleId] = $activations + 1;
                                $this->signalRulePatternMatches[$ruleId] = [];
                                $activatedRules->add($rule);
                            }
                        }
                        break;

                    case SignalMatchType::MATCH_ANY:
                        if ($rule->matchesAny($signal->name)) {
                            if (!isset($cap) || $cap > $activations) {
                                $this->signalRuleActivations[$ruleId] = $activations + 1;
                                $this->signalRulePatternMatches[$ruleId] ??= [];
                                $this->signalRulePatternMatches[$ruleId][$signal->name] = $signal;
                                $activatedRules->add($rule);
                            }
                        }
                        break;

                    case SignalMatchType::MATCH_LATEST:
                    case SignalMatchType::MATCH_REPLAY:
                        if ($rule->matchesAny($signal->name)) {
                            $this->signalRulePatternMatches[$ruleId] ??= [];
                            $this->signalRulePatternMatches[$ruleId][$signal->name] = $signal;
                            $activatedRules->add($rule);
                        }
                        break;
                }
            }

            return $activatedRules;
        }

        /**
         * Gets the handlers of a listener.
         * @return HandlerCollection The handlers.
         */
        public function getHandlers () : HandlerCollection {
            return $this->handlers;
        }

        /**
         * Gets the name of a listener.
         * @return ?string The name of the listener.
         */
        public function getName () : ?string {
            return $this->name;
        }

        /**
         * Gets the dispatch priority of a listener.
         * @return int The priority.
         */
        public function getPriority () : int {
            return $this->priority;
        }

        /**
         * Gets the signal ruleset of a listener.
         * @return SignalRuleset The signal ruleset.
         */
        public function getSignalRuleset () : SignalRuleset {
            return $this->signalRuleset;
        }

        /**
         * Gets the targets of a listener.
         * @return HandlerCollection The targets.
         */
        public function getTargets () : TargetCollection {
            return $this->targets;
        }

        /**
         * Gets the number of times a listener is activated.
         * @return int The number of times the listener has been activated.
         */
        public function getTimesActivated () : int {
            return $this->timesActivated;
        }

        /**
         * Gets the group tags assigned to this listener.
         * @return string[] The tags.
         */
        public function getTags () : array {
            return $this->tags;
        }

        /**
         * Checks whether a listener has a limit on how many times it can be activated.
         * @return bool Whether the listener is capped.
         */
        public function hasCap () : bool {
            return isset($this->maxTimesActivated);
        }

        /**
         * Checks whether propagation was stopped during the most recent activation of this listener.
         * @return bool Whether propagation was stopped.
         */
        public function isPropagationStopped () : bool {
            return $this->propagationStopped;
        }

        /**
         * Checks whether a listener has predicates.
         * @return bool Whether the listener has predicates.
         */
        public function hasPredicates () : bool {
            return $this->predicates->getSize() > 0;
        }

        /**
         * Specifies a number of predicates to be evaluated when a listener finds a signal, requiring that at least one yields true.
         * @param callable ...$predicates The predicates.
         * @return static The listener.
         */
        public function if (callable ...$predicates) : static {
            $this->predicates->add(Predicate::orAny(array_map(fn ($p) => Predicate::from($p), $predicates)));
            return $this;
        }

        /**
         * Specifies a number predicate to be evaluated when a listener finds a signal, requiring that all yield true.
         * @param callable ...$predicates The predicates.
         * @return static The listener.
         */
        public function ifAll (callable ...$predicates) : static {
            $this->predicates->add(Predicate::andAll(array_map(fn ($p) => Predicate::from($p), $predicates)));
            return $this;
        }

        /**
         * Returns whether the listener is currently within its debounce
         * window and should suppress the next activation.
         * @return bool Whether the listener is debounced.
         */
        public function isDebounced () : bool {
            if ($this->debounceMs === 0) return false;

            $now = (int) (microtime(true) * 1000);

            return ($now - $this->lastActivationTime) < $this->debounceMs;
        }

        /**
         * Registers a number of signals and immediately activates the listener with the most recent matching historical emission, if any. Continues listening for future emissions.
         * @param string|BackedEnum ...$signals The signals.
         * @return static The listener.
         */
        public function latest (string|BackedEnum ...$signals) : static {
            $signals = $this->coerceSignals(...$signals);
            $rule = SignalRule::from($signals, SignalMatchType::MATCH_LATEST);
            $this->signalRuleset->add($rule);
            $bus = Bus::get($this->bus);
            $bus->register($this, $rule->getPatterns());

            if ($this->handlers->getSize() > 0) {
                $this->replayLatestFromHistory($rule, $bus);
            }
            else {
                $this->pendingReplays[] = ['type' => 'latest', 'rule' => $rule, 'bus' => $bus];
            }

            return $this;
        }

        /**
         * Assigns a name to the listener for later lookup via `Bus::getListener()`.
         * @param string $name The name.
         * @return static The listener.
         */
        public function named (string $name) : static {
            $this->name = $name;
            return $this;
        }

        /**
         * Specifies a number of signals for a listener to listen for one time.
         * @param string|BackedEnum ...$signals The signals.
         * @return static The listener.
         */
        public function once (string|BackedEnum ...$signals) : static {
            $signals = $this->coerceSignals(...$signals);
            $rule = SignalRule::from($signals, SignalMatchType::MATCH_ANY, 1);
            $this->signalRuleset->add($rule);
            Bus::get($this->bus)->register($this, $rule->getPatterns());
            return $this;
        }

        /**
         * Specifies a number of signals for a listener to listen for one time, requiring that all of them are emitted to active.
         * @param string|BackedEnum ...$signals The signals.
         * @return static The listener.
         */
        public function onceAll (string|BackedEnum ...$signals) : static {
            $signals = $this->coerceSignals(...$signals);
            $rule = SignalRule::from($signals, SignalMatchType::MATCH_ALL, 1);
            $this->signalRuleset->add($rule);
            Bus::get($this->bus)->register($this, $rule->getPatterns());
            return $this;
        }

        /**
         * Specifies a number of signals for a listener to listen for one time, requiring that any of them are emitted to active.
         * @param string|BackedEnum ...$signals The signals.
         * @return static The listener.
         */
        public function onceAny (string|BackedEnum ...$signals) : static {
            return $this->once(...$signals);
        }

        /**
         * Records the current timestamp as the last activation time,
         * starting the debounce cooldown window.
         */
        public function recordActivationTime () : void {
            $this->lastActivationTime = (int) (microtime(true) * 1000);
        }

        /**
         * Sets the dispatch priority of the listener. Listeners with higher priority values are activated first.
         * @param int $priority The priority.
         * @return static The listener.
         */
        public function setPriority (int $priority) : static {
            $this->priority = $priority;
            return $this;
        }

        /**
         * Assigns one or more group tags to the listener for bulk lifecycle operations such as `Bus::deregisterGroup()`.
         * Tags are deduplicated; calling `tag()` multiple times is safe.
         * @param string ...$tags The tags to assign.
         * @return static The listener.
         */
        public function tag (string ...$tags) : static {
            $this->tags = array_values(array_unique([...$this->tags, ...$tags]));
            return $this;
        }

        /**
         * Registers a number of signals and immediately activates the listener for each of the last $n matching historical emissions. Continues listening for future emissions.
         * @param int $n The number of historical emissions to replay.
         * @param string|BackedEnum ...$signals The signals.
         * @return static The listener.
         */
        public function replay (int $n, string|BackedEnum ...$signals) : static {
            $signals = $this->coerceSignals(...$signals);
            $rule = SignalRule::from($signals, SignalMatchType::MATCH_REPLAY, $n);
            $this->signalRuleset->add($rule);
            $bus = Bus::get($this->bus);
            $bus->register($this, $rule->getPatterns());

            if ($this->handlers->getSize() > 0) {
                $this->replayFromHistory($rule, $n, $bus);
            }
            else {
                $this->pendingReplays[] = ['type' => 'replay', 'rule' => $rule, 'n' => $n, 'bus' => $bus];
            }

            return $this;
        }

        /**
         * Specifies the bus of a listener.
         * @param string $bus The bus.
         * @return static The listener.
         */
        public function useBus (string $bus) : static {
            $this->bus = $bus;
            return $this;
        }

        /**
         * Registers a number of signals for a listener to listen for, requiring that any of them are emitted to active.
         * @param string|BackedEnum ...$signals The signals.
         * @return static The listener.
         */
        public function when (string|BackedEnum ...$signals) : static {
            $signals = $this->coerceSignals(...$signals);
            $rule = SignalRule::from($signals, SignalMatchType::MATCH_ANY);
            $this->signalRuleset->add($rule);
            Bus::get($this->bus)->register($this, $rule->getPatterns());
            return $this;
        }

        /**
         * Registers a number of signals for a listener to listen for, requiring that all of them are emitted to active.
         * @param string|BackedEnum ...$signals The signals.
         * @return static The listener.
         */
        public function whenAll (string|BackedEnum ...$signals) : static {
            $signals = $this->coerceSignals(...$signals);
            $rule = SignalRule::from($signals, SignalMatchType::MATCH_ALL);
            $this->signalRuleset->add($rule);
            Bus::get($this->bus)->register($this, $rule->getPatterns());
            return $this;
        }

        /**
         * Registers a number of signals for a listener to listen for, requiring that any of them are emitted to active.
         * @param string|BackedEnum ...$signals The signals.
         * @return static The listener.
         */
        public function whenAny (string|BackedEnum ...$signals) : static {
            return $this->when(...$signals);
        }
    }
?>