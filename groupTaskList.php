<?php 
require_once('function.php');
require_once('auth.php');
// 表示準備
$currentPageNum = !empty($_GET['p']) ? intval($_GET['p']) : 1;//表示するページ（現在のページ)
$refineStatus = filter_input(INPUT_GET, 'refine_status',  FILTER_SANITIZE_SPECIAL_CHARS);
$refinePriority = filter_input(INPUT_GET, 'refine_priority',  FILTER_SANITIZE_SPECIAL_CHARS);
$refineDeadline = filter_input(INPUT_GET, 'refine_deadline',  FILTER_SANITIZE_SPECIAL_CHARS);
$refineResponsible = filter_input(INPUT_GET, 'refine_responsible',  FILTER_SANITIZE_SPECIAL_CHARS);
if (!is_int($currentPageNum)) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
if ($refineStatus || $refinePriority || $refineDeadline || $refineResponsible) {//refinegetパラメータが一つでもあった場合に全てのパラメータが存在しなければ不正アクセス
  if (empty($refineStatus) || empty($refinePriority) || empty($refineDeadline) || empty($refineResponsible)) {
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }
}
if ($refineStatus) validIn($refineStatus, 'refine');
if ($refinePriority) validIn($refinePriority, 'refine', array('1', '2', '3', '4'));
if ($refineDeadline) validIn($refineDeadline, 'refine', array('1', '2', '3', '4'));
if ($refineResponsible) validIn($refineResponsible, 'refine', array('1', '2', '3', '4'));
if (!empty($errs['refine'])) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

$getPara = '';//ページネーション用のURLパラメータ
if ($refineStatus && $refinePriority && $refineDeadline && $refineResponsible) {//getパラメータが存在する場合再度組み立てる
  $getPara = '&refine_status=' . $refineStatus . '&refine_priority=' . $refinePriority . '&refine_deadline=' . $refineDeadline . '&refine_responsible=' . $refineResponsible;
}

$link = '&t=l&p='. $currentPageNum . $getPara;//現在ページパラメータ(t=マイタスクからのリンク識別用)
$listSpan = 20;//タスク表示件数
$currentMinNum = ($currentPageNum - 1) * $listSpan;//表示タスクの最小番

try {//DBハンドラ取得
  $dbh = dbConnect();
} catch (\Exception $e) {
  error_log('エラー発生:' . $e->getMessage());
  header("Location:unauthorized.php");
  exit();
}


// マイタスクリスト取得
$participationGroupTasksList = getParticipationGroupTaskList($dbh, $_SESSION['user_id'], $refineStatus, $refinePriority, $refineDeadline, $refineResponsible, $currentMinNum, $listSpan);
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'グループタスク一覧';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="groupTaskList">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width msg-wrap">
    </div>

    <div class="site-width">
      <div class="task-menu-wrap">
        <h2>グループタスク一覧</h2>

        <!-- タスクメニュー -->
        <form class="refine" action="" method="get">
          <label for="refine-status">状態:</label>
          <select name="refine_status" id="refine-status">
            <option value="1" <?php if(getFormData('refine_status', false, false) === '1') echo 'selected'; ?>>全て</option>
            <option value="2" <?php if(getFormData('refine_status', false, false) === '2') echo 'selected'; ?>>未了</option>
            <option value="3" <?php if(getFormData('refine_status', false, false) === '3') echo 'selected'; ?>>完了</option>
          </select>

          <label for="refine-priority">重要度:</label>
          <select name="refine_priority" id="refine-priority">
            <option value="1" <?php if(getFormData('refine_priority', false, false) === '1') echo 'selected'; ?>>全て</option>
            <option value="2" <?php if(getFormData('refine_priority', false, false) === '2') echo 'selected'; ?>>高</option>
            <option value="3" <?php if(getFormData('refine_priority', false, false) === '3') echo 'selected'; ?>>中</option>
            <option value="4" <?php if(getFormData('refine_priority', false, false) === '4') echo 'selected'; ?>>低</option>
          </select>

          <label for="refine-deadline">期限:</label>
          <select name="refine_deadline" id="refine-deadline">
            <option value="1" <?php if(getFormData('refine_deadline', false, false) === '1') echo 'selected'; ?>>未選択</option>
            <option value="2" <?php if(getFormData('refine_deadline', false, false) === '2') echo 'selected'; ?>>近い順</option>
            <option value="3" <?php if(getFormData('refine_deadline', false, false) === '3') echo 'selected'; ?>>遠い順</option>
            <option value="4" <?php if(getFormData('refine_deadline', false, false) === '4') echo 'selected'; ?>>なしのみ</option>
          </select>

          <label for="refine-pesponsible">担当:</label>
          <select name="refine_responsible" id="refine-responsible">
            <option value="1" <?php if(getFormData('refine_responsible', false, false) === '1') echo 'selected'?>>自分＋担当なし</option>
            <option value="2" <?php if(getFormData('refine_responsible', false, false) === '2') echo 'selected'?>>自分</option>
            <option value="3" <?php if(getFormData('refine_responsible', false, false) === '3') echo 'selected'?>>担当なし</option>
            <option value="4" <?php if(getFormData('refine_responsible', false, false) === '4') echo 'selected'?>>全て</option>
          </select>

          <button class="my-color-background"><i class="fas fa-search"></i>絞り込み</button>
        </form>
      </div>

      <!-- タスク一覧 -->
      <?php if(!empty($participationGroupTasksList['data']) && !empty($participationGroupTasksList['total'])): ?>
      <p class="number-of-tasks"><?php echo sani($currentMinNum + 1); ?>-<?php echo sani($currentMinNum + count($participationGroupTasksList['data'])); ?> 件 / <?php echo sani($participationGroupTasksList['total']); ?> 件中</p>
      <ul class="task-list">
        <?php //---------------表示準備--------------- ?>
        <?php foreach($participationGroupTasksList['data'] as $key => $val): ?>
        <?php $EchoDeadline = $val['deadline_flg'] === 1 ? $val['deadline'] . 'まで' : '期限なし' ; ?>
        <?php $responsibleUser = !empty($val['responsible_name']) ? $val['responsible_name'] : 'なし'; ?>
        <?php if($val['responsible_id'] === $_SESSION['user_id']) $responsibleUser = '自分' ;?>
        <?php $status = $val['complete_flg'] === 1 ? '完了' : '未了'?>
        <li data-taskid="<?php echo sani($val['task_id'])?>" data-title="<?php echo sani($val['title'])?>" data-detail="<?php echo sani($val['details'])?>" data-deadline="<?php echo sani($val['deadline'])?>" data-priority="<?php echo sani($val['priority'])?>">
          <a class="task-wrap <?php echoTaskBorder($val['priority'])?>" href="groupTask.php?t_id=<?php echo sani($val['task_id'])?>&g_id=<?php echo $val['group_id'] . sani($link)?>">
            <p class="task-title"><?php echo mb_strimwidth(sani($val['title']), 0, 32, "...", 'UTF-8'); ?></p>
            <div class="task-right-inner">
              <p><?php echo mb_strimwidth(sani($val['group_name']), 0, 18, "...", 'UTF-8') ?></p>
              <p><?php echo sani($EchoDeadline); ?></p>
              <p>担当:<?php echo mb_strimwidth(sani($responsibleUser), 0, 12, "...", 'UTF-8') ?></p>
              <p><?php echo $status ?></p>
            </div>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="pagination-wrap">
        <?php pagination($currentPageNum, $participationGroupTasksList['total_page'], $getPara, 'my'); ?>
      </div>
      <?php else: ?>
      <p class="no-task-msg">タスクが存在しません</p>
      <?php endif; ?>
    </div>
  </main>

  <!-- タスク追加モーダル -->
<?php 
require_once('footer.php');