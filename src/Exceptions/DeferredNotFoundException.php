<?php
    /*/
	 * Project Name:    Wingman — Corvus — Deferred Not Found Exception
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
     * Thrown when attempting to operate on a deferred emission ID that does not exist in the queue.
     * @package Wingman\Corvus\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class DeferredNotFoundException extends RuntimeException implements Exception {
        /**
         * The deferred emission ID that was not found.
         * @var string
         */
        protected string $deferredId;

        /**
         * Creates a new exception.
         * @param string $deferredId The deferred emission ID that was not found.
         * @param int $code An optional exception code.
         * @param Throwable|null $previous An optional previous throwable.
         */
        public function __construct (string $deferredId, int $code = 0, ?Throwable $previous = null) {
            $this->deferredId = $deferredId;
            parent::__construct("No deferred emission with the ID '$deferredId' exists in the queue.", $code, $previous);
        }

        /**
         * Gets the deferred emission ID that was not found.
         * @return string The deferred ID.
         */
        public function getDeferredId () : string {
            return $this->deferredId;
        }
    }
?>