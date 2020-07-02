<?php 
require_once('function.php');
require_once('auth.php');

$groupData = false;
$participationStatus = false;
$pendingStatus = false;

$searchId = filter_input(INPUT_GET, 'g_id', FILTER_SANITIZE_SPECIAL_CHARS);
if (validLowrCase($searchId)) {//GETパラメータが正しい場合

  try {
    $dbh = dbConnect();//DBハンドラ取得
  } catch (\Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
    header("Location:unauthorized.php");
    exit();
  }

  $groupData = getGroupDetail($dbh, $searchId);

  if ($groupData) {//グループが存在している場合
    $participationStatus = getParticipationStatus($dbh, $searchId, $_SESSION['user_id']);//参加状態をチェック

    if ($participationStatus === 0) {//非参加のグループの場合申請状態をチェック
      debug('非参加');
      $pendingStatus = getPendingStatus($dbh, $searchId, $_SESSION['user_id']);
      debug($pendingStatus);
    }

    if ($participationStatus === 'err' || $pendingStatus === 'err') {
      header("Location:unauthorized.php");
      exit();
    }

    if ($participationStatus === 0 && $pendingStatus === 0 && !empty($_POST)) {//非参加&未申請でPOST送信ボタンされた場合
      checkToken();//正規アクセスか確認

      try {
        $sql = 'INSERT INTO pending_users(group_id, user_id, create_date) 
                VALUES (:group_id, :user_id, :create_date)';
        $data = array (':group_id' => $searchId, 
                       ':user_id' => $_SESSION['user_id'],
                       ':create_date' => date('Y-m-d H:i:s'));
        $stmt = queryPost($dbh, $sql, $data);

        if ($stmt) {
          $pendingStatus = true;
        }
      } catch (Exception $e) {
        error_log('エラー発生:' . $e->getMessage());
        header("Location:unauthorized.php");
        exit();
      }
      
    }
  }

}
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'グループ参加申請';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="joinGroup">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
<?php if($groupData) : //グループデータが取得できた場合?>
      <section class="like-memo-container group-color-border">


        <img src="<?php getImg($groupData['img']); ?>" alt="グループ画像">
        <p class="groupname"><?php echo sani($groupData['groupname']); ?></p>

  <?php if($participationStatus) : ?>
        <p class="participated-msg">参加しているグループです。</p>
  <?php elseif($pendingStatus) : ?>
        <p class="pending-msg">参加申請済みです。</p>
  <?php else : ?>
        <form action="" method="post">
          <input type="hidden" name="token" value="<?php echo sani($token);?>">
          <button class="group-color-background">参加申請</button>
        </form>
  <?php endif ; ?>

<?php else : //グループデータが取得できなかった場合 ?>
      <section class="like-memo-container" style="border-left: 30px #666 solid;">
        <h2>グループが存在しません。</h2>
<?php endif; ?>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');