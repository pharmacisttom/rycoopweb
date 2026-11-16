<?php

declare(strict_types=1);

// Legacy entry point now runs only the disposable SQLite suite.
// Never import, delete terms, or reset records in an environment database during review.
require __DIR__.'/BoardTermWorkflowHardeningTest.php';
