<?php 
require_once('function.php');
require_once('auth.php');

debug('-------------------------新規登録ページ---------------------');
try {
  $dbh = dbConnect();//DBハンドラ取得
} catch (\Exception $e) {
  error_log('エラー発生:' . $e->getMessage());
  header("Location:unauthorized.php");
  exit();
}
$userData = getUser($dbh, $_SESSION['user_id']);
$changeFlg = false;
debug('ユーザーデータ'.print_r($userData,true));
if (empty($userData)) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
if (!empty($_POST)) {//post送信されていた場合
  checkToken();//正規アクセスか確認

  debug('POSTデータ'.print_r($_POST,true));

  // POST情報を格納
  $username = filter_input(INPUT_POST, 'username');
  $introduction = filter_input(INPUT_POST, 'introduction');
  $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_SPECIAL_CHARS);

  // DBの情報とPOST情報が異なる場合バリデーション
  if ($userData['username'] !== $username) {
    validRequired($username, 'username');
    validMaxLen($username, 'username');
  }
  if ($userData['introduction'] !== $introduction) {
    validMaxLen($introduction, 'introduction');
  }
  if ($userData['color'] !== $color) {
    validRequired($color, 'color');
  }

  if (empty($errs)) {//バリデーションを通った場合
    debug('バリOK');
    if (!empty($_FILES['img']['name'])) {//ファイルが送信されていた場合アップロード処理と削除フラグを立てる
      $img = updateImg($dbh, $_FILES['img'], 'img', 'users');
      $chengeImg = true;
    }else {
      $img = '';
      $chengeImg = false;
    }
    $img = (empty($img) && !empty($userData['img'])) ? $userData['img'] : $img;
    debug('img'. print_r($img,true));
    if (empty($errs)) {//画像処理が正常な場合
      try {
        $sql = 'UPDATE users 
                SET username = :username,
                    introduction = :introduction,
                    img = :img,
                    color = :color, 
                    login_date = :login_date 
                WHERE search_id = :id AND delete_flg = 0';
        $data = array(':username' => $username,
                      ':introduction' => $introduction,
                      ':img' => $img,
                      ':color' => $color,
                      ':login_date' => date('Y-m-d H:i:s'), 
                      ':id' => $_SESSION['user_id']);
        debug('DB登録データ'.print_r($data,true));
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
          if ($chengeImg && !empty($userData['img'])) {//画像が変更されていれば以前の画像を削除
            debug('ファイ削除');
            unlink('uploads/' . $userData['img']);
          }
          $_SESSION['color'] = $color;
          $changeFlg = true;
          $userData['img'] = $img;
        }
      } catch (Exception $e) {
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
$siteTitle = 'プロフィール編集';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="profileEdit">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <section class="like-memo-container my-color-border">
<?php if($changeFlg) :?>
        <p id="slide-msg" class="my-color-background">プロフィールを変更しました</p>
<?php endif; ?>
        <form action="" method="post" enctype="multipart/form-data">
          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

          <!-- プロフィール画像 -->
          <div class="form-img-container">
            <label for="img">プロフィール画像</label>
            <div class="err-msg-wrap"><?php echoErrMsg('img'); ?></div>
            <img src="<?php getImg($userData['img']) ?>" alt="プロフィール画像">
            <input type="hidden" name="MAX_FILE_SIZE" value="512000">
            <input id="img" type="file" name="img">
            <label for="img" class="file-btn my-color-background">画像を選択</label>
          </div>

          <!-- 表示名 -->
          <label for="username">表示名</label>
          <div class="err-msg-wrap"><?php echoErrMsg('username'); ?></div>
          <input id="username" type="text" name="username" placeholder="プロフィールやグループ等で表示されます" value="<?php echo getFormData('username', $userData) ?>">

          <!-- 自己紹介 -->
          <label for="introduction">自己紹介</label>
          <div class="err-msg-wrap"><?php echoErrMsg('introduction'); ?></div>
          <textarea id="introduction" name="introduction" maxlength="255" placeholder="プロフィールやグループ等で表示されます"><?php echo getFormData('introduction', $userData); ?></textarea>

          <!-- 色 -->
          <label class="color-label" for="color">マイカラー(マイページやプロフィールに使用される色です)</label>
          <div class="err-msg-wrap"><?php echoErrMsg('color'); ?></div>
          <input id="color" type="color" name="color" value="<?php echo getFormData('color', $userData); ?>">


          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button class="my-color-background">変更</button>
        </form>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');