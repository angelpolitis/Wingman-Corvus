<?php
    /**
     * Project Name:    Wingman Corvus - Invalid Cap Exception
     * Created by:      Angel Politis
     * Creation Date:   Mar 11 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus.Exceptions namespace.
    namespace Wingman\Corvus\Exceptions;

    # Import the following classes to the current scope.
    use InvalidArgumentException;
    use Throwable;
    use Wingman\Corvus\Interfaces\Exception;

    /**
     * Thrown when a cap value is negative or otherwise outside an acceptable range.
     *
     * Applies to both listener-level caps (`Listener::cap()`) and signal-rule caps
     * (`SignalRule` constructor), as well as the history size limit on a bus.
     *
     * @package Wingman\Corvus\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class InvalidCapException extends InvalidArgumentException implements Exception {
        /**
         * The invalid cap value that was provided.
         * @var int
         */
        protected int $cap;

        /**
         * Creates a new exception.
         * @param int $cap The invalid cap value.
         * @param int $code An optional exception code.
         * @param Throwable|null $previous An optional previous throwable.
         */
        public function __construct (int $cap, int $code = 0, ?Throwable $previous = null) {
            $this->cap = $cap;
            parent::__construct("The cap must be greater than or equal to 0, $cap given.", $code, $previous);
        }

        /**
         * Gets the invalid cap value that was provided.
         * @return int The cap value.
         */
        public function getCap () : int {
            return $this->cap;
        }
    }
?>