<?php 
require_once('function.php');
require_once('auth.php');
$addTaskFlg = false;
$deleteTaskFlg = false;
$addTaskErrFlg = false;
// 表示準備
$currentPageNum = !empty($_GET['p']) ? intval($_GET['p']) : 1;//表示するページ（現在のページ)
$refineStatus = filter_input(INPUT_GET, 'refine_status',  FILTER_SANITIZE_SPECIAL_CHARS);
$refinePriority = filter_input(INPUT_GET, 'refine_priority',  FILTER_SANITIZE_SPECIAL_CHARS);
$refineDeadline = filter_input(INPUT_GET, 'refine_deadline',  FILTER_SANITIZE_SPECIAL_CHARS);
if (!is_int($currentPageNum)) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
if ($refineStatus || $refinePriority || $refineDeadline) {//getパラメータが一つでもあった場合に全てのパラメータが存在しなければ不正アクセス
  if (empty($refineStatus) || empty($refinePriority) || empty($refineDeadline)) {
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }
}
if ($refineStatus) validIn($refineStatus, 'refine');
if ($refinePriority) validIn($refinePriority, 'refine', array('1', '2', '3', '4'));
if ($refineDeadline) validIn($refineDeadline, 'refine', array('1', '2', '3', '4'));
if (!empty($errs['refine'])) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

$getPara = '';//ページネーション用のURLパラメータ
if ($refineStatus && $refinePriority && $refineDeadline) {//getパラメータが存在する場合再度組み立てる
  $getPara = '&refine_status=' . $refineStatus . '&refine_priority=' . $refinePriority . '&refine_deadline=' . $refineDeadline;
}

$link = '&t=m&p='. $currentPageNum . $getPara;//現在ページパラメータ(t=マイタスクからのリンク識別用)
$listSpan = 20;//タスク表示件数
$currentMinNum = ($currentPageNum - 1) * $listSpan;//表示タスクの最小番

try {//DBハンドラ取得
  $dbh = dbConnect();
} catch (\Exception $e) {
  error_log('エラー発生:' . $e->getMessage());
  header("Location:unauthorized.php");
  exit();
}

if (!empty($_POST)) {//POST送信されていた場合
  checkToken();//正規アクセスか確認
  $postType = filter_input(INPUT_POST, 'post-type', FILTER_SANITIZE_SPECIAL_CHARS);
  if ($postType === 'add_task') {//タスク追加POSTの場合
    $addTaskErrFlg = true;
    // POST内容を格納し未入力チェック
    $title = filter_input(INPUT_POST, 'title');
    $details = filter_input(INPUT_POST, 'details');
    $deadline_flg = filter_input(INPUT_POST, 'deadline_flg', FILTER_SANITIZE_SPECIAL_CHARS);
    $deadline = filter_input(INPUT_POST, 'deadline');
    $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_SPECIAL_CHARS);

    validRequired($title, 'title');
    validRequired($deadline_flg, 'deadline_flg');
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
      validIn($priority, 'priority');//優先度形式チェック

      $deadlineBool = $deadline_flg === 'on' ? 1 : 0;

      if (empty($errs)) {//バリデーションを全て通った場合
        $priority = intval($priority);//DB登録用に数値型へキャスト
        try {

          // サーチIDの生成・重複チェック
          $searchId = makeRandStr();
          $sql = 'SELECT count(*) FROM my_tasks WHERE search_id = :search_id';
          $data = array('search_id' => $searchId);
          while (true) {
            $stmt = queryPost($dbh, $sql, $data);
            $result = $stmt->fetchColumn();
            if (!$result) {
              break;
            }
            $searchId = makeRandStr();
          }

          // 登録処理
          $sql = 'INSERT INTO my_tasks(search_id, user_id, title, details, priority, deadline, deadline_flg, create_date) VALUES (:search_id, :user_id, :title, :details, :priority, :deadline, :deadline_flg, :create_date)';
          $data = array(':search_id' => $searchId, 
                        ':user_id' => $_SESSION['user_id'], 
                        ':title' => $title, 
                        ':details' => $details,
                        ':priority' => $priority,
                        ':deadline' => $deadline,
                        ':deadline_flg' => $deadlineBool,
                        ':create_date' => date('Y-m-d H:i:s'));

          $stmt = queryPost($dbh, $sql, $data);

          if ($stmt) {
            $_POST = array();
            $addTaskErrFlg = false;
            $addTaskFlg = true;
          }
          
        } catch (Exception $e) {
          error_log('エラー発生:' . $e->getMessage());
          $errs['common'] = MSG2;
        }
        
      }
    }



  }elseif ($postType === 'delete_task') {//タスク削除POSTの場合
    $searchId = filter_input(INPUT_POST, 'task-id');
    if (!validLowrCase($searchId)) {
      error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
      header("Location:unauthorized.php");
      exit();
    }
    debug('dlete');
    try {
      $sql = 'SELECT count(*) AS rst
              FROM my_tasks 
              WHERE search_id = :t_id AND user_id = :u_id AND delete_flg = 0';
      $data = array(':t_id' => $searchId, ':u_id' => $_SESSION['user_id']);
      $stmt = queryPost($dbh, $sql, $data);
      $rst = $stmt->fetch();
      $rst = $rst['rst'];
      debug($rst);
      if ($rst) {
        debug('作成中');
        $sql = 'UPDATE my_tasks 
                SET delete_flg = 1 
                WHERE search_id = :t_id AND user_id = :u_id AND delete_flg = 0';
        $stmt = queryPost($dbh, $sql, $data);

        if ($stmt) {
          debug('削除成功');
          $deleteTaskFlg = true;
        }
      }
    } catch (Exception $e) {
      error_log('エラー発生:' . $e->getMessage());
    }
  }elseif ($postType === 'batch_deletion') {//一括削除POSTの場合
    debug('一括削除');
    try {
      $sql = 'SELECT count(*) AS rst
              FROM my_tasks 
              WHERE user_id = :u_id AND delete_flg = 0 AND complete_flg = 1';
      $data = array(':u_id' => $_SESSION['user_id']);
      $stmt = queryPost($dbh, $sql, $data);
      $rst = $stmt->fetch();
      $rst = $rst['rst'];

      if ($rst) {
        $sql = 'UPDATE my_tasks 
                SET delete_flg = 1 
                WHERE user_id = :u_id AND delete_flg = 0 AND complete_flg = 1';
        $stmt = queryPost($dbh, $sql, $data);
        if ($stmt) {
          $deleteTaskFlg = true;
        }
      }
    } catch (Exception $e) {
      error_log('エラー発生:' . $e->getMessage());
    }
  }
}



// マイタスクリスト取得
$myTasksList = getMyTaskList($dbh, $_SESSION['user_id'], $refineStatus, $refinePriority, $refineDeadline, $currentMinNum, $listSpan);
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'マイタスク';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="myTaskList">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width msg-wrap">
      <?php if($addTaskFlg) :?>
        <p id="slide-msg" class="my-color-background">タスクを追加しました</p>
      <?php endif; ?>
      <?php if($deleteTaskFlg) :?>
        <p id="slide-msg" class="my-color-background">タスクを削除しました</p>
      <?php endif; ?>
    </div>

    <div class="site-width">
      <div class="task-menu-wrap">
        <h2>マイタスク一覧</h2>

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


          <button class="my-color-background"><i class="fas fa-search"></i>絞り込み</button>
        </form>

        <button class="batch-deletion-btn my-color-background"><i class="fas fa-trash-alt"></i>完了タスクを削除</button>
        <button class="add-task-btn my-color-background" href=""><i class="fas fa-plus"></i>タスクを追加</button>
      </div>

      <!-- タスク一覧 -->
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

              <div class="btn-wrap">
                <i class="far fa-check-square task-complete-btn<?php if ($val['complete_flg']) {echo ' success-color';}?>"></i>
                <i class="fas fa-trash-alt task-delete-btn" ></i>
              </div>
            </div>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="pagination-wrap">
        <?php pagination($currentPageNum, $myTasksList['total_page'], $getPara, 'my'); ?>
      </div>
      <?php else: ?>
      <p class="no-task-msg">タスクが存在しません</p>
      <?php endif; ?>
    </div>
  </main>

  <!-- タスク追加モーダル -->
      <div id="add-task-modal" class="modal-wrap" <?php if($addTaskErrFlg) echo 'style="display: block"'; ?>>
        <div class="modal-bg modal-close"></div>
        <div class="modal like-memo-container my-color-border">
          <i class="fas fa-times modal-close"></i>
          <h2>マイタスク追加</h2>
          <form action="" method="post">
            <div class="fundamental-err-msg-wrap"><?php echoErrMsg('common'); ?></div>

            <!-- タクス名 -->
            <label for="title">タスク名</label>
            <div class="err-msg-wrap"><?php echoErrMsg('title'); ?></div>
            <input id="title" type="text" name="title" value="<?php echo getFormData('title') ?>">

            <!-- 詳細 -->
            <label for="details">詳細(任意)</label>
            <textarea name="details" id="details"><?php echo getFormData('details') ?></textarea>

            <!-- 期限フラグ -->
            <?php $deadlineStatus = !empty($_POST['deadline_flg']) ? $_POST['deadline_flg'] : false;?>
            <p class="deadline-para">期限</p>
            <div class="err-msg-wrap"><?php echoErrMsg('deadline'); ?></div>
            <input type="radio" name="deadline_flg" value ="on" id="deadline_on" <?php if($deadlineStatus === 'on') echo 'checked';?>>
            <label for="deadline_on" class="deadline-label">有り</label>
            <input type="radio" name="deadline_flg" id="deadline_off" value="off" <?php if($deadlineStatus === 'off' || $deadlineStatus === false) echo 'checked'; ?>>
            <label for="deadline_off" class="deadline-label deadline-off-label">無し</label>
            <input id="date-input" type="text" name="deadline" autocomplete="off">


            <!-- 優先度 -->
            <?php $priorityStatus = !empty($_POST['priority']) ? $_POST['priority'] : false;?>
            <label for="priority">重要度</label>
            <div class="err-msg-wrap"><?php echoErrMsg('priority'); ?></div>
            <select name="priority" id="priority">
              <option value="1" <?php if($priorityStatus === '1') echo 'selected'?>>高: 赤</option>
              <option value="2" <?php if($priorityStatus === '2') echo 'selected' ?>>中: 黄</option>
              <option value="3" <?php if($priorityStatus === '3' || $priorityStatus === false) echo 'selected' ;?>>低: 青</option>
            </select>

            <input type="hidden" name="post-type" value="add_task">
            <input type="hidden" name="token" value="<?php echo sani($token);?>">

            <button class="my-color-background">タスクを追加</button>
          </form>
        </div>
      </div>

  
  <!-- タスク削除モーダル -->
      <div id="delete-task-modal" class="modal-wrap">
        <div class="modal-bg modal-close"></div>
        <div class="modal like-memo-container">
          <i class="fas fa-times modal-close"></i>
          <form action="" method="post">
            <input type="hidden" name="token" value="<?php echo sani($token);?>">
          </form>
        </div>
      </div>

      <div id="batch-deletion-modal" class="modal-wrap">
        <div class="modal-bg modal-close"></div>
        <div class="modal like-memo-container my-color-border">
          <i class="fas fa-times modal-close"></i>
          <form action="" method="post">
            <p>完了タスクを全て削除しますか？</p>
            <input type="hidden" name="post-type" value="batch_deletion">
            <input type="hidden" name="token" value="<?php echo sani($token);?>">
            <button class="my-color-background">削除</button>
          </form>
        </div>
      </div>
<?php 
require_once('footer.php');