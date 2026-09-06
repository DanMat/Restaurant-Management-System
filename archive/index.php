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
                            Restaurant Automation System
                            <small>Dashboard</small>
                        </h1>
                        <ol class="breadcrumb">
                            <li>
                                <i class="fa fa-dashboard"></i>  <a href="index.php">RAS</a>
                            </li>
                            <li class="active">
                                <i class="fa fa-file"></i> Welcome
                            </li>
                        </ol>
                    </div>
                </div>
                <!-- /.row -->
				<div class="well">
					<p>Please select one of the actions from the side menu.</p>
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
            <p>Only an authorized user can use this system.</p>			
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
