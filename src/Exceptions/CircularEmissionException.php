<?php
    /*/
	 * Project Name:    Wingman — Corvus — Circular Emission Exception
	 * Created by:      Angel Politis
	 * Creation Date:   Mar 11 2026
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Exceptions namespace.
    namespace Wingman\Corvus\Exceptions;

    # Import the following classes to the current scope.
    use RuntimeException;
    use Throwable;
    use Wingman\Corvus\Interfaces\Exception;

    /**
     * Thrown when the emission recursion depth exceeds the configured limit.
     *
     * This indicates a circular emission chain: a handler is directly or indirectly
     * emitting a signal that triggers itself, or a bridged bus is forming a cycle.
     *
     * @package Wingman\Corvus\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class CircularEmissionException extends RuntimeException implements Exception {
        /**
         * The name of the signal at which the recursion limit was hit.
         * @var string
         */
        protected string $signal;

        /**
         * The depth at which the recursion limit was exceeded.
         * @var int
         */
        protected int $depth;

        /**
         * Creates a new exception.
         * @param string $signal The name of the signal being dispatched.
         * @param int $depth The current recursion depth.
         * @param int $code An optional exception code.
         * @param Throwable|null $previous An optional previous throwable.
         */
        public function __construct (string $signal, int $depth, int $code = 0, ?Throwable $previous = null) {
            $this->signal = $signal;
            $this->depth = $depth;
            parent::__construct(
                "Circular emission detected for signal '$signal' at depth $depth.",
                $code,
                $previous
            );
        }

        /**
         * Gets the name of the signal that triggered the circular detection.
         * @return string The signal name.
         */
        public function getSignal () : string {
            return $this->signal;
        }

        /**
         * Gets the recursion depth at which the limit was exceeded.
         * @return int The depth.
         */
        public function getDepth () : int {
            return $this->depth;
        }
    }
?>