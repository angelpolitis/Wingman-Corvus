<?php
    /*/
	 * Project Name:    Wingman — Corvus — Handler Collection
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 17 2025
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Collections namespace.
    namespace Wingman\Corvus\Collections;

    # Import the following classes to the current scope.
    use Wingman\Corvus\Objects\Handler;
    use Wingman\Strux\TypedCollection;

    /**
     * Represents a collection of handlers.
     * @package Wingman\Corvus\Collections
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class HandlerCollection extends TypedCollection {
        /**
         * The type of a collection.
         * @var class-string<T>|string|null
         */
        protected ?string $type = Handler::class;

        /**
         * The items of a collection.
         * @var Handler[]
         */
        protected array $items = [];
    }
?>