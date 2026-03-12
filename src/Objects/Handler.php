<?php
    /*/
	 * Project Name:    Wingman — Corvus — Handler
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 18 2025
	 * Last Modified:   Nov 18 2025
    /*/

    # Use the Corvus.Objects namespace.
    namespace Wingman\Corvus\Objects;

    /**
     * Represents a handler.
     * @package Wingman\Corvus\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Handler {
        /**
         * The callback of a handler.
         * @var callable
         */
        protected $callback;

        /**
         * Creates a new handler.
         * @param callable $callback The callback.
         */
        public function __construct (callable $callback) {
            $this->callback = $callback;
        }

        /**
         * Runs a handler as a function.
         * @param mixed ...$args The arguments to pass to the handler's callback.
         * @return mixed The return value of the handler's callback.
         */
        public function __invoke (mixed ...$args) : mixed {
            return call_user_func($this->callback, ...$args);
        }
        
        /**
         * Creates a new handler.
         * @param callable $callback The callback.
         * @return static The created handler.
         */
        public static function from (callable $callback) : static {
            return new static($callback);
        }

        /**
         * Runs a handler.
         * @param mixed ...$args The arguments to pass to the handler's callback.
         * @return mixed The return value of the handler's callback.
         */
        public function run (mixed ...$args) : mixed {
            return call_user_func($this->callback, ...$args);
        }
    }
?>