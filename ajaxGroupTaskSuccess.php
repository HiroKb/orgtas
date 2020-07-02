<?php 
require_once('function.php');

if (!empty($_POST['taskId']) && !empty($_SESSION['user_id']) && isLogin()) {
  $taskId = filter_input(INPUT_POST, 'taskId', FILTER_SANITIZE_SPECIAL_CHARS);

  try {
    // taskIdが正しいかバリデーション
    if (empty($taskId)) {
      throw new Exception('不正なタスクID');
    }
    if (!validLowrCase($taskId)) {
      throw new Exception('不正なタスクID');
    }
    $dbh = dbConnect();
    // タスク情報を取得
    $sql = 'SELECT group_id, responsible_id ,complete_flg 
            FROM group_tasks 
            WHERE search_id = :t_id AND delete_flg = 0';
    $data = array(':t_id' => $taskId);
    $stmt = queryPost($dbh, $sql, $data);
    $rst = $stmt->fetch();
    if ($rst) {//レコードが存在する場合
      $authority = getAuthority($dbh, $rst['group_id'], $_SESSION['user_id']);//権限を取得

      if ($authority !== 1 && $authority !== 2 && $rst['responsible_id'] !== $_SESSION['user_id'] && ($rst['responsible_id']) !== 'null' && $authority !== 3) {
        throw new Exception('不正なアクセス');
      }

        $sql = 'UPDATE group_tasks 
                SET complete_flg = :c_flg 
                WHERE search_id = :t_id AND group_id = :g_id AND delete_flg = 0';

      if ($rst['complete_flg']) {//タスクが完了済みの場合未了に
        debug('完了済み');
        $data = array('c_flg' => 0, ':t_id' => $taskId, ':g_id' => $rst['group_id']);
      }else {//タスクが未了の場合完了済みに
        debug('未了');
        $data = array('c_flg' => 1, ':t_id' => $taskId, 'g_id' => $rst['group_id']);
      }
      $stmt = queryPost($dbh, $sql, $data);

      if (!$stmt) {
        throw new Exception('不正なアクセス');
      }
    }else {//レコードが存在しない場合(不正なアクセス)
      throw new Exception('不正なアクセス');
    }
  } catch (Exception $e) {
    error_log('エラー発生:'. $e->getMessage());
    header("HTTP/1.1 503 Service Unavailable");
  }
}
 ?>