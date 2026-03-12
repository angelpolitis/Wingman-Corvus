<?php
    /*/
	 * Project Name:    Wingman — Corvus — Emitter
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 17 2025
	 * Last Modified:   Mar 12 2026
    /*/

    # Use the Corvus namespace.
    namespace Wingman\Corvus;

    # Import the following classes to the current scope.
    use DateTime;
    use ReflectionException;
    use ReflectionMethod;
    use Wingman\Corvus\Attributes\ExcludeFromHistory;
    use Wingman\Corvus\Collections\PredicateCollection;
    use Wingman\Corvus\Collections\TargetCollection;
    use Wingman\Corvus\Interfaces\Identifiable;
    use Wingman\Corvus\Objects\Emission;
    use Wingman\Corvus\Objects\Predicate;
    use Wingman\Corvus\Objects\Signal;
    use Wingman\Corvus\Traits\HasId;

    /**
     * Represents a signal emitter.
     * @package Wingman\Corvus
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Emitter implements Identifiable {
        use HasId;

        /**
         * The bus (name) of an emitter.
         * @var string|null
         */
        protected ?string $bus = null;

        /**
         * The targets of an emitter.
         * @var TargetCollection
         */
        protected TargetCollection $targets;

        /**
         * The predicates of an emitter.
         * @var PredicateCollection
         */
        protected PredicateCollection $predicates;

        /**
         * The payload of an emitter.
         * @var array
         */
        protected array $payload = [];

        /**
         * Creates a new emitter.
         */
        protected function __construct () {
            $this->initialiseId();
            $this->targets = new TargetCollection();
            $this->predicates = new PredicateCollection();
        }

        /**
         * Creates a signal emission.
         * @param string $signal The signal pattern.
         * @return Emission A new signal emission.
         */
        protected function createSignalEmission (string $signal, TargetCollection $targets) : Emission {
            [$namespace, $type] = PatternAnalyser::analyse($signal);
            return new Emission(
                new Signal(Bus::get($this->bus)->getNextSignalId(), $type, $namespace, $signal),
                $this->payload,
                $targets,
                new DateTime(),
                $this->id
            );
        }
        
        /**
         * Resolves whether the calling method is decorated with the
         * ExcludeFromHistory attribute or whether the owning class carries
         * that attribute at the class level.
         * @return bool Whether emissions from this context should not be recorded in the Bus history.
         */
        protected function isExcludedFromHistory () : bool {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
            $caller = $trace[2] ?? null;

            if ($caller === null || !isset($caller["class"], $caller["function"])) {
                return false;
            }

            if (str_contains($caller["function"], "{closure")) {
                return false;
            }

            try {
                $method = new ReflectionMethod($caller["class"], $caller["function"]);
            }
            catch (ReflectionException) {
                return false;
            }

            if (!empty($method->getAttributes(ExcludeFromHistory::class))) return true;

            return !empty($method->getDeclaringClass()->getAttributes(ExcludeFromHistory::class));
        }

        /**
         * Creates a new emitter.
         * @return static A new emitter.
         */
        public static function create () : static {
            return new static();
        }

        /**
         * Emits all signals matching specified patterns.
         * @param string|string[] ...$signalPatterns The signal patterns to match against.
         * @return static The emitter.
         */
        public function emit (array|string ...$signalPatterns) : static {
            Bus::get($this->bus)->registerEmitter($this);

            $excludeFromHistory = $this->isExcludedFromHistory();
            $predicate = Predicate::andAll($this->predicates->getAll());
            $targets = null;

            $recordEmptyEmission = function (array $patterns, TargetCollection $targets) : void {
                foreach ($patterns as $pattern) {
                    $emission = $this->createSignalEmission($pattern, $targets);
                    Bus::get($this->bus)->addToHistory($emission);
                }
            };

            if ($this->targets->getSize() > 0) {
                $targets = $this->targets->filter($predicate);
                if ($targets->getSize() === 0) {
                    $recordEmptyEmission($signalPatterns, $targets);
                    return $this;
                }
            }
            else {
                if (!$predicate(null)) {
                    $recordEmptyEmission($signalPatterns, new TargetCollection());
                    return $this;
                }
                $targets = new TargetCollection();
            }

            # Normalise the signal patterns.
            $signalPatterns = PatternAnalyser::normalisePatterns($signalPatterns);

            /** @var Emission[] */
            $emissions = array_map(
                fn (string $pattern) => $this->createSignalEmission($pattern, $targets),
                $signalPatterns
            );

            Bus::get($this->bus)->dispatchBatch($emissions, $excludeFromHistory);

            return $this;
        }

        /**
         * Creates a new emitter with a number of targets.
         * @param object ...$targets The targets.
         * @return static A new emitter.
         */
        public static function for (object ...$targets) : static {
            $emitter = new static();
            $emitter->targets->add(...$targets);
            return $emitter;
        }

        /**
         * Gets the payload of an emitter.
         * @return array The payload.
         */
        public function getPayload () : array {
            return $this->payload;
        }

        /**
         * Gets the targets of an emitter.
         * @return array The targets.
         */
        public function getTargets () : TargetCollection {
            return $this->targets;
        }

        /**
         * Checks whether an emitter has predicates.
         * @return bool Whether the emitter has predicates.
         */
        public function hasPredicates () : bool {
            return $this->predicates->getSize() > 0;
        }

        /**
         * Specifies a number of predicates to be evaluated when an emitter emits a signal, requiring that at least one yields true.
         * @param callable ...$predicates The predicates.
         * @return static The emitter.
         */
        public function if (callable ...$predicates) : static {
            $this->predicates->add(Predicate::orAny(array_map(fn ($p) => Predicate::from($p), $predicates)));
            return $this;
        }

        /**
         * Specifies a number predicate to be evaluated when an emitter emits a signal, requiring that all yield true.
         * @param callable ...$predicates The predicates.
         * @return static The emitter.
         */
        public function ifAll (callable ...$predicates) : static {
            $this->predicates->add(Predicate::andAll(array_map(fn ($p) => Predicate::from($p), $predicates)));
            return $this;
        }

        /**
         * Specifies a number of predicates to be evaluated when an emitter emits a signal, requiring that at least one yields true.
         * @param callable ...$predicates The predicates.
         * @return static The emitter.
         */
        public function ifAny (callable ...$predicates) : static {
            return $this->if(...$predicates);
        }

        /**
         * Specifies the bus of an emitter.
         * @param string $bus The bus.
         * @return static The emitter.
         */
        public function useBus (string $bus) : static {
            $this->bus = $bus;
            return $this;
        }

        /**
         * Adds data to the payload of an emitter.
         * @param mixed ...$data The data to add to the payload.
         * @return static The emitter.
         */
        public function with (mixed ...$data) : static {
            array_push($this->payload, ...array_values($data));
            return $this;
        }

        /**
         * Overwrites the payload of an emitter with new data.
         * @param mixed ...$data The data to add to the payload.
         * @return static The emitter.
         */
        public function withOnly (mixed ...$data) : static {
            $this->payload = [];
            return $this->with(...$data);
        }
    }
?>