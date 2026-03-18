<?php
    /**
     * Project Name:    Wingman Corvus - Signal Rule
     * Created by:      Angel Politis
     * Creation Date:   Nov 17 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus.Objects namespace.
    namespace Wingman\Corvus\Objects;

    # Import the following classes to the current scope.
    use Wingman\Corvus\Enums\SignalMatchType;
    use Wingman\Corvus\Exceptions\InvalidCapException;
    use Wingman\Corvus\Interfaces\Identifiable;
    use Wingman\Corvus\Traits\HasId;
    use Wingman\Corvus\PatternAnalyser;

    /**
     * Represents a signal rule.
     * @package Wingman\Corvus\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class SignalRule implements Identifiable {
        use HasId;

        /**
         * The match type of a signal rule.
         * @var SignalMatchType
         */
        protected SignalMatchType $matchType = SignalMatchType::MATCH_ANY;

        /**
         * The patterns of a signal rule.
         * @var string[]
         */
        protected array $patterns;

        /**
         * The maximum number of time a signal can be activated.
         * @var int|null
         */
        protected ?int $cap = null;

        /**
         * Creates a new signal rule.
         * @param string|string[] $pattern The patterns.
         * @param SignalMatchType $matchType The match type.
         * @param int|null $cap The maximum number of times the rule can be activated.
         * @throws InvalidCapException If the cap is less than 0.
         */
        public function __construct (array|string $pattern, SignalMatchType $matchType = SignalMatchType::MATCH_ANY, ?int $cap = null) {
            $this->initialiseId();

            if ($cap !== null && $cap < 0) {
                throw new InvalidCapException($cap);
            }

            $this->patterns = PatternAnalyser::normalisePatterns($pattern);
            $this->matchType = $matchType;
            $this->cap = $cap;
        }

        /**
         * Creates a new signal rule.
         * @param string|string[] $pattern The patterns.
         * @param SignalMatchType $matchType The match type.
         */
        public static function from (array|string $pattern, SignalMatchType $matchType = SignalMatchType::MATCH_ANY, ?int $cap = null) : static {
            return new static($pattern, $matchType, $cap);
        }

        /**
         * Gets the cap of a signal rule.
         * @return int|null The match type.
         */
        public function getCap () : ?int {
            return $this->cap;
        }

        /**
         * Gets the match type of a signal rule.
         * @return SignalMatchType The match type.
         */
        public function getMatchType () : SignalMatchType {
            return $this->matchType;
        }

        /**
         * Gets the patterns of a signal rule.
         * @return string[] The patterns.
         */
        public function getPatterns () : array {
            return $this->patterns;
        }

        /**
         * Checks whether a specified signal pattern matches any pattern of a rule.
         * @param string $pattern A pattern.
         * @return bool Whether the pattern matches a pattern of the rule.
         */
        public function matchesAny (string $pattern) : bool {
            foreach ($this->patterns as $rulePattern) {
                if (PatternAnalyser::isOverlap($rulePattern, $pattern)) {
                    return true;
                }
            }

            return false;
        }
    }
?>