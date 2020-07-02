<?php 
require_once('function.php');
require_once('auth.php');

$TaskEditFlg = false;

$searchId = filter_input(INPUT_GET, 't_id', FILTER_SANITIZE_SPECIAL_CHARS);
$groupId = filter_input(INPUT_GET, 'g_id', FILTER_SANITIZE_SPECIAL_CHARS);
if (validLowrCase($searchId) && validLowrCase($groupId)) {//GETパラメータが正しい場合
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



$groupTask = getGroupTask($dbh, $searchId, $groupId);//グループタスク情報を取得
debug(print_r($groupTask, true));


if (empty($groupTask))  {//自分以外のタスクにアクセスしようとした場合などは不正アクセス
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

$groupMemberList = getGroupMember($dbh, $groupId);//グループのメンバー情報を取得

$linkType = filter_input(INPUT_GET, 't', FILTER_SANITIZE_SPECIAL_CHARS);//どこから飛んできたかを格納
debug($linkType);
validIn($linkType, 'linktype', array('g', 'l'));//正規のリンク確認
if (empty($errs)) {
  $refineStatus = filter_input(INPUT_GET, 'refine_status',  FILTER_SANITIZE_SPECIAL_CHARS);
  $refinePriority = filter_input(INPUT_GET, 'refine_priority',  FILTER_SANITIZE_SPECIAL_CHARS);
  $refineDeadline = filter_input(INPUT_GET, 'refine_deadline',  FILTER_SANITIZE_SPECIAL_CHARS);
  $refineResponsible = filter_input(INPUT_GET, 'refine_responsible', FILTER_SANITIZE_SPECIAL_CHARS);

  if ($refineStatus || $refinePriority || $refineDeadline || $refineResponsible) {//絞り込みgetパラメータが一つでもあった場合に全てのパラメータが存在しなければ不正アクセス
    if (empty($refineStatus) || empty($refinePriority) || empty($refineDeadline) || empty($refineStatus)) {
      error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
      header("Location:unauthorized.php");
      exit();
    }
  }

  if ($linkType === 'g') {//groupから飛んできた場合
    $backLink = 'group.php?p=' . intval(filter_input(INPUT_GET, 'p', FILTER_SANITIZE_SPECIAL_CHARS)) . '&g_id=' .$groupId;//ページ数とグループIDをバックリンクに追加
  $refineResponsibleList = array('1', '2', '3');//形式チェック用の配列
  foreach ($groupMemberList as $data) {
    $refineResponsibleList[] = $data['u_id'];
  }
  }elseif ($linkType === 'l') {//groupTaskListから飛んできた場合
    $backLink = 'groupTaskList.php?p=' . intval(filter_input(INPUT_GET, 'p', FILTER_SANITIZE_SPECIAL_CHARS));//ページ数をバックリンクに追加
    $refineResponsibleList = array('1', '2', '3', '4');
  }

  //$refine系の形式チェック
  if ($refineStatus) validIn($refineStatus, 'refine');
  if ($refinePriority) validIn($refinePriority, 'refine', array('1', '2', '3', '4'));
  if ($refineDeadline) validIn($refineDeadline, 'refine', array('1', '2', '3', '4'));
  if ($refineResponsible) validIn($refineResponsible, 'responsible', $refineResponsibleList);
  if (!empty($errs['refine'])) {
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }

    if ($refineStatus) {//絞り込み後のリンクから飛んできた場合
      $backLink .=  '&refine_status=' . $refineStatus . '&refine_priority=' . $refinePriority . '&refine_deadline=' . $refineDeadline . '&refine_responsible=' . $refineResponsible;
    }

}else{
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

if (!empty($_POST)) {//POST送信されていた場合
  checkToken();
  if ($authority !== 1 && $authority !== 2) {
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }
  $postType = filter_input(INPUT_POST, 'post-type', FILTER_SANITIZE_SPECIAL_CHARS);
  if ($postType === 'edit_task') {
    
    // POST内容を格納し未入力チェック
    $title = filter_input(INPUT_POST, 'title');
    $details = filter_input(INPUT_POST, 'details');
    $deadline_flg = filter_input(INPUT_POST, 'deadline_flg', FILTER_SANITIZE_SPECIAL_CHARS);
    $deadline = filter_input(INPUT_POST, 'deadline');
    $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_SPECIAL_CHARS);
    $responsible = filter_input(INPUT_POST, 'responsible', FILTER_SANITIZE_SPECIAL_CHARS);

    validRequired($title, 'title');
    validRequired($deadline_flg, 'deadline');
    validRequired($priority, 'priority');
    validRequired($responsible, 'responsible');
    if (empty($errs)) {
      validMaxLen($title, 'title');//タイトルの最大文字数チェック
      if (!empty($details)) {//詳細が入力されている場合
        validMaxLen($details, 'details');//詳細の最大文字数チェック
      }
      validDeadLineFlg($deadline_flg, 'deadline');
      validIn($responsible, 'responsible', $refineResponsibleList);
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


      if (empty($errs) && $title === $groupTask['title'] && $details === $groupTask['details'] && $deadlineBool === $groupTask['deadline_flg'] && $deadline === $groupTask['deadline'] && $priority === $groupTask['priority'] && $responsible === $groupTask['responsible_id']) {
        $errs['noedit'] = 'noedit';
      }
      if (empty($errs)) {//バリデーションを全て通った場合
        try {
          $sql = 'UPDATE group_tasks 
                  SET title = :title, details = :details, priority = :priority, deadline = :deadline, deadline_flg = :deadline_flg, responsible_id = :responsible_id 
                  WHERE search_id = :s_id AND group_id = :g_id AND delete_flg = 0';
          $data = array(':title' => $title, 
                        ':details' => $details,
                        ':priority' => $priority,
                        ':deadline' => $deadline,
                        ':deadline_flg' => $deadlineBool,
                        ':responsible_id' => $responsible,
                        ':s_id' => $searchId,
                        ':g_id' => $groupId);
          $stmt = queryPost($dbh, $sql, $data);
          if ($stmt) {
            $TaskEditFlg = true;
            $groupTask['priority'] = $priority;
            $groupTask['responsible_id'] = $responsible;
          }
        } catch (Exception $e) {
          error_log('エラー発生:' . $e->getMessage());
          $errs['common'] = MSG2;
        }
        
      }
    }
  }elseif ($postType === 'delete_task') {
    try {
      $sql = 'UPDATE group_tasks 
              SET delete_flg = 1 
              WHERE search_id = :s_id AND group_id = :g_id AND delete_flg = 0';
      $data = array(':s_id' => $searchId, 'g_id' => $groupId);
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
$groupData = getGroupDetail($dbh, $groupId);
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'グループタスク';
$bgType = 'group-color-background';
require_once('head.php');
 ?>
<body class="groupTask">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
<?php if($groupTask) : //タスクデータが取得できた場合?>
  <?php if($authority === 1 || $authority === 2) :?>
      <section class="like-memo-container <?php echoTaskBorder($groupTask['priority'])?>">
      <a href="<?php echo sani($backLink)?>" class="back-list-btn"><i class="fas fa-arrow-left"></i></a>
      <i class="far fa-check-square task-complete-btn grouptask-complete-btn<?php if ($groupTask['complete_flg']) {echo ' success-color';}?>" data-taskid="<?php echo sani($searchId);?>"></i>
      <i class="fas fa-trash-alt grouptask-delete-btn"></i>
<?php if($TaskEditFlg) :?>
        <p id="slide-msg" class="<?php echoTaskBackground($groupTask['priority'])?>">タスクを更新しました</p>
<?php endif; ?>

        <form action="" method="post">
          <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

          <!-- タクス名 -->
          <label for="title">タスク名</label>
          <div class="err-msg-wrap"><?php echoErrMsg('title'); ?></div>
          <input id="title" type="text" name="title" value="<?php echo getFormData('title', $groupTask);?>">

          <!-- 詳細 -->
          <label for="details">詳細(任意)</label>
          <textarea name="details" id="details"><?php echo getFormData('details', $groupTask); ?></textarea>

          <!-- 期限フラグ -->
          <p class="deadline-para">期限</p>
          <div class="err-msg-wrap"><?php echoErrMsg('deadline'); ?></div>
          <input type="radio" name="deadline_flg" value ="on" id="deadline_on" <?php if(getFormData('deadline_flg', $groupTask) === '1' || getFormData('deadline_flg') === 'on') echo 'checked'; ?>>
          <label for="deadline_on" class="deadline-label">有り</label>
          <input type="radio" name="deadline_flg" id="deadline_off" value="off" <?php if(getFormData('deadline_flg', $groupTask) === '0' || getFormData('deadline_flg') === 'off') echo 'checked'; ?>>
          <label for="deadline_off" class="deadline-label deadline-off-label">無し</label>
          <input id="date-input" type="text" name="deadline" value="<?php echo getFormData('deadline', $groupTask)?>">


          <!-- 重要度 -->
          <label for="priority">重要度</label>
          <div class="err-msg-wrap"><?php echoErrMsg('priority'); ?></div>
          <select name="priority" id="priority">
            <option value="1" <?php if(getFormData('priority', $groupTask) === '1' ||getFormData('priority') === 1) echo 'selected'?>>高: 赤</option>
            <option value="2" <?php if(getFormData('priority', $groupTask) === '2' ||getFormData('priority') === 2) echo 'selected'?>>中: 黄</option>
            <option value="3" <?php if(getFormData('priority', $groupTask) === '3' ||getFormData('priority') === 3) echo 'selected'?>>低: 青</option>
          </select>

          <label for="responsible">担当</label>
          <div class="err-msg-wrap"><?php echoErrMsg('responsible'); ?></div>
          <select name="responsible" id="responsible">
            <option value="none" <?php if(getFormData('responsible_id', $groupTask) === 'none') echo 'selected'?>>無し</option>
          <?php foreach ($groupMemberList as $key => $val) : ?>
            <option value="<?php echo sani($val['u_id'])?>" <?php if(getFormData('responsible_id', $groupTask) === $val['u_id'] ) echo 'selected'?>><?php echo mb_strimwidth(sani($val['username']), 0 , 50, "...", 'UTF-8') ?></option>
          <?php endforeach; ?>
          </select>

          <input type="hidden" name="post-type" value="edit_task">
          <input type="hidden" name="token" value="<?php echo sani($token);?>">

          <button class="<?php echoTaskBackground($groupTask['priority'])?>">タスクを更新</button>
        </form>
  <?php else : ?>
      <section class="like-memo-container <?php echoTaskBorder($groupTask['priority'])?>">
      <?php if($groupTask['responsible_id'] === $_SESSION['user_id'] || $groupTask['responsible_id'] === 'none') : ?>
      <a href="<?php echo sani($backLink)?>" class="responsible-back-list-btn"><i class="fas fa-arrow-left"></i></a>
      <i class="far fa-check-square task-complete-btn responsible-grouptask-complete-btn<?php if ($groupTask['complete_flg']) {echo ' success-color';}?>" data-taskid="<?php echo sani($searchId);?>"></i>

      <?php else :?>
        <a href="<?php echo sani($backLink)?>" class="general-back-list-btn"><i class="fas fa-arrow-left"></i></a>
      <?php endif; ?>


        <div class="task-detail-wrap">
          <p class="general-deadline">期限:<?php 
          if ($groupTask['deadline_flg']) {
            echo $groupTask['deadline'];
          }else {
            echo 'なし';
          }
          ?></p>
          <p class="general-responsible">担当:<?php 
          if ($groupTask['responsibleName']) {
            echo mb_strimwidth(sani($groupTask['responsibleName']), 0, 48, "...", 'UTF-8');
          }else {
            echo 'なし';
          }
          ?></p>
          <p class="general-title"><?php echo sani($groupTask['title']) ?></p>
          <!-- <p class="general-details">テスト詳細</p> -->
        </div>
  <?php endif; ?>

<?php else : //タスクデータが取得できなかった場合 ?>
      <section class="like-memo-container" style="border-left: 30px #666 solid;">
        <h2>タスクが存在しません。</h2>
<?php endif; ?>
      </section>
    </div>
  </main>

  <div id="delete-task-modal" class="modal-wrap">
    <div class="modal-bg modal-close"></div>
    <div class="modal like-memo-container <?php echoTaskBorder($groupTask['priority'])?>">
      <i class="fas fa-times modal-close"></i>
      <form action="" method="post">
        <p>タスクを削除しますか？</p>
        <input type="hidden" name="post-type" value="delete_task">
        <input type="hidden" name="token" value="<?php echo sani($token);?>">
        <button class="<?php echoTaskBackground($groupTask['priority'])?>">削除</button>
      </form>
    </div>
  </div>

<?php 
require_once('footer.php');