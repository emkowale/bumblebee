<?php
/*
 * Ravage GD renderer: garment-only tint using background sampling
 * Version: 1.3.1-mod
 */
if (!defined('ABSPATH')) exit;

/**
 * Render a tinted garment with GD.
 * Returns ['path'=>tmpPath, 'mime'=>..., 'filename'=>...] or null on failure.
 */
function ravage_render_tinted_gd(string $basePath, string $hex, int $canvas, string $tmp, string $filename){
  $src0 = ravage_gd_image_create($basePath);
  if(!$src0) return null;

  // Fit to square canvas
  $w = imagesx($src0); $h = imagesy($src0);
  $scale = min($canvas/max(1,$w), $canvas/max(1,$h));
  $dw = max(1,(int)round($w*$scale)); $dh = max(1,(int)round($h*$scale));
  $ox = (int)(($canvas-$dw)/2); $oy = (int)(($canvas-$dh)/2);

  $dst = imagecreatetruecolor($canvas,$canvas);
  imagealphablending($dst,true); imagesavealpha($dst,true);
  $bg = imagecolorallocatealpha($dst,255,255,255,127);
  imagefilledrectangle($dst,0,0,$canvas,$canvas,$bg);
  imagecopyresampled($dst,$src0,$ox,$oy,0,0,$dw,$dh,$w,$h);
  imagedestroy($src0);

  // Sample average background from corners
  $corners = [
    imagecolorat($dst,1,1),
    imagecolorat($dst,$canvas-2,1),
    imagecolorat($dst,1,$canvas-2),
    imagecolorat($dst,$canvas-2,$canvas-2),
  ];
  $bgR=$bgG=$bgB=0;
  foreach($corners as $c){
    $bgR += ($c>>16)&255; $bgG += ($c>>8)&255; $bgB += $c&255;
  }
  $bgR=(int)round($bgR/4); $bgG=(int)round($bgG/4); $bgB=(int)round($bgB/4);

  // Target color
  [$tr,$tg,$tb] = ravage_hex2rgb($hex);
  $blend = 0.34;
  $distThresh = 28; $whiteThresh=245;

  // Per-pixel mask: only pixels different enough from background
  for($y=0;$y<$canvas;$y++){
    for($x=0;$x<$canvas;$x++){
      $rgba=imagecolorat($dst,$x,$y);
      $r=($rgba>>16)&255; $g=($rgba>>8)&255; $b=$rgba&255;
      $a=($rgba & 0x7F000000)>>24;
      if($a===127) continue; // transparent pad
      if($r>=$whiteThresh && $g>=$whiteThresh && $b>=$whiteThresh) continue;
      $dr=$r-$bgR; $dg=$g-$bgG; $db=$b-$bgB;
      $dist = sqrt($dr*$dr+$dg*$dg+$db*$db);
      if($dist<$distThresh) continue;
      $nr=(int)round($r*(1-$blend)+$tr*$blend);
      $ng=(int)round($g*(1-$blend)+$tg*$blend);
      $nb=(int)round($b*(1-$blend)+$tb*$blend);
      $col=imagecolorallocatealpha($dst,$nr,$ng,$nb,$a);
      imagesetpixel($dst,$x,$y,$col);
    }
  }

  // Save webp/png
  if(function_exists('imagewebp')){
    $out=$tmp.'.webp'; imagewebp($dst,$out,85); imagedestroy($dst);
    return ['path'=>$out,'mime'=>'image/webp'];
  } else {
    $out=$tmp.'.png'; imagepng($dst,$out); imagedestroy($dst);
    $fn = preg_replace('/\.webp$/i','.png',$filename);
    return ['path'=>$out,'mime'=>'image/png','filename'=>$fn];
  }
}
