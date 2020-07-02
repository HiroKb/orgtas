<?php 
require_once('function.php');

if (!empty($_POST['taskId']) && !empty($_SESSION['user_id']) && isLogin()) {
  $taskId = filter_input(INPUT_POST, 'taskId', FILTER_SANITIZE_SPECIAL_CHARS);

  try {
    debug($taskId);
    // taskIdが正しいかバリデーション
    if (empty($taskId)) {
      throw new Exception('不正なタスクID');
    }
    if (!validLowrCase($taskId)) {
      throw new Exception('不正なタスクID');
    }
    $dbh = dbConnect();
    $sql = 'SELECT complete_flg 
            FROM my_tasks 
            WHERE search_id = :t_id AND user_id = :u_id AND delete_flg = 0';
    $data = array(':t_id' => $taskId, 'u_id' => $_SESSION['user_id']);
    $stmt = queryPost($dbh, $sql, $data);
    $rst = $stmt->fetch();
    debug(print_r($rst,true));
    if ($rst) {//レコードが存在する場合(正規アクセス)
        $sql = 'UPDATE my_tasks 
                SET complete_flg = :c_flg 
                WHERE search_id = :t_id AND user_id = :u_id AND delete_flg = 0';
      if ($rst['complete_flg']) {//タスクが完了済みの場合未了に
        debug('完了済み');
        $data = array('c_flg' => 0, ':t_id' => $taskId, 'u_id' => $_SESSION['user_id']);
      }else {//タスクが未了の場合完了済みに
        debug('未了');
        $data = array('c_flg' => 1, ':t_id' => $taskId, 'u_id' => $_SESSION['user_id']);
      }
      $stmt = queryPost($dbh, $sql, $data);
    }else {//レコードが存在しない場合(不正なアクセス)
      throw new Exception('不正なアクセス');
    }
  } catch (Exception $e) {
    error_log('エラー発生:'. $e->getMessage());
  }
}
 ?>