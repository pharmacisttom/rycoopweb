<?php
declare(strict_types=1);
$overrides=[];
function env(string $key,mixed $default=null):mixed{global $overrides;return $overrides[$key]??$default;}
$defaults=require __DIR__.'/../config/features.php';
if($defaults['member_portal']!==false||$defaults['member_login']!==true||$defaults['member_financial_data']!==false)throw new RuntimeException('Feature defaults changed');echo "PASS: isolated feature defaults preserve dividend login and deny unlaunched portal\n";
$overrides=['FEATURE_MEMBER_PORTAL'=>'false','FEATURE_MEMBER_LOGIN'=>'false','FEATURE_MEMBER_FINANCIAL_DATA'=>'false'];$ci=require __DIR__.'/../config/features.php';
if($ci['member_login']!==false||$ci['member_portal']!==false)throw new RuntimeException('CI feature flags ignored');echo "PASS: explicit CI flags override login defaults\n";
