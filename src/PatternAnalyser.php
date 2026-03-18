<?php
    /**
     * Project Name:    Wingman Corvus - Pattern Analyser
     * Created by:      Angel Politis
     * Creation Date:   Nov 19 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus namespace.
    namespace Wingman\Corvus;

    /**
     * Represents a wrapper for methods related to pattern matching.
     * @package Wingman\Corvus
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    final class PatternAnalyser {
        /**
         * The character sequence that denotes a namespace.
         * @var string
         */
        public const NAMESPACE_ACCESSOR = '.';

        /**
         * The character sequence that denotes a single-level wildcard.
         * @var string
         */
        public const SINGLE_LEVEL_WILDCARD = '*';

        /**
         * The character sequence that denotes a multi-level wildcard.
         * @var string
         */
        public const MULTI_LEVEL_WILDCARD = "**";

        # Prevent any sort of instantiation of the class.
        private function __construct () {}
        private function __clone () {}

        /**
         * Analyses a pattern and returns the namespace and type.
         * @param string $pattern A pattern.
         * @return array An array containing the namespace and type.
         */
        public static function analyse (string $pattern) : array {
            $parts = explode(PatternAnalyser::NAMESPACE_ACCESSOR, $pattern);
            $type = array_pop($parts);
            $namespace = empty($parts) ? null : implode(PatternAnalyser::NAMESPACE_ACCESSOR, $parts);
            return [$namespace, $type, "namespace" => $namespace, "type" => $type];
        }

        /**
         * Checks whether a pattern contains a multi-level wildcard.
         * @param string $pattern A pattern.
         * @return bool Whether the pattern contains a multi-level wildcard.
         */
        public static function containsMultiLevelWildcard (string $pattern) : bool {
            $segments = explode(self::NAMESPACE_ACCESSOR, $pattern);
            return in_array(self::MULTI_LEVEL_WILDCARD, $segments, true);
        }

        /**
         * Checks whether a pattern contains a single-level wildcard.
         * @param string $pattern A pattern.
         * @return bool Whether the pattern contains a single-level wildcard.
         */
        public static function containsSingleLevelWildcard (string $pattern) : bool {
            $segments = explode(self::NAMESPACE_ACCESSOR, $pattern);
            return in_array(self::SINGLE_LEVEL_WILDCARD, $segments, true);
        }
    
        /**
         * Checks whether a pattern contains a wildcard of any type.
         * @param string $pattern A pattern.
         * @return bool Whether the pattern contains a wildcard.
         */
        public static function containsWildcard (string $pattern) : bool {
            return self::containsSingleLevelWildcard($pattern)
                || self::containsMultiLevelWildcard($pattern);
        }

        /**
         * Gets a regular expression for a pattern.
         * @param string $pattern A pattern.
         * @return string The resulting regular expression after replacing any wildcards.
         */
        public static function getRegex (string $pattern) : string {
            $regex = str_replace(
                [preg_quote(self::MULTI_LEVEL_WILDCARD, '/'), preg_quote(self::SINGLE_LEVEL_WILDCARD, '/')],
                ['.*', '[^.]+'],
                preg_quote($pattern, '/')
            );
            return "/^{$regex}$/";
        }

        /**
         * Checks whether two patterns match.
         * @param string $pattern1 The first pattern.
         * @param string $pattern2 The second pattern.
         * @return bool Whether the patterns match.
         */
        public static function isMatch (string $pattern1, string $pattern2) : bool {
            return preg_match(self::getRegex($pattern1), $pattern2) === 1;
        }

        /**
         * Checks whether there's an overlap between patterns.
         * @param string $pattern1 The first pattern.
         * @param string $pattern2 The second pattern.
         * @return bool Whether there's overlap.
         */
        public static function isOverlap (string $pattern1, string $pattern2): bool {
            $segments1 = explode(self::NAMESPACE_ACCESSOR, $pattern1);
            $segments2 = explode(self::NAMESPACE_ACCESSOR, $pattern2);
        
            $len1 = sizeof($segments1);
            $len2 = sizeof($segments2);
        
            $i = $j = 0;
        
            while ($i < $len1 && $j < $len2) {
                if ($segments1[$i] === self::MULTI_LEVEL_WILDCARD || $segments2[$j] === self::MULTI_LEVEL_WILDCARD) {
                    # Multi-level wildcard overlaps everything.
                    return true;
                }
        
                if ($segments1[$i] === self::SINGLE_LEVEL_WILDCARD || $segments2[$j] === self::SINGLE_LEVEL_WILDCARD) {
                    # Single-level wildcard matches one segment.
                    $i++;
                    $j++;
                    continue;
                }
        
                if ($segments1[$i] !== $segments2[$j]) {
                    return false;
                }
        
                $i++;
                $j++;
            }
        
            # Handle the remaining segments.
            while ($i < $len1) {
                if ($segments1[$i] !== self::MULTI_LEVEL_WILDCARD) return false;
                $i++;
            }
        
            while ($j < $len2) {
                if ($segments2[$j] !== self::MULTI_LEVEL_WILDCARD) return false;
                $j++;
            }
        
            return true;
        }

        /**
         * Turns a value that contains multiple values into an array.
         * @param string|string[] $patterns A string or array of patterns.
         * @return string[] An array of patterns that corresponds to the given value.
         */
        public static function normalisePatterns(string|array $patterns): array {
            $patterns = is_string($patterns) ? [$patterns] : $patterns;
        
            $result = [];
            $seen = [];
        
            $stack = $patterns;
            $i = 0;
            $len = sizeof($stack);
        
            while ($i < $len) {
                $item = $stack[$i++];
                
                if (is_array($item)) {
                    foreach ($item as $subItem) {
                        $stack[] = $subItem;
                    }
                    $len = sizeof($stack);
                    continue;
                }
        
                foreach (preg_split("/\s*,\s*/", $item) as $subItem) {
                    $subItem = trim($subItem);
                    if ($subItem === "" || isset($seen[$subItem])) continue;
        
                    $seen[$subItem] = true;
                    $result[] = $subItem;
                }
            }
        
            return $result;
        }
        
    }
?>