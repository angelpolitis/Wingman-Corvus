<?php
    /**
     * Project Name:    Wingman Corvus - Identifiable Interface
     * Created by:      Angel Politis
     * Creation Date:   Mar 11 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus.Interfaces namespace.
    namespace Wingman\Corvus\Interfaces;

    /**
     * Describes an object that carries a stable, process-scoped unique identifier.
     * @package Wingman\Corvus
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Identifiable {
        /**
         * Gets the unique identifier of the object.
         * @return int The ID.
         */
        public function getId () : int;
    }
?>