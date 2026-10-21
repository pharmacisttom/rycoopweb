<?php
declare(strict_types=1);

// Must run before any Database::connect(), including schema-clone setup.
$testDatabase = (string) getenv('DB_DATABASE');
if (getenv('APP_ENV') !== 'testing' || !preg_match('/^(?:rycoop|rayongcoop)_(?:test|ci)(?:_|$)/D', $testDatabase)) {
    fwrite(STDERR, "REFUSED: database tests require APP_ENV=testing and an explicit rycoop_test_*/rayongcoop_test_*/rayongcoop_ci database.\n");
    exit(2);
}
