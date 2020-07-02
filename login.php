<?php 
require_once('function.php');
require_once('auth.php');
debug('-------------------------ログインページ---------------------');
if (!empty($_POST)) {//post送信されていた場合
  checkToken();//正規アクセスか確認

  // post内容を格納・未入力チェック
  $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
  $pass = filter_input(INPUT_POST, 'pass', FILTER_SANITIZE_SPECIAL_CHARS);
  $keep = filter_input(INPUT_POST, 'keep', FILTER_SANITIZE_SPECIAL_CHARS);
  validRequired($email, 'email');
  validRequired($pass, 'pass');

  if (empty($errs)) {//すべての項目が入力されている場合
    // passバリデーション
    validMaxLen($pass, 'pass');
    validMinLen($pass, 'pass');
    validHalf($pass, 'pass');

    // emailバリデーション
    validMaxLen($email, 'email');
    validEmail($email, 'email');

    if (empty($errs)) {//バリデーションを通った場合
      try {
        $dbh = dbConnect();//DBハンドラ取得

        // クエリ用意
        $sql = 'SELECT search_id, pass, color FROM users WHERE email = :email AND delete_flg = 0';
        $data = array(':email' => $email);
        $stmt = queryPost($dbh, $sql, $data);//実行
        $result = $stmt->fetch(PDO::FETCH_ASSOC);//結果を取得(id・passの配列かfalseが入る)

        // パスワード照合
        if (!empty($result) && password_verify($pass, $result['pass'])) {//照合に成功した場合ログイン処理

          // トークンを削除・ログイン処理
          unset($_SESSION['token']);
          $sesLimit = 60 * 60;//ログイン有効期限を設定(デフォルト1時間)
          if ($keep) {//ログイン保持にチェックがある場合
            $sesLimit *= 168;//ログイン有効期限を7日に
          }
          loginProcessing($sesLimit, $result['search_id'], $result['color']);

          updateLoginDate($dbh,$result['search_id']);//DB最終ログイン時間時間を更新

          header("Location:taskList.php");//タスク一覧画面へ
          exit();
        }else{//称号に失敗した場合
          $errs['common'] = MSG9;
        }

      } catch (Exception $e) {
        error_log('エラー発生 :'.$e->getMessage());
      }

    }else{//バリデーションに引っかかった場合
      $errs = array();
      $errs['common'] = MSG9;
    }
  }
}
$token;
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'ログイン';
require_once('head.php');
 ?>
<body class="login">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <section class="like-memo-container rand-color-border">
        <h2>ログイン</h2>
        <a href="" class="twitter-auth rand-color-background"><i class="fab fa-twitter"></i>Twitterでログイン</a>
        <div class="or-wrap">
          <p>または</p>
        </div>

        <form action="" method="post">

          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

          <!-- メールアドレス -->
          <label for="email">メールアドレス</label>
          <div class="err-msg-wrap"><?php echoErrMsg('email'); ?></div>
          <input id="email" type="text" name="email" value="<?php echo getFormData('email') ?>">

          <!-- パスワード -->
          <label for="pass">パスワード</label>
          <div class="err-msg-wrap"><?php echoErrMsg('pass'); ?></div>
          <input id="pass" type="password" name="pass" placeholder="半角英数8文字以上" value="<?php echo getFormData('pass') ?>">

          <input id="keep" type="checkbox" name="keep">
          <label for="keep">ログイン状態を保持する</label>

          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button class="rand-color-background">メールアドレスでログイン</button>
        </form>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');