<?php 
require_once('function.php');
require_once('auth.php');

if (!empty($_POST)) {//post送信されていた場合
  checkToken();//正規アクセスか確認

  debug('POSTデータ'.print_r($_POST,true));

  // POST情報を格納
  $groupName = filter_input(INPUT_POST, 'groupname');
  $msg = filter_input(INPUT_POST, 'msg');
  $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_SPECIAL_CHARS);

  // DBの情報とPOST情報が異なる場合バリデーション
    validRequired($groupName, 'groupname');
    validRequired($color, 'color');
    if (empty($errs)) {
      validMaxLen($groupName, 'groupname');
      validColorCode($color, 'color');
    }
    if ($msg) {
      validMaxLen($msg, 'msg');
    }

  if (empty($errs)) {//バリデーションを通った場合
    debug('バリOK');

    try {
      $dbh = dbConnect();//DBハンドラ取得
    } catch (\Exception $e) {
      error_log('エラー発生:' . $e->getMessage());
      header("Location:unauthorized.php");
      exit();
    }

    if (!empty($_FILES['img']['name'])) {//ファイルが送信されていた場合アップロード処理と削除フラグを立てる

      $img = updateImg($dbh, $_FILES['img'], 'img', 'groups');
      $addImg = true;
    }else {
      $img = '';
      $addImg = false;
    }
    debug('img'. print_r($img,true));
    if (empty($errs)) {//画像処理が正常な場合
      try {

        // サーチIDの生成・重複チェック
        $searchId = makeRandStr();
        $sql = 'SELECT count(*) FROM groups WHERE search_id = :search_id';
        $data = array('search_id' => $searchId);
        while (true) {
          $stmt = queryPost($dbh, $sql, $data);
          $result = $stmt->fetchColumn();
          if (!$result) {
            break;
          }
          $searchId = makeRandStr();
        }

        $dbh->beginTransaction();//トランザクション開始

        // groupsテーブルへの登録処理
        $sql = 'INSERT INTO groups(search_id, admin_id, groupname, msg, img, color, create_date) 
                VALUES (:search_id, :admin_id, :groupname, :msg, :img, :color, :create_date)';
        $data = array(':search_id' => $searchId,
                      ':admin_id' => $_SESSION['user_id'],
                      ':groupname' => $groupName,
                      ':msg' => $msg,
                      ':img' => $img,
                      ':color' => $color,
                      ':create_date' => date('Y-m-d H:i:s'));
        debug('DB登録データ'.print_r($data,true));
        $stmt = queryPost($dbh, $sql, $data);
        debug('groupsクエリ実行');
        if (!$stmt) {
          $errs['common'] = MSG2;
          throw new Exception('groupsテーブルへの登録でエラー発生');
        }

        // participation_usersテーブルへの登録処理
        $sql = 'INSERT INTO participation_users(group_id, user_id, authority ,create_date) 
                VALUES (:group_id, :user_id, 1, :create_date)';//authority=1が監理者
        $data = array(':group_id' => $searchId,
                      ':user_id' => $_SESSION['user_id'],
                      ':create_date' => date('Y-m-d H:i:s'));
        $stmt = queryPost($dbh, $sql, $data);


        if ($stmt && empty($errs)) {
          $dbh->commit();

          header("Location:group.php?g_id=". $searchId);
          exit();
        }else{
          $errs['common'] = MSG2;
          throw new Exception('participation_テーブルへの登録でエラー発生');
        }
      } catch (Exception $e) {
        error_log('エラー発生'. $e->getMessage());
        $dbh->rollBack();
        $errs['common'] = MSG2;
      }
    }
  }
}
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'グループ作成';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="createGroup">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <section class="like-memo-container">
        <form action="" method="post" enctype="multipart/form-data">
          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

          <!-- グループ画像 -->
          <div class="form-img-container">
            <label for="img">グループ画像(任意)</label>
            <div class="err-msg-wrap"><?php echoErrMsg('img'); ?></div>
            <img src="img/noimg.jpg" alt="プロフィール画像">
            <input type="hidden" name="MAX_FILE_SIZE" value="512000">
            <input id="img" type="file" name="img">
            <label for="img" class="file-btn">画像を選択</label>
          </div>

          <!-- グループ名 -->
          <label for="groupname">グループ名</label>
          <div class="err-msg-wrap"><?php echoErrMsg('groupname'); ?></div>
          <input id="groupname" type="text" name="groupname" placeholder="グループ検索時に表示されます" value="<?php echo getFormData('groupname') ?>">

          <!-- グループ紹介 -->
          <label for="msg">メッセージ(任意)</label>
          <div class="err-msg-wrap"><?php echoErrMsg('msg'); ?></div>
          <textarea id="msg" name="msg" maxlength="255" placeholder="グループ検索時に表示されます"><?php echo getFormData('msg'); ?></textarea>

          <!-- 色 -->
          <label class="color-label" for="color">グループカラー(グループのイメージカラーとして使用されます)</label>
          <div class="err-msg-wrap"><?php echoErrMsg('color'); ?></div>
          <input id="color" type="color" name="color" value="<?php echo getFormData('color'); ?>">


          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button>グループ作成</button>
        </form>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');