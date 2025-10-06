<?php
if (!defined('ABSPATH')) exit;

/* --- Parse Quality "Vendor(Item)" --- */
function bee_parse_quality($q){
  if(!preg_match('/^\s*([A-Za-z&\s\.\+\-]+)\s*\(([^)]+)\)\s*$/',(string)$q,$m)) return ['vendor'=>'','style'=>''];
  return ['vendor'=>trim($m[1]),'style'=>trim($m[2])];
}

/* --- Candidate vendor domains + URLs --- */
function bee_vendor_domains($vendor){
  $v=strtolower($vendor); $d=[];
  if(str_contains($v,'sanmar')||str_contains($v,'port')||str_contains($v,'district')) $d[]='sanmar.com';
  if(str_contains($v,'alphabroder')) $d[]='alphabroder.com';
  if(str_contains($v,'ssactivewear')||str_contains($v,'s&s')) $d[]='ssactivewear.com';
  if(str_contains($v,'gildan')) $d[]='gildan.com';
  if(str_contains($v,'bella')) $d[]='bellacanvas.com';
  if(str_contains($v,'next level')) $d[]='nextlevelapparel.com';
  if(str_contains($v,'augusta')) $d[]='augustasportswear.com';
  return $d;
}
function bee_vendor_urls($vendor,$style){
  $v=strtolower($vendor); $s=trim($style); $u=[];
  if(str_contains($v,'sanmar')||str_contains($v,'port')||str_contains($v,'district')) $u[]="https://www.sanmar.com/p/$s";
  if(str_contains($v,'alphabroder')) $u[]="https://www.alphabroder.com/product/$s";
  if(str_contains($v,'ssactivewear')||str_contains($v,'s&s')) $u[]="https://www.ssactivewear.com/search/$s";
  if(str_contains($v,'gildan')) $u[]="https://www.gildan.com/us/$s.html";
  if(str_contains($v,'bella')) $u[]="https://www.bellacanvas.com/collections/all/products/$s";
  if(str_contains($v,'next level')) $u[]="https://www.nextlevelapparel.com/$s";
  if(str_contains($v,'augusta')) $u[]="https://www.augustasportswear.com/$s";
  $u[]="https://duckduckgo.com/html/?q=".rawurlencode("$vendor $s product");
  return array_values(array_unique($u));
}

/* --- HTTP + HTML helpers --- */
function bee_http_get($url){
  $r=wp_remote_get($url,['timeout'=>15,'redirection'=>5,'headers'=>[
    'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
    'Accept-Language'=>'en-US,en;q=0.9'
  ]]);
  return [(int)wp_remote_retrieve_response_code($r), is_wp_error($r)?'':(string)wp_remote_retrieve_body($r)];
}
function bee_extract_links_from_ddg($html){
  $out=[]; if(!$html) return $out;
  if(preg_match_all('/href="([^"]+)"/i',$html,$m)){
    foreach($m[1] as $h){
      if(str_contains($h,'duckduckgo.com/l/?uddg=')){ parse_str(parse_url($h,PHP_URL_QUERY)??'',$q); $t=$q['uddg']??''; if($t) $out[]=urldecode($t); }
      elseif(preg_match('#^https?://#i',$h) && !str_contains($h,'duckduckgo.com')) $out[]=$h;
    }
  }
  return array_values(array_unique($out));
}
function bee_strip_to_text($html){
  $html=preg_replace('#<script[^>]*>.*?</script>#is','',$html);
  $html=preg_replace('#<style[^>]*>.*?</style>#is','',$html);
  $t=trim(html_entity_decode(strip_tags($html),ENT_QUOTES));
  return mb_substr(preg_replace('/\s{2,}/',' ',$t),0,6000);
}

/* --- Fetch vendor page (returns text + best-guess URL) --- */
function bee_fetch_vendor($vendor,$style,$override=''){
  $try=function($u){[$c,$b]=bee_http_get($u);return [$c,$b,($c===200&&$b)?bee_strip_to_text($b):''];};
  if($override && preg_match('#^https?://#i',$override)){[$c,$b,$t]=$try($override); if($t) return ['text'=>$t,'url'=>$override]; $guess=$override;}
  $guess='';
  foreach(bee_vendor_urls($vendor,$style) as $u){
    [$c,$b,$t]=$try($u);
    if(str_contains($u,'duckduckgo.com')){ $links=bee_extract_links_from_ddg($b); $doms=bee_vendor_domains($vendor);
      usort($links,function($a,$b)use($doms,$style){$sa=(int)array_reduce($doms,fn($m,$d)=>$m||str_contains($a,$d),false);$sb=(int)array_reduce($doms,fn($m,$d)=>$m||str_contains($b,$d),false);$ma=(int)str_contains(strtolower($a),strtolower($style));$mb=(int)str_contains(strtolower($b),strtolower($style));return ($sb+$mb)<=>($sa+$ma);});
      foreach($links as $lk){ if(!$guess) $guess=$lk; [$c2,$b2,$t2]=$try($lk); if($t2) return ['text'=>$t2,'url'=>$lk]; }
    } elseif($t){ if(!$guess) $guess=$u; return ['text'=>$t,'url'=>$u]; }
    elseif(!$guess) $guess=$u;
  }
  return ['text'=>'','url'=>$guess];
}

/* --- Pull site text (home page + tagline) --- */
function bee_fetch_site_text($home){
  $txt=[get_bloginfo('name'),get_bloginfo('description')];
  [$c,$b]=bee_http_get($home); if($c===200 && $b) $txt[]=bee_strip_to_text($b);
  return trim(implode(' • ',array_filter(array_map('trim',$txt))));
}

/* --- STRICT item-type detection (whole words; apparel first) --- */
function bee_infer_type_from_text($t){
  $m=[ // ordered by priority
    '/\bpolo\b/i'=>'Polo',
    '/\b(t[-\s]?shirt|tee|jersey)\b/i'=>'T-Shirt',
    '/\bshirt\b/i'=>'Shirt',
    '/\bhoodie\b/i'=>'Hoodie',
    '/\bsweatshirt\b/i'=>'Sweatshirt',
    '/\bfleece\b/i'=>'Fleece',
    '/\bjacket\b/i'=>'Jacket',
    '/\bpullover\b/i'=>'Pullover',
    '/\b(cap|hat|visor|beanie)\b/i'=>'Cap',
    '/\b(backpack|duffel|tote|bag)\b/i'=>'Bag',
    '/\bmug\b/i'=>'Mug',
    '/\btumbler\b/i'=>'Tumbler',
    '/\bbottle\b/i'=>'Bottle',
    '/\b(sticker|decal)\b/i'=>'Sticker',
    '/\b(banner|yard\s+sign|sign)\b/i'=>'Banner',
    '/\bpen(s)?\b/i'=>'Pen' // whole word only; last resort among small goods
  ];
  foreach($m as $re=>$label){ if(preg_match($re,$t)) return $label; }
  return 'Product';
}
