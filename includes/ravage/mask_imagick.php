<?php
/*
 * Ravage Imagick renderer: garment-only tint (corner flood-fill + detail mask)
 * Version: 1.3.3
 */
if (!defined('ABSPATH')) exit;

/**
 * Render a tinted garment with Imagick (handles white garment on white bg).
 * Returns ['path'=>tmpPath, 'mime'=>'image/webp'] or null on failure.
 */
function ravage_render_tinted_imagick(string $basePath, string $hex, int $canvas, string $tmp){
  try{
    $img = new Imagick($basePath);
    $img->setImageColorspace(Imagick::COLORSPACE_RGB);
    $img->thumbnailImage($canvas,$canvas,true);
    $img->extentImage($canvas,$canvas,0,0);

    // ---------- A) Background mask via corner flood-fill (handles off-white sweeps)
    $work = clone $img;
    $work->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
    $qr = Imagick::getQuantumRange(); $Q = (is_array($qr)&&isset($qr['quantumRangeLong'])) ? $qr['quantumRangeLong'] : 65535;
    $fuzz = (int) round($Q * 0.12); // ~12% tolerance
    $getPx = function(Imagick $im, int $x, int $y){ $p=$im->getImagePixelColor($x,$y); return new ImagickPixel($p->getColorAsString()); };
    $trans = new ImagickPixel('transparent');
    foreach([[0,0],[$canvas-1,0],[0,$canvas-1],[$canvas-1,$canvas-1]] as [$cx,$cy]){
      $work->floodfillPaintImage($trans, $fuzz, $getPx($work,$cx,$cy), $cx, $cy, false);
    }
    $bgInv = clone $work;                     // inverse alpha -> opaque where NOT background
    $bgInv->separateImageChannel(Imagick::CHANNEL_ALPHA);
    $bgInv->negateImage(false);
    if (class_exists('ImagickKernel')) {
      $kernel = ImagickKernel::fromBuiltIn(Imagick::KERNEL_DISK, "1");
      $bgInv->morphology(Imagick::MORPHOLOGY_CLOSE, 1, $kernel);
    }
    $bgInv->blurImage(0.0, 1.0);

    // ---------- B) Detail mask (high-pass edges -> fill interior)
    $gray = clone $img; $gray->transformImageColorspace(Imagick::COLORSPACE_GRAY);
    $blur = clone $gray; $blur->gaussianBlurImage(0, 2.5);
    $edge = clone $gray; $edge->compositeImage($blur, Imagick::COMPOSITE_DIFFERENCE, 0, 0); // |gray - blur|
    $edge->normalizeImage();
    $edge->levelImage($Q*0.05, $Q*0.95, 1.0); // stretch
    $edge->thresholdImage($Q*0.06);           // only stronger detail
    if (class_exists('ImagickKernel')) {
      $k2 = ImagickKernel::fromBuiltIn(Imagick::KERNEL_DISK, "2");
      $edge->morphology(Imagick::MORPHOLOGY_DILATE, 1, $k2); // grow edges inward
      $edge->morphology(Imagick::MORPHOLOGY_CLOSE, 1, $k2);  // seal gaps
    }
    $edge->blurImage(0.0, 1.2);

    // ---------- C) Union masks (garment = bgInv OR detail), then tint only there
    $mask = clone $bgInv;                       // mask = union(bgInv, edge)
    $mask->compositeImage($edge, Imagick::COMPOSITE_LIGHTEN, 0, 0);

    $tint = new Imagick();                      // build tint layer and clip with mask
    $tint->newImage($canvas,$canvas,new ImagickPixel(ravage_norm_hex($hex)),'png');
    $tint->setImageOpacity(0.34);
    $tint->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);

    $img->compositeImage($tint, Imagick::COMPOSITE_OVER, 0, 0); // apply tint

    // ---------- Export WEBP
    $img->setImageFormat('webp');
    $img->setImageCompressionQuality(85);
    $img->writeImage($tmp);

    // cleanup
    foreach ([$img,$work,$bgInv,$gray,$blur,$edge,$tint] as $o) { if ($o instanceof Imagick) $o->destroy(); }

    return ['path'=>$tmp, 'mime'=>'image/webp'];
  } catch (Throwable $e) {
    return null;
  }
}
