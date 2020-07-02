<?php 
require_once('function.php');
require_once('auth.php');

$TaskEditFlg = false;

$searchId = filter_input(INPUT_GET, 't_id', FILTER_SANITIZE_SPECIAL_CHARS);
if (validLowrCase($searchId)) {//GETパラメータが正しい場合
  try {
    $dbh = dbConnect();
  } catch (\Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
    header("Location:unauthorized.php");
    exit();
  }
  $myTask = getMyTask($dbh, $searchId);
}else{//GETパラメータが不正な場合
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

if (empty($myTask) || $myTask['user_id'] !== $_SESSION['user_id']) {//自分以外のタスクにアクセスしようとした場合などは不正アクセス
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
$linkType = filter_input(INPUT_GET, 't', FILTER_SANITIZE_SPECIAL_CHARS);//どこから飛んできたかを格納
validIn($linkType, 'linktype', array('m'));//正規のリンク確認
if (empty($errs)) {
  if ($linkType === 'm') {//myTaskListから飛んできた場合
    $backLink = 'myTaskList.php?p=' . intval(filter_input(INPUT_GET, 'p', FILTER_SANITIZE_SPECIAL_CHARS));//ページ数をリンクに追加

    $refineStatus = filter_input(INPUT_GET, 'refine_status',  FILTER_SANITIZE_SPECIAL_CHARS);
    $refinePriority = filter_input(INPUT_GET, 'refine_priority',  FILTER_SANITIZE_SPECIAL_CHARS);
    $refineDeadline = filter_input(INPUT_GET, 'refine_deadline',  FILTER_SANITIZE_SPECIAL_CHARS);
    if ($refineStatus || $refinePriority || $refineDeadline) {//絞り込みgetパラメータが一つでもあった場合に全てのパラメータが存在しなければ不正アクセス
      if (empty($refineStatus) || empty($refinePriority) || empty($refineDeadline)) {
        error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
        header("Location:unauthorized.php");
        exit();
      }
    }
    //$refine系の形式チェック
    if ($refineStatus) validIn($refineStatus, 'refine');
    if ($refinePriority) validIn($refinePriority, 'refine', array('1', '2', '3', '4'));
    if ($refineDeadline) validIn($refineDeadline, 'refine', array('1', '2', '3', '4'));
    if (!empty($errs['refine'])) {
      error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
      header("Location:unauthorized.php");
      exit();
    }

    if ($refineStatus) {//絞り込み後のリンクから飛んできた場合
      $backLink .=  '&refine_status=' . $refineStatus . '&refine_priority=' . $refinePriority . '&refine_deadline=' . $refineDeadline;
    }
  }
}else{
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

if (!empty($_POST)) {//POST送信されていた場合
  checkToken();
  $postType = filter_input(INPUT_POST, 'post-type', FILTER_SANITIZE_SPECIAL_CHARS);
  if ($postType === 'edit_task') {
    
    // POST内容を格納し未入力チェック
    $title = filter_input(INPUT_POST, 'title');
    $details = filter_input(INPUT_POST, 'details');
    $deadline_flg = filter_input(INPUT_POST, 'deadline_flg', FILTER_SANITIZE_SPECIAL_CHARS);
    $deadline = filter_input(INPUT_POST, 'deadline');
    $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_SPECIAL_CHARS);

    validRequired($title, 'title');
    validRequired($deadline_flg, 'deadline');
    validRequired($priority,' priority');
    if (empty($errs)) {
      validMaxLen($title, 'title');//タイトルの最大文字数チェック
      if (!empty($details)) {//詳細が入力されている場合
        validMaxLen($details, 'details');//詳細の最大文字数チェック
      }
      validDeadLineFlg($deadline_flg, 'deadline');
      if (empty($errs) && $deadline_flg === 'on') {
        validRequired($deadline, 'deadline');
        if (empty($errs)) {
          validDeadline($deadline, 'deadline');//期限の形式チェック
        }
      }else{
        $deadline = null;
      }
      $deadlineBool = $deadline_flg === 'on' ? 1 : 0;//DB登録用にbool型にキャスト

      validIn($priority, 'priority');//優先度形式チェック
      if (empty($errs)) {//DB登録用に数値型へキャスト
        $priority = intval($priority);
      }


      if (empty($errs) && $title === $myTask['title'] && $details === $myTask['details'] && $deadlineBool === $myTask['deadline_flg'] && $deadline === $myTask['deadline'] && $priority === $myTask['priority']) {
        $errs['noedit'] = 'noedit';
      }
      if (empty($errs)) {//バリデーションを全て通った場合
        try {
          $sql = 'UPDATE my_tasks 
                  SET title = :title, details = :details, priority = :priority, deadline = :deadline, deadline_flg = :deadline_flg 
                  WHERE search_id = :s_id AND user_id = :u_id AND delete_flg = 0';
          $data = array(':title' => $title, 
                        ':details' => $details,
                        ':priority' => $priority,
                        ':deadline' => $deadline,
                        ':deadline_flg' => $deadlineBool,
                        ':s_id' => $searchId,
                        ':u_id' => $_SESSION['user_id']);
          $stmt = queryPost($dbh, $sql, $data);
          if ($stmt) {
            $TaskEditFlg = true;
            $myTask['priority'] = $priority;
          }
        } catch (Exception $e) {
          error_log('エラー発生:' . $e->getMessage());
          $errs['common'] = MSG2;
        }
        
      }
    }
  }elseif ($postType === 'delete_task') {
    try {
      $sql = 'UPDATE my_tasks 
              SET delete_flg = 1 
              WHERE search_id = :s_id AND user_id = :u_id AND delete_flg = 0';
      $data = array(':s_id' => $searchId, 'u_id' => $_SESSION['user_id']);
      $stmt = queryPost($dbh, $sql, $data);
      if ($stmt) {
        header("Location:".$backLink);
        exit();
      }
    } catch (Exception $e ) {
      error_log('エラー発生:' . $e->getMessage());
      $errs['common'] = MSG2;
    }
  }
}
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'マイタスク';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="myTask">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
<?php if($myTask) : //タスクデータが取得できた場合?>
      <section class="like-memo-container <?php echoTaskBorder($myTask['priority'])?>">
      <a href="<?php echo sani($backLink)?>" class="back-list-btn"><i class="fas fa-arrow-left"></i></a>
      <i class="far fa-check-square mytask-complete-btn<?php if ($myTask['complete_flg']) {echo ' success-color';}?>" data-taskid="<?php echo sani($searchId);?>"></i>
      <i class="fas fa-trash-alt mytask-delete-btn"></i>
<?php if($TaskEditFlg) :?>
        <p id="slide-msg" class="<?php echoTaskBackground($myTask['priority'])?>">タスクを更新しました</p>
<?php endif; ?>

        <form action="" method="post">
          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

          <!-- タクス名 -->
          <label for="title">タスク名</label>
          <div class="err-msg-wrap"><?php echoErrMsg('title'); ?></div>
          <input id="title" type="text" name="title" value="<?php echo getFormData('title', $myTask);?>">

          <!-- 詳細 -->
          <label for="details">詳細(任意)</label>
          <textarea name="details" id="details"><?php echo getFormData('details', $myTask); ?></textarea>

          <!-- 期限フラグ -->
          <p class="deadline-para">期限</p>
          <div class="err-msg-wrap"><?php echoErrMsg('deadline'); ?></div>
          <input type="radio" name="deadline_flg" value ="on" id="deadline_on" <?php if(getFormData('deadline_flg', $myTask) === '1' || getFormData('deadline_flg') === 'on') echo 'checked'; ?>>
          <label for="deadline_on" class="deadline-label">有り</label>
          <input type="radio" name="deadline_flg" id="deadline_off" value="off" <?php if(getFormData('deadline_flg', $myTask) === '0' || getFormData('deadline_flg') === 'off') echo 'checked'; ?>>
          <label for="deadline_off" class="deadline-label deadline-off-label">無し</label>
          <input id="date-input" type="text" name="deadline" value="<?php echo getFormData('deadline', $myTask)?>">


          <!-- 重要度 -->
          <label for="priority">重要度</label>
          <div class="err-msg-wrap"><?php echoErrMsg('priority'); ?></div>
          <select name="priority" id="priority">
            <option value="1" <?php if(getFormData('priority', $myTask) === '1' ||getFormData('priority') === 1) echo 'selected'?>>高: 赤</option>
            <option value="2" <?php if(getFormData('priority', $myTask) === '2' ||getFormData('priority') === 2) echo 'selected'?>>中: 黄</option>
            <option value="3" <?php if(getFormData('priority', $myTask) === '3' ||getFormData('priority') === 3) echo 'selected'?>>低: 青</option>
          </select>

          <input type="hidden" name="post-type" value="edit_task">
          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button class="<?php echoTaskBackground($myTask['priority'])?>">タスクを更新</button>
        </form>

<?php else : //タスクデータが取得できなかった場合 ?>
      <section class="like-memo-container" style="border-left: 30px #666 solid;">
        <h2>タスクが存在しません。</h2>
<?php endif; ?>
      </section>
    </div>
  </main>

  <div id="delete-task-modal" class="modal-wrap">
    <div class="modal-bg modal-close"></div>
    <div class="modal like-memo-container <?php echoTaskBorder($myTask['priority'])?>">
      <i class="fas fa-times modal-close"></i>
      <form action="" method="post">
        <p>タスクを削除しますか？</p>
        <input type="hidden" name="post-type" value="delete_task">
        <input type="hidden" name="token" value="<?php echo sani($token);?>">
        <button class="<?php echoTaskBackground($myTask['priority'])?>">削除</button>
      </form>
    </div>
  </div>

<?php 
require_once('footer.php');