<?php
declare(strict_types=1);
namespace App\Services;

/** Draft bytes live outside the web root; publication only grants reads of immutable IDs. */
final class LayoutImageService
{
    public static function path(string $id,string $size): string {
        LayoutSchema::id($id);if(!in_array($size,['full','thumb'],true))throw new \InvalidArgumentException('ขนาดภาพไม่ถูกต้อง');
        return dirname(__DIR__,2).'/storage/private/layout-gallery/'.$id.'-'.$size.'.webp';
    }
    public static function store(string $id,string $bytes,array &$created): void {
        if(strlen($bytes)>5*1024*1024)throw new \InvalidArgumentException('ภาพต้องไม่เกิน 5 MB');
        $info=@getimagesizefromstring($bytes);$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if(!$info||!in_array($mime,['image/jpeg','image/png','image/webp'],true)||$info['mime']!==$mime||$info[0]>8000||$info[1]>8000||$info[0]*$info[1]>16000000)throw new \InvalidArgumentException('รองรับ JPEG, PNG, WebP ที่ถอดรหัสได้ ไม่เกิน 16 ล้านพิกเซล');
        if(!function_exists('imagewebp'))throw new \RuntimeException('ต้องติดตั้ง GD WebP');$source=@imagecreatefromstring($bytes);if(!$source)throw new \InvalidArgumentException('ไฟล์ภาพเสีย');
        try{foreach(['full'=>1920,'thumb'=>480] as $size=>$max){$ratio=min(1,$max/max($info[0],$info[1]));$w=max(1,(int)round($info[0]*$ratio));$h=max(1,(int)round($info[1]*$ratio));$canvas=imagecreatetruecolor($w,$h);
                try{imagealphablending($canvas,false);imagesavealpha($canvas,true);imagefilledrectangle($canvas,0,0,$w,$h,imagecolorallocatealpha($canvas,0,0,0,127));imagecopyresampled($canvas,$source,0,0,0,0,$w,$h,$info[0],$info[1]);
                    $path=self::path($id,$size);if(!is_dir(dirname($path))&&!mkdir(dirname($path),0700,true)&&!is_dir(dirname($path)))throw new \RuntimeException('พื้นที่จัดเก็บภาพไม่พร้อม');
                    $handle=@fopen($path,'x');if(!$handle)throw new \RuntimeException('ไม่สามารถสร้างไฟล์ภาพ');fclose($handle);$created[]=$path;if(!imagewebp($canvas,$path,85))throw new \RuntimeException('บันทึกภาพไม่สำเร็จ');chmod($path,0600);
                }finally{imagedestroy($canvas);}}
        }finally{imagedestroy($source);}
    }
    public static function cleanup(array $created): void {foreach($created as $path)if(is_file($path))unlink($path);}
}
