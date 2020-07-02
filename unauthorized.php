<?php 
require_once('function.php');
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = '不正なアクセス';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="unauthorised">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <div class="like-memo-container rand-color-border">
        <h2>エラーが発生しました</h2>
      </div>
    </div>
  </main>

<?php 
require_once('footer.php');