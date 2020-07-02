$(function(){

  // 画像ライブプレビュー
  let $imgFile = $('#img');
  $imgFile.on('change', function(e) {
    let file = this.files[0]
    let $img = $(this).siblings('img');
    let fileReader = new FileReader();

    fileReader.readAsDataURL(file);

    fileReader.onload = function(event) {
      $img.attr('src', event.target.result).show();
    };
  });

//-------------------------------------------------
//    Ajax
//-------------------------------------------------
  $('.myTaskList .task-complete-btn').click(function(){//完了ボタンが押されたらAjax処理(myTaskList)
    let $this = $(this);
    let myTaskId = $(this).parents('li').data('taskid') || null;
    if (myTaskId !== undefined && myTaskId !== null) {
      $.ajax({
        type: "POST",
        url: "ajaxMyTaskSuccess.php",
        data: { taskId : myTaskId}
      }).done(function(data) {
        console.log('Ajax ok');
        $this.toggleClass('success-color');
      }).fail(function(data) {
        console.log('Ajax no');
      });
    }
    return false;
  });
  $('.myTask .mytask-complete-btn').click(function(){//完了ボタンが押されたらAjax処理(myTask)
    let $this = $(this);
    let myTaskId = $(this).data('taskid') || null;
    if (myTaskId !== undefined && myTaskId !== null) {
      $.ajax({
        type: "POST",
        url: "ajaxMyTaskSuccess.php",
        data: { taskId : myTaskId}
      }).done(function(data) {
        console.log('Ajax ok');
        $this.toggleClass('success-color');
      }).fail(function(data) {
        console.log('Ajax no');
      });
    }
    return false;
  });

  $('.group .task-complete-btn').click(function(){//完了ボタンが押されたらAjax処理(groupTaskList)
    let $this = $(this);
    let groupTaskId = $(this).parents('li').data('taskid') || null;
    if (groupTaskId !== undefined && groupTaskId !== null) {
      $.ajax({
        type: "POST",
        url: "ajaxGroupTaskSuccess.php",
        data: { taskId : groupTaskId},
      }).then(
        function (data) {
          $this.toggleClass('success-color');
        },
        function (data) {
        });
    }
    return false;
  });

  $('.groupTask .task-complete-btn').click(function(){//完了ボタンが押されたらAjax処理(groupTaskList)
    console.log('test');
    
    let $this = $(this);
    let groupTaskId = $(this).data('taskid') || null;
    if (groupTaskId !== undefined && groupTaskId !== null) {
      $.ajax({
        type: "POST",
        url: "ajaxGroupTaskSuccess.php",
        data: { taskId : groupTaskId},
      }).then(
        function (data) {
          $this.toggleClass('success-color');
        },
        function (data) {
        });
    }
    return false;
  });

//-------------------------------------------------
//    モーダル
//-------------------------------------------------

  $('.add-task-btn').click(function () {
    $('#add-task-modal').fadeIn();
  });
  $('.modal-close').click(function () {
    $('.modal-wrap').fadeOut();
  });
  $('.batch-deletion-btn').click(function() {
    $('#batch-deletion-modal').fadeIn();
  })
  $('.myTask .mytask-delete-btn').click(function(){
    $('.myTask #delete-task-modal').fadeIn();
  })
  $('.groupTask .grouptask-delete-btn').click(function () {
    $('.groupTask #delete-task-modal').fadeIn();
  })

  //--------------DOM書き換え----------------
  //マイタスク削除ボタンを押されたときのモーダル表示処理(myTaskList)
  $('.myTaskList .task-delete-btn').click(function(){
    // データとDOMを取得・定義
    let myTaskId = $(this).parents('li').data('taskid') || null;
    let myTaskTitle = $(this).parents('li').data('title') || null;
    let myTaskDetail = $(this).parents('li').data('detail') || null;
    let myTaskDeadline = $(this).parents('li').data('deadline') || null;
    let myTaskPriority = $(this).parents('li').data('priority') || null;
    let $modal = $('#delete-task-modal .modal');
    let $deleteForm = $('#delete-task-modal form');
    let $tokenDom = $('#delete-task-modal [name="token"]');


    let bdClass,
        bgClass;
    if (myTaskPriority === 1) {
      bdClass = 'red-border';
      bgClass = 'red-bg';
    }else if(myTaskPriority === 2){
      bdClass = 'yellow-border';
      bgClass = 'yellow-bg';
    }else{
      bdClass = 'blue-border';
      bgClass = 'blue-bg';
    }

    // ボーダークラスを一度削除後タスクの重要度によってクラスを追加
    $modal.removeClass('red-border');
    $modal.removeClass('yellow-border');
    $modal.removeClass('blue-border');
    $modal.addClass(bdClass);

    $deleteForm.empty();//deleteフォーム内のDOMを全て削除

    if (myTaskDeadline) {//期限があれば期限付きDOMを追加
      $deleteForm.append('<p class="delete-task-deadline">' + myTaskDeadline + 'まで</p>');
    }else{//期限がなければ期限なしDOMを追加
      $deleteForm.append('<p class="delete-task-deadline">期限なし</p>');
    }

    $deleteForm.append('<p class="delete-task-title">' + myTaskTitle + '</p>');//タスクタイトルDOMを追加

    if (myTaskDetail) {//タスク詳細があればDOMを追加
      $deleteForm.append('<p class="delete-task-detail">' + myTaskDetail + '</p>');
    }
    $deleteForm.append('<input type="hidden" name="post-type" value="delete_task"></input>');//POST内容識別用hiddenDOMを追加
    $deleteForm.append('<input type="hidden" name="task-id" value="' + myTaskId + '"></input>');//タスクIDDOMを追加
    $deleteForm.append($tokenDom);//トークンDOMを追加

    $deleteForm.append('<button class="'+ bgClass +'">削除</button>');//送信ボタンを追加

    $('#delete-task-modal').fadeIn();
    return false;
  });

  //グループタスク削除ボタンを押されたときのモーダル表示処理(group)
  $('.group .task-delete-btn').click(function () {
    // データとDOMを取得・定義
    let groupTaskId = $(this).parents('li').data('taskid') || null;
    let groupTaskTitle = $(this).parents('li').data('title') || null;
    let groupTaskDetail = $(this).parents('li').data('detail') || null;
    let groupTaskDeadline = $(this).parents('li').data('deadline') || null;
    let groupTaskResponsible = $(this).parents('li').data('responsible') || null;
    let groupTaskPriority = $(this).parents('li').data('priority') || null;
    let $modal = $('#delete-task-modal .modal');
    let $deleteForm = $('#delete-task-modal form');
    let $tokenDom = $('#delete-task-modal [name="token"]');


    let bdClass,
        bgClass;
    if (groupTaskPriority === 1) {
      bdClass = 'red-border';
      bgClass = 'red-bg';
    }else if(groupTaskPriority === 2){
      bdClass = 'yellow-border';
      bgClass = 'yellow-bg';
    }else{
      bdClass = 'blue-border';
      bgClass = 'blue-bg';
    }

    // ボーダークラスを一度削除後タスクの重要度によってクラスを追加
    $modal.removeClass('red-border');
    $modal.removeClass('yellow-border');
    $modal.removeClass('blue-border');
    $modal.addClass(bdClass);

    $deleteForm.empty();//deleteフォーム内のDOMを全て削除

    if (groupTaskDeadline) {//期限があれば期限付きDOMを追加
      $deleteForm.append('<p class="delete-task-deadline">' + groupTaskDeadline + 'まで</p>');
    }else{//期限がなければ期限なしDOMを追加
      $deleteForm.append('<p class="delete-task-deadline">期限なし</p>');
    }
    if (groupTaskResponsible) {
      $deleteForm.append('<p class="delete-task-responsible">担当者：' + groupTaskResponsible + '</p>');
    }else{
      $deleteForm.append('<p class="delete-task-responsible">担当者：なし</p>');
    }

    $deleteForm.append('<p class="delete-task-title">' + groupTaskTitle + '</p>');//タスクタイトルDOMを追加

    if (groupTaskDetail) {//タスク詳細があればDOMを追加
      $deleteForm.append('<p class="delete-task-detail">' + groupTaskDetail + '</p>');
    }

    $deleteForm.append('<input type="hidden" name="post-type" value="delete_task"></input>');//POST内容識別用hiddenDOMを追加
    $deleteForm.append('<input type="hidden" name="task-id" value="' + groupTaskId + '"></input>');//タスクIDDOMを追加
    $deleteForm.append($tokenDom);//トークンDOMを追加

    $deleteForm.append('<button class="'+ bgClass +'">削除</button>');//送信ボタンを追加
    $('#delete-task-modal').fadeIn();
    return false;
  })

  //申請承認・削除ボタンを押されたときのモーダル表示処理(pendingUser)
  $('.pendingUser .approve-btn').click(function () {
    // データとDOMを取得・定義
    let userId = $(this).parents('li').data('userid') || null;
    let userName = $(this).parents('li').data('username') || null;
    let img = $(this).parents('li').find('img').attr('src') || null;
    let $approveForm = $('#approve-modal form');
    let $tokenDom = $('#approve-modal [name=token]');
    
    

    $approveForm.empty();

    $approveForm.append('<img src="' + img + '" alt="ユーザー画像"></img>');
    $approveForm.append('<p class="modal-username">' + userName + '</p>');
    $approveForm.append('<p class="confirm-msg">参加申請を承認しますか？</p>');
    $approveForm.append('<input type="hidden" name="post-type" value="approve">');
    $approveForm.append('<input type="hidden" name="user-id" value="' + userId + '"></input>');
    $approveForm.append($tokenDom);
    $approveForm.append('<button class="group-color-background">承認</button>');
    
    $('#approve-modal').fadeIn();
    return false;
  });
  $('.pendingUser .refuse-btn').click(function () {
    // データとDOMを取得・定義
    let userId = $(this).parents('li').data('userid') || null;
    let userName = $(this).parents('li').data('username') || null;
    let img = $(this).parents('li').find('img').attr('src') || null;
    let $refuseForm = $('#refuse-modal form');
    let $tokenDom = $('#refuse-modal [name=token]');
    

    $refuseForm.empty();

    $refuseForm.append('<img src="' + img + '" alt="ユーザー画像"></img>');
    $refuseForm.append('<p class="modal-username">' + userName + '</p>');
    $refuseForm.append('<p class="confirm-msg">参加申請を拒否しますか？</p>');
    $refuseForm.append('<input type="hidden" name="post-type" value="refuse">');
    $refuseForm.append('<input type="hidden" name="user-id" value="' + userId + '"></input>');
    $refuseForm.append($tokenDom);
    $refuseForm.append('<button class="group-color-background">拒否</button>');
    
    $('#refuse-modal').fadeIn();
    return false;
  });


  //メンバー設定・除名ボタンを押されたときのモーダル表示処理(groupMember)
  $('.groupMember .setting-btn').click(function () {
    
    let userId = $(this).parents('li').data('userid') || null;
    let userName = $(this).parents('li').data('username') || null;
    let userAuthority = $(this).parents('li').data('authority') || null;
    let img = $(this).parents('li').find('img').attr('src') || null;
    let $settingForm = $('#setting-modal form');

    $settingForm.find('img').attr('src',img);
    $settingForm.find('.modal-username').text(userName);
    $settingForm.find('[name=user-id]').val(userId);

    if (userAuthority === 2) {
      $('#form-authority').val('deputy');
    }else if(userAuthority === 3){
      $('#form-authority').val('general');
    }
    
    $('#setting-modal').fadeIn();
    return false;
  })
  $('.groupMember .expulsion-btn').click(function () {
    
    let userId = $(this).parents('li').data('userid') || null;
    let userName = $(this).parents('li').data('username') || null;
    let userAuthority = $(this).parents('li').data('authority') || null;
    let img = $(this).parents('li').find('img').attr('src') || null;
    let $expulsionForm = $('#expulsion-modal form');

    $expulsionForm.find('img').attr('src',img);
    $expulsionForm.find('.modal-username').text(userName);
    $expulsionForm.find('[name=user-id]').val(userId);

    
    $('#expulsion-modal').fadeIn();
    return false;
  })


//-------------------------------------------------
//    chat
//-------------------------------------------------

let $chatArea = $('.chat .msg-area') || false;

if ($.isEmptyObject($chatArea.get()) === false) {
  
  
  $chatArea.scrollTop($chatArea[0].scrollHeight);
}

//-------------------------------------------------
//    datepicker
//-------------------------------------------------
  $("#date-input").datepicker({
    showOn : "both",
    buttonImage : "img/ico_calender.png",
    buttonImageOnly: true,
    dateFormat: 'yy-mm-dd'
  });

  if ($('[id=deadline_on]').prop('checked')) {//読み込み時の表示、非表示処理
    $('#date-input').css('display', 'inline-block');
    $('.ui-datepicker-trigger').css('display', 'inline-block');
  }else if($('[id=deadline_off]').prop('checked')) {
    $('#date-input').css('display', 'none');
    $('.ui-datepicker-trigger').css('display', 'none');
  }

  $('[name="deadline_flg"]:radio').change(function () {//チェックボタン変更時の表示非表示処理
    if ($('[id=deadline_on]').prop('checked')) {
      $('#date-input').fadeIn();
      $('.ui-datepicker-trigger').fadeIn();
      $('#date-input').css('display', 'inline-block');
      $('.ui-datepicker-trigger').css('display', 'inline-block');
    }else if($('[id=deadline_off]').prop('checked')) {
      $('#date-input').fadeOut();
      $('.ui-datepicker-trigger').fadeOut();
    }
  });
});