<?php
    /*/
	 * Project Name:    Wingman — Corvus — Bus
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 15 2025
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus namespace.
    namespace Wingman\Corvus;

    # Import the following classes to the current scope.
    use Throwable;
    use Wingman\Corvus\Collections\EmissionCollection;
    use Wingman\Corvus\Collections\EmitterCollection;
    use Wingman\Corvus\Collections\ListenerCollection;
    use Wingman\Corvus\Exceptions\BusAlreadyExistsException;
    use Wingman\Corvus\Exceptions\BusNotFoundException;
    use Wingman\Corvus\Exceptions\CircularEmissionException;
    use Wingman\Corvus\Exceptions\DeferredNotFoundException;
    use Wingman\Corvus\Exceptions\HandlerException;
    use Wingman\Corvus\Exceptions\InvalidCapException;
    use Wingman\Corvus\Objects\Emission;
    use Wingman\Corvus\Objects\SignalRule;
    use Wingman\Strux\Node;
    use Wingman\Strux\NodeList;

    /**
     * Represents a container of signals.
     * @package Wingman\Corvus
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Bus {
        /**
         * The default name for a bus instance if no name is provided.
          * @var string
         */
        public const string DEFAULT_NAME = "default";

        /**
         * The maximum allowed recursion depth for signal dispatch.
         * Exceeding this limit raises a CircularEmissionException.
         * @var int
         */
        public const int MAX_EMIT_DEPTH = 32;

        /**
         * The cache of named bus instances.
         * @var array<string, static>
         */
        protected static array $instances = [];

        /**
         * The name of a bus.
         * @var string|null
         */
        protected ?string $name = null;

        /**
         * The tree of the bus.
         * @var Node
         */
        protected Node $tree;

        /**
         * The flat map of the bus.
         * @var array<string, Node>
         */
        protected array $map = [];

        /**
         * The index emission history of the bus.
         * @var EmissionCollection
         */
        protected EmissionCollection $history;

        /**
         * The emission history per signal of the bus.
         * @var array<string, Emission[]>
         */
        protected array $historyMap = [];

        /**
         * The match cache of the bus.
         * `[pattern => [listenerId => true]]`
         * @var array<string, array<int, true>>
         */
        protected array $matchCache = [];

        /**
         * The emitters registry of the bus.
         * @var array<int, Emitter>
         */
        protected array $emitters = [];

        /**
         * The listeners registry of the bus.
         * @var array<int, Listener>
         */
        protected array $listeners = [];

        /**
         * A reverse map from listener id to the patterns it is registered under.
         * @var array<int, string[]>
         */
        protected array $listenerPatterns = [];

        /**
         * A map from listener id to its dispatch priority.
         * @var array<int, int>
         */
        protected array $listenerPriorities = [];

        /**
         * A map from tag to the set of listener IDs belonging to that group.
         * `[tag => [listenerId => true]]`
         * @var array<string, array<int, true>>
         */
        protected array $listenerGroups = [];

        /**
         * A map from listener name to listener, for named-listener lookup.
         * @var array<string, Listener>
         */
        protected array $namedListeners = [];

        /**
         * The middleware pipeline applied to every emission before listener dispatch.
         * Middleware signature: `fn (Emission $emission, callable $next): void`.
         * @var callable[]
         */
        protected array $middlewarePipeline = [];

        /**
         * A map of signal patterns to target bus names for cross-bus forwarding.
         * `[pattern => [targetBusName => true]]`
         * @var array<string, array<string, true>>
         */
        protected array $bridges = [];

        /**
         * The deferred emission queue, populated by `defer()` and flushed by `flush()`.
         * Each entry carries the task callable and an optional set of string tags.
         * `[id => ['task' => callable, 'tags' => string[]]]`
         * @var array<string, array{task: callable, tags: string[]}>
         */
        protected array $queue = [];

        /**
         * A monotonically increasing counter used to assign unique IDs to signals.
         * @var int
         */
        protected int $nextSignalId = 0;

        /**
         * The maximum number of emissions retained in the history. Null means unlimited.
         * @var int|null
         */
        protected ?int $maxHistorySize = null;

        /**
         * The current signal-dispatch recursion depth, used to detect circular emissions.
         * @var int
         */
        protected int $emitDepth = 0;

        /**
         * Creates a new bus.
         * @param string|null $name An optional name for the bus. If provided, the bus will be registered under the name and can be retrieved through `Bus::get($name)`.
         * @throws BusAlreadyExistsException If a bus with the provided name already exists.
         */
        public function __construct (?string $name = null) {
            $this->name = $name;
            $this->tree = new Node();
            $this->history = new EmissionCollection();

            if ($name) {
                if (isset(static::$instances[$name])) {
                    throw new BusAlreadyExistsException($name);
                }
                static::$instances[$name] = $this;
            }
        }

        /**
         * Destroys a bus instance and removes it from the cache if it has a name.
         */
        public function __destruct () {
            if ($this->name && isset(static::$instances[$this->name]) && static::$instances[$this->name] === $this) {
                unset(static::$instances[$this->name]);
            }
        }

        /**
         * Performs the actual listener dispatch for one emission, respecting the deduplication map and propagation-stop semantics.
         * @param Emission $emission The emission to dispatch.
         * @param array $activatedRules A deduplication map of already-activated listener:rule pairs, passed by reference.
         * @param bool $propagationStopped Set to true if a handler stopped propagation, passed by reference.
         */
        private function dispatchEmissionCore (Emission $emission, array &$activatedRules, bool &$propagationStopped) : void {
            /** @var Listener */
            foreach ($this->findListeners($emission->signal->name) as $listener) {
                if (!$listener->canBeActivated()) continue;

                $eligibleRules = $listener->getEligibleSignalRules($emission->signal);

                /** @var SignalRule */
                foreach ($eligibleRules as $ruleIndex => $rule) {
                    $key = $listener->getId() . ':' . $ruleIndex;

                    if (isset($activatedRules[$key])) continue;

                    try {
                        $listener->activate($rule, $emission->targets, $emission->payload);
                    }
                    catch (CircularEmissionException $e) {
                        throw $e;
                    }
                    catch (Throwable $e) {
                        throw new HandlerException($emission->signal->name, $e);
                    }

                    $activatedRules[$key] = true;

                    if ($listener->isPropagationStopped()) {
                        $propagationStopped = true;
                        return;
                    }
                }
            }
        }

        /**
         * Rebuilds the per-signal history map from the current history collection.
         */
        private function rebuildHistoryMap () : void {
            $this->historyMap = [];

            foreach ($this->history->getAll() as $emission) {
                $this->historyMap[$emission->signal->name][] = $emission;
            }
        }

        /**
         * Trims the history to the configured maximum size, keeping the most recent emissions.
         * Also rebuilds the per-signal history map to stay consistent.
         */
        private function trimHistory () : void {
            $trimmed = array_slice($this->history->getAll(), -$this->maxHistorySize);
            $this->history = new EmissionCollection();

            foreach ($trimmed as $emission) {
                $this->history[] = $emission;
            }

            $this->rebuildHistoryMap();
        }

        /**
         * Builds a callable middleware pipeline around a core dispatch callable.
         * @param callable $core The innermost dispatch callable.
         * @return callable The composed pipeline callable.
         */
        protected function buildPipeline (callable $core) : callable {
            $pipeline = $core;

            foreach (array_reverse($this->middlewarePipeline) as $middleware) {
                $next = $pipeline;
                $pipeline = fn (Emission $emission) => $middleware($emission, $next);
            }

            return $pipeline;
        }

        /**
         * Dispatches a signal emission to all matching listeners via the middleware pipeline.
         * Also forwards to any bridged buses after local dispatch completes.
         * @param Emission $emission A signal emission.
         * @param array $activatedRules A deduplication map of already-activated listener:rule pairs, passed by reference.
         * @return bool Whether propagation was stopped by a handler.
         * @throws CircularEmissionException If the dispatch recursion depth exceeds MAX_EMIT_DEPTH.
         * @throws HandlerException If a handler throws an exception during dispatch.
         */
        protected function dispatchEmission (Emission $emission, array &$activatedRules) : bool {
            if ($this->emitDepth >= static::MAX_EMIT_DEPTH) {
                throw new CircularEmissionException($emission->signal->name, $this->emitDepth);
            }

            $this->emitDepth++;
            $propagationStopped = false;

            try {
                $core = function (Emission $emission) use (&$activatedRules, &$propagationStopped) : void {
                    $this->dispatchEmissionCore($emission, $activatedRules, $propagationStopped);
                };

                $pipeline = $this->buildPipeline($core);
                $pipeline($emission);

                if (!$propagationStopped) {
                    foreach ($this->bridges as $bridgePattern => $targetBuses) {
                        if (!PatternAnalyser::isMatch($bridgePattern, $emission->signal->name)) continue;
                        foreach ($targetBuses as $targetBusName => $_) {
                            if (!static::exists($targetBusName)) continue;
                            $forwardActivated = [];
                            static::get($targetBusName)->dispatchEmission($emission, $forwardActivated);
                        }
                    }
                }
            }
            finally {
                $this->emitDepth--;
            }

            return $propagationStopped;
        }

        /**
         * Invalidates the cache of the bus for a specified pattern.
         * @param string $pattern A pattern.
         */
        protected function invalidateCacheForPattern (string $pattern) : void {
            foreach ($this->matchCache as $cachedPattern => $_) {
                if (PatternAnalyser::isOverlap($cachedPattern, $pattern)) {
                    unset($this->matchCache[$cachedPattern]);
                }
            }
        }
        
        /**
         * Resolves a set of ids to a collection of listeners.
         * @param int[] $idSet The ids to resolve.
         * @return ListenerCollection The listeners.
         */
        protected function resolveCachedListeners (array $idSet) : ListenerCollection {
            $result = new ListenerCollection();

            foreach ($idSet as $id => $_) {
                if (isset($this->listeners[$id])) {
                    $result->add($this->listeners[$id]);
                }
            }

            return $result;
        }

        /**
         * Adds a signal emission to the history of the bus.
         * If a maximum history size has been set and the limit is exceeded, the oldest entries are discarded.
         * @param Emission $emission A signal emission.
         */
        public function addToHistory (Emission $emission) : void {
            $this->history[] = $emission;
            $this->historyMap[$emission->signal->name] ??= [];
            $this->historyMap[$emission->signal->name][] = $emission;

            if ($this->maxHistorySize !== null && count($this->history) > $this->maxHistorySize) {
                $this->trimHistory();
            }
        }

        /**
         * Deregisters a listener from the bus, removing it from all registered signal patterns and invalidating any cached matches.
         * @param Listener $listener A listener.
         */
        public function deregister (Listener $listener) : void {
            $listenerId = $listener->getId();

            foreach ($this->listenerPatterns[$listenerId] ?? [] as $pattern) {
                $node = $this->tree->get($pattern);

                if ($node) {
                    $content = $node->getContent() ?: [];
                    unset($content[$listenerId]);
                    $node->setContent($content);
                }

                if (isset($this->map[$pattern])) {
                    $nodeContent = $this->map[$pattern]->getContent() ?: [];

                    if (empty($nodeContent)) {
                        unset($this->map[$pattern]);
                    }
                }
            }

            unset($this->listenerPatterns[$listenerId], $this->listenerPriorities[$listenerId], $this->listeners[$listenerId]);

            if ($listener->getName() !== null) {
                unset($this->namedListeners[$listener->getName()]);
            }

            foreach ($listener->getTags() as $tag) {
                unset($this->listenerGroups[$tag][$listenerId]);

                if (empty($this->listenerGroups[$tag])) {
                    unset($this->listenerGroups[$tag]);
                }
            }

            foreach ($this->matchCache as $pattern => $idSet) {
                if (isset($idSet[$listenerId])) {
                    unset($this->matchCache[$pattern]);
                }
            }
        }

        /**
         * Deregisters all listeners belonging to a group (tag), removing them from the bus entirely.
         * @param string $tag The group tag.
         * @return static The bus.
         */
        public function deregisterGroup (string $tag) : static {
            foreach (array_keys($this->listenerGroups[$tag] ?? []) as $listenerId) {
                if (isset($this->listeners[$listenerId])) {
                    $this->deregister($this->listeners[$listenerId]);
                }
            }

            return $this;
        }

        /**
         * Queues a deferred emission to be dispatched when `flush()` is called.
         * Returns a unique string ID that can be used to tag or cancel the entry before flush.
         * @param Emitter $emitter The emitter.
         * @param array|string ...$signalPatterns The signal patterns to emit.
         * @return string The unique deferred emission ID.
         */
        public function defer (Emitter $emitter, array|string ...$signalPatterns) : string {
            $id = uniqid("deferred_", more_entropy: true);
            $this->queue[$id] = ["task" => fn () => $emitter->emit(...$signalPatterns), "tags" => []];
            return $id;
        }

        /**
         * Cancels a queued deferred emission by its ID.
         * @param string $id The deferred emission ID returned by `defer()`.
         * @return static The bus.
         * @throws DeferredNotFoundException If no deferred emission with the given ID exists.
         */
        public function cancel (string $id) : static {
            if (!isset($this->queue[$id])) {
                throw new DeferredNotFoundException($id);
            }

            unset($this->queue[$id]);
            return $this;
        }

        /**
         * Cancels all queued deferred emissions that carry a given tag.
         * @param string $tag The tag to cancel emissions for.
         * @return static The bus.
         */
        public function cancelGroup (string $tag) : static {
            foreach ($this->queue as $id => $entry) {
                if (in_array($tag, $entry["tags"], strict: true)) {
                    unset($this->queue[$id]);
                }
            }

            return $this;
        }

        /**
         * Assigns one or more tags to a queued deferred emission for later bulk operations.
         * @param string $id The deferred emission ID returned by `defer()`.
         * @param string ...$tags The tags to assign.
         * @return static The bus.
         * @throws DeferredNotFoundException If no deferred emission with the given ID exists.
         */
        public function tagDeferred (string $id, string ...$tags) : static {
            if (!isset($this->queue[$id])) {
                throw new DeferredNotFoundException($id);
            }

            $this->queue[$id]["tags"] = array_values(array_unique([...$this->queue[$id]["tags"], ...$tags]));
            return $this;
        }

        /**
         * Gets the number of deferred emissions currently in the queue, optionally filtered by tag.
         * @param string|null $tag An optional tag to filter by.
         * @return int The count.
         */
        public function getPendingCount (?string $tag = null) : int {
            if ($tag === null) return count($this->queue);

            return count(array_filter(
                $this->queue,
                fn ($entry) => in_array($tag, $entry["tags"], strict: true)
            ));
        }

        /**
         * Checks whether the deferred queue contains at least one pending emission, optionally filtered by tag.
         * @param string|null $tag An optional tag to filter by.
         * @return bool Whether there are pending emissions.
         */
        public function hasPending (?string $tag = null) : bool {
            return $this->getPendingCount($tag) > 0;
        }

        /**
         * Dispatches a batch of emissions produced by a single Emitter::emit() cycle.
         * All emissions in the batch share the same deduplication map so that a listener:rule
         * pair cannot fire more than once per emit cycle, even when multiple signal patterns
         * are emitted in a single call. Each emission is recorded to history before dispatch.
         * Propagation stop in one emission halts the remainder of the batch.
         * @param Emission[] $emissions The emissions to dispatch, in order.
         * @return bool Whether propagation was stopped.
         * @throws CircularEmissionException If the dispatch recursion depth exceeds MAX_EMIT_DEPTH.
         * @throws HandlerException If a handler throws an exception during dispatch.
         */
        public function dispatchBatch (array $emissions) : bool {
            $activatedRules = [];

            foreach ($emissions as $emission) {
                $this->addToHistory($emission);

                if ($this->dispatchEmission($emission, $activatedRules)) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Checks whether a bus with a given name exists.
         * @param string|null $name The name of the bus to check. If null, checks for the default bus.
         * @return bool Whether a bus with the given name exists.
         */
        public static function exists (?string $name = null) : bool {
            return isset(static::$instances[$name ?? static::DEFAULT_NAME]);
        }

        /**
         * Finds all historical emissions whose signal name matches a given pattern, in chronological order.
         * @param string $pattern A signal pattern.
         * @return EmissionCollection The matching emissions.
         */
        public function findEmissions (string $pattern) : EmissionCollection {
            $result = new EmissionCollection();

            foreach ($this->history->getAll() as $emission) {
                if (PatternAnalyser::isMatch($pattern, $emission->signal->name)) {
                    $result->add($emission);
                }
            }

            return $result;
        }

        /**
         * Gets the listeners that match a signal pattern.
         * @param string $pattern A pattern.
         * @return ListenerCollection The listeners.
         */
        public function findListeners (string $pattern) : ListenerCollection {
            # 1. Check the cache in case the listeners for the pattern have already been cached.
            if (isset($this->matchCache[$pattern])) {
                return $this->resolveCachedListeners($this->matchCache[$pattern]);
            }

            # 2. Collect candidate listener IDs (deduped).
            $seen = [];

            # 2a. Collect via a direct match through the flat map.
            if (isset($this->map[$pattern])) {
                foreach ($this->map[$pattern]->getContent() as $listenerId => $_) {
                    $seen[$listenerId] = true;
                }
            }

            # 2b. Collect via wildcard / structural match through tree traversal.
            foreach ($this->findMatchingNodes($pattern) as $node) {
                foreach ($node->getContent() as $listenerId => $_) {
                    $seen[$listenerId] = true;
                }
            }

            # 3. Sort by listener priority in descending order so higher-priority listeners fire first.
            uksort($seen, fn ($a, $b) => ($this->listenerPriorities[$b] ?? 0) <=> ($this->listenerPriorities[$a] ?? 0));

            # 4. Store the sorted ID set in the cache.
            $this->matchCache[$pattern] = $seen;

            # 5. Resolve to a collection in priority order.
            return $this->resolveCachedListeners($seen);
        }
        
        /**
         * Uses a state-machine style breadth-first matcher to get all nodes that match a signal pattern.
         *
         * Each traversal state carries a 'sticky' flag that is set to true whenever the
         * current tree-node is a registered ** wildcard. A sticky state re-queues itself
         * at the next segment index on every iteration, allowing a single ** tree-node to
         * consume arbitrarily many input segments before the pattern is exhausted.
         *
         * @param string $pattern A pattern.
         * @return NodeList The list of nodes matching the pattern.
         */
        public function findMatchingNodes (string $pattern) : NodeList {
            $segments = explode(PatternAnalyser::NAMESPACE_ACCESSOR, $pattern);
            $count = count($segments);

            # Each state is: ["node" => Node, "index" => int, "sticky" => bool]
            $states = [
                ["node" => $this->tree, "index" => 0, "sticky" => false]
            ];

            $final = [];

            while ($states) {
                $newStates = [];

                foreach ($states as $state) {
                    $node   = $state["node"];
                    $index  = $state["index"];
                    $sticky = $state["sticky"];

                    # Reached end of pattern — capture this tree node.
                    if ($index === $count) {
                        $final[] = $node;
                        continue;
                    }

                    # ** tree-node: consume one more segment and remain sticky, allowing it
                    # to match arbitrarily-deep suffixes until the pattern is exhausted.
                    if ($sticky) {
                        $newStates[] = ["node" => $node, "index" => $index + 1, "sticky" => true];
                    }

                    $seg = $segments[$index];

                    # 1) Emitted segment is a multi-level wildcard: traverse all concrete children
                    #    at this depth, staying on the same wildcard index so they also run below.
                    if ($seg === PatternAnalyser::MULTI_LEVEL_WILDCARD || $seg === '#') {
                        foreach ($node->getChildren() as $childName => $childNode) {
                            if ($childName === PatternAnalyser::SINGLE_LEVEL_WILDCARD
                                || $childName === PatternAnalyser::MULTI_LEVEL_WILDCARD
                                || $childName === '#') {
                                continue;
                            }

                            $newStates[] = ["node" => $childNode, "index" => $index, "sticky" => false];
                        }

                        continue;
                    }

                    # 2) Emitted segment is a single-level wildcard: advance past one segment
                    #    for every non-wildcard child registered in the tree.
                    if ($seg === PatternAnalyser::SINGLE_LEVEL_WILDCARD) {
                        foreach ($node->getChildren() as $childName => $childNode) {
                            if ($childName === PatternAnalyser::SINGLE_LEVEL_WILDCARD) continue;

                            $newStates[] = ["node" => $childNode, "index" => $index + 1, "sticky" => false];
                        }

                        continue;
                    }

                    # 3) Literal segment: try exact child, registered * child, and registered ** child.
                    $literalChild = $node->getChild($seg);
                    if ($literalChild) {
                        $newStates[] = ["node" => $literalChild, "index" => $index + 1, "sticky" => false];
                    }

                    $wildcardChild = $node->getChild(PatternAnalyser::SINGLE_LEVEL_WILDCARD);
                    if ($wildcardChild) {
                        $newStates[] = ["node" => $wildcardChild, "index" => $index + 1, "sticky" => false];
                    }

                    $multiWildcardChild = $node->getChild(PatternAnalyser::MULTI_LEVEL_WILDCARD) ?? $node->getChild('#');
                    if ($multiWildcardChild) {
                        $newStates[] = ["node" => $multiWildcardChild, "index" => $index + 1, "sticky" => true];
                    }
                }

                $states = $newStates;
            }

            return new NodeList(array_unique($final, SORT_REGULAR));
        }

        /**
         * Dispatches queued deferred emissions in the order they were enqueued.
         * When `$tag` is given, only emissions carrying that tag are dispatched and removed;
         * untagged or differently-tagged entries remain in the queue.
         * When `$tag` is null, the entire queue is dispatched and cleared.
         * @param string|null $tag An optional tag to flush selectively.
         */
        public function flush (?string $tag = null) : void {
            if ($tag === null) {
                $queue = $this->queue;
                $this->queue = [];

                foreach ($queue as $entry) {
                    ($entry["task"])();
                }

                return;
            }

            foreach ($this->queue as $id => $entry) {
                if (in_array($tag, $entry["tags"], strict: true)) {
                    unset($this->queue[$id]);
                    ($entry["task"])();
                }
            }
        }

        /**
         * Registers a cross-bus forwarding rule: any emission on this bus whose signal matches `$pattern`
         * will also be dispatched to the bus named `$targetBus` after local listeners have run.
         * @param string $pattern The signal pattern to match for forwarding.
         * @param string $targetBus The name of the target bus.
         * @return static The bus.
         * @throws BusNotFoundException If the target bus does not exist.
         */
        public function forward (string $pattern, string $targetBus) : static {
            if (!static::exists($targetBus)) {
                throw new BusNotFoundException($targetBus);
            }

            $this->bridges[$pattern][$targetBus] = true;
            return $this;
        }

        /**
         * Removes a previously registered forwarding rule.
         * If `$targetBus` is null, all forwarding rules for `$pattern` are removed.
         * @param string $pattern The signal pattern.
         * @param string|null $targetBus The target bus name, or null to remove all targets for the pattern.
         * @return static The bus.
         */
        public function unforward (string $pattern, ?string $targetBus = null) : static {
            if ($targetBus === null) {
                unset($this->bridges[$pattern]);
            }
            else {
                unset($this->bridges[$pattern][$targetBus]);

                if (empty($this->bridges[$pattern])) {
                    unset($this->bridges[$pattern]);
                }
            }

            return $this;
        }

        /**
         * Gets a bus instance by name.
         * @param string|null $name The name of the bus.
         * @return static The bus instance.
         */
        public static function get (?string $name = null) : static {
            if ($name === null) {
                return static::$instances[static::DEFAULT_NAME] ?? new static(static::DEFAULT_NAME);
            }
            return static::$instances[$name] ?? new static($name);
        }

        /**
         * Gets the registered emitters.
         * @return EmitterCollection The emitters.
         */
        public function getEmitters () : EmitterCollection {
            $collection = new EmitterCollection();

            foreach ($this->emitters as $emitter) {
                $collection->add($emitter);
            }

            return $collection;
        }

        /**
         * Gets the emission history of the bus.
         * @return EmissionCollection The history.
         */
        public function getHistory () : EmissionCollection {
            return $this->history;
        }

        /**
         * Gets the emission history per signal of the bus.
         * @return array<string, EmissionCollection>
         */
        public function getHistoryPerSignal () : array {
            return $this->historyMap;
        }

        /**
         * Gets a named listener registered on this bus.
         * @param string $name The name of the listener.
         * @return Listener|null The listener, if found.
         */
        public function getListener (string $name) : ?Listener {
            return $this->namedListeners[$name] ?? null;
        }

        /**
         * Gets all listeners belonging to a group (tag) as a collection.
         * @param string $tag The group tag.
         * @return ListenerCollection The listeners in the group.
         */
        public function getGroup (string $tag) : ListenerCollection {
            $collection = new ListenerCollection();

            foreach (array_keys($this->listenerGroups[$tag] ?? []) as $listenerId) {
                if (isset($this->listeners[$listenerId])) {
                    $collection->add($this->listeners[$listenerId]);
                }
            }

            return $collection;
        }

        /**
         * Gets the registered listeners.
         * @return ListenerCollection The listeners.
         */
        public function getListeners () : ListenerCollection {
            $collection = new ListenerCollection();

            foreach ($this->listeners as $listener) {
                $collection->add($listener);
            }

            return $collection;
        }

        /**
         * Gets a unique sequential ID for a new signal.
         * @return int An ID.
         */
        public function getNextSignalId () : int {
            return ++$this->nextSignalId;
        }

        /**
         * Gets the emission history of the bus for a specific signal.
         * @param string $signal A signal.
         * @return EmissionCollection The history for the signal.
         */
        public function getSignalHistory (string $signal) : EmissionCollection {
            return new EmissionCollection($this->historyMap[$signal] ?? []);
        }

        /**
         * Gets the last emission of a signal recorded in the bus.
         * @param string $signal A signal.
         * @return Emission|null The last emission, if any.
         */
        public function getSignalLastEmission (string $signal) : ?Emission {
            return $this->getSignalHistory($signal)->getLast();
        }

        /**
         * Checks whether a signal has been emitted before.
         * @param string $signal A signal.
         * @return bool Whether the signal has been emitted before.
         */
        public function hasBeenEmitted (string $signal) : bool {
            return sizeof($this->historyMap[$signal] ?? []) > 0;
        }

        /**
         * Appends a middleware callable to the dispatch pipeline.
         * Middleware receives the emission and a `$next` callable; it must call `$next($emission)` to continue dispatch.
         * Middleware signature: `fn(Emission $emission, callable $next): void`.
         * @param callable $middleware The middleware.
         * @return static The bus.
         */
        public function pipe (callable $middleware) : static {
            $this->middlewarePipeline[] = $middleware;
            return $this;
        }

        /**
         * Registers a listener to the bus.
         * @param Listener $listener A listener.
         * @param string[] $patterns The signal patterns.
         */
        public function register (Listener $listener, array $patterns) : void {
            $listenerId = $this->registerListener($listener);

            $this->listenerPriorities[$listenerId] = $listener->getPriority();

            if ($listener->getName() !== null) {
                $this->namedListeners[$listener->getName()] = $listener;
            }

            foreach ($listener->getTags() as $tag) {
                $this->listenerGroups[$tag][$listenerId] = true;
            }

            foreach ($patterns as $pattern) {
                $node = $this->tree->get($pattern);

                if (!$node) {
                    $node = new Node();
                    $this->tree->set($pattern, $node);
                }
                
                /** @var int[] */
                $content = $node->getContent() ?: [];
                $content[$listenerId] = true;
                $node->setContent($content);

                if (!isset($this->map[$pattern]) && !PatternAnalyser::containsWildcard($pattern)) {
                    $this->map[$pattern] = $node;
                }

                $this->invalidateCacheForPattern($pattern);
                $this->listenerPatterns[$listenerId][] = $pattern;
            }
        }

        /**
         * Registers an emitter to the bus.
         * @param Emitter $emitter A emitter.
         * @return int The id of the emitter.
         */
        public function registerEmitter (Emitter $emitter) : int {
            $id = $emitter->getId();

            if (!isset($this->emitters[$id])) {
                $this->emitters[$id] = $emitter;
            }

            return $id;
        }

        /**
         * Registers a listener to the bus.
         * @param Listener $listener A listener.
         * @return int The id of the listener.
         */
        public function registerListener (Listener $listener) : int {
            $id = $listener->getId();

            if (!isset($this->listeners[$id])) {
                $this->listeners[$id] = $listener;
            }

            return $id;
        }

        /**
         * Removes a bus instance from the cache.
         * @param string|null $name The name of the bus to remove. If null, removes the default bus.
         */
        public static function remove (?string $name = null) : void {
            unset(static::$instances[$name ?? static::DEFAULT_NAME]);
        }

        /**
         * Removes all bus instances from the cache.
         */
        public static function reset () : void {
            static::$instances = [];
        }

        /**
         * Sets an existing bus in the cache.
         * @param string $name The name of the bus.
         * @param Bus $bus The bus.
         */
        public static function set (string $name, Bus $bus) : void {
            static::$instances[$name] = $bus;
        }

        /**
         * Sets the maximum number of emissions retained in the history.
         * If the current history already exceeds the new limit, it is trimmed immediately.
         * @param int $limit The maximum number of emissions to retain. Must be at least 1.
         * @return static The bus.
         * @throws InvalidCapException If `$limit` is less than 1.
         */
        public function withHistoryLimit (int $limit) : static {
            if ($limit < 1) {
                throw new InvalidCapException($limit);
            }

            $this->maxHistorySize = $limit;

            if (count($this->history) > $this->maxHistorySize) {
                $this->trimHistory();
            }

            return $this;
        }
    }
?>