<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$service=file_get_contents($root.'/app/Services/HomeCompositionService.php');
$controller=file_get_contents($root.'/app/Controllers/Admin/HomeCompositionController.php');
$routes=file_get_contents($root.'/config/routes.php');
$home=file_get_contents($root.'/src/pages/HomePage.jsx');
$editor=file_get_contents($root.'/src/components/admin/HomeCompositionEditor.jsx');
$slider=file_get_contents($root.'/src/components/home/HomeHighlightSlider.jsx');
foreach (['service'=>$service,'controller'=>$controller,'routes'=>$routes,'home'=>$home,'editor'=>$editor,'slider'=>$slider] as $part=>$text) {
  if (!is_string($text) || $text==='') {fwrite(STDERR,"RC15 missing $part\n");exit(1);}
}
$checks=[
  ['only published output',$controller,'HomeCompositionService::published()'],
  ['step-up MFA',$controller,'requireFreshStepUpMfa'],
  ['CSRF routes',$routes,'HomeCompositionController@draft'],
  ['public route',$routes,'/api/public/home-composition'],
  ['slide 5 to 6 publishing',$service,'count($draft[\'slides\'])<5'],
  ['version lock',$service,'VERSION_CONFLICT'],
  ['audit publication',$service,"'home_composition','publish'"],
  ['home reads published data',$home,'/api/public/home-composition'],
  ['rates/news moved below hero',$home,'home-rates-news-lowered'],
  ['highlight after hero',$home,"<HomeHighlightSlider composition={composition}/>"],
  ['editor has preview',$editor,'<Preview composition={model}/>'],
  ['slider is accessible',$slider,'aria-label="ผลงานและจุดเด่นของสหกรณ์"'],
];
foreach($checks as [$name,$file,$needle])if(!str_contains($file,$needle)){fwrite(STDERR,"RC15 contract FAIL: $name\n");exit(1);}
$hero=strpos($home,'<figure className="hero-portrait">');
$highlight=strpos($home,'<HomeHighlightSlider composition={composition}/>');
$lower=strpos($home,'home-rates-news-lowered');
if(!($hero<$highlight&&$highlight<$lower)){fwrite(STDERR,"RC15 order FAIL\n");exit(1);}
echo "RC15_HOME_COMPOSITION_SOURCE_CONTRACT=PASS\n";
