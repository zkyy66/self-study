$.fn.initUploadImg = function(opts){
    var $this = $(this);
    var $listCntr = $this.find('.fileList');
    var oneFlag = false;
    var $upBtn = $this.find('.uploadBtn');
    $this.status = "finished";
    $this.filePath = [];
    var defaultOpt = {
        auto: false,// 选完文件后，是否自动上传。
        swf: './Uploader.swf',// swf文件路径
        server: opts.server,// 文件接收服务端。
        pick: {
            id: $this.find('.filePicker'),// 选择文件的按钮。可选。
            multiple: (typeof opts !== 'undefined' && opts.multi !== 'undefined')? opts.multi : true
        },
        thumb:{
            allowMagnify: false,
            crop: false,
            type: 'image/jpeg',
            quality: 100
        },
        accept: {
            title: 'Images',
            extensions: 'gif,jpg,jpeg,bmp,png',
            mimeTypes: 'image/*'
        }// 只允许选择图片文件。       
    }
    // 初始化Web Uploader
    var thumbnailWidth = (typeof opts !== 'undefined'  && opts.thumbW)? opts.thumbW : 335;
    var thumbnailHeight = (typeof opts !== 'undefined'  && opts.thumbH)? opts.thumbH : 134;
    var uploader = WebUploader.create(defaultOpt);
    // 当有文件添加进来的时候
    uploader.on( 'fileQueued', function( file ) {
        var $li = $(
            '<div id="' + file.id + '" class="img-item thumbnail"><img></div>');
        var $img = $li.find('img');
        if(defaultOpt.pick.multiple){
            $li.append('<a herf="javascript:void(0);" class="img-item-del">X<span>')
            $listCntr.append( $li );
        }else{
            if(!oneFlag){
                $listCntr.append( $li );
                oneFlag = true;
            }else{
                $this.filePath.length = 0;
                var $lastFile = $listCntr.find('.img-item');
                var lastFileId = $lastFile.attr('id');
                uploader.removeFile( lastFileId );
                $listCntr.html($li);
            }
        }
        // 创建缩略图
        // 如果为非图片文件，可以不用调用此方法。
        uploader.makeThumb( file, function( error, src ) {
            if ( error ) {
                $img.replaceWith('<span>不能预览</span>');
                return;
            }
            $img.attr( 'src', src );
        }, thumbnailWidth, thumbnailHeight );
    });
    // 文件上传过程中创建进度条实时显示。
    uploader.on( 'uploadProgress', function( file, percentage ) {
        var $li = $( '#'+file.id ),
            $percent = $li.find('.progress span');
        // 避免重复创建
        if ( !$percent.length ) {
            $percent = $('<p class="progress"><span></span></p>')
            .appendTo( $li )
            .find('span');   
        }
        $percent.css( 'width', percentage * 100 + '%' );
    });

    // 文件上传成功，给item添加成功class, 用样式标记上传成功。
    uploader.on( 'uploadSuccess', function( file , response) {
        if(response.state == 0){
            // console.log('response:',response);
            $this.filePath.push(response.data.path);
            $( '#'+file.id ).find('.progress').html('上传成功');
            $( '#'+file.id ).find('.progress').css('color','#0f0');
            $( '#'+file.id ).find('.img-item-del').remove();
        }else{
            $( '#'+file.id ).find('.progress').css('color','#f00');
            $( '#'+file.id ).find('.progress').html(response.msg);
        }
        // $( '#'+file.id ).data('path',);
    });

    // 文件上传失败，显示上传出错。
    uploader.on( 'uploadError', function( file ) {
        var $li = $( '#'+file.id ),
            $error = $li.find('div.error');
        // 避免重复创建
        if ( !$error.length ) {
            $error = $('<div class="error"></div>').appendTo( $li );
        }
        $error.text('上传失败');
    });

    // 完成上传完了，成功或者失败，先删除进度条。
    uploader.on( 'uploadComplete', function( file ) {
    });
    uploader.on( 'uploadFinished', function( file ) {
        $this.status = 'finished';
        $upBtn.removeClass('btn-disable');
        $upBtn.text('开始上传');
    });
    $upBtn.on('click',function(e){
        $this.status = 'start';
        if(!$upBtn.hasClass('btn-disable')){
            uploader.upload();
            $upBtn.addClass('btn-disable');
            $upBtn.text('正在上传')
        }  
    })
    $listCntr.on('click','.img-item-del',function(e){
        var $parent = $(this).parent();
        var fileId = $parent.attr('id');
        uploader.removeFile( fileId );
        $parent.remove();
    })
    return $this;
}