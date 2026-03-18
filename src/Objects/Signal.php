<?php
    /**
     * Project Name:    Wingman Corvus - Signal
     * Created by:      Angel Politis
     * Creation Date:   Nov 15 2025
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2025-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus.Objects namespace.
    namespace Wingman\Corvus\Objects;

    /**
     * Represents a signal that has been emitted.
     * @package Wingman\Corvus\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Signal {
        /**
         * Creates a new signal.
         * @param int $id The ID of a signal.
         * @param string $type The type of a signal.
         * @param string|null $namespace The namespace of a signal.
         * @param string $name The name of a signal.
         */
        public function __construct (
            public readonly int $id,
            public readonly string $type,
            public readonly ?string $namespace,
            public readonly string $name
        ) {}
    }
?>