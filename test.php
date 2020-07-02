<?php 
require_once('function.php');
$test = false;
if (!empty($test)) {
  debug('hi');
}else{
  debug('nohi');
}

 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'TOP';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="index">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
 <main>
   <div>test</div>
 </main>
<?php 
require_once('footer.php');