<?php 
require_once('function.php');
require_once('auth.php');
$userId = $_SESSION['user_id'];

try {
  $dbh = dbConnect();
} catch (\Exception $e) {
  error_log('エラー発生:' . $e->getMessage());
  header("Location:unauthorized.php");
  exit();
}
$adminPageNum = !empty($_GET['a_p']) ? intval($_GET['a_p']) : 1;//管理グループのページ数（現在のページ)
$participationPageNum = !empty($_GET['p_p']) ? intval($_GET['p_p']) : 1;//参加グループのページ（現在のページ)
$adminGroupList = getAdminGroupList($dbh, $userId, $adminPageNum);
$participationGroupList = getParticipationGroupList($dbh, $userId, $participationPageNum);
debug(print_r($adminGroupList, true));
debug(print_r($participationGroupList, true));
 ?>
<?php 
//---------------HTML部分---------------
$siteTitle = 'マイページ';
$bgType = 'my-color-background';
require_once('head.php');
 ?>
<body class="mypage">
<?php 
// ヘッダー呼び出し
require_once('header.php');
 ?>
  <!-- コンテンツ -->
  <main>
    <div class="site-width">
      <div class="left-container">
        <section class="admin-group-wrap">
          <h2>管理グループ</h2>
          <ul class="group-list">
            <?php if(!empty($adminGroupList['data'])) : ?>
              <?php foreach($adminGroupList['data'] as $key => $val): ?>
            <li>
              <a class="group-wrap" href="group.php?g_id=<?php echo $val['id']?>" style="border-left:solid 20px <?php echo sani($val['color'])?>">
                <img src="<?php getImg($val['img'])?>" alt="">
                <p><?php echo mb_strimwidth(sani($val['groupname']), 0, 40, "...", 'UTF-8') ?></p>
              </a>
            </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
          <?php mypagePagination($adminPageNum, $adminGroupList['total_page'], $participationPageNum, $participationGroupList['total_page'], 'admin') ?>
        </section>

        <section class="general-group-wrap">
          <h2>参加グループ</h2>
          <ul class="group-list">
            <?php if(!empty($participationGroupList['data'])) : ?>
              <?php foreach($participationGroupList['data'] as $key => $val): ?>
            <li>
              <a class="group-wrap" href="group.php?g_id=<?php echo $val['id']?>" style="border-left:solid 20px <?php echo sani($val['color'])?>">
                <img src="<?php getImg($val['img'])?>" alt="">
                <p><?php echo mb_strimwidth(sani($val['groupname']), 0, 40, "...", 'UTF-8') ?></p>
              </a>
            </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
          <?php mypagePagination($adminPageNum, $adminGroupList['total_page'], $participationPageNum, $participationGroupList['total_page'], 'participation') ?>
        </section>
      </div>
      <div class="right-container">
        <ul class="mypage-menu my-color-border">
          <li><a href="myTaskList.php">マイタスク一覧</a></li>
          <li><a href="groupTaskList.php">グループタスク一覧</a></li>
          <li><a href="createGroup.php">グループ作成</a></li>
          <li><a href="searchGroup.php">グループ検索</a></li>
          <li><a href="profile.php">プロフィール</a></li>
          <li><a href="emailEdit.php">メールアドレス変更</a></li>
          <li><a href="passEdit.php">パスワード変更</a></li>
          <li><a href="">退会</a></li>
        </ul>
      </div>
    </div>
  </main>

<?php 
require_once('footer.php');