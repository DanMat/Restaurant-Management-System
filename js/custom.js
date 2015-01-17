$(document).ready(function() {
if($(".side-nav").attr("id") == "host") {
$("#changeStatusBoy").hide();
$("#txtLabel").html("The current table status");
}

$(".circleBase").click(function () {
	$(".circleSize").css("border", "5px solid black");
	$(".circleSize").removeClass("selected");
	$(this).css("border", "5px solid #1F86B5");
	$(this).addClass("selected");
	
	$("#changeStatus").prop('disabled', false);
	$("#changeStatus").removeClass('btn-disabled');
	$("#changeStatus").addClass('btn-success');

	if($(this).css("background-color") == "rgb(255, 0, 0)" || $(this).css("background-color") == "rgb(0, 128, 0)") {
		$("#addItem").prop('disabled', true);
		$("#addItem").addClass('btn-disabled');
		$("#addItem").removeClass('btn-success');
	}
	
	if($(this).css("background-color") == "rgb(255, 255, 0)") {
		$("#addItem").prop('disabled', false);
		$("#addItem").removeClass('btn-disabled');
		$("#addItem").addClass('btn-success');
	}
	
});

$(".circleBaseBoy").click(function () {
	$(".circleSize").css("border", "5px solid black");
	$(".circleSize").removeClass("selected");
	$(this).css("border", "5px solid #1F86B5");
	$(this).addClass("selected");
	
	$("#changeStatus").prop('disabled', false);
	$("#changeStatus").removeClass('btn-disabled');
	$("#changeStatus").addClass('btn-success');

	if($(this).css("background-color") == "rgb(0, 128, 0)" || $(this).css("background-color") == "rgb(255, 255, 0)") {
		$("#changeStatusBoy").prop('disabled', true);
		$("#changeStatusBoy").addClass('btn-disabled');
		$("#changeStatusBoy").removeClass('btn-success');
	}
	
	if($(this).css("background-color") == "rgb(255, 0, 0)") {
		$("#changeStatusBoy").prop('disabled', false);
		$("#changeStatusBoy").removeClass('btn-disabled');
		$("#changeStatusBoy").addClass('btn-success');
	}
	
});

$("#changeStatus").click(function () {
	$("input[name=status]").prop("checked", false);
	$(".radioStatus").show();
	$("#tabSave").show();
	$("#changeDenied").hide();
	if($(".tableContainer").find(".selected").css("background-color") == "rgb(255, 0, 0)") {$(".radioStatus").hide(); $("#tabSave").hide(); $("#changeDenied").show(); }
});

$("#changeStatusBoy").click(function () {
	$("input[name=status]").prop("checked", false);
	$(".radioStatus").show();
	$("#tabSave").show();
	$("#changeDenied").hide();
});

$("#tabSave").click(function () {
	if($("input:radio[name=status]:checked").val() == "yellow")	{
		$(".tableContainer").find(".selected").css("background", "#FFFF00");
		$("#addItem").prop('disabled', false);
		$("#addItem").removeClass('btn-disabled');
		$("#addItem").addClass('btn-success');
	}
	if($("input:radio[name=status]:checked").val() == "red")	{
		$(".tableContainer").find(".selected").css("background", "red");
		$("#addItem").prop('disabled', true);
		$("#addItem").addClass('btn-disabled');
		$("#addItem").removeClass('btn-success');
	}
	$('#status').modal('hide');
	getid = $(".tableContainer").find(".selected").html();
	if($(".tableContainer").find(".selected").css("background-color") == "rgb(0, 128, 0)") getstat = "open";
	if($(".tableContainer").find(".selected").css("background-color") == "rgb(255, 255, 0)") getstat = "occupied";
	if($(".tableContainer").find(".selected").css("background-color") == "rgb(255, 0, 0)") getstat = "dirty";
	$.post("table.php",{ id:getid, status: getstat}, function(){
	});
});

$("#tabSaveBoy").click(function () {
	if($("input:radio[name=status]:checked").val() == "green")	{
		$(".tableContainer").find(".selected").css("background", "rgb(0, 128, 0)");
	}

	$('#status').modal('hide');
	getid = $(".tableContainer").find(".selected").html();
	getstat = "open";
	$.post("tablelow.php",{ id:getid, status: getstat}, function(){
		$("#changeStatusBoy").prop('disabled', true);
		$("#changeStatusBoy").addClass('btn-disabled');
		$("#changeStatusBoy").removeClass('btn-success');
	});
});

$("#catSave").click(function () {
	$('#selCategory').modal('hide');
});

$( "#catSelect" ).change(function() {
  id = "#selItem"+$(this).val();
  $("#catSave").attr("data-target",id);
});


$(".itemSave").click(function () {
	$('#selItem1').modal('hide');
	$('#selItem2').modal('hide');
	$('#selItem3').modal('hide');
	$('#selItem4').modal('hide');
	descval = $(this).parent().parent().find(".itemSelect option:selected").text();
	quantityval = $(this).parent().parent().find(".quan").val();
	priceval = $(this).parent().parent().find(".itemSelect option:selected").val();
	tab = $(".tableContainer").find(".selected").html();
	if(descval != "") {
	$(".itemSave").attr("data-target", "#confirmBox");
	$.post("table.php",{ tabid: tab, desc: descval, quantity: quantityval, price: priceval}, function(){
	});
	} else $(".itemSave").attr("data-target", "#");
});

$("#placeOrder").click(function () {
	tab = $(".tableContainer").find(".selected").html();
	$.post("table.php",{ tabidOrder: tab}, function(){
	});
});

$("#addItemAgain, #placeOrder").click(function () {
	$('#confirmBox').modal('hide');
});

$('#catSelect').on('load', function(){
        $('select').attr('size', $('select option').size());
})

$(".orderListSel").click(function () {
		$(".orderListSel").css("background-color", "rgb(255, 255, 255)");
		$(this).css("background-color", "#1F86B5");
		$("#itemList").empty();
		$("#itemList").html($(this).find(".orderDetails").html());
});

 $('body').on('click', '.notify', function () {
  id = ($(this).attr("id")).split("notify");
  $.post("order.php",{ tabidNoti: id[1]}, function(){
	location.reload();
  });
  });
  
   $('body').on('click', '.pay', function () {
  id = ($(this).attr("id")).split("payment");
  $.post("payment.php",{ tabidNoti: id[1]}, function(){
	location.reload();
  });
  });




});