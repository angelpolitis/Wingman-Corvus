<?php
    /**
     * Project Name:    Wingman Corvus - Emission Collection
     * Created by:      Angel Politis
     * Creation Date:   Nov 19 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus.Collections namespace.
    namespace Wingman\Corvus\Collections;

    # Import the following classes to the current scope.
    use Wingman\Corvus\Objects\Emission;
    use Wingman\Strux\TypedCollection;

    /**
     * Represents a collection of emissions.
     * @package Wingman\Corvus\Collections
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class EmissionCollection extends TypedCollection {
        /**
         * The type of a collection.
         * @var class-string<T>|string|null
         */
        protected ?string $type = Emission::class;

        /**
         * The items of a collection.
         * @var Emission[]
         */
        protected array $items = [];
    }
?>