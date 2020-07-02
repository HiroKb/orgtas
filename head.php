<?php 
$randNum = mt_rand(0,2);//ランダムカラー・ロゴ用
 ?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title><?php echo $siteTitle; ?> | OrgTas</title>
  <link href="https://fonts.googleapis.com/css?family=M+PLUS+1p&display=swap" rel="stylesheet">
  <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/base/jquery-ui.min.css">
  <link rel="stylesheet" type="text/css" href="css/style.css">
  <style>
    .my-color-border{
      border-left: 30px <?php echoMyColor(); ?> solid;
    }
    .my-color-background{
      background-color: <?php echoMyColor() ?>;
    }
    .rand-color-border{
      border-left: 30px <?php echoRandColor($randNum); ?> solid;
    }
    .rand-color-background{
      background-color: <?php echoRandColor($randNum); ?>;
    }
    <?php if(!empty($groupData)): ?>
    .group-color-border{
      border-left: 30px <?php echoGroupColor();?> solid;
    }
    .group-color-background{
      background-color: <?php echoGroupColor();?>; 
    }
    <?php endif; ?>
  </style>
</head>