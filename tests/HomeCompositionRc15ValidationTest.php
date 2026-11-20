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
$validated=Composition::validate($good);
if(count($validated['slides'])!==6)throw new RuntimeException('Valid carousel rejected');
$cases=[
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
unset($legacy['layers']);
if(count(Composition::validate($legacy)['layers'])!==8)throw new RuntimeException('Legacy compatibility failed');
echo "RC15_HOME_COMPOSITION_VALIDATION=PASS\n";
