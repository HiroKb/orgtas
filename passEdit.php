<?php 
require_once('function.php');
require_once('auth.php');
debug('-------------------------pass変更ページ---------------------');
$changeFlg = false;
if (!empty($_POST)) {//post送信されていた場合
  checkToken();//正規アクセスか確認

  // post内容を格納・未入力チェック
  $currentPass = filter_input(INPUT_POST, 'current_pass', FILTER_SANITIZE_SPECIAL_CHARS);
  $newPass = filter_input(INPUT_POST, 'new_pass', FILTER_SANITIZE_SPECIAL_CHARS);
  $newPassRe = filter_input(INPUT_POST, 'new_pass_re', FILTER_SANITIZE_SPECIAL_CHARS);
  validRequired($currentPass, 'current_pass');
  validRequired($newPass, 'new_pass');
  validRequired($newPassRe, 'new_pass_re');
  if (empty($errs)) {//未入力じゃない場合
    // currentpassバリデーション
    validMaxLen($currentPass,'current_pass');
    validMinLen($currentPass,'current_pass');
    validHalf($currentPass,'current_pass');

    // newpass,newpassreバリデーション
    validMaxLen($newPass,'new_pass');
    validMinLen($newPass,'new_pass');
    validHalf($newPass,'new_pass');
    validEqual($newPass, $newPassRe, 'new_pass_re');

    if (empty($errs)) {//バリデーションを通ったらパスワード照合
      debug('照合');

      try {
        $dbh = dbConnect();//DBハンドラ取得
      } catch (\Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
        header("Location:unauthorized.php");
        exit();
      }
      $userData = getUser($dbh, $_SESSION['user_id']);
      if (!password_verify($currentPass, $userData['pass'])) {//パスワード照合
        $errs['current_pass'] = MSG8;
      }
    }

    if (empty($errs)) {//バリデーションを通過した場合
      debug('バリでおｋ');
      try {
        // passアップデート処理
        $sql = 'UPDATE users 
                SET pass = :pass 
                WHERE search_id = :id AND delete_flg = 0';
        $data = array(':pass' => password_hash($newPass, PASSWORD_DEFAULT),
                      ':id' => $_SESSION['user_id']);
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {//アップデートに成功した場合の処理
          debug('アップデート成功');
          $changeFlg = true;
        }
      } catch (Exception $e ) {
        error_log('エラー発生:' . $e->getMessage());
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
<body class="passEdit">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <section class="like-memo-container my-color-border">
<?php if($changeFlg) :?>
        <p id="slide-msg" class="my-color-background">パスワードを変更しました</p>
<?php endif; ?>
        <form action="" method="post">
          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

          <!-- 現在のパスワード -->
          <label for="current_pass">現在のパスワード</label>
          <div class="err-msg-wrap"><?php echoErrMsg('current_pass'); ?></div>
          <input id="current_pass" type="password" name="current_pass" value="<?php if($changeFlg === false) echo getFormData('current_pass'); ?>">

          <!-- 新しいパスワード -->
          <label for="new_pass">新しいパスワード</label>
          <div class="err-msg-wrap"><?php echoErrMsg('new_pass'); ?></div>
          <input id="new_pass" type="password" name="new_pass" value="<?php if($changeFlg === false) echo getFormData('new_pass'); ?>">

          <!-- パスワード -->
          <label for="new_pass_re">新しいパスワード(再入力)</label>
          <div class="err-msg-wrap"><?php echoErrMsg('new_pass_re'); ?></div>
          <input id="new_pass_re" type="password" name="new_pass_re" value="<?php if($changeFlg === false) echo getFormData('new_pass_re'); ?>">

          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button class="my-color-background">パスワードを変更</button>
        </form>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');