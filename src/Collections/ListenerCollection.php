<?php
    /*/
	 * Project Name:    Wingman — Corvus — Listener Collection
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 18 2025
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Collections namespace.
    namespace Wingman\Corvus\Collections;

    # Import the following classes to the current scope.
    use Wingman\Corvus\Listener;
    use Wingman\Strux\TypedCollection;

    /**
     * Represents a collection of listeners.
     * @package Wingman\Corvus\Collections
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ListenerCollection extends TypedCollection {
        /**
         * The type of a collection.
         * @var class-string<T>|string|null
         */
        protected ?string $type = Listener::class;

        /**
         * The items of a collection.
         * @var Listener[]
         */
        protected array $items = [];
    }
?>