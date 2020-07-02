<?php 
//-------------------------------------------------
//    ログイン認可・自動ログアウト
//-------------------------------------------------
$accessFile = basename($_SERVER['SCRIPT_NAME']);//アクセスファイル名
const NOTLOGINFILES = array('index.php', 'signup.php', 'login.php');//未ログイン閲覧可ファイル

if (!empty($_SESSION['login_date']) && !empty($_SESSION['login_limit']) && !empty($_SESSION['user_id']) && !empty($_SESSION['color'])) {//ログインしている場合

  if (($_SESSION['login_date'] + $_SESSION['login_limit']) < time()) {//ログイン有効期限オーバーの場合
    logoutProcessing('login.php');//ログアウト処理を行いログインページへ

  }else {//ログイン有効期限内の場合
    if (time() > $_SESSION['login_date'] + 1200) {//前回のアクセスから20分以上経過していた場合最終ログイン時間を更新しセッションIDを再生成
      $dbh = dbConnect();
      updateLoginDate($dbh, $_SESSION['user_id']);
      session_regenerate_id(true);
    }
    $_SESSION['login_date'] = time();

    if (in_array($accessFile, NOTLOGINFILES, true)) {//トップ・新規登録・ログインページの場合
      header("Location:taskList.php");
      exit();
    }
  }

}else{//ログインしていない場合
  if (!in_array($accessFile, NOTLOGINFILES, true)) {//トップ・新規登録・ログインページ以外の場合
    header("Location:index.php");
    exit();
  }
}