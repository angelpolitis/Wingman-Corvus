<?php
    /*/
     * Project Name:    Wingman — Corvus — Test Runner
     * Created by:      Angel Politis
     * Creation Date:   Mar 12 2026
     * Last Modified:   Mar 12 2026
    /*/

    use Wingman\Argus\Tester;

    require_once __DIR__ . "/../autoload.php";

    if (!class_exists(Tester::class)) {
        http_response_code(500);
        echo "Argus test framework not found. Install wingman/argus alongside wingman/corvus.";
        exit(1);
    }

    Tester::runTestsInDirectory(__DIR__, "Wingman\\Corvus\\Tests");
?>