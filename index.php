<?php 
require_once('function.php');
require_once('auth.php');
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'TOP';
require_once('head.php');
 ?>
<body class="index">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <section class="site-width">
      <h2>個人・グループのタスク管理を<br>
      全て一ヶ所で
      </h2>
      <div class="btn-wrap">
        <a href="login.php" class="login-btn">ログイン</a>
        <a href="signup.php" class="signup-btn">新規登録</a>
        <a href="" class="trial-btn">お試し</a>
      </div>
    </section>
  </main>

<?php 
require_once('footer.php');