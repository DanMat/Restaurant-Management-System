<!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="index.php">RAS</a>
            </div>
            <!-- Top Menu Items -->
            <ul class="nav navbar-right top-nav">
			<?php
				if (!isset($_SESSION)) {
					session_start();
				}
				if(isset($_SESSION['uName'])) {
				$uName = ucfirst($_SESSION['uName']);
			?>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-user"></i> <?php echo $uName ?> <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                        <li>
                            <a href="profile.php"><i class="fa fa-fw fa-user"></i> Profile</a>
                        </li>
                        <li class="divider"></li>
                        <li>
                            <a href=<?php echo "'logout.php'"; ?>><i class="fa fa-fw fa-power-off"></i> Log Out</a>
                        </li>
                    </ul>
                </li>
				<?php
					} else {
				?>
				<li>
					<div class="row fa login-btn">
					<a class="btn btn-primary" data-toggle="modal" data-target="#myModal" >Login</a>
					</div>
				</li>
				<?php
					}
				?>
            </ul>
	<!-- jQuery Version 1.11.0 -->
    <script src="js/jquery-1.11.0.js"></script>
	
	<!-- Simulation JavaScript -->
    <script src="js/simulation.js"></script>
            