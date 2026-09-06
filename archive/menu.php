<?php
	$pageName = basename($_SERVER['PHP_SELF']);
	include ("./includes/employee.php"); 
	$currentUser = new Employee;
	if($currentUser->getRole($_SESSION['uName']) == "waiter") {
?>
<!-- Sidebar Menu Items - These collapse to the responsive navigation menu on small screens -->
<div class="collapse navbar-collapse navbar-ex1-collapse">
	<ul class="nav navbar-nav side-nav">
		<li <?php if($pageName == "table.php") echo "class='active'" ?>>
			<a href="table.php"><i class="fa fa-fw fa-dashboard"></i> Table Status</a>
		</li>
		<li <?php if($pageName == "payment.php") echo "class='active'" ?>>
			<a href="payment.php"><i class="fa fa-fw fa-bar-chart-o"></i> Payment</a>
		</li>
	</ul>
</div>
<!-- /.navbar-collapse -->
<?php 
}
if($currentUser->getRole($_SESSION['uName']) == "cook") {
?>
<div class="collapse navbar-collapse navbar-ex1-collapse">
	<ul class="nav navbar-nav side-nav">
		<li <?php if($pageName == "order.php") echo "class='active'" ?>>
			<a href="order.php"><i class="fa fa-fw fa-bar-chart-o"></i> Order Queue</a>
		</li>
	</ul>
</div>
<?php 
}
if($currentUser->getRole($_SESSION['uName']) == "busboy" || $currentUser->getRole($_SESSION['uName']) == "host") {
?>
<div class="collapse navbar-collapse navbar-ex1-collapse">
	<ul class="nav navbar-nav side-nav" id=<?php echo "'".$currentUser->getRole($_SESSION['uName'])."'";?>>
		<li <?php if($pageName == "tablelow.php") echo "class='active'" ?>>
			<a href="tablelow.php"><i class="fa fa-fw fa-bar-chart-o"></i> Table Status</a>
		</li>
	</ul>
</div>
<?php 
}
?>