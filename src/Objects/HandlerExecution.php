<?php
    /*/
	 * Project Name:    Wingman — Corvus — Handler Execution
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 17 2025
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Objects namespace.
    namespace Wingman\Corvus\Objects;

    # Import the following classes to the current scope.
    use DateTimeImmutable;
    use Wingman\Corvus\Collections\SignalCollection;

    /**
     * Represents the execution of a handler.
     *
     * The execution object is passed to every handler callback. A handler may call
     * `stopPropagation()` to prevent any further handlers or listeners from being
     * activated within the current emit cycle.
     *
     * @package Wingman\Corvus\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class HandlerExecution {
        /**
         * Whether the current propagation has been stopped by this execution.
         * @var bool
         */
        protected bool $propagationStopped = false;

        /**
         * Creates a new handler execution.
         * @param SignalCollection $signals The signals that triggered the execution.
         * @param object|null $target The target of the execution.
         * @param array $payload The payload of the execution.
         * @param Handler $handler The handler that was executed.
         * @param DateTimeImmutable $date The date of the execution.
         */
        public function __construct (
            public readonly SignalCollection $signals,
            public readonly ?object $target,
            public readonly array $payload,
            public readonly Handler $handler,
            public readonly DateTimeImmutable $date
        ) {}

        /**
         * Checks whether propagation has been stopped by this execution.
         * @return bool Whether propagation has been stopped.
         */
        public function isPropagationStopped () : bool {
            return $this->propagationStopped;
        }

        /**
         * Stops further handlers and listeners from being activated within the current emit cycle.
         */
        public function stopPropagation () : void {
            $this->propagationStopped = true;
        }
    }
?>