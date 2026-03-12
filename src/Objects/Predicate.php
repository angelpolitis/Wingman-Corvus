<?php
    /*/
	 * Project Name:    Wingman — Corvus — Predicate
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 15 2025
	 * Last Modified:   Mar 12 2026
    /*/

    # Use the Corvus.Objects namespace.
    namespace Wingman\Corvus\Objects;

    # Import the following classes to the current scope.
    use LogicException;

    /**
     * Represents a typed callable that must return a boolean value.
     *
     * A Predicate wraps any PHP callable and enforces that it returns a strict
     * boolean, throwing a LogicException otherwise.  It provides a full suite of
     * composable logical operations (and, or, not, xor, implies, …) that each
     * return a new Predicate, keeping instances immutable by convention.
     *
     * Static factory helpers (andAll, orAny, xorAll) operate over arrays of
     * Predicates, enabling concise multi-condition expressions.
     *
     * @package Wingman\Corvus\Objects
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    class Predicate {
        /**
         * The wrapped callable.
         * @var callable
         */
        protected mixed $callback;

        /**
         * Creates a new Predicate wrapping the given callable.
         * @param callable $callback The callable to wrap. Must return a boolean.
         */
        public function __construct (callable $callback) {
            $this->callback = $callback;
        }

        /**
         * Returns an array representation for debugging purposes.
         * @return array{callback: callable} The debug representation.
         */
        public function __debugInfo () : array {
            return ["callback" => $this->callback];
        }

        /**
         * Evaluates the predicate with the given value.
         * @param mixed $value The value to test.
         * @return bool The result of the predicate.
         */
        public function __invoke (mixed $value) : bool {
            return $this->test($value);
        }

        /**
         * Returns a human-readable string representation of the predicate.
         * @return string The string representation.
         */
        public function __toString () : string {
            return "Predicate(<callback>)";
        }

        /**
         * Returns a new Predicate that is true only when both this predicate and
         * the given predicate are true (logical conjunction).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function and (Predicate $other) : static {
            return new static(fn ($v) => $this->test($v) && $other->test($v));
        }

        /**
         * Returns a new Predicate that is true only when all predicates in the
         * given array are true. Returns true for an empty array (vacuous truth).
         * @param Predicate[] $predicates The predicates to combine.
         * @return static A new composed predicate.
         */
        public static function andAll (array $predicates) : static {
            return new static(fn ($v) => array_reduce(
                $predicates,
                fn (bool $carry, Predicate $p) => $carry && $p->test($v),
                true
            ));
        }

        /**
         * Returns a new Predicate equivalent to the converse implication of this
         * predicate and the given predicate: true unless the given predicate is
         * true and this predicate is false (i.e. $other → $this).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function converseImplies (Predicate $other) : static {
            return new static(fn ($v) => !$other->test($v) || $this->test($v));
        }

        /**
         * Returns a new Predicate that is true only when this predicate is false
         * and the given predicate is true (converse non-implication: $other ∧ ¬$this).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function converseNonImplication (Predicate $other) : static {
            return new static(fn ($v) => $other->test($v) && !$this->test($v));
        }

        /**
         * Returns a constant Predicate that always evaluates to false.
         * @return static A predicate that always returns false.
         */
        public static function false () : static {
            return new static(fn () => false);
        }

        /**
         * Creates a new Predicate wrapping the given callable.
         * @param callable $callback The callable to wrap. Must return a boolean.
         * @return static A new predicate.
         */
        public static function from (callable $callback) : static {
            return new static($callback);
        }

        /**
         * Returns a new Predicate that is true only when both this predicate and
         * the given predicate return the same boolean value (logical biconditional).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function iff (Predicate $other) : static {
            return new static(fn ($v) => $this->test($v) === $other->test($v));
        }

        /**
         * Returns a new Predicate representing the material implication of this
         * predicate and the given predicate: true unless this predicate is true
         * and the given predicate is false (i.e. $this → $other).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function implies (Predicate $other) : static {
            return new static(fn ($v) => !$this->test($v) || $other->test($v));
        }

        /**
         * Returns a new Predicate that is true only when at least one of this
         * predicate and the given predicate is false (logical NAND).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function nand (Predicate $other) : static {
            return new static(fn ($v) => !($this->test($v) && $other->test($v)));
        }

        /**
         * Returns a new Predicate that is true only when this predicate is true
         * and the given predicate is false (non-implication: $this ∧ ¬$other).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function nonImplication (Predicate $other) : static {
            return new static(fn ($v) => $this->test($v) && !$other->test($v));
        }

        /**
         * Returns a new Predicate that is true only when both this predicate and
         * the given predicate are false (logical NOR).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function nor (Predicate $other) : static {
            return new static(fn ($v) => !($this->test($v) || $other->test($v)));
        }

        /**
         * Returns a new Predicate that negates this predicate (logical NOT).
         * @return static A new composed predicate.
         */
        public function not () : static {
            return new static(fn ($v) => !$this->test($v));
        }

        /**
         * Returns a new Predicate that is true when at least one of this
         * predicate or the given predicate is true (logical disjunction).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function or (Predicate $other) : static {
            return new static(fn ($v) => $this->test($v) || $other->test($v));
        }

        /**
         * Returns a new Predicate that is true when at least one predicate in
         * the given array is true. Returns false for an empty array.
         * @param Predicate[] $predicates The predicates to combine.
         * @return static A new composed predicate.
         */
        public static function orAny (array $predicates) : static {
            return new static(fn ($v) => array_reduce(
                $predicates,
                fn (bool $carry, Predicate $p) => $carry || $p->test($v),
                false
            ));
        }

        /**
         * Evaluates the predicate with the given value and returns the result.
         * @param mixed $value The value to test.
         * @return bool The result of the predicate.
         * @throws LogicException If the wrapped callable does not return a boolean.
         */
        public function test (mixed $value) : bool {
            $result = ($this->callback)($value);

            if (!is_bool($result)) {
                throw new LogicException("Predicate callback must return a boolean, got " . gettype($result) . ".");
            }

            return $result;
        }

        /**
         * Returns a constant Predicate that always evaluates to true.
         * @return static A predicate that always returns true.
         */
        public static function true () : static {
            return new static(fn () => true);
        }

        /**
         * Returns a new Predicate that is true only when exactly one predicate in
         * the given array is true. Returns false for an empty array.
         * @param Predicate[] $predicates The predicates to combine.
         * @return static A new composed predicate.
         */
        public static function xorAll (array $predicates) : static {
            return new static(function ($v) use ($predicates) : bool {
                $trueCount = 0;

                foreach ($predicates as $p) {
                    if ($p->test($v)) $trueCount++;
                    if ($trueCount > 1) return false;
                }

                return $trueCount === 1;
            });
        }

        /**
         * Returns a new Predicate that is true only when exactly one of this
         * predicate and the given predicate is true (exclusive disjunction).
         * @param Predicate $other The other predicate.
         * @return static A new composed predicate.
         */
        public function xor (Predicate $other) : static {
            return new static(fn ($v) => $this->test($v) xor $other->test($v));
        }
    }
?>