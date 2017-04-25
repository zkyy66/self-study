$(function(){
    function Oprate() {
    	var that = this;
        that.hideAllDlg = function(){
            $('#addContent').dialog('close');
            $('#editContent').dialog('close');
            $('#tipDlg').dialog('close');
            $('#tipDlgError').dialog('close');
            $('#tipDlgErrorContent').dialog('close');
        }
    }
    var requestUrl = 'http://p100.signon.systoon.com/admin/activitymanagement/';

    var rqUrlObj = {

        list:{ // 获取活动审核术语列表
            url: requestUrl + 'checklist',
            data:{
            	tab: 0,
                t: QueryParamT
            }  
        },
        edit:{
        	url: requestUrl + 'editcontent'
        }
    }
    var oprate = new Oprate();
    oprate.hideAllDlg();
	listData(rqUrlObj.list);

	$('#addSubmit').on('click',function() {

		$('#addContent').dialog('open');
	});
	 
	 //关闭弹窗
    $('#btnCancel').on('click',function(){
       $('#addContent').dialog('close');
    });

    //确定保存
    $('#btnSub').on('click',function(){
       var url = requestUrl + 'addcheckcontent'
       $.post(url,$('#checkContent').serialize(),function(message) {
       		if (message == 1) {
		        $('#tipDlg').dialog('open');
		        setTimeout(function(){
		            $('#tipDlg').dialog('close');
		        },1000)
		        $('#addContent').dialog('close');
		        parent.location.reload()
       		}  else if (message == -1){
                $('#tipDlgErrorContent').dialog('open');
                setTimeout(function(){
                    $('#tipDlgErrorContent').dialog('close');
                },1000)
                $('#addContent').dialog('close');
       		} else {
                $('#tipDlgError').dialog('open');
                setTimeout(function(){
                    $('#tipDlgError').dialog('close');
                },1000)
                $('#addContent').dialog('close');
            }
       });
    });
	/**
	 * [listData description]
	 * @param  {[type]} url  [description]
	 * @param  {[type]} page [description]
	 * @return {[type]}      [description]
	 */
    function listData(url,page) {
    	
    	$("#tableListOn").datagrid({
            width:$("#body").width(),
            idField:'id',
            method: 'GET',
            url: url.url,
            singleSelect:false, 
            nowrap:true,
            fitColumns:true,
            rownumbers:false,
            showPageList:false,
            checkOnSelect:true,
            selectOnCheck:true,
            ctrlSelect: true,
            pageNumber: ( page && page > 0) ? page : 1,
            queryParams:url.data,
            columns:[[
                {field:"content",width:"35%",title:"内容",halign:"center", align:"center"},
                {field:"create_time",width:"35%",title:"创建时间",halign:"center", align:"center"},
                {field:"operate",width:"32%",title:"操作",halign:"center", align:"center"}
            ]],
            toolbar:'#listData',  
            pagination:true,
            onLoadSuccess:function(data){
            	console.log(data);
                var rows = $('#tableListOn').datagrid('getRows');//获取所有当前加载的数据行
                if (rows.length > 0){
                    for (var i in rows){
                        // 操作按钮显示处理
                        var opStr = '';
                        opStr +='<a href=javascript:; class="contentID" data-id='+rows[i]['id']+' data-mark = 1 ><font color=blue>修改</font></a> &nbsp;&nbsp;'; 
                        
                        opStr +='<a href="javascript:;" id="deleteContent" data-id='+rows[i]['id']+' data-mark = 2 ><font color=blue>删除</font></a>';
                        $('#tableListOn').datagrid('updateRow',{index:i,row:{operate:opStr}});
                    }
                }
                if(data.rows.length <= 0) return;

            }
    	});
    	/**
    	 * [description]
    	 * @param  {[type]} )              {	              	var           obj [description]
    	 * @param  {[type]} function(data) {	              		alert(data)	                    	})				    } [description]
    	 * @return {[type]}                [description]
    	 */
        $(document).on('click','.contentID',function() {
	    	var obj = $(this);
	    	$('#editContent').dialog('open');
			var content = $(this).closest('tr').find('td[field=content]').find('div').text();
			$('#edit_content').val(content);
			$("#checkContentID").val(obj.data('id'));
	    });

	 	//关闭弹窗
	    $(document).on('click','#editCancel',function(){
	       $('#editContent').dialog('close');
	    });
	    //编辑
	    $('#editSub').on('click',function() {
	    	$.post(requestUrl + 'editcontent',{id:$('#checkContentID').val(),mark:1,content:$('#edit_content').val()},function(data) {
	       		if (data == 1) {
			        $('#tipDlg').dialog('open');
			        setTimeout(function(){
			            $('#tipDlg').dialog('close');
			        },1000)
			        $('#editContent').dialog('close');
			        parent.location.reload()
	       		}  else if (data == -1) {

                    $('#tipDlgErrorContent').dialog('open');
                    setTimeout(function(){
                        $('#tipDlgErrorContent').dialog('close');
                    },1000)
                    $('#editContent').dialog('close');
	       		} else {
                    $('#tipDlgError').dialog('open');
                    setTimeout(function(){
                        $('#tipDlgError').dialog('close');
                    },1000)
                    $('#editContent').dialog('close');
                }
	    	});
	    });
	    //删除
        $(document).on('click','#deleteContent',function() {
	    	var obj = $(this);
	    	$.post(requestUrl + 'editcontent',{id:obj.data('id'),mark:obj.data('mark')},function(data) {
               
                if (data == 1) {
			        $('#tipDlg').dialog('open');
			        setTimeout(function(){
			            $('#tipDlg').dialog('close');
			        },1000)
			        $('#editContent').dialog('close');
			        parent.location.reload()
	       		}  else {
			        $('#tipDlgError').dialog('open');
			        setTimeout(function(){
			            $('#tipDlgError').dialog('close');
			        },1000)
			        $('#editContent').dialog('close');
	       		}
	    	})
	    });
    }
})