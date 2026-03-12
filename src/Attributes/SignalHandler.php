<?php
    /*/
     * Project Name:    Wingman — Corvus — Signal Handler Attribute
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 12 2026
    /*/

    # Use the Corvus.Attributes namespace.
    namespace Wingman\Corvus\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Marks a method as a signal handler to be discovered and registered
     * by the ListenerScanner. The attribute is repeatable, allowing a
     * single method to handle multiple distinct signal patterns.
     *
     * Example usage:
     *
     *     class NotificationService
     *     {
     *         #[SignalHandler("user.created")]
     *         #[SignalHandler("user.updated")]
     *         public function onUserChanged (HandlerExecution $e) : void { ... }
     *     }
     *
     * @package Wingman\Corvus\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
    class SignalHandler {
        /**
         * The signal patterns this handler listens for.
         * @var string[]
         */
        public readonly array $patterns;

        /**
         * The name of the bus this handler should be registered on.
         * @var string|null
         */
        public readonly ?string $bus;

        /**
         * The priority of the listener. Higher values activate first.
         * @var int
         */
        public readonly int $priority;

        /**
         * The match type controlling how multiple patterns are evaluated.
         * Accepts 'any', 'all', 'latest', or 'replay'.
         * @var string
         */
        public readonly string $match;

        /**
         * The maximum number of times the listener may be activated.
         * A value of -1 means unlimited.
         * @var int
         */
        public readonly int $cap;

        /**
         * The group tags to assign to the registered listener.
         * @var string[]
         */
        public readonly array $tags;

        /**
         * Creates a new SignalHandler attribute instance.
         * @param string $patterns One or more dot-namespaced signal patterns.
         * @param string $match The match mode: 'any', 'all', 'latest', or 'replay'.
         * @param int $priority The listener priority. Higher values run first.
         * @param int $cap Maximum activations. -1 means unlimited.
         * @param string[] $tags Group tags to assign to the listener.
         * @param string|null $bus The named bus to register on. Null uses the default.
         */
        public function __construct (
            public readonly string $pattern,
            string $match = "any",
            int $priority = 0,
            int $cap = -1,
            array $tags = [],
            ?string $bus = null,
        ) {
            $this->match = $match;
            $this->priority = $priority;
            $this->cap = $cap;
            $this->tags = $tags;
            $this->bus = $bus;
        }
    }
?>