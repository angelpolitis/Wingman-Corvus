<?php
    /*/
	 * Project Name:    Wingman — Corvus — Signal Match Type
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 17 2025
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Enums namespace.
    namespace Wingman\Corvus\Enums;

    /**
     * Represents a signal match.
     * @package Wingman\Corvus\Enums
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    enum SignalMatchType : string {
        /**
         * Matches every signal.
         * @var string
         */
        case MATCH_ALL = "all";

        /**
         * Matches any signal.
         * @var string
         */
        case MATCH_ANY = "any";

        /**
         * Matches the latest signal.
         * @var string
         */
        case MATCH_LATEST = "latest";

        /**
         * Matches the n-latest signals.
         * @var string
         */
        case MATCH_REPLAY = "replay";

        /**
         * Resolves a signal match type from a string or returns the existing instance.
         * @param static|string $modifier The signal match type to resolve.
         * @return static The resolved signal match type.
         */
        public static function resolve (self|string $modifier) : static {
            return $modifier instanceof static ? $modifier : static::from(strtolower($modifier));
        }
    }
?>