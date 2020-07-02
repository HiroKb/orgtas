<?php 
require_once('function.php');
require_once('auth.php');

debug('-------------------------新規登録ページ---------------------');

if (!empty($_POST)) {//post送信されていた場合
  checkToken();//正規アクセスか確認

  // post内容を格納・未入力チェック
  $username = filter_input(INPUT_POST, 'username');
  $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
  $pass = filter_input(INPUT_POST, 'pass', FILTER_SANITIZE_SPECIAL_CHARS);
  $pass_re = filter_input(INPUT_POST, 'pass_re', FILTER_SANITIZE_SPECIAL_CHARS);
  validRequired($username, 'username');
  validRequired($email, 'email');
  validRequired($pass, 'pass');
  validRequired($pass_re, 'pass_re');

  if (empty($errs)) {//すべての項目が入力されている場合

    // usernameバリデーション
    validMaxLen($username, 'username');

    // pass,pass_reバリデーション
    validMaxLen($pass, 'pass');
    validMinLen($pass, 'pass');
    validHalf($pass, 'pass');
    validEqual($pass, $pass_re, 'pass_re');

    // emailバリデーション
    validMaxLen($email, 'email');
    validEmail($email, 'email');
    if (empty($errs)) {//ここまででエラーが出てなければ重複チェック
      validEmailDup($email, 'email');
    }

    if (empty($errs)) {//バリデーションを通った場合登録処理
      try {
        $dbh = dbConnect();//DBハンドラ取得

        // サーチIDの生成・重複チェック
        $searchId = makeRandStr();
        $sql = 'SELECT count(*) FROM users WHERE search_id = :search_id';
        $data = array('search_id' => $searchId);
        while (true) {
          $stmt = queryPost($dbh, $sql, $data);
          $result = $stmt->fetchColumn();
          if (!$result) {
            break;
          }
          $searchId = makeRandStr();
        }

        // 登録処理
        $sql = 'INSERT INTO users(search_id, email, pass, username, login_date, create_date) VALUES (:search_id, :email, :pass, :username, :login_date, :create_date)';
        $data = array(':search_id' => $searchId,
                      ':email' => $email,
                      ':pass' => password_hash($pass, PASSWORD_DEFAULT),
                      ':username' => $username,
                      ':login_date' => date('Y-m-d H:i:s'),
                      ':create_date' => date('Y-m-d H:i:s')
                      );
        $stmt = queryPost($dbh, $sql, $data);//実行

        if ($stmt) {//クエリ成功の場合
          //トークンを削除・ログイン処理
          unset($_SESSION['token']);
          $sesLimit = 60 * 60;//ログイン有効期限を設定(デフォルト1時間)
          loginProcessing($sesLimit, $searchId, '#666666');//ログイン処理

          header("Location:mypage.php");
          exit();
        }
        debug('クエリ実行');
      } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
        debug('SQLエラー'. print_r($stmt, true));
        debug('SQLエラー(code)'. $stmt->errorCode());
        debug('SQLエラー(info)'. serialize($stmt->errorInfo()));
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
$siteTitle = '新規登録';
require_once('head.php');
 ?>
<body class="signup">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <section class="like-memo-container rand-color-border">
        <h2>新規登録</h2>
        <a href="" class="twitter-auth rand-color-background"><i class="fab fa-twitter"></i>Twitterで登録</a>
        <div class="or-wrap">
          <p>または</p>
        </div>

        <form action="" method="post">
          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>
          <!-- 表示名 -->
          <label for="username">表示名</label>
          <div class="err-msg-wrap"><?php echoErrMsg('username'); ?></div>
          <input id="username" type="text" name="username" placeholder="プロフィールやグループ等で表示されます" value="<?php echo getFormData('username') ?>">

          <!-- メールアドレス -->
          <label for="email">メールアドレス</label>
          <div class="err-msg-wrap"><?php echoErrMsg('email'); ?></div>
          <input id="email" type="text" name="email" value="<?php echo getFormData('email') ?>">

          <!-- パスワード -->
          <label for="pass">パスワード</label>
          <div class="err-msg-wrap"><?php echoErrMsg('pass'); ?></div>
          <input id="pass" type="password" name="pass" placeholder="半角英数8文字以上" value="<?php echo getFormData('pass') ?>">

          <!-- パスワード -->
          <label for="pass_re">パスワード(再入力)</label>
          <div class="err-msg-wrap"><?php echoErrMsg('pass_re'); ?></div>
          <input id="pass_re" type="password" name="pass_re" placeholder="半角英数8文字以上" value="<?php echo getFormData('pass_re') ?>">

          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button class="rand-color-background">メールアドレスで登録</button>
        </form>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');