<?php
    // database values — env-overridable so the archived 2014 app can run in Docker
    // (docker-compose sets DB_HOST=db etc.); the original hard-coded values are kept
    // as the fallback, so behaviour outside Docker is unchanged. See archive/README.md.
    $dblocation = getenv("DB_HOST") ?: "localhost";
    $dbname     = getenv("DB_NAME") ?: "oose";
    $dbuser     = getenv("DB_USER") ?: "root";
    $dbpass     = getenv("DB_PASS") !== false ? getenv("DB_PASS") : "";
?>
