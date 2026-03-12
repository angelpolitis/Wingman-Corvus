<?php
    /*/
     * Project Name:    Wingman — Corvus — Standalone Autoloader
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 12 2026
    /*/

    /**
     * Minimal PSR-0 style autoloader for Wingman\Corvus and its dependencies.
     *
     * Resolves:
     *   - Wingman\Corvus\* → <module>/src/{...}.php
     *   - Wingman\Strux\*  → <module>/{ClassName}.php  (flat module, no src/)
     *   - Wingman\Utils\*  → <module>/{ClassName}.php  (flat module, no src/)
     *   - Wingman\Argus\*  → <module>/src/{...}.php    (optional; only if installed)
     *
     * All modules are resolved relative to the directory that sits one level above this file.
     */
    spl_autoload_register(function (string $class) : void {
        $parts = explode("\\", $class);

        if (count($parts) < 2 || $parts[0] !== "Wingman") return;

        $module = $parts[1];
        $remainder = array_slice($parts, 2);

        if (empty($remainder)) return;

        $modulesDir = __DIR__ . "/..";
        $moduleDir  = $modulesDir . "/" . $module;

        if (!is_dir($moduleDir)) return;

        $base = is_dir($moduleDir . "/src") ? $moduleDir . "/src" : $moduleDir;
        $path = $base . "/" . implode("/", $remainder) . ".php";

        if (file_exists($path)) require_once $path;
    });
?>