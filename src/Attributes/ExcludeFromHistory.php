<?php
    /**
     * Project Name:    Wingman Corvus - Exclude From History Attribute
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 18 2026
     *
     * Copyright (c) 2026-2026 Angel Politis <info@angelpolitis.com>
     * This Source Code Form is subject to the terms of the Mozilla Public License, v. 2.0.
     * If a copy of the MPL was not distributed with this file, You can obtain one at http://mozilla.org/MPL/2.0/.
     */

    # Use the Corvus.Attributes namespace.
    namespace Wingman\Corvus\Attributes;

    # Import the following classes to the current scope.
    use Attribute;

    /**
     * Marks an Emitter method or class so that any signals it dispatches
     * are not recorded in the Bus emission history. This is useful for
     * high-frequency signals such as heartbeats or cursor movements that
     * would otherwise flood the history buffer and trigger unintended
     * MATCH_LATEST or MATCH_REPLAY replays on newly registered listeners.
     *
     * When applied to a class, all emit calls originating from that class
     * are excluded. When applied to a method, only emissions from that
     * specific method are excluded.
     *
     * Example usage:
     *
     *     #[ExcludeFromHistory]
     *     public function emitCursorMoved (float $x, float $y) : void
     *     {
     *         Emitter::create()->emit("cursor.moved");
     *     }
     *
     * @package Wingman\Corvus\Attributes
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    #[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
    class ExcludeFromHistory {}
?>