<?php 
require_once('function.php');
require_once('auth.php');

try {//DBハンドラ取得
  $dbh = dbConnect();
} catch (\Exception $e) {
  error_log('エラー発生:' . $e->getMessage());
  header("Location:unauthorized.php");
  exit();
}
$refineStatus = '2';
$refinePriority = '1';
$refineDeadline = '2';

$myTasksList = getMyTaskList($dbh, $_SESSION['user_id'], $refineStatus, $refinePriority, $refineDeadline);


$refineStatus = '2';
$refineResponsible = '1';
$participationGroupTasksList = getParticipationGroupTaskList($dbh, $_SESSION['user_id'], $refineStatus, $refinePriority, $refineDeadline, $refineResponsible);
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'タスク一覧';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="taskList">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <div class="mytask-list-wrap">
        <div class="title-wrap">
          <h2>マイタスク</h2>
          <a href="myTaskList.php">一覧へ</a>
        </div>

        <?php if(!empty($myTasksList['data']) && !empty($myTasksList['total'])): ?>
        <p class="number-of-tasks"><?php echo sani($currentMinNum + 1); ?>-<?php echo sani($currentMinNum + count($myTasksList['data'])); ?> 件 / <?php echo sani($myTasksList['total']); ?> 件中</p>
        <ul class="task-list">
          <?php //---------------表示準備--------------- ?>
          <?php foreach($myTasksList['data'] as $key => $val): ?>
          <?php $EchoDeadline = $val['deadline_flg'] === 1 ? $val['deadline'] . 'まで' : '期限なし' ; ?>
          <li data-taskid="<?php echo sani($val['search_id'])?>" data-title="<?php echo sani($val['title'])?>" data-detail="<?php echo sani($val['details'])?>" data-deadline="<?php echo sani($val['deadline'])?>" data-priority="<?php echo sani($val['priority'])?>">
            <a class="task-wrap <?php echoTaskBorder($val['priority'])?>" href="myTask.php?t_id=<?php echo sani($val['search_id']).sani($link)?>">
              <p class="task-title"><?php echo mb_strimwidth(sani($val['title']), 0, 32, "...", 'UTF-8'); ?></p>
              <div class="task-right-inner">
                <div class="deadline-responsible-wrap">
                  <p class="deadline">
                    <?php echo sani($EchoDeadline); ?>
                  </p>
                </div>
              </div>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="no-task-msg">タスクが存在しません</p>
        <?php endif; ?>
      </div>


      <div class="grouptask-list-wrap">
        <div class="title-wrap">
          <h2>グループタスク</h2>
          <a href="groupTaskList.php">一覧へ</a>
        </div>

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
      <?php else: ?>
      <p class="no-task-msg">タスクが存在しません</p>
      <?php endif; ?>
      </div>
    </div>
  </main>

<?php 
require_once('footer.php');