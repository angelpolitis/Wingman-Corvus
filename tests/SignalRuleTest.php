<?php
    /**
     * Project Name:    Wingman Corvus - SignalRule Tests
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
    use Wingman\Corvus\Enums\SignalMatchType;
    use Wingman\Corvus\Exceptions\InvalidCapException;
    use Wingman\Corvus\Objects\SignalRule;

    /**
     * Unit tests for SignalRule.
     *
     * Verifies that the constructor and static factory correctly store patterns,
     * match types, and caps; that a negative cap throws InvalidCapException;
     * and that matchesAny() correctly resolves literal and wildcard patterns.
     * These tests are entirely stateless and require no Bus instance.
     *
     * @package Wingman\Corvus\Tests
     * @author  Angel Politis <info@angelpolitis.com>
     * @since   1.0
     */
    #[Group("signal-rule")]
    #[Tags("unit", "signals")]
    class SignalRuleTest extends Test {

        // ──────────────────────────────────────────────────────────────────────
        // Construction
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "Constructor stores patterns as array",
            description: "A string pattern must be normalised to a single-element array via getPatterns()."
        )]
        public function testConstructorStoresPatternsAsArray () : void {
            $rule = new SignalRule("user.created");

            $this->assertEquals(["user.created"], $rule->getPatterns(), "getPatterns() must wrap a string in an array.");
        }

        #[Define(
            name: "Constructor stores array of patterns",
            description: "An array of patterns must be stored verbatim."
        )]
        public function testConstructorStoresArrayOfPatterns () : void {
            $rule = new SignalRule(["user.created", "order.placed"]);

            $this->assertEquals(["user.created", "order.placed"], $rule->getPatterns(), "getPatterns() must return all patterns.");
        }

        #[Define(
            name: "Constructor defaults match type to MATCH_ANY",
            description: "Without an explicit match type the rule must default to MATCH_ANY."
        )]
        public function testConstructorDefaultsMatchTypeToMatchAny () : void {
            $rule = new SignalRule("user.created");

            $this->assertEquals(SignalMatchType::MATCH_ANY, $rule->getMatchType(), "Default match type must be MATCH_ANY.");
        }

        #[Define(
            name: "Constructor stores explicit match type",
            description: "A MATCH_ALL rule must report MATCH_ALL from getMatchType()."
        )]
        public function testConstructorStoresExplicitMatchType () : void {
            $rule = new SignalRule(["a", "b"], SignalMatchType::MATCH_ALL);

            $this->assertEquals(SignalMatchType::MATCH_ALL, $rule->getMatchType(), "Explicit match type must be preserved.");
        }

        #[Define(
            name: "Constructor stores positive cap",
            description: "A positive cap must be returned unchanged by getCap()."
        )]
        public function testConstructorStoresPositiveCap () : void {
            $rule = new SignalRule("user.created", SignalMatchType::MATCH_ANY, 5);

            $this->assertEquals(5, $rule->getCap(), "Positive cap must be stored.");
        }

        #[Define(
            name: "Constructor stores null cap",
            description: "An explicit null cap means unlimited activation; getCap() must return null."
        )]
        public function testConstructorStoresNullCap () : void {
            $rule = new SignalRule("user.created");

            $this->assertNull($rule->getCap(), "Default cap must be null (unlimited).");
        }

        #[Define(
            name: "Constructor throws InvalidCapException for negative cap",
            description: "A negative cap value must not be accepted; InvalidCapException must be thrown."
        )]
        public function testConstructorThrowsForNegativeCap () : void {
            $this->assertThrows(
                InvalidCapException::class,
                fn () => new SignalRule("user.created", SignalMatchType::MATCH_ANY, -1),
                "A negative cap must throw InvalidCapException."
            );
        }

        #[Define(
            name: "from() factory creates an equivalent instance",
            description: "SignalRule::from() must produce a rule identical to the constructor."
        )]
        public function testFromFactoryCreatesEquivalentInstance () : void {
            $rule = SignalRule::from("user.*", SignalMatchType::MATCH_ANY, 3);

            $this->assertInstanceOf(SignalRule::class, $rule, "from() must return a SignalRule instance.");
            $this->assertEquals(["user.*"], $rule->getPatterns(), "Patterns must be set correctly.");
            $this->assertEquals(3, $rule->getCap(), "Cap must be set correctly.");
        }

        // ──────────────────────────────────────────────────────────────────────
        // matchesAny()
        // ──────────────────────────────────────────────────────────────────────

        #[Define(
            name: "matchesAny returns true for exact literal match",
            description: "A rule containing 'user.created' must match 'user.created'."
        )]
        public function testMatchesAnyReturnsTrueForExactLiteral () : void {
            $rule = new SignalRule("user.created");

            $this->assertTrue($rule->matchesAny("user.created"), "Exact literal must match.");
        }

        #[Define(
            name: "matchesAny returns false for non-matching literal",
            description: "A rule for 'user.created' must not match 'order.placed'."
        )]
        public function testMatchesAnyReturnsFalseForNonMatchingLiteral () : void {
            $rule = new SignalRule("user.created");

            $this->assertFalse($rule->matchesAny("order.placed"), "Non-matching literal must not match.");
        }

        #[Define(
            name: "matchesAny respects wildcard patterns",
            description: "A rule with 'user.*' must match 'user.created' but not 'order.created'."
        )]
        public function testMatchesAnyRespectsWildcardPatterns () : void {
            $rule = new SignalRule("user.*");

            $this->assertTrue($rule->matchesAny("user.created"), "Single-level wildcard must match a child signal.");
            $this->assertFalse($rule->matchesAny("order.created"), "Wildcard must not match a different namespace.");
        }

        #[Define(
            name: "matchesAny returns true when any pattern in a set matches",
            description: "A rule with multiple patterns must match when at least one pattern matches."
        )]
        public function testMatchesAnyReturnsTrueWhenOnePatternMatches () : void {
            $rule = new SignalRule(["user.created", "order.placed"]);

            $this->assertTrue($rule->matchesAny("order.placed"), "The second pattern must be checked.");
        }
    }
?>