<?php
    /*/
	 * Project Name:    Wingman — Corvus — Emitter Collection
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 19 2025
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Collections namespace.
    namespace Wingman\Corvus\Collections;

    # Import the following classes to the current scope.
    use Wingman\Corvus\Emitter;
    use Wingman\Strux\TypedCollection;

    /**
     * Represents a collection of emitters.
     * @package Wingman\Corvus\Collections
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class EmitterCollection extends TypedCollection {
        /**
         * The type of a collection.
         * @var class-string<T>|string|null
         */
        protected ?string $type = Emitter::class;

        /**
         * The items of a collection.
         * @var Emitter[]
         */
        protected array $items = [];
    }
?>