<!DOCTYPE html>
<html lang="en">

<head>

<?php
	include ("head.php"); 
?>

</head>

<body>
<?php
include ("./includes/settings.inc.php");        // database settings
include ("./includes/connectdb.inc.php"); 
session_start();
if(isset($_SESSION['uName'])) {
error_reporting(0);
include ("./includes/payment.php"); 
?>

    <div id="wrapper">

        <!-- Navigation -->
        <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
            <?php 
				include ("header.php");  
				include ("menu.php");
			?>
        </nav>

        <div id="page-wrapper">

            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="row">
                    <div class="col-lg-12">
                        <h1 class="page-header">
                            Payment System
                            <small>View</small>
                        </h1>
                        <ol class="breadcrumb">
                            <li>
                                <i class="fa fa-dashboard"></i>  <a href="order.php">OQS</a>
                            </li>
                            <li class="active">
                                <i class="fa fa-file"></i> List
                            </li>
                        </ol>
                    </div>
                </div>
                <!-- /.row -->
				<div class="well">
					<p>Please select an order from the list to view the total.</p>
					<div id="orderContainer">
						<div id="orderQueueList" class="orderList">
						<?php
							$list = new Payment;
							$orderList = explode("*",$list->OrderQueueList());
							for($i = 0; $i<$list->orderLimit(); $i++) {
								$orderDetails = explode(";",$orderList[($i*3)+2]);
								echo "<div class='orderListSel'>".$orderList[$i*3]."
									  <div class='orderDetails hide'>
									  <p>Table ".$orderList[($i*3)+1]."</p>";
									  $sum = 0;
										for($j = 0; $j<count($orderDetails)-1; $j++) {
											$orderPrettyList = explode("-",$orderDetails[$j]);
												echo "<span class='left'>".$orderPrettyList[0]."</span><span class='right'>".$orderPrettyList[1]." X ".$orderPrettyList[2]."</span><br/>";
												$sum += $orderPrettyList[1]*$orderPrettyList[2];
										}
								echo "<hr/>
									  <span class='left'><b>Total</b></span><span class='right'>$".$sum."</span>	
									  <p class='mt65'><button class='btn btn-success pay' id='payment".$orderList[($i*3)+1]."'>Pay</button></p>
									  </div>
									  </div>";
							} 
							if(isset($_REQUEST['tabidNoti']) && $_REQUEST['tabidNoti']!="") {
								$list->pay($_REQUEST['tabidNoti']);
							} 
						?>
						</div>
						<div id="itemList" class="orderList"></div>
					</div>
					<div class="clear"></div>
				</div>
            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- /#page-wrapper -->

    </div>
    <!-- /#wrapper -->
	
	<?php
	} else {
	include ("login.php"); 
	?>
	<div class="wrapIndex">

        <!-- Navigation -->
        <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
            <?php 
				include ("header.php");  
			?>
        </nav>

        <div id="page-wrapper">

            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="row">
                    <div class="col-lg-12">
                        <h1 class="page-header">
                            Restaurant Automation System
                            <small>RAS</small>
                        </h1>
                        <ol class="breadcrumb">
                            <li>
                                <i class="fa fa-dashboard"></i>  <a href="index.php">RAS</a>
                            </li>
                            <li class="active">
                                <i class="fa fa-file"></i> Warning
                            </li>
                        </ol>
                    </div>
                </div>
                <!-- /.row -->
			<div class="well">
            <p>Authorized Users Only</p>			
			</div>
            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- /#page-wrapper -->

    </div>
    <!-- /#wrapper -->
	
	<?php
	}
	?>

    <!-- jQuery Version 1.11.0 -->
    <script src="js/jquery-1.11.0.js"></script>

    <!-- Bootstrap Core JavaScript -->
    <script src="js/bootstrap.min.js"></script>
	
	<!-- Custom JavaScript -->
    <script src="js/custom.js"></script>

</body>

</html>
