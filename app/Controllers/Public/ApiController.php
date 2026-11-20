<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Core\Database;

class ApiController extends Controller
{
    public function logCookieConsent(): void
    {
        $input = $this->request->all();
        $anonId = (string)($input['anonymous_consent_id'] ?? '');
        $version = (string)($input['consent_version'] ?? '');
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $anonId)
            || $version !== '2.0') {
            $this->json(['success'=>false,'message'=>'ข้อมูลการยินยอมไม่ถูกต้อง'],422);
            return;
        }
        foreach (['necessary','functional','analytics','marketing','third_party'] as $key) {
            if (!array_key_exists($key,$input) || !in_array((string)$input[$key],['0','1'],true)) {
                $this->json(['success'=>false,'message'=>'ค่าคุกกี้ไม่ถูกต้อง'],422);
                return;
            }
        }
        if ((int)$input['necessary'] !== 1) {
            $this->json(['success'=>false,'message'=>'ต้องเปิดคุกกี้จำเป็น'],422);
            return;
        }
        try {
            $ipHash = hash('sha256', $this->request->ip());
            $ua = substr($this->request->userAgent(),0,255);
            // Unique anonymous_consent_id retains the current recorded choice;
            // consented_at tracks the latest explicit choice, not full history.
            Database::execute(
                "INSERT INTO cookie_consents
                (anonymous_consent_id,consent_version,necessary,functional,analytics,marketing,third_party,ip_hash,user_agent,consented_at)
                VALUES (?,?,?,?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE
                  consent_version=VALUES(consent_version),
                  necessary=VALUES(necessary),
                  functional=VALUES(functional),
                  analytics=VALUES(analytics),
                  marketing=VALUES(marketing),
                  third_party=VALUES(third_party),
                  consented_at=NOW(),updated_at=NOW()",
                [$anonId,$version,1,(int)$input['functional'],(int)$input['analytics'],
                 (int)$input['marketing'],(int)$input['third_party'],$ipHash,$ua]
            );
            $this->json(['success'=>true,'message'=>'บันทึกการตั้งค่าคุกกี้แล้ว']);
        } catch (\Throwable $e) {
            error_log('Cookie consent write failure: '.get_class($e));
            $this->json(['success'=>false,'message'=>'ยังไม่สามารถบันทึกการเลือกคุกกี้ได้'],503);
        }
    }

    public function logPopupEvent(): void
    {
        $input = $this->request->all();
        $popupId = (int) ($input['popup_id'] ?? 0);
        $eventType = $input['event_type'] ?? 'impression';
        $pagePath = $input['page_path'] ?? '/';

        if ($popupId <= 0) {
            $this->json(['success' => false, 'message' => 'Invalid popup ID'], 400);
            return;
        }

        // Update popup counter
        if ($eventType === 'impression') {
            Database::execute("UPDATE popups SET impressions_count = impressions_count + 1 WHERE id = ?", [$popupId]);
        } elseif (in_array($eventType, ['click', 'cta_click'], true)) {
            Database::execute("UPDATE popups SET clicks_count = clicks_count + 1 WHERE id = ?", [$popupId]);
        }

        // Insert event record
        $ip = $this->request->ip();
        $ua = substr($this->request->userAgent(), 0, 255);
        $device = (str_contains(strtolower($ua), 'mobile')) ? 'mobile' : 'desktop';

        Database::execute(
            "INSERT INTO popup_events (popup_id, event_type, ip_address, user_agent, device_type, page_path, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$popupId, $eventType, $ip, $ua, $device, $pagePath]
        );

        $this->json(['success' => true, 'message' => 'Event tracked']);
    }
}
