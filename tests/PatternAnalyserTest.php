<?php
    /**
     * Project Name:    Wingman Corvus - PatternAnalyser Tests
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus.Tests namespace.
    namespace Wingman\Corvus\Tests;

    # Import the following classes to the current scope.
    use Wingman\Argus\Attributes\Define;
    use Wingman\Argus\Attributes\Group;
    use Wingman\Argus\Attributes\Tags;
    use Wingman\Argus\Test;
    use Wingman\Corvus\PatternAnalyser;

    /**
     * Unit tests for PatternAnalyser.
     *
     * Verifies signal analysis (namespace/type splitting), wildcard detection
     * flags, single- and multi-level wildcard matching, overlap detection between
     * two patterns, and pattern normalisation from both string and array inputs.
     * These tests are entirely stateless: no Bus registration is required.
     *
     * @package Wingman\Corvus\Tests
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    #[Group("pattern-analyser")]
    #[Tags("unit", "signals")]
    class PatternAnalyserTest extends Test {

        // ──────────────────────────────────────────────────────────────────────
        // analyse()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "analyse splits namespace and type",
            description: "For 'a.b.c' the namespace must be 'a.b' and the type must be 'c'."
        )]
        public function testAnalyseSplitsNamespaceAndType () : void {
            [$namespace, $type] = PatternAnalyser::analyse("a.b.c");

            $this->assertEquals("a.b", $namespace, "Namespace must be everything before the last segment.");
            $this->assertEquals("c", $type, "Type must be the last segment.");
        }

        #[Define(
            name: "analyse returns null namespace for single-segment signal",
            description: "A signal with no dot must have a null namespace and the whole string as the type."
        )]
        public function testAnalyseReturnNullNamespaceForSingleSegment () : void {
            [$namespace, $type] = PatternAnalyser::analyse("created");

            $this->assertNull($namespace, "A single-segment signal must have a null namespace.");
            $this->assertEquals("created", $type, "Type must equal the full signal string.");
        }

        #[Define(
            name: "analyse handles deep hierarchy",
            description: "For 'a.b.c.d' the namespace must be 'a.b.c' and the type must be 'd'."
        )]
        public function testAnalyseHandlesDeepHierarchy () : void {
            [$namespace, $type] = PatternAnalyser::analyse("a.b.c.d");

            $this->assertEquals("a.b.c", $namespace, "Namespace must be all segments except the last.");
            $this->assertEquals("d", $type, "Type must be the final segment.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // containsWildcard() / containsSingleLevelWildcard() / containsMultiLevelWildcard()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "containsWildcard detects single-level wildcard",
            description: "'user.*' must be recognised as containing a wildcard."
        )]
        public function testContainsWildcardDetectsSingleLevel () : void {
            $this->assertTrue(PatternAnalyser::containsWildcard("user.*"), "Single-level wildcard must be detected.");
        }

        #[Define(
            name: "containsWildcard detects multi-level wildcard",
            description: "'user.**' must be recognised as containing a wildcard."
        )]
        public function testContainsWildcardDetectsMultiLevel () : void {
            $this->assertTrue(PatternAnalyser::containsWildcard("user.**"), "Multi-level wildcard must be detected.");
        }

        #[Define(
            name: "containsWildcard returns false for literal pattern",
            description: "A fully literal pattern must not be recognised as containing a wildcard."
        )]
        public function testContainsWildcardReturnsFalseForLiteral () : void {
            $this->assertFalse(PatternAnalyser::containsWildcard("user.created"), "Literal pattern must not be treated as a wildcard.");
        }

        #[Define(
            name: "containsSingleLevelWildcard is true for '*' only",
            description: "'user.*' must match; 'user.**' must not."
        )]
        public function testContainsSingleLevelWildcardDistinguishesFromMulti () : void {
            $this->assertTrue(PatternAnalyser::containsSingleLevelWildcard("user.*"), "Single-level wildcard must be detected.");
            $this->assertFalse(PatternAnalyser::containsSingleLevelWildcard("user.**"), "Multi-level wildcard must not be classified as single-level.");
        }

        #[Define(
            name: "containsMultiLevelWildcard is true for '**' only",
            description: "'user.**' must match; 'user.*' must not."
        )]
        public function testContainsMultiLevelWildcardDistinguishesFromSingle () : void {
            $this->assertTrue(PatternAnalyser::containsMultiLevelWildcard("user.**"), "Multi-level wildcard must be detected.");
            $this->assertFalse(PatternAnalyser::containsMultiLevelWildcard("user.*"), "Single-level wildcard must not be classified as multi-level.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // isMatch()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "isMatch returns true for identical literals",
            description: "An exact literal must match itself."
        )]
        public function testIsMatchReturnsTrueForIdenticalLiterals () : void {
            $this->assertTrue(PatternAnalyser::isMatch("user.created", "user.created"), "Identical literals must match.");
        }

        #[Define(
            name: "isMatch returns false for differing literals",
            description: "'a.b.c' must not match 'a.b.d'."
        )]
        public function testIsMatchReturnsFalseForDifferingLiterals () : void {
            $this->assertFalse(PatternAnalyser::isMatch("a.b.c", "a.b.d"), "Different literals must not match.");
        }

        #[Define(
            name: "isMatch: single-level wildcard matches one segment",
            description: "'user.*' must match 'user.created' but not 'user.profile.updated'."
        )]
        public function testIsMatchSingleLevelWildcardMatchesExactlyOneSegment () : void {
            $this->assertTrue(
                PatternAnalyser::isMatch("user.*", "user.created"),
                "'user.*' must match a single child segment."
            );
            $this->assertFalse(
                PatternAnalyser::isMatch("user.*", "user.profile.updated"),
                "'user.*' must not match a multi-segment descendant."
            );
        }

        #[Define(
            name: "isMatch: multi-level wildcard matches any number of segments",
            description: "'user.**' must match both 'user.created' and 'user.profile.avatar.changed'."
        )]
        public function testIsMatchMultiLevelWildcardMatchesAnyDepth () : void {
            $this->assertTrue(
                PatternAnalyser::isMatch("user.**", "user.created"),
                "'user.**' must match a direct child."
            );
            $this->assertTrue(
                PatternAnalyser::isMatch("user.**", "user.profile.avatar.changed"),
                "'user.**' must match a deeply nested descendant."
            );
        }

        #[Define(
            name: "isMatch: '**' alone matches any signal",
            description: "A bare '**' pattern must match any signal name."
        )]
        public function testIsMatchBareDoubleStarMatchesAnything () : void {
            $this->assertTrue(PatternAnalyser::isMatch("**", "any.deep.signal.path"), "Bare '**' must match everything.");
            $this->assertTrue(PatternAnalyser::isMatch("**", "single"), "Bare '**' must match a single-segment signal.");
        }

        #[Define(
            name: "isMatch: mid-path single wildcard",
            description: "'a.*.c' must match 'a.b.c' but not 'a.b.d' or 'a.b.b.c'."
        )]
        public function testIsMatchMidPathSingleWildcard () : void {
            $this->assertTrue(PatternAnalyser::isMatch("a.*.c", "a.b.c"), "Mid-path '*' must match one segment at that position.");
            $this->assertFalse(PatternAnalyser::isMatch("a.*.c", "a.b.d"), "Literal after wildcard must still be verified.");
            $this->assertFalse(PatternAnalyser::isMatch("a.*.c", "a.b.b.c"), "'*' must not consume multiple segments.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // isOverlap()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "isOverlap: wildcard and matching literal overlap",
            description: "'user.*' and 'user.created' share at least one potential signal."
        )]
        public function testIsOverlapWildcardAndLiteralOverlap () : void {
            $this->assertTrue(PatternAnalyser::isOverlap("user.*", "user.created"), "Wildcard and a matching literal must overlap.");
        }

        #[Define(
            name: "isOverlap: different namespaces never overlap",
            description: "'user.*' and 'order.*' share no potential signals."
        )]
        public function testIsOverlapDifferentNamespacesDoNotOverlap () : void {
            $this->assertFalse(PatternAnalyser::isOverlap("user.*", "order.*"), "Patterns from different namespaces must not overlap.");
        }

        #[Define(
            name: "isOverlap: multi-level wildcard and deep literal overlap",
            description: "'app.**' and 'app.db.query.error' share a potential signal."
        )]
        public function testIsOverlapMultiLevelWildcardAndDeepLiteralOverlap () : void {
            $this->assertTrue(PatternAnalyser::isOverlap("app.**", "app.db.query.error"), "'app.**' must overlap with any descendant.");
        }

        #[Define(
            name: "isOverlap: two identical literals overlap",
            description: "The same literal pattern trivially overlaps with itself."
        )]
        public function testIsOverlapIdenticalLiteralsOverlap () : void {
            $this->assertTrue(PatternAnalyser::isOverlap("x.y.z", "x.y.z"), "Two identical literals must overlap.");
        }

        #[Define(
            name: "isOverlap: different literals do not overlap",
            description: "'a.b.c' and 'a.b.d' have no common potential signal."
        )]
        public function testIsOverlapDifferentLiteralsDoNotOverlap () : void {
            $this->assertFalse(PatternAnalyser::isOverlap("a.b.c", "a.b.d"), "Different literals must not overlap.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // normalisePatterns()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "normalisePatterns wraps a string in an array",
            description: "A bare string input must produce a single-element array."
        )]
        public function testNormalisePatternsWrapsStringInArray () : void {
            $result = PatternAnalyser::normalisePatterns("user.created");

            $this->assertEquals(["user.created"], $result, "A string must be wrapped in an array.");
        }

        #[Define(
            name: "normalisePatterns passes an array through unchanged",
            description: "An array input must be returned as-is."
        )]
        public function testNormalisePatternsPassesArrayThrough () : void {
            $result = PatternAnalyser::normalisePatterns(["user.created", "order.placed"]);

            $this->assertEquals(["user.created", "order.placed"], $result, "An array must be returned unchanged.");
        }
    }
?>