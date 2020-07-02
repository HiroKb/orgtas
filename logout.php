<?php 
require_once('function.php');
debug('-------------------------ログアウトページ---------------------');

logoutProcessing();//ログアウト処理




if (!empty($_POST)) {//post送信されていた場合
  checkToken();//正規アクセスか確認

  if (empty($errs)) {//正規アクセスだった場合
  }else{//不正アクセスだった場合
    error_log('不正なアクセス  IP:' . $_SERVER['REMOTE_ADDR']);
    header("Location:unauthorized.php");
    exit();
  }
}