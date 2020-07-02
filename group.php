<?php 
require_once('function.php');
require_once('auth.php');
$addTaskFlg = false;
$deleteTaskFlg = false;
$addTaskErrFlg = false;
$authority = false;

// グループIDを取得・形式チェック
$groupId = filter_input(INPUT_GET, 'g_id', FILTER_SANITIZE_SPECIAL_CHARS);
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
if (empty($authority)) {//不正なアクセスの場合
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

$groupData = getGroupDetail($dbh, $groupId);//グループのデータを取得
$groupMemberList = getGroupMember($dbh, $groupId);//グループのメンバーを取得

if (!empty($_POST)) {//POST送信されていた場合
  if ($authority !== 1 && $authority !== 2) {
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }

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
    $responsible = filter_input(INPUT_POST, 'responsible', FILTER_SANITIZE_SPECIAL_CHARS);

    validRequired($title, 'title');
    validRequired($deadline_flg, 'deadline_flg');
    validRequired($priority, 'priority');
    validRequired($responsible, 'responsible');
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

      $groupMemberIdList = array('none');
      foreach ($groupMemberList as $data) {
        $groupMemberIdList[] = $data['u_id'];
      }
      validIn($responsible, 'responsible', $groupMemberIdList);

      if (empty($errs)) {//バリデーションを全て通った場合
        $priority = intval($priority);//DB登録用に数値型へキャスト
        try {
          // サーチIDの生成・重複チェック
          $searchId = makeRandStr();
          $sql = 'SELECT count(*) FROM group_tasks WHERE search_id = :search_id';
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
          $sql = 'INSERT INTO group_tasks(search_id, group_id, title, details, priority, deadline, deadline_flg, responsible_id,  create_date) VALUES (:search_id, :group_id, :title, :details, :priority, :deadline, :deadline_flg, :responsible_id, :create_date)';
          $data = array(':search_id' => $searchId, 
                        ':group_id' => $groupId, 
                        ':title' => $title, 
                        ':details' => $details,
                        ':priority' => $priority,
                        ':deadline' => $deadline,
                        ':deadline_flg' => $deadlineBool,
                        ':responsible_id' => $responsible,
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
    $searchId = filter_input(INPUT_POST, 'task-id', FILTER_SANITIZE_SPECIAL_CHARS);
    if (!validLowrCase($searchId)) {
      error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
      header("Location:unauthorized.php");
      exit();
    }
    debug('dlete');
    try {
      $sql = 'SELECT count(*) AS rst
              FROM group_tasks 
              WHERE search_id = :t_id AND group_id = :g_id AND delete_flg = 0';
      $data = array(':t_id' => $searchId, ':g_id' => $groupId);
      $stmt = queryPost($dbh, $sql, $data);
      $rst = $stmt->fetch();
      $rst = $rst['rst'];
      debug($rst);
      if ($rst) {
        $sql = 'UPDATE group_tasks 
                SET delete_flg = 1 
                WHERE search_id = :t_id AND group_id = :g_id AND delete_flg = 0';
        $stmt = queryPost($dbh, $sql, $data);

        if ($stmt) {
          debug('削除成功');
          $deleteTaskFlg = true;
        }
      }
    } catch (Exception $e) {
      error_log('エラー発生:' . $e->getMessage());
    }
  }else{//不正POST
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }
}

// 表示処理
$currentPageNum = !empty($_GET['p']) ? intval($_GET['p']) : 1;//表示するページ（現在のページ)
if (!is_int($currentPageNum)) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}
$refineStatus = filter_input(INPUT_GET, 'refine_status',  FILTER_SANITIZE_SPECIAL_CHARS);
$refinePriority = filter_input(INPUT_GET, 'refine_priority',  FILTER_SANITIZE_SPECIAL_CHARS);
$refineDeadline = filter_input(INPUT_GET, 'refine_deadline',  FILTER_SANITIZE_SPECIAL_CHARS);
$refineResponsible = filter_input(INPUT_GET, 'refine_responsible', FILTER_SANITIZE_SPECIAL_CHARS);
if ($refineStatus || $refinePriority || $refineDeadline || $refineResponsible) {//getパラメータが一つでもあった場合に全てのパラメータが存在しなければ不正アクセス
  if (empty($refineStatus) || empty($refinePriority) || empty($refineDeadline) || empty($refineResponsible)) {
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }
}
if ($refineStatus) validIn($refineStatus, 'refine');
if ($refinePriority) validIn($refinePriority, 'refine', array('1', '2', '3', '4'));
if ($refineDeadline) validIn($refineDeadline, 'refine', array('1', '2', '3', '4'));

$refineResponsibleList = array('1', '2', '3');//担当者絞り込みの判定用配列を作成
foreach ($groupMemberList as $data) {
  $refineResponsibleList[] = $data['u_id'];
}
if ($refineResponsible) validIn($refineResponsible, 'refine', $refineResponsibleList);

if (!empty($errs['refine'])) {
  error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
  header("Location:unauthorized.php");
  exit();
}

$getPara = '&g_id='. $groupId;//ページネーション用のURLパラメータ
if ($refineStatus && $refinePriority && $refineDeadline && $refineResponsible) {//getパラメータが存在する場合再度組み立てる
  $getPara .= '&refine_status=' . $refineStatus . '&refine_priority=' . $refinePriority . '&refine_deadline=' . $refineDeadline . '&refine_responsible='. $refineResponsible;
}

$link = '&t=g&p='. $currentPageNum . $getPara;//現在ページパラメータ(t=グループからのリンク識別用)
$listSpan = 20;//タスク表示件数
$currentMinNum = ($currentPageNum - 1) * $listSpan;//表示タスクの最小番
// マイタスクリスト取得
$groupTaskList = getGroupTaskList($dbh, $groupId, $refineStatus, $refinePriority, $refineDeadline, $refineResponsible, $currentMinNum);
generateToken();
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'グループ';
$bgType = 'group-color-background';
require_once('head.php');
 ?>
<body class="group">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width msg-wrap">
      <?php if($addTaskFlg) :?>
        <p id="slide-msg" class="group-color-background">タスクを追加しました</p>
      <?php endif; ?>
      <?php if($deleteTaskFlg) :?>
        <p id="slide-msg" class="group-color-background">タスクを削除しました</p>
      <?php endif; ?>
    </div>

    <div class="site-width">
      <div class="group-detail-menu-wrap group-color-border">
        <div class="group-detail-wrap">
          <img src="<?php getImg($groupData['img']);?>" alt="グループ画像">
          <div class="group-name-msg-wrap">
            <p class="group-name"><?php echo mb_strimwidth(sani($groupData['groupname']), 0, 40, "...", 'UTF-8'); ?></p>
            <?php if(!empty($groupData['msg'])): ?>
            <p class="group-msg"><?php echo mb_strimwidth(sani($groupData['msg']), 0, 120, "...", 'UTF-8'); ?></p>
            <?php endif; ?>
          </div>
        </div>

        <div class="group-menu-wrap">
          <?php if($authority === 1) : ?>
          <div class="menber-menu">
            <a href="groupMember.php?g_id=<?php echo sani($groupId)?>" class="member-btn group-color-background"><i class="fas fa-users"></i>メンバー</a>
            <a href="chat.php?g_id=<?php echo $groupId?>" class="chat-btn group-color-background"><i class="fas fa-comments"></i>グループチャット</a>
          </div>
          <div class="admin-menu">
            <button class="add-task-btn group-color-background" href=""><i class="fas fa-plus"></i>タスクを追加</button>
            <a href="pendingUser.php?g_id=<?php echo $groupId?>" class="pending-btn group-color-background"><i class="fas fa-user-check">承認待ちユーザー</i></a>
            <a href="groupEdit.php?g_id=<?php echo $groupId;?>" class="config-btn group-color-background"><i class="fas fa-cog"></i>設定</a>
          </div>
          <?php elseif($authority === 2) : ?>
          <div class="menber-menu">
            <a href="groupMember.php?g_id=<?php echo $groupId?>" class="member-btn group-color-background"><i class="fas fa-users"></i>メンバー</a>
            <a href="chat.php?g_id=<?php echo $groupId?>" class="chat-btn group-color-background"><i class="fas fa-comments"></i>グループチャット</a>
            <button class="withdrawal-btn group-color-background"><i class="fas fa-user-times"></i>脱退</button>
          </div>
          <div class="deputy-menu">
            <button class="add-task-btn group-color-background" href=""><i class="fas fa-plus"></i>タスクを追加</button>
            <a href="pendingUser.php?g_id=<?php echo $groupId?>" class="pending-btn group-color-background"><i class="fas fa-user-check">承認待ちユーザー</i></a>
          </div>
          <?php else : ?>
          <div class="menber-menu">
            <a href="groupMember.php?g_id=<?php echo $groupId?>" class="member-btn group-color-background"><i class="fas fa-users"></i>メンバー</a>
            <a href="chat.php?g_id=<?php echo $groupId?>" class="chat-btn group-color-background"><i class="fas fa-comments"></i>グループチャット</a>
            <button class="withdrawal-btn group-color-background"><i class="fas fa-user-times"></i>脱退</button>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="task-menu-wrap">
        <h2>グループタスク一覧</h2>

        <!-- タスクメニュー -->
        <form class="refine" action="" method="get">
          <input type="hidden" name="g_id" value="<?php echo $groupId?>">
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
            <option value="1" <?php if(getFormData('refine_responsible', false, false) === '1') echo 'selected'?>>全て</option>
            <option value="2" <?php if(getFormData('refine_responsible', false, false) === '2') echo 'selected'?>>担当なし</option>
            <option value="3" <?php if(getFormData('refine_responsible', false, false) === '3') echo 'selected'?>>自分</option>
            <?php foreach ($groupMemberList as $key => $val) : ?>
              <?php if($val['u_id'] !== $_SESSION['user_id']) : ?>
            <option value="<?php echo $val['u_id']?>" <?php if(getFormData('refine_responsible', false, false) === $val['u_id']) echo 'selected'?>><?php echo mb_strimwidth(sani($val['username']),0, 24, '...', 'UTF-8') ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>

          <button class="group-color-background"><i class="fas fa-search"></i>絞り込み</button>
        </form>
      </div>

      <!-- タスク一覧 -->
      <?php if(!empty($groupTaskList['data']) && !empty($groupTaskList['total'])): ?>
      <p class="number-of-tasks"><?php echo sani($currentMinNum + 1); ?>-<?php echo sani($currentMinNum + count($groupTaskList['data'])); ?> 件 / <?php echo sani($groupTaskList['total']); ?> 件中</p>
      <ul class="task-list">
        <?php //---------------表示準備--------------- ?>
        <?php foreach($groupTaskList['data'] as $key => $val): ?>
        <?php switch ($val['priority']) {
        } ?>
        <?php $EchoDeadline = $val['deadline_flg'] === 1 ? $val['deadline'] . 'まで' : '期限なし' ; ?>
        <?php $responsibleUser = !empty($val['username']) ? $val['username'] : 'なし'?>
        <?php if($val['responsible_id'] === $_SESSION['user_id']) $responsibleUser = '自分' ;?>
        <li data-taskid="<?php echo sani($val['task_id'])?>" data-title="<?php echo sani($val['title'])?>" data-detail="<?php echo sani($val['details'])?>" data-responsible="<?php echo sani($val['username'])?>" data-deadline="<?php echo sani($val['deadline'])?>" data-priority="<?php echo sani($val['priority'])?>">
          <a class="task-wrap <?php echoTaskBorder($val['priority'])?>" href="groupTask.php?t_id=<?php echo sani($val['task_id']).sani($link)?>">
            <p class="task-title"><?php echo mb_strimwidth(sani($val['title']), 0, 32, "...", 'UTF-8'); ?></p>
            <div class="task-right-inner">
              <div class="deadline-responsible-wrap">
                <p class="deadline">
                  <?php echo sani($EchoDeadline); ?>
                </p>
                <p class="responsible-user">
                  担当:<?php echo mb_strimwidth(sani($responsibleUser), 0, 20, "...", 'UTF-8') ?>
                </p>
              </div>

              <div class="btn-wrap">
                <?php if($authority === 1 || $authority === 2) : ?>
                <i class="far fa-check-square task-complete-btn<?php if ($val['complete_flg']) {echo ' success-color';}?>"></i>
                <i class="fas fa-trash-alt task-delete-btn" ></i>
                <?php elseif($val['responsible_id'] === $_SESSION['user_id'] || $val['responsible_id'] === 'none') : ?>
                <i class="far fa-check-square task-complete-btn<?php if ($val['complete_flg']) {echo ' success-color';}?>"></i>
      <?php endif; ?>
              </div>
            </div>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="pagination-wrap">
        <?php pagination($currentPageNum, $groupTaskList['total_page'], $getPara, 'group'); ?>
      </div>
      <?php else: ?>
      <p class="no-task-msg">タスクが存在しません</p>
      <?php endif; ?>
    </div>
  </main>

  <!-- タスク追加モーダル -->
      <div id="add-task-modal" class="modal-wrap" <?php if($addTaskErrFlg) echo 'style="display: block"'; ?>>
        <div class="modal-bg modal-close"></div>
        <div class="modal like-memo-container group-color-border">
          <i class="fas fa-times modal-close"></i>
          <h2>グループタスク追加</h2>
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

            <!-- 担当者 -->
            <label for="responsible">担当</label>
            <div class="err-msg-wrap"></div>
            <select name="responsible" id="responsible">
              <option value="none">無し</option>
            <?php foreach ($groupMemberList as $key => $val) : ?>
              <option value="<?php echo $val['u_id']?>"><?php echo mb_strimwidth(sani($val['username']), 0, 48, "...", 'UTF-8') ?></option>
            <?php endforeach; ?>
            </select>

            <input type="hidden" name="post-type" value="add_task">
            <input type="hidden" name="token" value="<?php echo sani($token);?>">

            <button class="group-color-background">タスクを追加</button>
          </form>
        </div>
      </div>

  
  <!-- タスク削除モーダル -->
      <div id="delete-task-modal" class="modal-wrap">
        <div class="modal-bg modal-close"></div>
        <div class="modal like-memo-container">
          <i class="fas fa-times modal-close"></i>
          <form action="" method="post">
            <p class="delete-task-deadline">test</p>
            <p class="delete-task-title">test</p>
            <p class="delete-task-detail">test</p>
            <input type="hidden" name="token" value="<?php echo sani($token);?>">
          </form>
        </div>
      </div>

<?php 
require_once('footer.php');