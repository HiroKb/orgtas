<?php 
require_once('function.php');
require_once('auth.php');
debug('-------------------------email変更ページ---------------------');
try {
  $dbh = dbConnect();//DBハンドラ取得
} catch (\Exception $e) {
  error_log('エラー発生:' . $e->getMessage());
  header("Location:unauthorized.php");
  exit();
}

$userData = getUser($dbh, $_SESSION['user_id']);
$changeFlg = false;
debug(print_r($userData,true));
if (!empty($_POST)) {//post送信されていた場合
  checkToken();//正規アクセスか確認

  // post内容を格納・未入力チェック
  $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
  $pass = filter_input(INPUT_POST, 'pass', FILTER_SANITIZE_SPECIAL_CHARS);
  validRequired($email, 'email');
  validRequired($pass, 'pass');
  if (empty($errs)) {//未入力じゃない場合
    validEmail($email,'email');
    if (!password_verify($pass, $userData['pass'])) {//パスワード照合
      $errs['pass'] = MSG8;
    }
    if (empty($errs)) {//email重複チェック
      validEmailDup($email, 'email');
    }

    if (empty($errs)) {//バリデーションを通過した場合
      try {
        // emailアップデート処理
        $sql = 'UPDATE users 
                SET email = :email 
                WHERE search_id = :id AND delete_flg = 0';
        $data = array(':email' => $email, ':id' => $_SESSION['user_id']);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {//アップデートに成功した場合の処理
          $changeFlg = true;
          $userData['email'] = $email;
        }
      } catch (Exception $e ) {
        error_log('エラー発生'. $e->getMessage());
        $errs['common'] = MSG2;
      }
    }
  }
}
$token;
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'メールアドレス変更';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="emailEdit">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <section class="like-memo-container my-color-border">
<?php if($changeFlg) :?>
        <p id="slide-msg" class="my-color-background">メールアドレスを変更しました</p>
<?php endif; ?>
        <form action="" method="post">
          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

          <p>現在のメールアドレス</p>
          <p class="current-email"><?php echo sani($userData['email']); ?></p>
          <!-- メールアドレス -->
          <label for="email">新しいメールアドレス</label>
          <div class="err-msg-wrap"><?php echoErrMsg('email'); ?></div>
          <input id="email" type="text" name="email" value="<?php if($changeFlg === false) echo getFormData('email') ?>">

          <!-- パスワード -->
          <label for="pass">パスワード</label>
          <div class="err-msg-wrap"><?php echoErrMsg('pass'); ?></div>
          <input id="pass" type="password" name="pass" placeholder="半角英数8文字以上" value="<?php if($changeFlg === false) echo getFormData('pass') ?>">

          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button class="my-color-background">メールアドレスを変更</button>
        </form>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');