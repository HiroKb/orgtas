
<?php 
require_once('function.php');
require_once('auth.php');


$groupId = filter_input(INPUT_GET, 'g_id', FILTER_SANITIZE_SPECIAL_CHARS);
if (validLowrCase($groupId)) {//GETパラメータが正しい場合
  try {
    $dbh = dbConnect();
  } catch (\Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
    header("Location:unauthorized.php");
    exit();
  }
}else{//GETパラメータが不正な場合
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

$authority = getAuthority($dbh, $groupId, $_SESSION['user_id']);
if (empty($authority)) {//不正なアクセスの場合
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}




if (!empty($_POST)) {//POST送信されていた場合
  checkToken();
  $message = filter_input(INPUT_POST, 'message');
  validRequired($message, 'common');

  if (empty($errs)) {

    $sql = 'INSERT INTO chat_messages(group_id, user_id, `message`, create_date)
            VALUES (:group_id, :user_id, :message, :create_date)';
    $data = array(':group_id' => $groupId,
                  ':user_id' => $_SESSION['user_id'],
                  ':message' => $message,
                  ':create_date' => date('Y-m-d H:i:s'));
    $stmt = queryPost($dbh, $sql, $data);

    if (!$stmt) {
      $errs['common'] = MSG2;
    }
  }
}
$chatMessages = getChatMessages($dbh, $groupId);//グループチャットメッセージ一覧を取得
$groupData = getGroupDetail($dbh, $groupId);
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'グループチャット';
$bgType = 'group-color-background';
require_once('head.php');
 ?>
<body class="chat">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <section class="like-memo-container" style="border-left:solid 30px<?php echo $groupData['color'] ?>;">
        <a href="group.php?g_id=<?php echo $groupId?>" class="back-btn"><i class="fas fa-arrow-left"></i></a>
        <a href="" class="reload-btn"><i class="fas fa-redo-alt"></i></a>
        <div class="msg-area" id="msg-area">
<?php foreach ($chatMessages as $val):?>
  <?php if($val['user_id'] === $_SESSION['user_id']): //自分のメッセージの場合?>
          <div class="msg-wrap right-msg">
            <div class="right-baloon">
              <p class="msg"><?php echo sani($val['message']) ?></p>
              <p class="post-date"><?php echo $val['post_date'] ?></p>
            </div>
            <div class='user-info'>
              <img src="<?php getImg($val['img'])?>" alt="">
            </div>
          </div>
  <?php else : ?>
          <div class="msg-wrap left-msg">
            <div class='user-info'>
              <p class="user-name"><?php echo sani($val['username']) ?></p>
              <img src="<?php getImg($val['img'])?>" alt="">
            </div>
            <div class="left-baloon">
              <p class="msg"><?php echo sani($val['message']) ?></p>
              <p class="post-date"><?php echo $val['post_date'] ?></p>
            </div>
          </div>

  <?php endif; ?>
<?php endforeach; ?>
        </div>
        <form action="" method="post">
          <textarea name="message" id="" cols="" rows=""></textarea>
          <input type="hidden" name="token" value="<?php echo sani($token);?>">
          <button class="group-color-background">送信</button>
        </form>
      </section>
    </div>
  </main>

<?php 
require_once('footer.php');