<?php
declare(strict_types=1);
namespace App\Services;

class RichTextService
{
    public static function clean(string $html): string
    {
        if (!preg_match('/<\/?[a-z][\s\S]*>/i', $html)) {
            $html = '<p>'.nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>';
        }
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,h2,h3,blockquote,ul,ol,li,a[href|title]');
        $config->set('URI.AllowedSchemes', ['http'=>true,'https'=>true,'mailto'=>true,'tel'=>true]);
        $config->set('Cache.DefinitionImpl', null);
        return (new \HTMLPurifier($config))->purify($html);
    }

    public static function text(string $html): string
    {
        return trim(html_entity_decode(strip_tags(self::clean($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
