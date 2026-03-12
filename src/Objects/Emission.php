<?php
    /*/
	 * Project Name:    Wingman — Corvus — Emission
	 * Created by:      Angel Politis
	 * Creation Date:   Nov 19 2025
	 * Last Modified:   Mar 11 2026
    /*/

    # Use the Corvus.Objects namespace.
    namespace Wingman\Corvus\Objects;

    # Import the following classes to the current scope.
    use DateTime;
    use Wingman\Corvus\Collections\TargetCollection;

    /**
     * Represents a signal emission.
     * @package Wingman\Corvus\Objects
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class Emission {
        /**
         * Creates a new emission.
          * @param Signal $signal The signal of an emission.
          * @param array $payload The payload of an emission.
          * @param TargetCollection $targets The targets of an emission.
          * @param DateTime $date The date of an emission.
          * @param int $emitterId The ID of the emitter that emitted the signal.
         */
        public function __construct (
            public readonly Signal $signal,
            public readonly array $payload,
            public readonly TargetCollection $targets,
            public readonly DateTime $date,
            public readonly int $emitterId
        ) {}
    }
?>