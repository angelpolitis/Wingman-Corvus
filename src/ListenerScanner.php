<?php
    /*/
     * Project Name:    Wingman — Corvus — Listener Scanner
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 12 2026
    /*/

    # Use the Corvus namespace.
    namespace Wingman\Corvus;

    # Import the following classes to the current scope.
    use ReflectionClass;
    use ReflectionException;
    use ReflectionMethod;
    use Wingman\Corvus\Attributes\Debounce;
    use Wingman\Corvus\Attributes\SignalHandler;
    use Wingman\Corvus\Enums\SignalMatchType;
    use Wingman\Corvus\Exceptions\InvalidPatternException;

    /**
     * Scans one or more objects or class names for methods decorated with
     * the SignalHandler attribute and registers them as Listeners on the
     * appropriate Bus instances.
     *
     * The scanner supports all SignalHandler options (match mode, priority,
     * cap, tags, bus name) and the Debounce attribute for cooldown-based
     * suppression. It is the bridge between the declarative attribute API
     * and the programmatic Listener builder.
     *
     * Basic usage:
     *
     *     ListenerScanner::scan(new NotificationService());
     *
     * Scanning multiple targets at once:
     *
     *     ListenerScanner::scan(
     *         new NotificationService(),
     *         new AuditService(),
     *         OrderService::class,
     *     );
     *
     * @package Wingman\Corvus
     * @author Angel Politis <info@angelpolitis.com>
     * @since 1.0
     */
    class ListenerScanner {
        /**
         * Maps a SignalHandler match string to its SignalMatchType enum case.
         * @var array<string, SignalMatchType>
         */
        private static array $matchTypeMap = [
            "any"    => SignalMatchType::MATCH_ANY,
            "all"    => SignalMatchType::MATCH_ALL,
            "latest" => SignalMatchType::MATCH_LATEST,
            "replay" => SignalMatchType::MATCH_REPLAY,
        ];

        /**
         * Scans one or more targets for SignalHandler attributes and
         * registers each decorated method as a Listener on the Bus.
         *
         * Targets may be object instances or fully-qualified class name
         * strings. When a string is provided, the method is registered
         * as a static callable; when an object is provided, the method
         * is bound to that instance.
         * @param  object|string ...$targets One or more objects or class names to scan.
         * @return void
         * @throws InvalidPatternException   If a pattern on a SignalHandler is invalid.
         * @throws ReflectionException      If a class name cannot be reflected.
         */
        public static function scan (object|string ...$targets) : void {
            foreach ($targets as $target) {
                self::scanTarget($target);
            }
        }

        /**
         * Reflects a single target and processes all of its methods.
         * @param  object|string $target The object instance or class name to reflect.
         * @throws ReflectionException If the class cannot be reflected.
         */
        private static function scanTarget (object|string $target) : void {
            $reflection = new ReflectionClass($target);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                self::processMethod($target, $method);
            }
        }

        /**
         * Reads all SignalHandler attributes from a method and registers
         * a Listener for each one found.
         * @param  object|string  $target The owning object or class name.
         * @param  ReflectionMethod $method The reflected method to inspect.
         * @return void
         */
        private static function processMethod (object|string $target, ReflectionMethod $method) : void {
            $handlerAttributes = $method->getAttributes(SignalHandler::class, \ReflectionAttribute::IS_INSTANCEOF);

            if (empty($handlerAttributes)) return;

            $debounceMs = self::resolveDebounce($method);

            foreach ($handlerAttributes as $attribute) {
                /** @var SignalHandler $handler */
                $handler = $attribute->newInstance();
                self::registerListener($target, $method, $handler, $debounceMs);
            }
        }

        /**
         * Resolves the debounce duration in milliseconds from the method's
         * Debounce attribute, if present. Returns null if no Debounce
         * attribute is found.
         * @param  ReflectionMethod $method The reflected method to inspect.
         * @return int|null The debounce duration in milliseconds, or null.
         */
        private static function resolveDebounce (ReflectionMethod $method) : ?int {
            $debounceAttributes = $method->getAttributes(Debounce::class);

            if (empty($debounceAttributes)) return null;

            /** @var Debounce $debounce */
            $debounce = $debounceAttributes[0]->newInstance();

            return $debounce->milliseconds;
        }

        /**
         * Resolves the SignalMatchType enum case from a match string.
         * Defaults to MATCH_ANY for unrecognised values.
         * @param  string $match The match mode string from the attribute.
         * @return SignalMatchType The resolved enum case.
         */
        private static function resolveMatchType (string $match) : SignalMatchType {
            return self::$matchTypeMap[$match] ?? SignalMatchType::MATCH_ANY;
        }

        /**
         * Builds and registers a Listener from a reflected method and its
         * SignalHandler attribute configuration.
         * @param  object|string   $target     The owning object or class name.
         * @param  ReflectionMethod $method    The method to use as the handler callable.
         * @param  SignalHandler    $handler   The attribute instance carrying configuration.
         * @param  int|null        $debounceMs Optional debounce window in milliseconds.
         * @return void
         */
        private static function registerListener (
            object|string $target,
            ReflectionMethod $method,
            SignalHandler $handler,
            ?int $debounceMs,
        ) : void {
            $callable  = is_object($target) ? [$target, $method->getName()] : [$target, $method->getName()];
            $matchType = self::resolveMatchType($handler->match);
            $busName   = $handler->bus;

            $listener = Listener::create($busName);

            match ($matchType) {
                SignalMatchType::MATCH_ANY    => $listener->when($handler->pattern),
                SignalMatchType::MATCH_ALL    => $listener->onceAll($handler->pattern),
                SignalMatchType::MATCH_LATEST => $listener->latest($handler->pattern),
                SignalMatchType::MATCH_REPLAY => $listener->replay(1, $handler->pattern),
            };

            if ($handler->priority !== 0) {
                $listener->priority($handler->priority);
            }

            if ($handler->cap > 0) {
                $listener->cap($handler->cap);
            }

            if (!empty($handler->tags)) {
                $listener->tag(...$handler->tags);
            }

            if ($debounceMs !== null) {
                $listener->debounce($debounceMs);
            }

            $listener->do($callable);
        }
    }
?>