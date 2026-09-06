<!-- Login Modal Box -->
<div class="modal fade" id="selItem1">
  <div class="modal-dialog w300">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
		<h4 class="modal-title">Select Item</h4>
	  </div>
	  <div class="modal-body">
		  <select class="itemSelect" class="form-control" multiple>
		  <?php
				$items = new Item;
				$items->itemList(1);
				$list = explode(";",$items->getItemList());
				for($i = 0; $i<$items->itemLimit(); $i++) {
					$itemOpt = explode("-",$list[$i]);
					echo "<option value='$itemOpt[1]'>$itemOpt[0]</option>";
				}
		  ?>
		  </select>
		  <p>Quantity: <input type="text" value="1" class="form-control quan" /></p>
		  <p class="mt10"><button class="btn btn-success itemSave" data-toggle="modal"  >Select</button></p>
	  </div>
	</div>
  </div>
</div>

<div class="modal fade" id="selItem2">
  <div class="modal-dialog w300">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
		<h4 class="modal-title">Select Item</h4>
	  </div>
	  <div class="modal-body">
		  <select class="itemSelect" class="form-control" multiple>
		  <?php
				$items = new Item;
				$items->itemList(2);
				$list = explode(";",$items->getItemList());
				for($i = 0; $i<$items->itemLimit(); $i++) {
					$itemOpt = explode("-",$list[$i]);
					echo "<option value='$itemOpt[1]'>$itemOpt[0]</option>";
				}
		  ?>
		  </select>
		  <p>Quantity: <input type="text" value="1" class="form-control quan"/></p>
		  <p class="mt10"><button class="btn btn-success itemSave" data-toggle="modal"  >Select</button></p>
	  </div>
	</div>
  </div>
</div>

<div class="modal fade" id="selItem3">
  <div class="modal-dialog w300">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
		<h4 class="modal-title">Select Item</h4>
	  </div>
	  <div class="modal-body">
		  <select class="itemSelect" class="form-control" multiple>
		  <?php
				$items = new Item;
				$items->itemList(3);
				$list = explode(";",$items->getItemList());
				for($i = 0; $i<$items->itemLimit(); $i++) {
					$itemOpt = explode("-",$list[$i]);
					echo "<option value='$itemOpt[1]'>$itemOpt[0]</option>";
				}
		  ?>
		  </select>
		  <p>Quantity: <input type="text" value="1" class="form-control quan"/></p>
		  <p class="mt10"><button class="btn btn-success itemSave" data-toggle="modal"  >Select</button></p>
	  </div>
	</div>
  </div>
</div>

<div class="modal fade" id="selItem4">
  <div class="modal-dialog w300">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
		<h4 class="modal-title">Select Item</h4>
	  </div>
	  <div class="modal-body">
		  <select class="itemSelect" class="form-control" multiple>
		  <?php
				$items = new Item;
				$items->itemList(4);
				$list = explode(";",$items->getItemList());
				for($i = 0; $i<$items->itemLimit(); $i++) {
					$itemOpt = explode("-",$list[$i]);
					echo "<option value='$itemOpt[1]'>$itemOpt[0]</option>";
				}
		  ?>
		  </select>
		  <p>Quantity: <input type="text" value="1" class="form-control quan"/></p>
		  <p class="mt10"><button class="btn btn-success itemSave" data-toggle="modal"  >Select</button></p>
	  </div>
	</div>
  </div>
</div>