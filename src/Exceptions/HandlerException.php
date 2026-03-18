<?php
    /**
     * Project Name:    Wingman Corvus - Handler Exception
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
     * Thrown when a handler callback raises an exception during signal dispatch.
     *
     * The original throwable is always available via `getPrevious()`.
     *
     * @package Wingman\Corvus\Exceptions
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class HandlerException extends RuntimeException implements Exception {
        /**
         * The name of the signal that was being dispatched when the exception occurred.
         * @var string
         */
        protected string $signal;

        /**
         * Creates a new exception.
         * @param string $signal The name of the signal being dispatched.
         * @param Throwable $previous The original exception thrown by the handler.
         * @param int $code An optional exception code.
         */
        public function __construct (string $signal, Throwable $previous, int $code = 0) {
            $this->signal = $signal;
            parent::__construct(
                "A handler threw an exception while processing signal '$signal': " . $previous->getMessage(),
                $code,
                $previous
            );
        }

        /**
         * Gets the name of the signal that was being dispatched.
         * @return string The signal name.
         */
        public function getSignal () : string {
            return $this->signal;
        }
    }
?>