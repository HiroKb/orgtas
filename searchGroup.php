<?php 
require_once('function.php');
require_once('auth.php');

if (!empty($_POST)) {//post送信されていた場合
  checkToken();//正規アクセスか確認

  // post内容を格納・未入力チェック
  $groupId = filter_input(INPUT_POST, 'group_id', FILTER_SANITIZE_SPECIAL_CHARS);
  validRequired($groupId, 'group_id');

  if (empty($errs)) {//未入力じゃない場合

    if(!validLowrCase($groupId)){
      $errs['group_id'] = MSG8;
    }

    if (empty($errs)) {
      debug('グループ照合');

      try {
        $dbh = dbConnect();//DBハンドラ取得
      } catch (\Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
        header("Location:unauthorized.php");
        exit();
      }
      $groupData = getGroupDetail($dbh, $groupId);

      if ($groupId) {//検索されたグループが存在する場合
        debug(print_r($groupData, true));
        header("Location:joinGroup.php?g_id=" . $groupId);
      }else{
        $errs['group_id'] = MSG8;
      }
    }
  }
}
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'グループ検索';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="searchGroup">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <section class="like-memo-container my-color-border">
        <form action="" method="post">
          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

          <label for="group_id">グループID</label>
          <div class="err-msg-wrap"><?php echoErrMsg('group_id'); ?></div>
          <input id="group_id" type="text" name="group_id" value="<?php echo getFormData('group_id'); ?>">


          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button class="my-color-background">検索</button>
        </form>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');