<?php 
require_once('function.php');
require_once('auth.php');

$approveErrFlg = false;
$refuseErrFlg = false;

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

//ユーザーの参加状況・権限を取得
$authority = getAuthority($dbh, $groupId, $_SESSION['user_id']);//authority 1=監理者 2=副管理者 3=一般
debug($authority);
if ($authority !== 1 && $authority !== 2) {//不正なアクセスの場合
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
//グループのデータを取得
$groupData = getGroupDetail($dbh, $groupId);

if (!empty($_POST)) {//POST送信されていた場合
  checkToken();//正規アクセスか確認
  $postType = filter_input(INPUT_POST, 'post-type', FILTER_SANITIZE_SPECIAL_CHARS);
  $userId = filter_input(INPUT_POST, 'user-id', FILTER_SANITIZE_SPECIAL_CHARS);

  if (in_array($postType, array('approve', 'refuse'), true) && validLowrCase($userId) ) {//postTypeとuserId形式が正しい場合
    
    $participationStatus = getParticipationStatus($dbh, $groupId, $userId);//参加状態をチェック

    if ($participationStatus === 0) {//非参加のグループの場合申請状態をチェック
      $pendingStatus = getPendingStatus($dbh, $groupId, $userId);
    }

    if ($participationStatus === 'err' || $pendingStatus === 'err') {//エラー発生した場合
      header("Location:unauthorized.php");
      exit();
    }

    if ($participationStatus !== 0 || $pendingStatus !== 1) {//参加・申請状態が誤ってる場合
      error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
      header("Location:unauthorized.php");
      exit();
    }else{
      debug('正規');
    }



    if ($postType === 'approve') {//承認POSTの場合
      try {
        $dbh->beginTransaction();

        //participion_userテーブルへの登録処理
        $sql = 'INSERT INTO participation_users(group_id, user_id, authority, create_date) 
                VALUES (:g_id, :u_id, 3, :create_date)';
        $data = array(':g_id' => $groupId, ':u_id' => $userId, ':create_date' => date('Y-m-d H:i:s'));
        $stmt = queryPost($dbh, $sql, $data);
        if (!$stmt) {
          $errs['common'] = MSG2;
          throw new Exception('participionテーブルへの登録でエラー発生');
        }

        //pending_usersテーブルの削除処理
        $sql = 'DELETE FROM pending_users 
                WHERE group_id = :g_id AND user_id = :u_id';
        $data = array(':g_id' => $groupId, ':u_id' => $userId);
        $stmt = queryPost($dbh, $sql, $data);

        if ($stmt && empty($errs)) {
          $dbh->commit();
        }else{
          $errs['common'] = MSG2;
          throw new Exception('pendingテーブルの処理でエラー発生');
        }
      } catch (Exception $e) {
        error_log('エラー発生'. $e->getMessage());
        $dbh->rollBack();
        $errs['common'] = MSG2;
      }

    }elseif ($postType === 'refuse') {
      try{
        $sql = 'DELETE FROM pending_users 
                WHERE group_id = :g_id AND user_id = :u_id';
        $data = array(':g_id' => $groupId, ':u_id' => $userId);
        $stmt = queryPost($dbh, $sql, $data);

        if (!$stmt) {
          $errs['common'] = MSG2;
        }
      } catch (Exception $e) {
        error_log('エラー発生'. $e->getMessage());
        $errs['common'] = MSG2;
      }
    }else{
      debug('unaut');
    }
  }else{
    header("Location:unauthorized.php");
    exit();
  }


}


$currentPageNum = !empty($_GET['p']) ? intval($_GET['p']) : 1;
if (!is_int($currentPageNum)) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
$listSpan = 20;
$currentMinNum = ($currentPageNum - 1) * $listSpan;//表示ユーザーのの最小番

$pendingUsersList = getPendingUsersList($dbh, $groupId, $currentMinNum, $listSpan);
debug(print_r($pendingUsersList, true));

$getPara = '&g_id=' . $groupId;



// マイタスクリスト取得
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = '承認待ちユーザー';
$bgType = 'group-color-background';
require_once('head.php');
 ?>
<body class="pendingUser">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width msg-wrap">
      <?php if(false) :?>
        <p id="slide-msg" class="my-color-background">タスクを追加しました</p>
      <?php endif; ?>
      <?php if(false) :?>
        <p id="slide-msg" class="my-color-background">タスクを削除しました</p>
      <?php endif; ?>
    </div>

    <div class="site-width">
      <h2>承認待ちユーザー(<?php echo mb_strimwidth(sani($groupData['groupname']), 0, 40, "...", 'UTF-8')?></h2>


      <!-- ユーザー一覧 -->
      <?php if(!empty($pendingUsersList['data'])) : ?>
      <p class="number-of-users"><?php echo sani($currentMinNum + 1); ?>-<?php echo sani($currentMinNum + count($pendingUsersList['data'])); ?> 人 / <?php echo sani($pendingUsersList['total']); ?> 人中</p>
      <ul class="user-list">
        <?php //---------------表示準備--------------- ?>
        <?php foreach($pendingUsersList['data'] as $key => $val): ?>
        <li data-userid="<?php echo sani($val['u_id'])?>" data-username="<?php echo sani($val['username'])?>">
          <a class="user-wrap" href="" style="border-left: solid 30px <?php echo sani($val['color'])?>">
            <div class="user-left-inner">
              <img src="<?php echo getImg($val['img'])?>" alt="ユーザー画像">
              <p class="username"><?php echo mb_strimwidth(sani($val['username']), 0, 32, "...", 'UTF-8'); ?></p>
            </div>
            <div class="user-right-inner">
              <i class="fas fa-user-plus approve-btn"></i>
              <i class="fas fa-user-times refuse-btn"></i>
            </div>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="pagination-wrap">
        <?php pagination($currentPageNum, $pendingUsersList['total_page'], $getPara, 'group'); ?>
      </div>
      <?php else: ?>
      <p class="no-user-msg">承認待ちユーザーが居ません</p>
      <?php endif; ?>
  </main>

  <!-- 承認モーダル -->
      <div id="approve-modal" class="modal-wrap">
        <div class="modal-bg modal-close"></div>
        <div class="modal like-memo-container group-color-border">
          <i class="fas fa-times modal-close"></i>

          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>
          <form action="" method="post">
            <input type="hidden" name="token" value="<?php echo sani($token);?>">
          </form>
        </div>
      </div>

  <!-- 拒否モーダル -->
      <div id="refuse-modal" class="modal-wrap">
        <div class="modal-bg modal-close"></div>
        <div class="modal like-memo-container group-color-border">
          <i class="fas fa-times modal-close"></i>

            <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>
          <form action="" method="post">
            <input type="hidden" name="token" value="<?php echo sani($token);?>">
          </form>
        </div>
      </div>

  

<?php 
require_once('footer.php');