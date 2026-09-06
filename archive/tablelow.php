<!DOCTYPE html>
<html lang="en">

<head>

<?php
	include ("head.php"); 
?>

</head>

<body>
<?php
//error_reporting(0);
include ("./includes/settings.inc.php");        // database settings
include ("./includes/connectdb.inc.php"); 
session_start();
if(isset($_SESSION['uName'])) {
include ("./includes/floorplan.php"); 
include ("change table low.php");
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
                            Table Management System
                            <small>Controls</small>
                        </h1>
                        <ol class="breadcrumb">
                            <li>
                                <i class="fa fa-dashboard"></i>  <a href="table.php">TMS</a>
                            </li>
                            <li class="active">
                                <i class="fa fa-file"></i> Configuration
                            </li>
                        </ol>
                    </div>
                </div>
                <!-- /.row -->
				<div class="well">
					<p id="txtLabel">Please select a table from the list below.</p>
					<div class="tableContainer">
					<?php
						$tabs = new Table;
						$limit = $tabs->totCount();
						$status = $tabs->totStatus();
						$tables = explode(";",$status);
						for($i = 0; $i<$limit; $i++) {
							$tabDet = explode("-",$tables[$i]);
							echo "<div class='circleBaseBoy circleSize ".$tabDet[1]."'>".$tabDet[0]."</div>";
						}
						if(isset($_REQUEST['id']) && $_REQUEST['status']!="") {
							$tabs->updateStatus($_REQUEST['id'], $_REQUEST['status']);
						} 
						
						
						
					?>
						<div class="controls">
							<button class="btn btn-disabled" data-toggle="modal" data-target="#status" id="changeStatusBoy" disabled>Change Status</button>
						</div>
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
