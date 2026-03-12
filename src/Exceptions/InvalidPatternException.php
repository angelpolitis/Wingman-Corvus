<?php
    /*/
	 * Project Name:    Wingman — Corvus — Invalid Pattern Exception
	 * Created by:      Angel Politis
	 * Creation Date:   Mar 11 2026
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Exceptions namespace.
    namespace Wingman\Corvus\Exceptions;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Throwable;
    use Wingman\Corvus\Interfaces\Exception;

    /**
     * Thrown when a signal pattern string is syntactically invalid.
     *
     * For example, an empty string or a pattern with illegal characters would
     * produce this exception.
     *
     * @package Wingman\Corvus\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InvalidPatternException extends InvalidArgumentException implements Exception {
        /**
         * The invalid pattern that was provided.
         * @var string
         */
        protected string $pattern;

        /**
         * Creates a new exception.
         * @param string $pattern The invalid pattern.
         * @param string $reason An optional human-readable explanation of why the pattern is invalid.
         * @param int $code An optional exception code.
         * @param Throwable|null $previous An optional previous throwable.
         */
        public function __construct (string $pattern, string $reason = "", int $code = 0, ?Throwable $previous = null) {
            $this->pattern = $pattern;
            $message = $reason === ""
                ? "The pattern '$pattern' is invalid."
                : "The pattern '$pattern' is invalid: $reason";
            parent::__construct($message, $code, $previous);
        }

        /**
         * Gets the invalid pattern that was provided.
         * @return string The pattern.
         */
        public function getPattern () : string {
            return $this->pattern;
        }
    }
?>