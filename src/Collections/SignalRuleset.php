<?php
    /*/
	 * Project Name:    Wingman — Corvus — Signal Ruleset
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 17 2025
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Collections namespace.
    namespace Wingman\Corvus\Collections;

    # Import the following classes to the current scope.
    use Wingman\Corvus\Objects\SignalRule;
    use Wingman\Strux\TypedCollection;

    /**
     * Represents a signal ruleset.
     * @package Wingman\Corvus\Collections
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class SignalRuleset extends TypedCollection {
        /**
         * The type of a collection.
         * @var class-string<T>|string|null
         */
        protected ?string $type = SignalRule::class;

        /**
         * The items of a collection.
         * @var SignalRule[]
         */
        protected array $items = [];

        /**
         * Gets all signal patterns of a collection.
         * @return string[] The signal patterns.
         */
        public function getSignalPatterns () : array {
            $patterns = [];
            $patternMap = [];
            
            foreach ($this->items as $item) {
                foreach ($item->getPatterns() as $pattern) {
                    if (isset($patternMap[$pattern])) continue;

                    $patterns[] = $pattern;
                    $patternMap[$pattern] = true;
                }
            }

            return $patterns;
        }
    }
?>