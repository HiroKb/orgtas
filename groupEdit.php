<?php 
require_once('function.php');
require_once('auth.php');


// グループIDを取得・形式チェック
$groupId = filter_input(INPUT_GET, 'g_id', FILTER_SANITIZE_SPECIAL_CHARS);
debug($groupId);
if(!validLowrCase($groupId)){
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
try {
  $dbh = dbConnect();//DBハンドラ取得
} catch (\Exception $e) {
  error_log('エラー発生:' . $e->getMessage());
  header("Location:unauthorized.php");
  exit();
}
$authority = getAuthority($dbh, $groupId, $_SESSION['user_id']);
debug($authority);
if ($authority !== 1) {//$authorityが1以外の場合は不正なアクセス（監理者以外
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
$groupData = getGroupDetail($dbh, $groupId);
debug(print_r($groupData,true));

$changeFlg = false;
if (empty($groupData)) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

if (!empty($_POST)) {//post送信されていた場合
  checkToken();//正規アクセスか確認

  debug('POSTデータ'.print_r($_POST,true));

  // POST情報を格納
  $groupName = filter_input(INPUT_POST, 'groupname');
  $msg = filter_input(INPUT_POST, 'msg');
  $color = filter_input(INPUT_POST, 'color', FILTER_SANITIZE_SPECIAL_CHARS);

  // DBの情報とPOST情報が異なる場合バリデーション
  if ($groupData['groupname'] !== $groupName) {
    validRequired($groupName, 'groupname');
    validMaxLen($groupName, 'groupname');
  }
  if ($groupData['msg'] !== $msg) {
    validMaxLen($msg, 'msg');
  }
  if ($groupData['color'] !== $color) {
    validRequired($color, 'color');
  }

  if (empty($errs)) {//バリデーションを通った場合
    debug('バリOK');
    if (!empty($_FILES['img']['name'])) {//ファイルが送信されていた場合アップロード処理と削除フラグを立てる
      $img = updateImg($dbh, $_FILES['img'], 'img', 'groups');
      $chengeImg = true;
    }else {
      $img = '';
      $chengeImg = false;
    }
    $img = (empty($img) && !empty($groupData['img'])) ? $groupData['img'] : $img;
    debug('img'. print_r($img,true));
    if (empty($errs)) {//画像処理が正常な場合
      try {
        $sql = 'UPDATE groups 
                SET groupname = :groupname,
                    msg = :msg,
                    img = :img,
                    color = :color
                WHERE search_id = :groupid AND admin_id = :admin_id AND delete_flg = 0';
        $data = array(':groupname' => $groupName,
                      ':msg' => $msg,
                      ':img' => $img,
                      ':color' => $color,
                      ':groupid' => $groupId,
                      ':admin_id' => $_SESSION['user_id']);
        debug('DB登録データ'.print_r($data,true));
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
          if ($chengeImg && !empty($groupData['img'])) {//画像が変更されていれば以前の画像を削除
            debug('ファイ削除');
            unlink('uploads/' . $groupData['img']);
          }
          $groupData['color'] = $color;
          $changeFlg = true;
          $groupData['img'] = $img;
        }
      } catch (Exception $e) {
        error_log('エラー発生'. $e->getMessage());
        $errs['common'] = MSG2;
      }
    }
  }
}
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'グループ編集';
$bgType = 'group-color-background';
require_once('head.php');
 ?>
<body class="groupEdit">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <section class="like-memo-container group-color-border">
<?php if($changeFlg) :?>
        <p id="slide-msg" class="group-color-background">グループ情報を変更しました</p>
<?php endif; ?>
        <form action="" method="post" enctype="multipart/form-data">
          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

          <!-- グループ画像 -->
          <div class="form-img-container">
            <label for="img">グループ画像</label>
            <div class="err-msg-wrap"><?php echoErrMsg('img'); ?></div>
            <img src="<?php getImg($groupData['img']) ?>" alt="グループ画像">
            <input type="hidden" name="MAX_FILE_SIZE" value="512000">
            <input id="img" type="file" name="img">
            <label for="img" class="file-btn group-color-background">画像を選択</label>
          </div>

          <!-- 表示名 -->
          <label for="groupname">グループ名</label>
          <div class="err-msg-wrap"><?php echoErrMsg('groupname'); ?></div>
          <input id="groupname" type="text" name="groupname" placeholder="検索時に表示されます" value="<?php echo getFormData('groupname', $groupData) ?>">

          <!-- メッセージ -->
          <label for="msg">メッセージ(任意)</label>
          <div class="err-msg-wrap"><?php echoErrMsg('msg'); ?></div>
          <textarea id="msg" name="msg" maxlength="255" placeholder="グループページで表示されます"><?php echo getFormData('msg', $groupData); ?></textarea>

          <!-- 色 -->
          <label class="color-label" for="color">グループカラー(グループのイメージカラーとして使用されます)</label>
          <div class="err-msg-wrap"><?php echoErrMsg('color'); ?></div>
          <input id="color" type="color" name="color" value="<?php echo getFormData('color', $groupData); ?>">


          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button class="group-color-background">変更</button>
        </form>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');