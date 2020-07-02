  <!-- ヘッダー -->
  <header class="<?php echo $bgType?>">
    <div class="site-width">
      <h1 class="header-left">
        <a href="index.php"><img src="<?php echoLogoUrl($randNum); ?>" alt=""><span>OrgTas</span></a>
      </h1>
      <nav class="header-right">
        <ul>
        <?php if(empty($_SESSION['user_id'])) :?>
          <li><a href="login.php">ログイン</a></li>
          <li><a href="signup.php">新規登録</a></li>
        <?php else : ?>
          <li><a href="taskList.php">タスク一覧</a></li>
          <li><a href="mypage.php">マイページ</a></li>
          <li><a href="logout.php">ログアウト</a></li>
        <?php endif; ?>
        </ul>
      </nav>
    </div>
  </header>