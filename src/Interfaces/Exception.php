<?php
    /**
     * Project Name:    Wingman Corvus - Exception Interface
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
     * Marker interface implemented by every Corvus-specific exception.
     *
     * Catch this interface to handle any exception thrown by the Corvus package
     * without needing to enumerate individual exception classes.
     *
     * @package Wingman\Corvus\Interfaces
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    interface Exception {}
?>