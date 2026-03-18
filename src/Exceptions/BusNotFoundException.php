<?php
    /**
     * Project Name:    Wingman Corvus - Bus Not Found Exception
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
     * Thrown when referencing a named bus that does not exist in the registry.
     * @package Wingman\Corvus\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class BusNotFoundException extends RuntimeException implements Exception {
        /**
         * Creates a new exception.
         * @param string $name The name of the bus that was not found.
         * @param int $code An optional exception code.
         * @param Throwable|null $previous An optional previous throwable.
         */
        public function __construct (string $name, int $code = 0, ?Throwable $previous = null) {
            parent::__construct("No bus with the name '$name' exists.", $code, $previous);
        }
    }
?>