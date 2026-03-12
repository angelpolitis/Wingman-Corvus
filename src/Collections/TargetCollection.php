<?php
    /*/
	 * Project Name:    Wingman — Corvus — Target Collection
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 17 2025
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Collections namespace.
    namespace Wingman\Corvus\Collections;

    # Import the following classes to the current scope.
    use Wingman\Strux\TypedCollection;

    /**
     * Represents a collection of targets.
     * @package Wingman\Corvus\Collections
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class TargetCollection extends TypedCollection {
        /**
         * The type of a collection.
         * @var class-string<T>|string|null
         */
        protected ?string $type = "object";

        /**
         * The items of a collection.
         * @var object[]
         */
        protected array $items = [];
    }
?>