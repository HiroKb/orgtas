<?php 
//-------------------------------------------------
//    ログの設定・ヘッダー設定・デバッグ関連
//-------------------------------------------------
ini_set('log_errors','on');
ini_set('error_log','php.log');

header('Content-Type: text/html; charset=UTF-8');

$debug_flg = true;//デバッグする場合はtrue しない場合はfalse
function debug($str){
  global $debug_flg;
  if ($debug_flg) {
    error_log('デバッグ:  '.$str);
  }
}

function debugSession(){
  debug('セッションID:'.session_id());
  debug('セッション変数の中身:'.print_r($_SESSION,true));
}

//-------------------------------------------------
//    セッション関連
//-------------------------------------------------
session_save_path("/var/tmp");
// ガーベジコレクションの削除機能開始を7日後に
ini_set('session.gc_maxlifetime', 60*60*24*7);
//ブラウザを閉じても削除されないようにクッキー自体の有効期限を延ばす
ini_set('session.cookie_lifetime ', 60*60*24*7);
// セッション開始・セッションIDの置き換え
session_start();

//-------------------------------------------------
//    定数・変数
//-------------------------------------------------
// ロゴURL
const LOGOCOLORS = array('img/bluelogo.png', 'img/yellowlogo.png', 'img/redlogo.png'); 
// テーマカラーコード
const COLORCORDS = array('#68CFC3','#FBE481','#E38692');

// エラーメッセージ
const MSG1 = '不正なアクセスです。';
const MSG2 = 'エラー発生。しばらく経ってから再度お試しください。';
const MSG3 = '入力してください。';
const MSG4 = '文字以内で入力してください。';
const MSG5 = '文字以上で入力してください。';
const MSG6 = '半角英数字のみで入力してください。';
const MSG7 = 'パスワード(再入力)が合っていません。';
const MSG8 = '入力内容を確認してください。';
const MSG9 = 'メールアドレスまたはパスワードが違います。';
const MSG10 ='カラーコード形式が誤っています。';

// エラーフラグ兼エラーメッセージ格納用配列
$errs = array();





//-------------------------------------------------
//    関数
//-------------------------------------------------
function sani($str){//サニタイズ
  return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

//---------------DB関係---------------
function dbConnect(){//DB接続関数(DBハンドラを返す)
	// 本番ではenvに切り出す
  $dsn = 'mysql:dbname=orgtas;host=localhost;charaset=uth8';
  $user = 'root';
  $password = 'root';
  $options = array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    PDO::ATTR_EMULATE_PREPARES => false
  );
  $dbh = new PDO($dsn, $user, $password, $options);
  return $dbh;
}

function queryPost($dbh, $sql, $data){//クエリ実行関数
  $stmt = $dbh->prepare($sql);//クエリ作成
  // プレースホルダーに値をセットしクエリ実行
  if (!$stmt->execute($data)) {//クエリ失敗の場合
    debug('クエリー失敗');
    debug('失敗SQL :'. print_r($stmt, true));
    debug('SQLエラー(code)'. $stmt->errorCode());
    debug('SQLエラー(info)'. serialize($stmt->errorInfo()));
    global $errs;
    $errs['common'] = MSG7;
    return false;
  }
  //クエリ成功の場合
  return $stmt;
}

function updateLoginDate($dbh,$id){//最終ログイン時間更新
  try {
    $sql = 'UPDATE users SET login_date = :login_date WHERE search_id = :id';
    $data = array(':login_date' => date('Y-m-d H:i:s'), ':id' => $id);
    queryPost($dbh, $sql, $data);
  } catch (Exception $e) {
    error_log('エラー発生:'. $e->getMessage());
  }
}

function getUser($dbh, $id){
  try {
    $sql = 'SELECT search_id, email, pass, username, introduction, img, color FROM users WHERE search_id = :id AND delete_flg = 0';
    $data = array(':id' => $id);
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      return $stmt->fetch(PDO::FETCH_ASSOC);
    }else{
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生：'. $e->getMessage());
  }
}

function getMyTaskList($dbh, $userId, $status, $priority, $deadline, $currentMinNum = 0, $listSpan = 20){//個人タスクリストを取得
  try {

    // 件数表示
    $sql = 'SELECT count(*) FROM my_tasks WHERE user_id = :userid AND delete_flg = 0';
    switch ($status) {//タクスの状態(完了、未了)で絞り込み
      case '2':
        $sql .= ' AND complete_flg = 0';
        break;
      case '3':
        $sql .= ' AND complete_flg = 1';
        break;
    }
    switch ($priority) {//タスクの重要度で絞り込み
      case '2':
        $sql .= ' AND priority = 1';
        break;
      case '3':
        $sql .= ' AND priority = 2';
        break;
      case '4':
        $sql .= ' AND priority = 3';
        break;
    }
    switch ($deadline) {//期限の情報で絞り込み
      case '4':
        $sql .= ' AND deadline_flg = 0';
        break;
    }
    $data = array(':userid' => $userId);
    $stmt = queryPost($dbh, $sql, $data);
    if (!$stmt) {
      return false;
    }
    $rst['total'] = intval($stmt->fetch(PDO::FETCH_COLUMN));//絞り込み後総タスク数
    $rst['total_page'] = ceil($rst['total'] / $listSpan);//絞り込み後総ページ数



    // タスクデータを取得
    $sql = 'SELECT search_id, title, details, priority, deadline, deadline_flg, complete_flg FROM my_tasks WHERE user_id = :userid AND delete_flg = 0';
    switch ($status) {//タクスの状態(完了、未了)で絞り込み
      case '2':
        $sql .= ' AND complete_flg = 0';
        break;
      case '3':
        $sql .= ' AND complete_flg = 1';
        break;
    }
    switch ($priority) {//タスクの重要度で絞り込み
      case '2':
        $sql .= ' AND priority = 1';
        break;
      case '3':
        $sql .= ' AND priority = 2';
        break;
      case '4':
        $sql .= ' AND priority = 3';
        break;
    }
    switch ($deadline) {//期限の情報で絞り込み
      case NULL:
      case '1':
        $sql .= ' ORDER BY create_date DESC';
        break;
      case '2':
       $sql .= ' ORDER BY deadline IS NULL ASC, deadline ASC';
        break;
      case '3':
       $sql .= ' ORDER BY deadline IS NULL ASC, deadline DESC';
        break;
      case '4':
        $sql .= ' AND deadline_flg = 0';
        break;
    }
    $sql .= ' LIMIT :limit OFFSET :min';
    $data = array(':userid' => $userId, ':limit' => $listSpan, ':min' => $currentMinNum);
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      $rst['data'] = $stmt->fetchAll();
      return $rst;
    }else {
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生：'. $e->getMessage());
  }
}
function getGroupTaskList($dbh, $groupId, $status, $priority, $deadline, $responsible, $currentMinNum = 0, $listSpan = 20){
  try {
    // 件数表示
    $sql = 'SELECT count(*) FROM group_tasks WHERE group_id = :group_id AND delete_flg = 0';
    switch ($status) {//タクスの状態(完了、未了)で絞り込み
      case '2':
        $sql .= ' AND complete_flg = 0';
        break;
      case '3':
        $sql .= ' AND complete_flg = 1';
        break;
    }
    switch ($priority) {//タスクの重要度で絞り込み
      case '2':
        $sql .= ' AND priority = 1';
        break;
      case '3':
        $sql .= ' AND priority = 2';
        break;
      case '4':
        $sql .= ' AND priority = 3';
        break;
    }
    switch ($responsible) {
      case NULL://担当未選択の場合
      case '1':
        break;
      default://担当なし・自分・指定の場合
        $sql .= ' AND responsible_id = :responsible';
        break;
    }
    switch ($deadline) {//期限の情報で絞り込み
      case '4':
        $sql .= ' AND deadline_flg = 0';
        break;
    }

    switch ($responsible) {
      case NULL://担当未選択の場合
      case '1':
        $data = array(':group_id' => $groupId);
        break;
      case '2'://担当なしの場合
        $data = array(':group_id' => $groupId, ':responsible' => 'none');
        break;
      case '3'://担当自分の場合
        $data = array(':group_id' => $groupId, ':responsible' => $_SESSION['user_id']);
        break;
      default:
        $data = array(':group_id' => $groupId, ':responsible' => $responsible);
        break;
    }
    $stmt = queryPost($dbh, $sql, $data);
    if (!$stmt) {
      return false;
    }
    $rst['total'] = intval($stmt->fetch(PDO::FETCH_COLUMN));//絞り込み後総タスク数
    $rst['total_page'] = ceil($rst['total'] / $listSpan);//絞り込み後総ページ数



    // タスクデータを取得
    $sql = 'SELECT g.search_id AS task_id, title, details, priority, deadline, deadline_flg, complete_flg, responsible_id,username, u.delete_flg AS user_delete_flg 
            FROM group_tasks AS g LEFT OUTER JOIN users AS u 
            ON g.responsible_id = u.search_id 
            WHERE g.group_id = :group_id AND g.delete_flg = 0';
    switch ($status) {//タクスの状態(完了、未了)で絞り込み
      case '2':
        $sql .= ' AND complete_flg = 0';
        break;
      case '3':
        $sql .= ' AND complete_flg = 1';
        break;
    }
    switch ($priority) {//タスクの重要度で絞り込み
      case '2':
        $sql .= ' AND priority = 1';
        break;
      case '3':
        $sql .= ' AND priority = 2';
        break;
      case '4':
        $sql .= ' AND priority = 3';
        break;
    }
    switch ($responsible) {
      case NULL://担当未選択の場合
      case '1':
        break;
      default://担当なし・自分・指定の場合
        $sql .= ' AND responsible_id = :responsible';
        break;
    }
    switch ($deadline) {//期限の情報で絞り込み
      case NULL:
      case '1':
        $sql .= ' ORDER BY g.create_date DESC';
        break;
      case '2':
       $sql .= ' ORDER BY deadline IS NULL ASC, deadline ASC';
        break;
      case '3':
       $sql .= ' ORDER BY deadline IS NULL ASC, deadline DESC';
        break;
      case '4':
        $sql .= ' AND deadline_flg = 0';
        break;
    }
    $sql .= ' LIMIT :limit OFFSET :min';
    switch ($responsible) {
      case NULL://担当未選択の場合
      case '1':
        $data = array(':group_id' => $groupId, ':limit' => $listSpan, ':min' => $currentMinNum);
        break;
      case '2'://担当なしの場合
        $data = array(':group_id' => $groupId, ':responsible' => 'none', ':limit' => $listSpan, ':min' => $currentMinNum);
        break;
      case '3'://担当自分の場合
        $data = array(':group_id' => $groupId, ':responsible' => $_SESSION['user_id'], ':limit' => $listSpan, ':min' => $currentMinNum);
        break;
      default:
        $data = array(':group_id' => $groupId, ':responsible' => $responsible, ':limit' => $listSpan, ':min' => $currentMinNum);
        break;
    }
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      $rst['data'] = $stmt->fetchAll();
      return $rst;
    }else {
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生：'. $e->getMessage());
  }
}
function getParticipationGroupTaskList($dbh, $userId, $status, $priority, $deadline, $responsible, $currentMinNum = 0, $listSpan = 20){
  try {
    // 件数表示
    $sql = 'SELECT count(*) 
            FROM group_tasks AS t INNER JOIN participation_users AS p
            ON t.group_id = p.group_id
            WHERE p.user_id = :user_id AND t.delete_flg = 0';
    switch ($status) {//タクスの状態(完了、未了)で絞り込み
      case '2':
        $sql .= ' AND complete_flg = 0';
        break;
      case '3':
        $sql .= ' AND complete_flg = 1';
        break;
    }
    switch ($priority) {//タスクの重要度で絞り込み
      case '2':
        $sql .= ' AND priority = 1';
        break;
      case '3':
        $sql .= ' AND priority = 2';
        break;
      case '4':
        $sql .= ' AND priority = 3';
        break;
    }
    switch ($responsible) {
      case NULL://自分＋担当なしの場合
      case '1':
        $sql .= ' AND (t.responsible_id = :responsible OR t.responsible_id = :none)';
        break;
      case '2'://自分の場合
      case '3'://担当なしの場合
        $sql .= ' AND t.responsible_id = :responsible';
        break;

      default://すべての場合
        break;
    }
    switch ($deadline) {//期限の情報で絞り込み
      case '4':
        $sql .= ' AND deadline_flg = 0';
        break;
    }

    switch ($responsible) {
      case NULL://担当未選択の場合
      case '1':
        $data = array(':user_id' => $userId, ':responsible' => $userId, ':none' => 'none');
        break;
      case '2'://自分のみの場合
        $data = array(':user_id' => $userId, ':responsible' => $userId);
        break;
      case '3'://担当なしの場合
        $data = array(':user_id' => $userId, ':responsible' => 'none');
        break;
      default:
        $data = array(':user_id' => $userId);
        break;
    }
    debug($sql);
    debug(print_r($data,true));
    $stmt = queryPost($dbh, $sql, $data);
    if (!$stmt) {
      return false;
    }
    $rst['total'] = intval($stmt->fetch(PDO::FETCH_COLUMN));//絞り込み後総タスク数
    $rst['total_page'] = ceil($rst['total'] / $listSpan);//絞り込み後総ページ数



    // タスクデータを取得
    $sql = 'SELECT t.search_id AS task_id, t.group_id AS group_id, g.groupname AS group_name, title, details, priority, deadline, deadline_flg, complete_flg, t.responsible_id AS responsible_id, u.username AS responsible_name
            FROM group_tasks AS t 
            INNER JOIN participation_users AS p
            ON t.group_id = p.group_id
            INNER JOIN groups AS g
            ON t.group_id = g.search_id
            LEFT OUTER JOIN users AS u
            ON t.responsible_id = u.search_id
            WHERE p.user_id = :user_id AND t.delete_flg = 0';
    switch ($status) {//タクスの状態(完了、未了)で絞り込み
      case '2':
        $sql .= ' AND complete_flg = 0';
        break;
      case '3':
        $sql .= ' AND complete_flg = 1';
        break;
    }
    switch ($priority) {//タスクの重要度で絞り込み
      case '2':
        $sql .= ' AND priority = 1';
        break;
      case '3':
        $sql .= ' AND priority = 2';
        break;
      case '4':
        $sql .= ' AND priority = 3';
        break;
    }
    switch ($responsible) {
      case NULL://自分・担当なしの場合
      case '1':
        $sql .= ' AND (responsible_id = :responsible OR responsible_id = :none)';
        break;
      case '2'://自分のみの場合
      case '3'://担当なしの場合
        $sql .= ' AND t.responsible_id = :responsible';
        break;
      default://すべての場合
        break;
    }
    switch ($deadline) {//期限の情報で絞り込み
      case NULL:
      case '1':
        $sql .= ' ORDER BY t.create_date DESC';
        break;
      case '2':
       $sql .= ' ORDER BY deadline IS NULL ASC, deadline ASC';
        break;
      case '3':
       $sql .= ' ORDER BY deadline IS NULL ASC, deadline DESC';
        break;
      case '4':
        $sql .= ' AND deadline_flg = 0';
        break;
    }
    $sql .= ' LIMIT :limit OFFSET :min';
    switch ($responsible) {
      case NULL://自分・担当なしの場合
      case '1':
        $data = array(':user_id' => $userId, ':responsible' => $userId, 'none' => 'none', ':limit' => $listSpan, ':min' => $currentMinNum);
        break;
      case '2'://自分のみの場合
        $data = array(':user_id' => $userId, ':responsible' => $userId, ':limit' => $listSpan, ':min' => $currentMinNum);
        break;
      case '3'://担当なしの場合
        $data = array(':user_id' => $userId, ':responsible' => 'none', ':limit' => $listSpan, ':min' => $currentMinNum);
        break;
      default:
        $data = array(':user_id' => $userId, ':limit' => $listSpan, ':min' => $currentMinNum);
        break;
    }
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      $rst['data'] = $stmt->fetchAll();
      return $rst;
    }else {
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生：'. $e->getMessage());
  }
}

function getMyTask($dbh, $taskId){
  try {
    $sql = 'SELECT user_id, title, details, priority, deadline, deadline_flg, complete_flg  
            FROM my_tasks
            WHERE search_id = :id AND user_id = :u_id AND delete_flg = 0';
    $data = array(':id' => $taskId, ':u_id' => $_SESSION['user_id']);
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      return $stmt->fetch(PDO::FETCH_ASSOC);
    }else{
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生：'. $e->getMessage());
  }
}

function getGroupTask($dbh, $searchId, $groupId){
  try {
    $sql = 'SELECT title, details, priority, deadline, deadline_flg, responsible_id, username AS responsibleName, complete_flg
            FROM group_tasks AS g LEFT OUTER JOIN users AS u
            ON g.responsible_id = u.search_id 
            WHERE g.search_id = :search_id AND g.group_id = :group_id';
    $data = array(':search_id' => $searchId, ':group_id' => $groupId);
    $stmt = queryPost($dbh, $sql, $data);
    if ($stmt) {
      return $stmt->fetch(PDO::FETCH_ASSOC);
    }else{
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生：'. $e->getMessage());
  }
}

function getGroupDetail($dbh, $id){
  try {
    $sql = 'SELECT admin_id, groupname, msg, img, color 
            FROM groups 
            WHERE search_id = :search_id AND delete_flg = 0';
    $data = array(':search_id' => $id);
    $stmt = queryPost($dbh, $sql, $data);
    if ($stmt) {
      return $stmt->fetch(PDO::FETCH_ASSOC);
    }else{
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生：'. $e->getMessage());
  }

}


function getAuthority($dbh, $groupId, $userId){
  try {
    $sql = 'SELECT authority 
            FROM participation_users 
            WHERE group_id = :g_id AND user_id = :u_id';
    $data = array(':g_id' => $groupId, ':u_id' => $userId);
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      return $stmt->fetch(PDO::FETCH_COLUMN);//authority 1=監理者 2=副管理者 3=一般
    }else {
      return false;
    }
  } catch (Exception $e) {
      error_log('エラー発生:' . $e->getMessage());
  }
}

function getParticipationStatus($dbh, $groupId, $userId){
  try {
    $sql = 'SELECT count(*) FROM participation_users WHERE group_id = :g_id AND user_id = :u_id';
    $data = array(':g_id' => $groupId, 'u_id' => $userId);
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      return $stmt->fetchColumn();
    }else{
      return 'err';
    }
  } catch (Exception $e) {
      error_log('エラー発生:' . $e->getMessage());
  }
}

function getPendingStatus($dbh, $groupId, $userId){
  try {
    $sql = 'SELECT count(*) FROM pending_users WHERE group_id = :g_id AND user_id = :u_id';
    $data = array(':g_id' => $groupId, 'u_id' => $userId);
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      return $stmt->fetchColumn();
    }else{
      return 'err';
    }
  } catch (Exception $e) {
      error_log('エラー発生:' . $e->getMessage());
  }
}

function getPendingUsersList($dbh, $groupId, $currentMinNum, $listSpan = 20){
  try {
    $sql = 'SELECT count(*) 
            FROM pending_users AS p INNER JOIN users AS u 
            ON p.user_id = u.search_id
            WHERE p.group_id = :g_id AND u.delete_flg = 0';
    $data = array(':g_id' => $groupId);
    $stmt = queryPost($dbh, $sql, $data);
    if (!$stmt) {
      return false;
    }

    $rst['total'] = intval($stmt->fetch(PDO::FETCH_COLUMN));//総ユーザー数
    $rst['total_page'] = ceil($rst['total'] / $listSpan);//総ページ数


    $sql = 'SELECT u.search_id AS u_id, u.username AS username, u.introduction AS introduction, u.img AS img, u.color AS color
            FROM pending_users AS p INNER JOIN users AS u 
            ON p.user_id = u.search_id
            WHERE p.group_id = :g_id AND u.delete_flg = 0
            ORDER BY p.create_date DESC
            LIMIT :limit OFFSET :min';
    $data = array(':g_id' => $groupId ,':limit' => $listSpan, ':min' => $currentMinNum);
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      $rst['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $rst;
    }else{
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
  }
}

function getGroupMemberList($dbh, $groupId, $currentMinNum, $listSpan = 20){
  try {
    $sql = 'SELECT count(*) 
            FROM participation_users AS p INNER JOIN users AS u 
            ON p.user_id = u.search_id
            WHERE p.group_id = :g_id AND u.delete_flg = 0';
    $data = array(':g_id' => $groupId);
    $stmt = queryPost($dbh, $sql, $data);
    if (!$stmt) {
      return false;
    }

    $rst['total'] = intval($stmt->fetch(PDO::FETCH_COLUMN));//総ユーザー数
    $rst['total_page'] = ceil($rst['total'] / $listSpan);//総ページ数


    $sql = 'SELECT p.authority AS authority ,u.search_id AS u_id, u.username AS username, u.introduction AS introduction, u.img AS img, u.color AS color
            FROM participation_users AS p INNER JOIN users AS u 
            ON p.user_id = u.search_id
            WHERE p.group_id = :g_id AND u.delete_flg = 0
            ORDER BY p.authority ASC, p.create_date ASC
            LIMIT :limit OFFSET :min';
    $data = array(':g_id' => $groupId ,':limit' => $listSpan, ':min' => $currentMinNum);
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      $rst['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $rst;
    }else{
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
  }
}
function getGroupMember($dbh, $groupId){
  try {
    $sql = 'SELECT p.authority AS authority ,u.search_id AS u_id, u.username AS username, u.introduction AS introduction, u.img AS img, u.color AS color
            FROM participation_users AS p INNER JOIN users AS u 
            ON p.user_id = u.search_id
            WHERE p.group_id = :g_id AND u.delete_flg = 0
            ORDER BY p.authority ASC, p.create_date ASC';
    $data = array(':g_id' => $groupId);
    $stmt = queryPost($dbh, $sql, $data);

    if ($stmt) {
      return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }else{
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
  }
}

function getChatMessages($dbh, $groupId){
  try {
    $sql = 'SELECT user_id, username, img, color, message, m.create_date AS post_date
            FROM chat_messages AS m INNER JOIN users AS u 
            ON m.user_id = u.search_id 
            WHERE group_id = :g_id
            ORDER BY m.create_date ASC';
    $data = array(':g_id' => $groupId);
    $stmt = queryPost($dbh, $sql, $data);
    
    if ($stmt) {
      return $stmt->fetchALL(PDO::FETCH_ASSOC);
    }else{
      return false;
    }
  } catch (\Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
  }
}

function getAdminGroupList($dbh, $userId, $currentPageNum, $listSpan = 5){
  try {
    //参加グループを取得
    $sql = 'SELECT count(*) 
            FROM participation_users AS p INNER JOIN groups AS g 
            ON p.group_id = g.search_id
            WHERE p.user_id = :u_id AND p.authority = 1 AND g.delete_flg = 0';
    $data = array(':u_id' => $userId);
    $stmt = queryPost($dbh, $sql, $data);
    if (!$stmt) {
      return false;
    }

    $rst['total'] = intval($stmt->fetch(PDO::FETCH_COLUMN));//総ユーザー数
    $rst['total_page'] = ceil($rst['total'] / $listSpan);//総ページ数

    $sql = 'SELECT p.authority AS authority, g.search_id AS id, g.groupname AS groupname, g.img AS img, g.color AS color
            FROM participation_users AS p INNER JOIN groups AS g 
            ON p.group_id = g.search_id 
            WHERE p.user_id = :u_id AND p.authority = 1 AND g.delete_flg = 0 
            ORDER BY p.create_date ASC 
            LIMIT :limit OFFSET :min';
    $data = array(':u_id' => $userId, 
                  ':limit' => $listSpan, 
                  ':min' => ($currentPageNum - 1) * 5);
    $stmt = queryPost($dbh, $sql, $data);
    
    if ($stmt) {
      $rst['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $rst;
    }else{
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
  }
}
function getParticipationGroupList($dbh, $userId, $currentPageNum, $listSpan = 5){
  try {
    $sql = 'SELECT count(*) 
            FROM participation_users AS p INNER JOIN groups AS g 
            ON p.group_id = g.search_id
            WHERE p.user_id = :u_id AND (p.authority = 2 OR p.authority = 3) AND g.delete_flg = 0';
    $data = array(':u_id' => $userId);
    $stmt = queryPost($dbh, $sql, $data);
    if (!$stmt) {
      return false;
    }

    $rst['total'] = intval($stmt->fetch(PDO::FETCH_COLUMN));//総ユーザー数
    $rst['total_page'] = ceil($rst['total'] / $listSpan);//総ページ数

    //参加グループを取得
    $sql = 'SELECT p.authority AS authority, g.search_id AS id, g.groupname AS groupname, g.img AS img, g.color AS color
            FROM participation_users AS p INNER JOIN groups AS g 
            ON p.group_id = g.search_id 
            WHERE p.user_id = :u_id AND (p.authority = 2 OR p.authority = 3) AND g.delete_flg = 0 
            ORDER BY p.create_date ASC
            LIMIT :limit OFFSET :min';
    $data = array(':u_id' => $userId, 
                  ':limit' => $listSpan, 
                  ':min' => ($currentPageNum - 1) * 5);
    $stmt = queryPost($dbh, $sql, $data);
    
    if ($stmt) {
      $rst['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
      return $rst;
    }else{
      return false;
    }
  } catch (Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
  }
  
}

//---------------バリデーション---------------
function validRequired($str, $key){//未入力チェック
  if ($str === '') {//空文字(未入力)だった場合エラー処理
    global $errs;
    $errs[$key] = MSG3;
  }
}
function validMaxLen($str, $key, $maxLen = 255){//最大文字数チェック
  if (mb_strlen($str) > $maxLen) {
    global $errs;
    $errs[$key] = $maxLen.MSG4;
  }
}
function validMinLen($str, $key, $minLen = 8){//最大文字数チェック
  if (mb_strlen($str) < $minLen) {
    global $errs;
    $errs[$key] = $minLen.MSG5;
  }
}
function validHalf($str, $key){//半角英数字チェック
  if (!preg_match("/^[a-zA-Z0-9]+$/", $str)) {
    global $errs;
    $errs[$key] = MSG6;
  }
}
function validEqual($str1, $str2, $key){//同値チェック
  if ($str1 !== $str2) {
    global $errs;
    $errs[$key] = MSG7;
  }
}
function validEmail($str, $key){//email形式チェック
  if (false === filter_var($str, FILTER_VALIDATE_EMAIL)) {
    global $errs;
    $errs[$key] = MSG8;
  }
}
function validColorCode($str,$key){
  if (!preg_match("/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/", $str)) {
    global $errs;
    $errs[$key] = MSG10;
  }
}
function validEmailDup($email, $key){//email重複チェック
  global $errs;
  try {
    $dbh = dbConnect();//DBハンドラ取得

    // SQL文
    $sql = 'SELECT count(*) FROM users WHERE email = :email AND delete_flg = 0';
    $data = array(':email' => $email);

    $stmt = queryPost($dbh, $sql, $data);
    $result = $stmt->fetchColumn();
    if ($result) {//検索結果が1件でもあれば重複している
      $errs[$key] = MSG8;
    }
  } catch (Exception $e) {
    error_log('エラー発生:' . $e->getMessage());
    $errs['common'] = MSG2;
  }
}
function validLowrCase($str, $length = 12){//半角小文字チェック
  if (preg_match("/^[a-z0-9]{".$length."}$/", $str)) {
    return true;
  }else{
    return false;
  }
}
function validDeadLineFlg($deadline_flg, $key){//期限形式チェック
  if (!in_array($deadline_flg, array('on', 'off'), true)) {
    global $errs;
    $errs[$key] = MSG8;
  }
}
function validIn($str, $key, $array = array('1', '2', '3')){
  if (!in_array($str, $array, true)) {
    global $errs;
    $errs[$key] = MSG8;
  }
}
function validDeadLine($str,$key){
  if (!preg_match("/^([1-9][0-9]{3})\-(0[1-9]{1}|1[0-2]{1})\-(0[1-9]{1}|[1-2]{1}[0-9]{1}|3[0-1]{1})$/", $str)) {
    global $errs;
    $errs[$key] = MSG8;
  }
}

//---------------トークン関係---------------
function generateToken(){//トークンが生成されていなければ生成する
  global $token;
  $token = bin2hex(random_bytes(24));
  $_SESSION['token'] = $token;
}
function checkToken(){
  global $errs;
  $checktoken = filter_input(INPUT_POST, 'token');
  if (empty($_SESSION['token']) || $checktoken !== $_SESSION['token']) {
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }
}

//---------------データ取得・表示関係---------------
function echoLogoUrl($num){//ヘッダー内ロゴをランダム取得
  echo LOGOCOLORS[$num];
}

function echoRandColor($num){//カラーコードを3種からランダム取得
  echo COLORCORDS[$num];
}

function echoMyColor(){//ユーザーカラー取得
  if (!empty($_SESSION['color'])) {
    echo sani($_SESSION['color']);
  }else{
    echo '#666666';
  }
}
function echoGroupColor(){//グループカラー
  global $groupData;
  if(!empty($groupData['color'])){
    echo sani($groupData['color']);
  }else{
    echo '#666666';
  }
}

function echoTaskBorder($priority){
  switch ($priority) {
    case 1:
      echo 'red-border';
      break;
    case 2:
      echo 'yellow-border';
      break;
    case 3:
      echo 'blue-border';
      break;
    default:
      echo 'red-border';
      break;
  }
}

function echoTaskBackground($priority){
  switch ($priority) {
    case 1:
      echo 'red-bg';
      break;
    case 2:
      echo 'yellow-bg';
      break;
    case 3:
      echo 'blue-bg';
      break;
    default:
      echo 'red-bg';
      break;
  }
}

function echoErrMsg($key){//エラーがあればエラーメッセージを表示
  global $errs;
  if (!empty($errs[$key])) {
    echo '<p class="err-msg">※'. sani($errs[$key]) . '</p>';
  }
}


function getFormData($key, $data = false, $method = true){//POST・GET・DBデータ取得
  if($method){//デフォルトではPOST
    $method = $_POST;
  }else{//引数がfalseの場合GET
    $method = $_GET;
  }
  global $errs;
  if (!empty($data)) {//DBデータがある場合
    if (!empty($errs[$key])) {//フォームにエラーがある場合
      if (isset($method[$key])) {//POST・GET情報がある場合
        return sani($method[$key]);
      }else {//POST・GET情報がない場合（エラーが発生しているので基本ありえない
        return sani($data[$key]);
      }

    }else{//フォームにエラーがない場合
      if (isset($method[$key]) && $method[$key] !== $data[$key]) {//POST・GET情報があり、DBデータと異なる場合
        return sani($method[$key]);
      }else{
        return sani($data[$key]);
      }
    }

  }elseif(isset($method[$key])) {//DBデータがなくPOST・GET情報がある場合
    return sani($method[$key]);
  }
}

function getImg($data){
  if (!empty($data)) {
    echo 'uploads/' . sani($data);
  }else{
    echo 'img/noimg.jpg';
  }
}

//---------------画像アップロード---------------
function updateImg($dbh, $file, $key, $saveTable){
  debug('画像アップロード処理・FILE情報'.print_r($file,true));

  if (isset($file['error']) && is_int($file['error'])) {//ファイルが存在していれば
    try {
      switch ($file['error']) {//ファイルのバリデーション
        case UPLOAD_ERR_OK://問題なし
          break;
        case UPLOAD_ERR_NO_FILE://ファイル未選択
          throw new RuntimeException('ファイルが選択されていません');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
        case $file['size'] > 512000;//フアイルサイズが大きい場合
          throw new RuntimeException('500KB以下の画像を選択してください。');
        default://何かしらのエラーが起きた場合
        throw new RuntimeException(MSG2);
      }

      // ファイル形式のチェック
      $type = @exif_imagetype($file['tmp_name']);
      if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        throw new RuntimeException('jpeg(jpg),png形式の画像を選択してください');
      }

      if ($saveTable === 'users') {//ユーザーテーブルに保存する場合のファイル名生成・重複チェック
        $fileName = 'user'. makeRandStr(40) . image_type_to_extension($type);//ファイル名を生成
        //ファイル名重複チェック
        $sql = 'SELECT count(*) FROM users WHERE img = :img';
        $data = array(':img' => $fileName);
        while (true) {
          $stmt = queryPost($dbh, $sql, $data);
          $result = $stmt->fetchColumn();
          if (!$result) {
            break;
          }
          $fileName = 'user'. makeRandStr(40) . image_type_to_extension($type);
        }
      }elseif ($saveTable === 'groups') {//グループテーブルに保存する場合のファイル名生成・重複チェック
        $fileName = 'group'. makeRandStr(40) . image_type_to_extension($type);//ファイル名を生成
        //ファイル名重複チェック
        $sql = 'SELECT count(*) FROM groups WHERE img = :img';
        $data = array(':img' => $fileName);
        while (true) {
          $stmt = queryPost($dbh, $sql, $data);
          $result = $stmt->fetchColumn();
          if (!$result) {
            break;
          }
          $fileName = 'user'. makeRandStr(40) . image_type_to_extension($type);
        }
      }
      $uploadPath = 'uploads/' . $fileName;//格納パスを組み立て

      //ファイルをuploadsフォルダに移動・失敗した場合エラーをスローする
      if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new RuntimeException(MSG2);
      }

      chmod($uploadPath, 0644);//パーミッションを変更

      debug('ファイルを正常にアップロード' . $uploadPath);
      return $fileName;

    } catch (\Exception $e) {
      debug($e->getMessage());
      global $errs;
      $errs[$key] = $e->getMessage();
    }
  }
}

//---------------その他---------------
function loginProcessing($sesLimit, $userId, $color){//ログイン処理
  $_SESSION['login_date'] = time();//最終ログイン時間
  $_SESSION['login_limit'] = $sesLimit;//ログイン有効期限
  $_SESSION['user_id'] = $userId;
  $_SESSION['color'] = $color;
  session_regenerate_id(true);
}
function logoutProcessing($location = 'index.php'){//ログアウト処理
  $_SESSION = array();
  if (isset($_COOKIE["PHPSESSID"])) {
    setcookie("PHPSESSID", '', time() - 1800, '/');
  }
  session_destroy();
  header("Location:" . $location);
  exit();
}
function makeRandStr($length = 12){//0-9,a-fを使ったランダムな文字列を生成
  return substr(bin2hex(random_bytes($length)), 0, $length);
}

function isLogin(){//ログイン認証(true,false返却)
  if (!empty($_SESSION['login_date'])) {// ログインしている場合
    if ($_SESSION['login_date'] + $_SESSION['login_limit'] < time()) {//ログイン有効期限切れ
      // セッション削除（ログアウト）
      session_destroy();
      return false;
    }else {//ログイン有効期限
      return true;
    }
  }else{//ログインしていない場合
    return false;
  }
}

//ページネーション
// $currentPageNum : 現在のページ数
// $totalPageNum : 総ページ数
// $getPara : 検索用getパラメータ
// $pageColNum : 表示ページネーション数
// $minPageNum : 最小ページネーション数
// $maxPageNum : 最大ページネーション数
function pagination($currentPageNum, $totalPageNum, $getPara, $backgroundType = 'my', $pageColNum = 5){
  //---------------総ページ数が表示数以上---------------
  if ($totalPageNum >= $pageColNum) {
    if ($currentPageNum === $totalPageNum) {//現在のページが総ページと同じ
      //現在のページと左に4つ
      $minPageNum = $currentPageNum - 4;
      $maxPageNum = $currentPageNum;
    }elseif ($currentPageNum === ($totalPageNum - 1)) {//現在のページが総ページの1つ前
      // 左に3つ、現在のページ、右に１つ
      $minPageNum = $currentPageNum - 3;
      $maxPageNum = $currentPageNum + 1;
    }elseif ($currentPageNum === 1 || $currentPageNum === 2) {//現在のページが1ページ目か2ページ目の場合
      $minPageNum = 1;
      $maxPageNum = 5;
    }else{//上記以外
      $minPageNum = $currentPageNum - 2;
      $maxPageNum = $currentPageNum + 2;
    }
    //---------------総ページ数が表示数未満---------------
  }else {
    $minPageNum = 1;
    $maxPageNum = $totalPageNum;
  }

  //ページネーション表示処理
  if ($totalPageNum >= 2) {//総ページ数が2ページ以上の場合
    if ($backgroundType === 'my') {
      $backgroundClass = 'my-color-background';
    }elseif ($backgroundType === 'group') {
      $backgroundClass = 'group-color-background';
    }
    
    echo '<ul class="pagination">';
    if ($currentPageNum != 1) {
      echo ' <li><a class="'. $backgroundClass .'" href="?p=1'. sani($getPara) .'">&lt;</a></li>';
    }
    for ($i=$minPageNum; $i <= $maxPageNum ; $i++) { 
    echo ' <li><a class="' . $backgroundClass;
    if ($currentPageNum === $i) echo ' active-pagination';
    echo '" href="?p='. $i . $getPara .'">'.$i.'</a></li>';
    }

    if ($currentPageNum != $maxPageNum) {
      echo ' <li><a class="'. $backgroundClass .'" href="?p='. sani($maxPageNum) . sani($getPara) .'">&gt;</a></li>';
    }
    echo '</ul>';
  }
}

function mypagePagination($adminPageNum, $adminTotalPageNum, $participationPageNum, $participationTotalPageNum, $pageType, $backgroundType = 'my', $pageColNum = 5){
  if ($pageType === 'admin') {
  //---------------総ページ数が表示数以上---------------
    if ($adminTotalPageNum >= $pageColNum) {
      if ($adminPageNum === $adminTotalPageNum) {//現在のページが総ページと同じ
        //現在のページと左に4つ
        $minPageNum = $adminPageNum - 4;
        $maxPageNum = $adminPageNum;
      }elseif ($adminPageNum === ($adminTotalPageNum - 1)) {//現在のページが総ページの1つ前
        // 左に3つ、現在のページ、右に１つ
        $minPageNum = $adminPageNum - 3;
        $maxPageNum = $adminPageNum + 1;
      }elseif ($adminPageNum === 1 || $adminPageNum === 2) {//現在のページが1ページ目か2ページ目の場合
        $minPageNum = 1;
        $maxPageNum = 5;
      }else{//上記以外
        $minPageNum = $adminPageNum - 2;
        $maxPageNum = $adminPageNum + 2;
      }
      //---------------総ページ数が表示数未満---------------
    }else {
      $minPageNum = 1;
      $maxPageNum = $adminTotalPageNum;
    }

    //ページネーション表示処理
    if ($adminTotalPageNum >= 2) {//総ページ数が2ページ以上の場合
      
      echo '<ul class="pagination">';
      if ($adminPageNum != 1) {
        echo ' <li><a class="my-color-background" href="?a_p=1&p_p='. $participationPageNum .'">&lt;</a></li>';
      }
      for ($i=$minPageNum; $i <= $maxPageNum ; $i++) { 
      echo ' <li><a class="my-color-background' ;
      if ($adminPageNum === $i) echo ' active-pagination';
      echo '" href="?a_p='. $i .'&p_p='. $participationPageNum .'">'.$i.'</a></li>';
      }

      if ($adminPageNum != $maxPageNum) {
        echo ' <li><a class="my-color-background" href="?a_p='. $maxPageNum . '&p_p='. $participationPageNum .'">&gt;</a></li>';
      }
      echo '</ul>';
    }
  }elseif ($pageType === 'participation') {
  //---------------総ページ数が表示数以上---------------
    if ($participationPageNum >= $pageColNum) {
      if ($participationPageNum === $participationTotalPageNum) {//現在のページが総ページと同じ
        //現在のページと左に4つ
        $minPageNum = $participationPageNum - 4;
        $maxPageNum = $participationPageNum;
      }elseif ($participationPageNum === ($participationTotalPageNum - 1)) {//現在のページが総ページの1つ前
        // 左に3つ、現在のページ、右に１つ
        $minPageNum = $participationPageNum - 3;
        $maxPageNum = $participationPageNum + 1;
      }elseif ($participationPageNum === 1 || $participationPageNum === 2) {//現在のページが1ページ目か2ページ目の場合
        $minPageNum = 1;
        $maxPageNum = 5;
      }else{//上記以外
        $minPageNum = $participationPageNum - 2;
        $maxPageNum = $participationPageNum + 2;
      }
      //---------------総ページ数が表示数未満---------------
    }else {
      $minPageNum = 1;
      $maxPageNum = $participationTotalPageNum;
    }

    debug($participationTotalPageNum);
    debug($minPageNum);
    debug($maxPageNum);
    //ページネーション表示処理
    if ($participationTotalPageNum >= 2) {//総ページ数が2ページ以上の場合
      
      echo '<ul class="pagination">';
      if ($participationPageNum != 1) {
        echo ' <li><a class="my-color-background" href="?a_p='.$adminPageNum.'&p_p=1">&lt;</a></li>';
      }
      for ($i=$minPageNum; $i <= $maxPageNum ; $i++) { 
      echo ' <li><a class="my-color-background' ;
      if ($participationPageNum === $i) echo ' active-pagination';
      echo '" href="?a_p='. $adminPageNum .'&p_p='. $i .'">'.$i.'</a></li>';
      }

      if ($participationPageNum != $maxPageNum) {
        echo ' <li><a class="my-color-background" href="?a_p='. $adminPageNum . '&p_p='. $maxPageNum .'">&gt;</a></li>';
      }
      echo '</ul>';
    }
  }
}