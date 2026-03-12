<?php
    /*/
	 * Project Name:    Wingman — Corvus — Exception Interface
	 * Created by:      Angel Politis
	 * Creation Date:   Mar 11 2026
	 * Last Modified:   Mar 11 2026
    /*/

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