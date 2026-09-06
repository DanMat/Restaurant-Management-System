<!-- Login Modal Box -->
	<div class="modal fade" id="selCategory">
	  <div class="modal-dialog w300">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
			<h4 class="modal-title">Select Category</h4>
		  </div>
		  <div class="modal-body">
			  <select id="catSelect" class="form-control">
			    <option disabled selected value="0"> -- select an category -- </option>
				<?php
					$cat = new Category;
					$category = explode(";",$cat->categoryList());
					for($i = 0; $i<$cat->catLimit(); $i++) {
						$catOpt = explode("-",$category[$i]);
						echo "<option value='$catOpt[0]'>$catOpt[1]</option>";
					}
					
				?>
			  </select>
			  <p class="mt10"><button class="btn btn-success" data-toggle="modal" data-target="#selItem1" data-number="1" id="catSave">Select</button></p>
		  </div>
		</div>
	  </div>
	</div>