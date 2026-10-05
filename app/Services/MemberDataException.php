<?php
declare(strict_types=1);
namespace App\Services;

/** Validation/conflict messages safe to return to an authenticated administrator. */
class MemberDataException extends \RuntimeException {}
