<?php
    /**
     * Project Name:    Wingman Corvus - Has Id Trait
     * Created by:      Angel Politis
     * Creation Date:   Mar 11 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus.Traits namespace.
    namespace Wingman\Corvus\Traits;

    /**
     * Provides a stable, process-scoped unique identifier for the using class.
     *
     * Each class that uses this trait maintains its own independent counter via
     * late-static binding (`static::$nextId`), ensuring that IDs are unique within
     * each class hierarchy rather than globally.
     *
     * Usage: call `$this->initialiseId()` at the top of the using class's constructor.
     *
     * @package Wingman\Corvus
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    trait HasId {
        /**
         * The incremental ID counter for instances of the using class.
         * @var int
         */
        private static int $nextId = 0;

        /**
         * The unique identifier of the instance.
         * @var int
         */
        protected readonly int $id;

        /**
         * Assigns the next sequential ID to this instance.
         * Must be called at the beginning of the using class's constructor.
         */
        protected function initialiseId () : void {
            $this->id = ++static::$nextId;
        }

        /**
         * Gets the unique identifier of the instance.
         * @return int The ID.
         */
        public function getId () : int {
            return $this->id;
        }
    }
?>