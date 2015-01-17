<?php
error_reporting(0);
// POST HANDLER -->

if(isset($_POST['submit'])){
	include ("./includes/employee.php"); 
	$user = new Employee;
	$user->setCredentials($_POST['uid'], $_POST['passwd']);

	if(md5($_POST['passwd']) == $user->getPwd()) {
	session_start();
	$_SESSION['uName'] = $_POST['uid'];
	if($user->getRole($_SESSION['uName']) == "waiter") header('Location: table.php');
	if($user->getRole($_SESSION['uName']) == "cook") header('Location: order.php');
	if($user->getRole($_SESSION['uName']) == "busboy" || $user->getRole($_SESSION['uName']) == "host") header('Location: tablelow.php');
	} else {
	?>
	<div class="alert alert-info alert-danger">
		<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
		<i class="fa fa-info-circle"></i>  <strong>Check your username and password.</strong> 
	</div>
	<?php
	}
}
?>
<!-- Login Modal Box -->
	<div class="modal fade" id="myModal">
	  <div class="modal-dialog">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
			<h4 class="modal-title">Login to RAS</h4>
		  </div>
		  <div class="modal-body">
			<form method="post" action='index.php' name="login_form">
			  <p><input type="text" class="span3" name="uid" id="uname" placeholder="Username"></p>
			  <p><input type="password" class="span3" name="passwd" placeholder="Password"></p>
			  <p><input type="submit" value="Sign in" name="submit" class="btn btn-primary"/></p>
			</form>
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
		  </div>
		</div>
	  </div>
	</div>