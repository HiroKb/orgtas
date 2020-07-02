<?php 
require_once('function.php');
require_once('auth.php');

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
if (empty($authority)) {//不正なアクセスの場合
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
//グループのデータを取得
$groupData = getGroupDetail($dbh, $groupId);

if (!empty($_POST)) {//POST送信されていた場合
  if ($authority !== 1) {//管理者以外がPOST送信した場合
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }

  checkToken();//正規アクセスか確認
  $postType = filter_input(INPUT_POST, 'post-type', FILTER_SANITIZE_SPECIAL_CHARS);
  $userId = filter_input(INPUT_POST, 'user-id', FILTER_SANITIZE_SPECIAL_CHARS);

  if (in_array($postType, array('setting', 'expulsion'), true) && validLowrCase($userId) ) {//postTypeとuserId形式が正しい場合
    
    $participationStatus = getParticipationStatus($dbh, $groupId, $userId);//参加状態をチェック

    if ($participationStatus !== 1 || $userId === $groupData['admin_id']) {//参加していないユーザーもしくは管理者の設定を変更しようとした場合
      error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
      header("Location:unauthorized.php");
      exit();
    }

    if ($postType === 'setting') {//権限設定POSTの場合

      // post内容格納確認
      $postAuthority = filter_input(INPUT_POST, 'authority', FILTER_SANITIZE_SPECIAL_CHARS);
      if (!in_array($postAuthority, array('deputy', 'general'))) {
        error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
        header("Location:unauthorized.php");
        exit();
      }

      try {
        //participation_userテーブルの権限変更処理
        $sql = 'UPDATE participation_users
                SET authority = :authority 
                WHERE group_id = :g_id AND user_id = :u_id';
        if ($postAuthority === 'deputy') {
          $data = array(':authority' => 2, ':g_id' => $groupId, ':u_id' => $userId);
        }elseif ($postAuthority === 'general') {
          $data = array(':authority' => 3, ':g_id' => $groupId, ':u_id' => $userId);
        }
        $stmt = queryPost($dbh, $sql, $data);

      } catch (Exception $e) {
        error_log('エラー発生'. $e->getMessage());
        $errs['common'] = MSG2;
      }


    }elseif ($postType === 'expulsion') {//除名POSTの場合
      try{
        $sql = 'DELETE FROM participation_users 
                WHERE group_id = :g_id AND user_id = :u_id';
        $data = array(':g_id' => $groupId, ':u_id' => $userId);
        $stmt = queryPost($dbh, $sql, $data);

      } catch (Exception $e) {
        error_log('エラー発生'. $e->getMessage());
        $errs['common'] = MSG2;
      }
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

$groupMemberList = getGroupMemberList($dbh, $groupId, $currentMinNum, $listSpan);

$getPara = '&g_id=' . $groupId;


if (empty($groupMemberList['data'])) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'グループメンバー';
$bgType = 'group-color-background';
require_once('head.php');
 ?>
<body class="groupMember">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width msg-wrap">
      <?php if(false) :?>
        <p id="slide-msg" class="group-color-background">メンバーを除名しました</p>
      <?php endif; ?>
        <p id="slide-msg" class="group-color-background">権限を変更しました</p>
    </div>

    <div class="site-width">
      <h2>グループメンバー(<?php echo mb_strimwidth(sani($groupData['groupname']), 0, 40, "...", 'UTF-8')?></h2>


      <!-- ユーザー一覧 -->
      <?php if(!empty($groupMemberList['data'])) : ?>
      <p class="number-of-users"><?php echo sani($currentMinNum + 1); ?>-<?php echo sani($currentMinNum + count($groupMemberList['data'])); ?> 人 / <?php echo sani($groupMemberList['total']); ?> 人中</p>

      <ul class="user-list">
        <?php //---------------表示準備--------------- ?>
        <?php foreach($groupMemberList['data'] as $key => $val): ?>
        <?php switch ($val['authority']) {
          case 1:
            $authorityStatus = '管理者';
            break;
          case 2:
            $authorityStatus = '副管理者';
            break;
          default:
            $authorityStatus = '一般';
            break;
        } ?>
        <li data-userid="<?php echo sani($val['u_id'])?>" data-username="<?php echo sani($val['username'])?>" data-authority="<?php echo $val['authority']?>">
          <a class="user-wrap" href="" style="border-left: solid 30px <?php echo sani($val['color'])?>">
            <div class="user-left-inner">
              <img src="<?php echo getImg($val['img'])?>" alt="ユーザー画像">
              <p class="username"><?php echo mb_strimwidth(sani($val['username']), 0, 32, "...", 'UTF-8'); ?></p>
            </div>
            <?php if($authority === 1 && $val['authority'] !== 1) :  ?>
            <div class="user-right-inner">
              <p class="authority-status"><?php echo $authorityStatus ?></p>
              <div class="btn-wrap">
                <i class="fas fa-user-cog setting-btn"></i>
                <i class="fas fa-user-times expulsion-btn"></i>
              </div>
            </div>
            <?php else : ?>
              <p class="authority-status"><?php echo $authorityStatus ?></p>
      <?php endif; ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="pagination-wrap">
        <?php pagination($currentPageNum, $groupMemberList['total_page'], $getPara, 'group'); ?>
      </div>
      <?php else: ?>
      <?php endif; ?>
  </main>

  <!-- メンバー設定モーダル -->
  <?php if($authority === 1) :?>
      <div id="setting-modal" class="modal-wrap">
        <div class="modal-bg modal-close"></div>
        <div class="modal like-memo-container group-color-border">
          <i class="fas fa-times modal-close"></i>

          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>
          <form action="" method="post">
            <img src="uploads/userf2f6ae7cfdd044def016c1804fb5934c48753224.jpeg" alt="">
            <p class="modal-username">tesut</p>

            <label for="form-authority">権限</label>
            <select name="authority" id="form-authority">
              <option value="deputy">副管理</option>
              <option value="general">一般</option>
            </select>
            <p class="description-msg">※副管理者はタスクの追加・編集・削除が行なえます</p>

            <input type="hidden" name="post-type" value="setting">
            <input type="hidden" name="user-id" value="">
            <input type="hidden" name="token" value="<?php echo sani($token);?>">
            <button class="group-color-background">更新</button>
          </form>
        </div>
      </div>

  <!-- 拒否モーダル -->
      <div id="expulsion-modal" class="modal-wrap">
        <div class="modal-bg modal-close"></div>
        <div class="modal like-memo-container group-color-border">
          <i class="fas fa-times modal-close"></i>

            <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>
          <form action="" method="post">
            <img src="uploads/userf2f6ae7cfdd044def016c1804fb5934c48753224.jpeg" alt="">
            <p class="modal-username">tesut</p>

            <p class="confirm-msg">除名しますか？</p>
            <input type="hidden" name="post-type" value="expulsion">
            <input type="hidden" name="user-id" value="">
            <input type="hidden" name="token" value="<?php echo sani($token);?>">
            <button class="group-color-background">除名</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

  

<?php 
require_once('footer.php');