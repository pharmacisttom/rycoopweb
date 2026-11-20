<?php
declare(strict_types=1);
// Pure validation test. Does not connect to a database.
require_once dirname(__DIR__).'/app/Services/HomeCompositionService.php';
use App\Services\HomeCompositionService as Composition;
$baseline=Composition::defaults();
if (count($baseline['order'])!==8 || count($baseline['layers'])!==8) throw new RuntimeException('Default layout missing');
$good=$baseline;
$good['slides']=[];
for($i=1;$i<=6;$i++)$good['slides'][]=[
  'id'=>'item-'.$i, 'title'=>'หัวข้อ '.$i,'body'=>'รายละเอียด '.$i,'link'=>'/about'
];
$good['background']=['image_url'=>'/storage/uploads/highlights/cover.webp','mobile_image_url'=>'/storage/uploads/highlights/cover-mobile.webp','overlay_opacity'=>65,'position'=>'center'];
$good['slides'][0]['image_url']='/storage/uploads/highlights/item-1.jpg';
$good['slides'][0]['mobile_image_url']='/storage/uploads/highlights/item-1-mobile.webp';
$validated=Composition::validate($good);
if(count($validated['slides'])!==6)throw new RuntimeException('Valid carousel rejected');
$cases=[
  'external mobile background'=>static function(array &$x){$x['background']['mobile_image_url']='https://evil.example/track.webp';},
  'traversal mobile image'=>static function(array &$x){$x['slides'][0]['mobile_image_url']='/uploads/../private.jpg';},
  'external background'=>static function(array &$x){$x['background']['image_url']='https://evil.example/track.jpg';},
  'javascript background'=>static function(array &$x){$x['background']['image_url']='javascript:alert(1)';},
  'traversal background'=>static function(array &$x){$x['background']['image_url']='/uploads/../secret.jpg';},
  'svg background'=>static function(array &$x){$x['background']['image_url']='/uploads/logo.svg';},
  'invalid overlay'=>static function(array &$x){$x['background']['overlay_opacity']=2;},
  'external slide image'=>static function(array &$x){$x['slides'][0]['image_url']='https://evil.example/track.webp';},
  'duplicate ids'=>static function(array &$x){$x['slides'][1]['id']=$x['slides'][0]['id'];},
  'external link'=>static function(array &$x){$x['slides'][0]['link']='https://example.com';},
  'javascript link'=>static function(array &$x){$x['slides'][0]['link']='javascript:alert(1)';},
  'duplicate section'=>static function(array &$x){$x['order'][1]=$x['order'][0];},
  'missing visibility'=>static function(array &$x){unset($x['visible']['hero']);},
  'invalid theme'=>static function(array &$x){$x['theme']='bad';},
  'oversized autoplay'=>static function(array &$x){$x['autoplay_seconds']=999;},
  'invalid layer'=>static function(array &$x){$x['layers']['hero']['surface']='url(https://example.com)';},
  'missing layer'=>static function(array &$x){unset($x['layers']['hero']);},
  'seven slides'=>static function(array &$x){$x['slides'][]=['id'=>'item-7','title'=>'เพิ่ม','body'=>'รายละเอียด'];},
];
foreach($cases as $name=>$change){
  $copy=$good;$change($copy);
  try{Composition::validate($copy);}catch(InvalidArgumentException $e){continue;}
  throw new RuntimeException('Accepted invalid input: '.$name);
}
// Older persisted drafts without appearance remain loadable.
$legacy=$good;
unset($legacy['layers'], $legacy['background']);
foreach($legacy['slides'] as &$slide)unset($slide['image_url'],$slide['mobile_image_url']);
unset($slide);
$legacyValidated=Composition::validate($legacy);
if(count($legacyValidated['layers'])!==8 || $legacyValidated['background']['overlay_opacity']!==60 || $legacyValidated['slides'][0]['image_url']!=='' || $legacyValidated['slides'][0]['mobile_image_url']!=='' || $legacyValidated['background']['mobile_image_url']!=='')throw new RuntimeException('Legacy compatibility failed');
echo "RC15_HOME_COMPOSITION_VALIDATION=PASS\n";
