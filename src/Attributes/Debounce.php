<?php
    /*/
     * Project Name:    Wingman — Corvus — Debounce Attribute
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 12 2026
    /*/

    # Use the Corvus.Attributes namespace.
    namespace Wingman\Corvus\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Suppresses repeated activations of a handler within a given time
     * window. When a debounced handler is triggered, the Bus records the
     * activation timestamp against the listener ID and signal pattern. Any
     * subsequent emission of the same pattern within the cooldown window
     * is silently dropped for that listener.
     *
     * The implementation is timestamp-based and works within PHP's
     * synchronous execution model. The cooldown is measured in
     * milliseconds and compared against the last activation time stored
     * in the Bus debounce registry.
     *
     * Example usage:
     *
     *     #[SignalHandler("search.query.changed")]
     *     #[Debounce(300)]
     *     public function onQueryChanged (HandlerExecution $e) : void { ... }
     *
     * @package Wingman\Corvus
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_METHOD)]
    class Debounce {
        /**
         * Creates a new Debounce attribute instance.
         * @param int $milliseconds The minimum number of milliseconds that
         *                          must elapse between two activations of
         *                          the decorated handler.
         */
        public function __construct (
            public readonly int $milliseconds,
        ) {}
    }
?>