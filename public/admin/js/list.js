$(function(){
     var urlHost = window.location.host;
//     if (urlHost == 'ac.signon.systoon.com:8081') {
//    	 var requestUrl = 'http://ac.signon.systoon.com:8081/admin/index/';
//     } else {
//    	 // var requestUrl = 'http://admin.signon.innertoon.com/admin/index/';
//    	 var requestUrl = 'http://activity.com/admin/index/';
//     }
     var requestUrl = 'http://'+urlHost+'/admin/index/';

    
    // p100.signon.systoon.com/admin/index/list?wall_type=-1&perpage=10&page=1 // 列表
    // p100.signon.systoon.com/admin/index/onoffwall // 上下墙
    // p100.signon.systoon.com/admin/index/delete // 删除
    // p100.signon.systoon.com/admin/index/changeorder 更新顺序
    // p100.signon.systoon.com/admin/index/view?ac_id=11 // 查看活动详情
    
    
    //console.log(parent.document.cookie)
    // console.log(window.frames[0])
    // console.log(window.frames[0].document)
    // console.log(window.frames[0].document.cookie)
    
    var selectId,showCheckRow;
    var imgServer = requestUrl + 'uploads'
    var rqUrlObj = {
        detail:{ // 查看活动详情
            url: requestUrl + 'view',
            data:{
               t: QueryParamT,
               ac_id:'',
            }
        },
        order:{ // 修改排序
            url: requestUrl + 'changeorder',
            data:{
                t: QueryParamT,
                ord: ''
            } 
        },
        /*edit:{
            url: requestUrl + '',
            data:{
                t: QueryParamT
            } 
        },*/
        del:{ // 删除活动
            url: requestUrl + 'delete',
            data:{
                t: QueryParamT
            } 
        },
        list:{ // 获取活动列表
            url: requestUrl + 'list',
            data:{
            	tab: 0,
                t: QueryParamT
            }  
        },
        search:{ // 搜索
            url: requestUrl + 'list',
            data:{
                tab: 0,
                t: QueryParamT
            }  
        },
        onwall:{ // 上墙
            url: requestUrl + 'onoffwall',
            data:{
                t: QueryParamT
            }
        }
    }
    
    var payType = {
        '0':'AA制',
        '1':'群主请客'
    }
    
    var hdType = {
        '0':'聚餐',
        '1':'兴趣',
        '2':'户外',
        '3':'展览',
        '4':'演出',
        '5':'会议',
        '6':'运动',
        '7':'沙龙'
    }

    var stateType = {
        'init': 0,
        'read': 1,
        'edit': 2,
        'modify':4,
        'beixuan': 8,
        'onWall': 16,
        'offWall': 32
    }
   
    /*var eidt = new Edit({
        id:'#tplEdit',
        rqParma:rqUrlObj.detail,
        pstParma:rqUrlObj.edit
    });*/
    
    var oprate = new Oprate();
    oprate.hideAllDlg();
    renderTable(rqUrlObj.list);

    //编辑
//    $("#update").on("click", function(){
//        oprate.modify();
//    });

    //下墙请求，直接处理后刷新
    $("#off").on("click", function(){
        oprate.offWall();
    });

    //上墙请求，直接处理后刷新
    $("#on").on("click", function(){
        oprate.onWall();
    });

    //tab切换
    $('#listTab').on('click',function(e){
        oprate.changeTab($(this),e);  
    });

    // 修改排序浮层，点击确定按钮
    $('#changeOder').on('click',function(){
       oprate.changeOrder();
    });
    // 修改排序浮层，点击取消按钮
    $('#changeOderCancel').on('click',function(){
        $('#orderDlg').dialog('close');
     });
    
    // 加载数据完成后，绑定编辑删除，查看事件
    function bindOpEvent()
    {
    	// 点击删除按钮, 弹出提示浮层
		$('.btnDel').on('click',function(){
			var delId = $(this).closest('tr').find("td[field='id']>div").text();
			$('#delId').val(delId);
	    	$('#delDlg').dialog('open');
		});
		
		// 点击下墙按钮
		$('.btnOff').on('click',function(){
			var id = $(this).closest('tr').find("td[field='id']>div").text();
			$("#offwallId").val(id);
			$('#offwallDlg').dialog('open');
		});
		// 点击上墙按钮
		$('.btnOn').on('click',function(){
			var id = $(this).closest('tr').find("td[field='id']>div").text();
			$("#onwallId").val(id);
			$('#onwallDlg').dialog('open');
		});
		
		/*$('.btnView').on('click',function(e){
	        var thisId = $(this).closest('tr').find("td[field='id']>div").text();
	        console.log(thisId);
	        var row = $("#tableListOn").datagrid('getRowIndex', thisId);
	        console.log(row);
	        oprate.openIframe($(this),'?id='+ thisId +'&t='+ QueryParamT +'&row='+ row);
	        return false;
	    })*/
		
    }
    
    // 删除浮层中，点击取消按钮
    $('#btnDelCancel').on('click',function(){
       $('#delDlg').dialog('close');
    });
    // 删除浮层中，点击确定按钮
    $('#btnDelSub').on('click',function(){
    	oprate.delOne();
     });
    
     // 上墙浮层中，点击确定按钮
    $('#btnOnwallSub').on('click',function(){
    	oprate.onwallOne();
     });
    // 上墙浮层中，点击取消按钮
    $('#btnOnwallCancel').on('click',function(){
       $('#onwallDlg').dialog('close');
    });
    
    // 上墙浮层中，点击确定按钮
    $('#btnOffwallSub').on('click',function(){
    	oprate.offwallOne();
     });
    // 上墙浮层中，点击取消按钮
    $('#btnOffwallCancel').on('click',function(){
       $('#offwallDlg').dialog('close');
    });
    
    // 设置上墙时间时，上面的立即上墙按钮去掉选中状态。
    $('input[name=onwallTime]').prev().on('click',function(){
    	$('input[name=onwallNow]').removeAttr('checked');
    });
    
   // 设置下墙时间时，上面的立即下墙按钮去掉选中状态。
    $('input[name=offwallTime]').prev().on('click',function(){
    	$('input[name=offwallNow]').removeAttr('checked');
    });
    
    

    // 添加备选
    /*$('#beixuan').on('click',function(){
        oprate.beixuan();
    });*/
    
    // 查看详情
    /*$('#viewDetail').on('click',function(e){
        var checkBox = choiceOne();
        if(checkBox){
            var row = $("#tableListOn").datagrid('getRowIndex',checkBox);
            oprate.openIframe($(this),'?id='+ checkBox.id +'&t='+ QueryParamT +'&row='+ row);
        }
        return false;
    })*/
    
    // 跳转发布
    /*$('#release').on('click',function(e){
        e.preventDefault();
        oprate.openIframe($(this),'?t='+ QueryParamT);
    })
    // 跳转配置管理员
    $('#setAdmin').on('click',function(){
        oprate.openIframe($(this),'?t='+ QueryParamT);
        return false;
    })
    // 跳转预览页面
    $('#preview').on('click',function(){
        oprate.openIframe($(this),'?t='+ QueryParamT);
        return false;
    })*/

    // 搜索

    $('#searchSubmit').on('click',function(e){
        var queryData = formSerialize($('#searchForm').serialize());
        rqUrlObj.search.data = $.extend({},rqUrlObj.search.data,queryData);
        var tab = $('#listTab').find('.select_tab').index();
        switch(rqUrlObj.search.data.s){
            case '4' :
                tab = 2;
                break;
            case '8':
                tab = 0;
                break;
            case '16':
                tab = 3;
                break;
            default: break;
        }
        rqUrlObj.search.data.tab = tab;
        renderTable(rqUrlObj.search);
    })
    
    //渲染table
    function renderTable(opts,page){
        $("#tableListOn").datagrid({
            width:$("#body").width(),
            idField:'id',
            method: 'GET',
            url: opts.url,
            singleSelect:false, 
            nowrap:true,
            fitColumns:true,
            rownumbers:false,
            showPageList:false,
            checkOnSelect:true,
            selectOnCheck:true,
            ctrlSelect: true,
            pageNumber: ( page && page > 0) ? page : 1,
            queryParams:opts.data,
            columns:[[
                {field:"ck",checkbox:true,halign:"center", align:"center"},
                {field:"id",width:"5%",title:"序号",halign:"center", align:"center"},
                {field:"u_no",width:"10%",title:"名片号",halign:"center", align:"center"},
                {field:"nickname",width:"10%",title:"昵称",halign:"center", align:"center"},
                {field:"title",width:"18%",title:"活动名称",halign:"center", align:"left"},
                {field:"type",width:"7%",title:"活动类型",halign:"center", align:"center"},
                {field:"create_time",width:"10%",title:"发布时间",halign:"center", align:"center"},
                {field:"start_time",width:"10%",title:"活动开始时间",halign:"center", align:"center"},
                {field:"publicity_str",width:"10%",title:"公开状态",halign:"center", align:"center"},
                {field:"operator",width:"10%",title:"操作人",halign:"center", align:"center"},
                {field:"op",width:"10%",title:"操作",halign:"center", align:"right"},
                {field:"ord",width:"10%",title:"排序(双击修改)",halign:"center", align:"center"}
            ]],
            toolbar:'#tt_btn',  
            pagination:true,
            // onDblClickCell: function(rowIndex,field, rowData){
            //     if(field == 'ord'){
            //         var rowObj = $('#tableListOn').datagrid('getSelected');
            //         if (rowObj.wall_type != 1) {
            //         	tipShow('请先上墙！');
            //         	return;
            //         }
            //         openOrderDlg(rowObj, rowData);
            //     }
            // },
            onLoadSuccess:function(data){
            	console.log(data);
                var rows = $('#tableListOn').datagrid('getRows');//获取所有当前加载的数据行
                if (rows.length > 0){
                    for (var i in rows){
                    	
                    	// 时间格式处理
                    	var applytime = starttime = createtime = '';
                    	
                        if (rows[i]["apply_end_time"] > 0) {
                        	applytime = showDT(new Date(parseInt(rows[i]["apply_end_time"])*1000));
                        }
                        if (rows[i]["start_time"] > 0) {
                        	starttime = showDT(new Date(parseInt(rows[i]["start_time"])*1000));
                        }
                        if (rows[i]["create_time"] > 0) {
                        	createtime = showDT(new Date(parseInt(rows[i]["create_time"])*1000));
                        }
                        
                        
                        $('#tableListOn').datagrid('updateRow',{index:i,row:{create_time:createtime,start_time:starttime, apply_end_time:applytime }});
                        // 操作按钮显示处理
                        var opStr = '';
                        
                        if (rows[i]['wall_type'] == 1) {
                        	opStr +='<a href="detail.html?id='+rows[i]['id']+'&t='+QueryParamT+'&referPage=1" class="  button button-rounded button-tiny button-highlight">审核</a>';
                        } else {
                        	
                    		opStr +='<a href="detail.html?id='+rows[i]['id']+'&t='+QueryParamT+'&referPage=2" class="button button-primary button-rounded button-small ">详情</a>';
                        	
                        }
                        
                        //opStr +='<a href="detail.html?id='+rows[i]['id']+'&t='+QueryParamT+'" class=" btnView button button-tiny button-action button-rounded">查看</a>';
                        opStr +='<a href="javascript:;" class=" btnDel button button-tiny button-caution button-rounded">删除</a>';
                        $('#tableListOn').datagrid('updateRow',{index:i,row:{op:opStr}});
                    }
                }
                if(data.rows.length <= 0) return;
                if(showCheckRow){
                    $("#tableListOn").datagrid('selectRow', showCheckRow);
                    showCheckRow = undefined;
                }else{
                    $("#tableListOn").datagrid('clearChecked');
                }  
                // 绑定删除编辑查看事件
                bindOpEvent();
            }
        });

        // 切换选中tab
        $('#listTab').find('.select_tab').removeClass('select_tab');
        $('#listTab li').eq(opts.data.tab).addClass('select_tab');
        
//        // 上下墙显示切换
//        if(opts.data.tab > 0){
//            $("#off").hide();
//            $("#on").show();
//        }else{
//            $("#off").show();
//            $("#on").hide();
//        }  
    }
    
    function showDT(currentDT) {
        var y,m,date,hs,ms,ss,theDateStr;
        y = currentDT.getFullYear(); //四位整数表示的年份
        m = currentDT.getMonth()+1; //月
        if (m < 10){
            m = '0'+ m.toString();
        }
        date = currentDT.getDate(); //日
        if (date < 10){
            date = '0'+ date.toString();
        }
        hs = currentDT.getHours(); //时
        if (hs < 10){
            hs = '0'+ hs.toString();
        }
        ms = currentDT.getMinutes(); //分
        if (ms < 10){
            ms = '0'+ ms.toString();
        }
        ss = currentDT.getSeconds(); //秒
        if (ss < 10){
            ss = '0'+ ss.toString();
        }
//        theDateStr = y+"年"+  m +"月"+date+"日 "+hs+":"+ms+":"+ss;
        theDateStr = y+"-"+m+"-"+date+" "+hs+":"+ms;
        //document.getElementById("theClock"). innerHTML =theDateStr;
        // setTimeout 在执行时,是在载入后延迟指定时间后,去执行一次表达式,仅执行一次
        //window.setTimeout( showDT, 1000);
        return theDateStr;
    }
    
    function changeState(id,s){
        rqUrlObj.state.data.id = id;
        rqUrlObj.state.data.s = s;
        $.post(rqUrlObj.state.url,rqUrlObj.state.data)
        .done(function(e){
            e = JSON.parse(e);
            if(e.state == 0){
                $("#tableListOn").datagrid('reload');
                switch(s){
                    case 16:
                        tipShow('上墙成功');
                        break;
                    case 32:
                        tipShow('下墙成功');
                        break;
                    default:break;
                }    
            }
            else{
                console.log(e.msg);
            }   
        });
    }
    function postRequest(parma,s){
        $.post(parma.url,parma.data)
        .done(function(e){
//            e = JSON.parse(e);
            if(e.state == 0 || e.code == 0){
                if(s){
                    changeState(parma.data.id,s);
                    tableReload();
                }else{
                    // $("#tableListOn").datagrid('reload');
                    tableReload();
                } 
                tipShow('操作成功！');
            }
            else{
                tipShow(e.msg);
            }
        })
        .fail(function(e){
            tipShow('请求错误！');
        })
    }

    function getInitParma(){
        var urlParmas = window.location.search.split('&');
        var tabValue = -1;
        var page = 1;
        if(urlParmas.length > 0){
            urlParmas.map(function(x){
                if(x.indexOf('tab') > -1 ){
                    tabValue = x.split('=')[1];
                }
                if(x.indexOf('page') > -1){
                    page = x.split('=')[1];
                }
            });
        }
        $('#listTab').find('.select_tab').removeClass('select_tab');
        $('#listTab li').eq(tabValue).addClass('select_tab');
        return {
            tableValue: tabValue,
            page: page
        };
    }

    function choiceOne(){
        var checkBoxIds =  $("#tableListOn").datagrid('getChecked');
        if(checkBoxIds.length > 1){
            tipShow('只能选择一个项目');
            return false;
        }
        else if(checkBoxIds.length == 0){
            tipShow('请选择一个项目');
            return false;
        }else{
            return checkBoxIds[0];
        }
    }
    function choiceMulit(){
        var checkBoxIds =  $("#tableListOn").datagrid('getChecked');
        if(checkBoxIds.length > 0){
            var data = [];
            checkBoxIds.map(function(em){
                data.push(em.id);
            })
            return data;  
        }
        else{
            alert('请勾选要上墙的产品');
            return false;
        }     
    }
    function openOrderDlg(rowObj, rowData){
        $('#orderId').val(rowObj.id);
        $('#orderDlg').find('input[type="number"]').val(rowData);
        $('#orderDlg').dialog('open');
    }
    
    //监听窗口大小变化
    window.onresize = function(){
        setTimeout(domReload,300);
    };
    //改变表格宽高
    function domReload(){
        $('#tableListOn').datagrid('resize',{  
            width:$("#body").width()
        });
    }

    window.tableReload = function(row,dontClose){
    	// 判断一下如果在详情页面的话，就刷新一下页面
    	if ($('#detailCont').length > 0) {
    		window.location.reload();
    		return;
    	}
        showCheckRow = row;
        if(!dontClose){
            $('#iframeDlg').dialog('close');
            $('#editDlg').dialog('close');
        }
        $("#tableListOn").datagrid('reload');

    }

    function tipShow(str,reflash){
        $('#tipDlg').find('.tip_cont').html(str);
        $('#tipDlg').dialog('open');
        setTimeout(function(){
            $('#tipDlg').dialog('close');
        },1000)
    }


    function formSerialize(str){
        var temp = str.split('&');
        var target = {};
        temp.length > 0 && temp.map(function(em){
           var key = em.split('=');
           target[key[0]] = key[1];
        }) 
        return target;
    }
    function Oprate(){
        var that = this;
        that.modify = function(){
            var checkBoxIds = choiceOne();
            if(checkBoxIds){
                rqUrlObj.detail.data.id = checkBoxIds.id;
                eidt.requestDate(rqUrlObj.detail.data);
            } 
        }
        that.offWall = function(){
            var ids = choiceMulit();
            if(ids){
                changeState(ids,stateType['offWall']);
            }
        }
        that.onWall = function(){
            var ids = choiceMulit();
            if(ids){
                changeState(ids,stateType['onWall']);
            }
        }
        that.changeTab = function($el,e){
			$('#searchForm input').val('');
            $el.find('.select_tab').removeClass('select_tab');
            $(e.target).addClass('select_tab');
            rqUrlObj.list.data.tab = $(e.target).index(); 
            renderTable(rqUrlObj.list);
        }
        that.changeOrder = function(){
            rqUrlObj.order.data.ord= $('#orderDlg').find('input[type=number]').val();
            rqUrlObj.order.data.id= $('#orderDlg').find('input[type=hidden]').val();
            $('#orderDlg').dialog('close');
            postRequest(rqUrlObj.order);
        }
        that.delOne = function(){
        	// 删除提交操作
            rqUrlObj.del.data.id= $('#delDlg').find('input[type=hidden]').val();
            $('#delDlg').dialog('close');
            postRequest(rqUrlObj.del);
        }
        that.onwallOne = function(){
        	// 上墙一个活动
        	rqUrlObj.onwall.data.id= $('#onwallDlg').find('input[type=hidden]').val();
        	rqUrlObj.onwall.data.wall_type = 1;
	    	rqUrlObj.onwall.data.time = $("input[name=onwallTime]").val();
	    	
	    	$("input[name=onwallTime]").val('');
	    	$("input[name=onwallTime]").prev().val('');
            $('#onwallDlg').dialog('close');
            postRequest(rqUrlObj.onwall);
        }
        that.offwallOne = function(){
        	// 下墙一个活动
        	rqUrlObj.onwall.data.id= $('#offwallDlg').find('input[type=hidden]').val();
        	rqUrlObj.onwall.data.wall_type = 2;
        	rqUrlObj.onwall.data.time = $("input[name=offwallTime]").val();
        	$("input[name=offwallTime]").val('');
	    	$("input[name=offwallTime]").prev().val('');
            $('#onwallDlg').dialog('close');
            $('#offwallDlg').dialog('close');
            postRequest(rqUrlObj.onwall);
        }
        that.hideAllDlg = function(){
            $('#orderDlg').dialog('close');
            $('#delDlg').dialog('close');
            $('#overViewDlg').dialog('close');
            $('#editDlg').dialog('close');
            $('#tipDlg').dialog('close');
            $('#iframeDlg').dialog('close');
            $('#onwallDlg').dialog('close');
            $('#offwallDlg').dialog('close');
        }
//        that.beixuan = function(){
//            var ids = choiceMulit();
//            if(ids){
//                changeState(ids,stateType['beixuan']);
//            }
//        }
        that.openIframe = function($el,str){
            var $iframe = $('#iframeDlg iframe');
            $iframe.html('加载中...');
            $('#iframeDlg').dialog('open');
            var linkUrl = $el.attr('href') + str;
            var linkUrl = 'detail.html'+str;
            $iframe.attr('src',linkUrl);   
        }
    }
})