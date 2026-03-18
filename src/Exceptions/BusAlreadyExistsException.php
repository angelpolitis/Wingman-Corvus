<?php
    /**
     * Project Name:    Wingman Corvus - Bus Already Exists Exception
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
    use RuntimeException;
    use Throwable;
    use Wingman\Corvus\Interfaces\Exception;

    /**
     * Thrown when attempting to create a named bus that already exists in the registry.
     * @package Wingman\Corvus\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class BusAlreadyExistsException extends RuntimeException implements Exception {
        /**
         * Creates a new exception.
         * @param string $name The name of the bus that already exists.
         * @param int $code An optional exception code.
         * @param Throwable|null $previous An optional previous throwable.
         */
        public function __construct (string $name, int $code = 0, ?Throwable $previous = null) {
            parent::__construct("A bus with the name '$name' already exists.", $code, $previous);
        }
    }
?>