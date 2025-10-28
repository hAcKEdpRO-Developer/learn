<?php
/* 
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing 
Edit by 2025-10-24 15:32:35 
จำนวน 30 บรรทัด
*/
?>
<?php
function simple_qr_png($text, $file){
    $im = imagecreatetruecolor(300,300);
    $white = imagecolorallocate($im,255,255,255);
    $black = imagecolorallocate($im,0,0,0);
    imagefilledrectangle($im,0,0,299,299,$white);
    // วาด pattern ง่ายๆ แทน QR จริง (เดโม)
    for($i=0;$i<300;$i+=10){
        imageline($im,$i,0,$i,299,$black);
        imageline($im,0,$i,299,$i,$black);
    }
    imagestring($im,5,10,140,substr($text,0,20),$black);
    imagepng($im,$file);
    imagedestroy($im);
}
?>
<?php
/* 
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing 
Edit by 2025-10-24 15:32:35 
จำนวน 30 บรรทัด
*/
?>