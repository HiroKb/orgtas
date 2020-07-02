<?php 
require_once('function.php');
require_once('auth.php');

$userData = false;
if (!empty($_GET['u_id'])) {//GETパラメータがある場合
  $searchId = filter_input(INPUT_GET, 'u_id', FILTER_SANITIZE_SPECIAL_CHARS);
  if (validLowrCase($searchId)) {//GETパラメータが正しい場合
    $userData = getUser($dbh, $searchId);
  }else{//GETパラメータが不正な場合
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
  }
}elseif (!empty($_SESSION['user_id'])) {//GETパラメータがなくログイン済みの場合
  try {
    $dbh = dbConnect();//DBハンドラ取得
  } catch (\Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
    header("Location:unauthorized.php");
    exit();
  }
  $userData = getUser($dbh, $_SESSION['user_id']);
  debug(print_r($userData,true));
  debug(print_r($_SESSION['user_id'],true));
}else{//GETパラメータがなくログインもしていない場合(認可で弾くので基本的にありえない)
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
}
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'プロフィール';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="profile">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
<?php if($userData) : //ユーザーデータが取得できた場合?>
      <section class="like-memo-container" style="border-left: 30px <?php echo sani($userData['color']);?> solid;">

    <?php if($userData['search_id'] === $_SESSION['user_id']) : //自分のプロフィールの場合?>
        <a href="profileEdit.php"><i class="fas fa-cog" style="color: <?php echoMyColor(); ?>"></i></a>
    <?php endif; ?>

        <img src="<?php getImg($userData['img']); ?>" alt="プロフィール画像">
        <p class="username"><?php echo sani($userData['username']); ?></p>
    <?php if(!empty($userData['introduction'])) : //自己紹介がある場合?>
        <p class="introduce"><?php echo sani($userData['introduction']); ?></p>
    <?php endif; ?>

<?php else : //ユーザーデータが取得できなかった場合 ?>
      <section class="like-memo-container" style="border-left: 30px #666 solid;">
        <h2>存在しないか退会済みのユーザーです。</h2>
<?php endif; ?>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');