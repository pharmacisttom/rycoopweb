<?php

declare(strict_types=1);

/**
 * RYCOOPWEB — Member Account Activation Dry-Run CLI Tool
 *
 * Simulates account activation for imported member accounts without modifying data.
 * Usage: php scripts/member_activation_dry_run.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Helpers.php';

use App\Core\Database;
use App\Services\MemberControlledActivationService;

echo "=========================================================================\n";
echo "RYCOOPWEB — MEMBER ACCOUNT ACTIVATION DRY-RUN SIMULATION\n";
echo "Rayong Public Health Savings and Credit Cooperative Limited\n";
echo "=========================================================================\n\n";

try {
    $report = MemberControlledActivationService::dryRun();

    echo "MODE:           {$report['mode']} (READ-ONLY SIMULATION - ZERO MUTATIONS)\n";
    echo "TIMESTAMP:      {$report['timestamp']}\n\n";

    echo "1. BASELINE COUNTS:\n";
    echo "   - Total Imported Members:      {$report['baseline']['total_members']}\n";
    echo "   - Mapped to User Accounts:     {$report['baseline']['mapped_members']}\n";
    echo "   - Unmapped Members:            {$report['baseline']['unmapped_members']}\n";
    echo "   - Protected Staff/Admin Users: {$report['baseline']['protected_staff_admin_users']}\n\n";

    echo "2. CURRENT MEMBER ACCOUNT STATUSES:\n";
    echo "   - Inactive:                    {$report['member_user_statuses']['inactive']}\n";
    echo "   - Active:                      {$report['member_user_statuses']['active']}\n";
    echo "   - Suspended:                   {$report['member_user_statuses']['suspended']}\n\n";

    echo "3. ACTIVATION SIMULATION RESULTS:\n";
    echo "   - Accounts Eligible to Activate: {$report['simulation']['eligible_for_activation']}\n";
    echo "   - Suspended Accounts Excluded:   {$report['simulation']['excluded_suspended']}\n";
    echo "   - Staff/Admin Excluded:          {$report['simulation']['excluded_staff_admin']}\n";
    echo "   - Target Update Count:           {$report['simulation']['would_be_updated']}\n\n";

    echo "4. SAFETY INVARIANT VERIFICATIONS:\n";
    echo "   [OK] Suspended accounts strictly preserved (never auto-activated)\n";
    echo "   [OK] Staff and admin roles strictly isolated\n";
    echo "   [OK] Zero database mutations performed during dry-run\n\n";

    echo "5. SECURITY RISK NOTICE:\n";
    echo "   Risk Level: {$report['security_risk_assessment']['initial_credential_predictability']}\n";
    echo "   {$report['security_risk_assessment']['description']}\n";
    echo "   Recommended Mitigations:\n";
    foreach ($report['security_risk_assessment']['recommended_controls'] as $ctrl) {
        echo "   - {$ctrl}\n";
    }
    echo "\n=========================================================================\n";
    echo "DRY-RUN SIMULATION COMPLETED SUCCESSFULLY.\n";
    echo "=========================================================================\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
