<?php
    /*/
	 * Project Name:    Wingman — Corvus — Identifiable Interface
	 * Created by:      Angel Politis
	 * Creation Date:   Mar 11 2026
	 * Last Modified:   Mar 11 2026
    /*/

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