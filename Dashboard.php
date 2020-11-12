<?php
class Dashboard {
	public $settings = array(
		'admin_menu_category' => 'General',
		'admin_menu_name' => 'Dashboard',
		'admin_menu_icon' => '<i class="icon-tachometer"></i>',
		'description' => 'Provides a dashboard for admins with support for sub-modules.',
	);
	function call_submodule($id) {
		global $billic;
		$billic->current_module = $id;
		$data = call_user_func(array(
                	$billic->modules[$id],
                	'dashboard_submodule'
                ));
		$billic->current_module = 'Dashboard';
		return $data;
	}
	function admin_area() {
		global $billic, $db;
		if (!$billic->user_has_permission($billic->user, 'Dashboard')) {
			err('You do not have permission to view this page');
		}
		if ($_GET['Ajax'] == 'Update') {
			ob_clean();
			$billic->disable_content();
			//var_dump($_POST); exit;
			$data = json_decode($_POST['data'], true);
			if (empty($data)) {
				err('Corrupt POST data');
			}
			$order = array(
				'1' => array() ,
				'2' => array() ,
				'3' => array() ,
			);
			foreach ($data as $wd) {
				$order[$wd['x']][$wd['y']] = $wd['id'];
			}
			ksort($order[1]);
			ksort($order[2]);
			ksort($order[3]);
			set_config('dashboard_order_' . $billic->user['id'], json_encode($order));
			echo 'OK';
			exit;
		}
		if ($_GET['ClearOrder'] == 'Yes') {
			set_config('dashboard_order_' . $billic->user['id'], '');
			$billic->redirect('/Admin/Dashboard/');
		}
		$billic->set_title('Admin Dashboard');
		echo '<h1><i class="icon-tachometer"></i> Admin Dashboard</h1>';
		//echo '<meta http-equiv="refresh" content="60">';
		
?>
<script>
var Charts = [];

var gridster;
var unit_height = 190;
var unit_margin = 10;
var header_height = 30;
var base_width;

addLoadEvent(function () {
    // Set the dimensions of canvas elements before the graphs are drawn on page load
    base_width = (Math.floor($("#dashboardWrap").innerWidth() / 3)-unit_margin);
    //console.log("Base Width "+base_width);

    gridster = $(".gridster submodules").gridster({
      widget_selector: "submodule",
      widget_margins: [10, 10],
      widget_base_dimensions: [base_width, 200],
      max_cols: 3,
      autogenerate_stylesheet: false,
      resize: {
        enabled: true,
        stop: function(e, ui, $widget) {
            var newDimensions = this.serialize($widget)[0];
            var height = ((unit_height * newDimensions.size_y)-header_height+(unit_margin*newDimensions.size_y));
            var width = (base_width * newDimensions.size_x);
            //console.log("New width: " + width);
            //console.log("New height: " + height);
            $( $widget ).find( "canvas" ).width(width).height(height);
            $( $widget ).find( "canvas" ).css('height', height+"px");
            $( $widget ).find( "canvas" ).css('width', width+"px");
            var chartID = $( $widget ).find( "canvas" ).attr("chartID");
            if (typeof chartID != 'undefined') {
                Charts[chartID].resize(Charts[chartID].render, true);
            }
        }
      },
		draggable: {
			stop: function(e, ui, $widget) {
				saveOrder();
			}
		},
	serialize_params: function($w, wgd) {
        return {
            x: wgd.col,
            y: wgd.row,
            width: wgd.size_x,
            height: wgd.size_y,
            id: $($w).attr('id')
        }
    }
    }).data('gridster');

	var data = gridster.serialize();
    $.each(gridster.$widgets, function( index, value ) {
        var height = ((unit_height * data[index].size_y)-header_height+(unit_margin*data[index].size_y));
        var width = (base_width * data[index].size_x);
        //console.log("Start width: " + width);
        //console.log("Start height: " + height);
        $(this).find( "canvas" ).width(width).height(height);
        $(this).find( "canvas" ).css('height', height+"px");
        $(this).find( "canvas" ).css('width', width+"px");
    });
});
					
function saveOrder(){
	$.ajax({
		url: "/Admin/Dashboard/Ajax/Update/",
		data: {data: JSON.stringify(gridster.serialize())},
		dataType: "html",
		type: "POST",
		success: function(html){
			if (html=='OK') {
				$("#results").html('<div class="alert alert-success" role="alert">Successfully saved</div>');
			} else {
				$("#results").html('<div class="alert alert-danger" role="alert">'+html+'</div>');
			}
			setTimeout(function(){$("#results").html('');},5000);
		}
	});
}

</script>
<div id="results"></div>

                <?php
		$modules = $billic->module_list_function('dashboard_submodule');
		$order_json = get_config('dashboard_order_' . $billic->user['id']);
		$order = @json_decode($order_json, true);
		if (!is_array($order) || empty($order)) {
			$order = array(
				'1' => array() ,
				'2' => array() ,
				'3' => array() ,
			);
			$i = 0;
			foreach ($modules as $module) {
				$i++;
				if ($i > 3) {
					$i = 1;
				}
				$order[$i][] = $module['id'];
			}
		}
		foreach ($modules as $module) {
			$exists = false;
			foreach ($order as $column => $list) {
				if (in_array($module['id'], $list)) {
					$exists = true;
					break;
				}
			}
			if ($exists === false) {
				$count = array();
				for ($i = 1;$i <= 3;$i++) {
					$count[$i] = count($order[$i]);
				}
				$col = array_keys($count, min($count));
				$col = $col[0];
				$order[$col][] = $module['id'];
			}
		}
		$order_json_new = json_encode($order);
		if ($order_json_new != $order_json) {
			//echo 'NEED TO SAVE!';
			set_config('dashboard_order_' . $billic->user['id'], $order_json_new);
		}
		echo '<div id="dashboardLoader">Loading...</div><div id="dashboardWrap"><div class="gridster"><submodules>';
		$row = 1;
		foreach ($order[1] as $id) {
			if (!$billic->user_has_permission($billic->user, $id)) {
				continue;
			}
			$billic->module($id);
			if (method_exists($billic->modules[$id], 'dashboard_submodule')) {
				echo '<submodule id="' . $id . '" data-row="' . $row . '" data-col="1" data-sizex="1" data-sizey="1">';
				$a = $this->call_submodule($id);
				echo '<div class="submodule-header">' . $a['header'] . '</div><div class="submod">' . $a['html'] . '</div>';
				echo '</submodule>';
				$row++;
			}
		}
		$row = 1;
		foreach ($order[2] as $id) {
			if (!$billic->user_has_permission($billic->user, $id)) {
				continue;
			}
			$billic->module($id);
			if (method_exists($billic->modules[$id], 'dashboard_submodule')) {
				echo '<submodule id="' . $id . '" data-row="' . $row . '" data-col="2" data-sizex="1" data-sizey="1">';
				$a = $this->call_submodule($id);
				echo '<div class="submodule-header">' . $a['header'] . '</div><div class="submod">' . $a['html'] . '</div>';
				echo '</submodule>';
				$row++;
			}
		}
		$row = 1;
		foreach ($order[3] as $id) {
			if (!$billic->user_has_permission($billic->user, $id)) {
				continue;
			}
			$billic->module($id);
			if (method_exists($billic->modules[$id], 'dashboard_submodule')) {
				echo '<submodule id="' . $id . '" data-row="' . $row . '" data-col="3" data-sizex="1" data-sizey="1">';
				$a = $this->call_submodule($id);
				echo '<div class="submodule-header">' . $a['header'] . '</div><div class="submod">' . $a['html'] . '</div>';
				echo '</submodule>';
				$row++;
			}
		}
		echo '</submodules></div></div>';
		echo '<div style="clear:both"></div>';
		echo '<script type="text/javascript" src="https://www.google.com/jsapi"></script>';
		echo '<style src="/Modules/Core/jquery.gridster.min.css">';
		$billic->add_script('/Modules/Core/jquery.gridster.min.js');
		$billic->add_script('//cdnjs.cloudflare.com/ajax/libs/dygraph/1.1.1/dygraph-combined.js');		
?>
<style>
#dashboardLoader{position:absolute;left:50%;font-size:25px;margin:5em auto;width:1em;height:1em;border-radius:50%;text-indent:-9999em;-webkit-animation:load4 1.3s infinite linear;animation:load4 1.3s infinite linear;-webkit-transform:translateZ(0);-ms-transform:translateZ(0);transform:translateZ(0)}@-webkit-keyframes load4{0%,100%{box-shadow:0 -3em 0 .2em #074f99,2em -2em 0 0 #074f99,3em 0 0 -.5em #074f99,2em 2em 0 -.5em #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 -.5em #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 0 #074f99}12.5%{box-shadow:0 -3em 0 0 #074f99,2em -2em 0 .2em #074f99,3em 0 0 0 #074f99,2em 2em 0 -.5em #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 -.5em #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 -.5em #074f99}25%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 0 #074f99,3em 0 0 .2em #074f99,2em 2em 0 0 #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 -.5em #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 -.5em #074f99}37.5%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 -.5em #074f99,3em 0 0 0 #074f99,2em 2em 0 .2em #074f99,0 3em 0 0 #074f99,-2em 2em 0 -.5em #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 -.5em #074f99}50%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 -.5em #074f99,3em 0 0 -.5em #074f99,2em 2em 0 0 #074f99,0 3em 0 .2em #074f99,-2em 2em 0 0 #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 -.5em #074f99}62.5%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 -.5em #074f99,3em 0 0 -.5em #074f99,2em 2em 0 -.5em #074f99,0 3em 0 0 #074f99,-2em 2em 0 .2em #074f99,-3em 0 0 0 #074f99,-2em -2em 0 -.5em #074f99}75%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 -.5em #074f99,3em 0 0 -.5em #074f99,2em 2em 0 -.5em #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 0 #074f99,-3em 0 0 .2em #074f99,-2em -2em 0 0 #074f99}87.5%{box-shadow:0 -3em 0 0 #074f99,2em -2em 0 -.5em #074f99,3em 0 0 -.5em #074f99,2em 2em 0 -.5em #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 0 #074f99,-3em 0 0 0 #074f99,-2em -2em 0 .2em #074f99}}@keyframes load4{0%,100%{box-shadow:0 -3em 0 .2em #074f99,2em -2em 0 0 #074f99,3em 0 0 -.5em #074f99,2em 2em 0 -.5em #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 -.5em #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 0 #074f99}12.5%{box-shadow:0 -3em 0 0 #074f99,2em -2em 0 .2em #074f99,3em 0 0 0 #074f99,2em 2em 0 -.5em #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 -.5em #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 -.5em #074f99}25%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 0 #074f99,3em 0 0 .2em #074f99,2em 2em 0 0 #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 -.5em #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 -.5em #074f99}37.5%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 -.5em #074f99,3em 0 0 0 #074f99,2em 2em 0 .2em #074f99,0 3em 0 0 #074f99,-2em 2em 0 -.5em #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 -.5em #074f99}50%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 -.5em #074f99,3em 0 0 -.5em #074f99,2em 2em 0 0 #074f99,0 3em 0 .2em #074f99,-2em 2em 0 0 #074f99,-3em 0 0 -.5em #074f99,-2em -2em 0 -.5em #074f99}62.5%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 -.5em #074f99,3em 0 0 -.5em #074f99,2em 2em 0 -.5em #074f99,0 3em 0 0 #074f99,-2em 2em 0 .2em #074f99,-3em 0 0 0 #074f99,-2em -2em 0 -.5em #074f99}75%{box-shadow:0 -3em 0 -.5em #074f99,2em -2em 0 -.5em #074f99,3em 0 0 -.5em #074f99,2em 2em 0 -.5em #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 0 #074f99,-3em 0 0 .2em #074f99,-2em -2em 0 0 #074f99}87.5%{box-shadow:0 -3em 0 0 #074f99,2em -2em 0 -.5em #074f99,3em 0 0 -.5em #074f99,2em 2em 0 -.5em #074f99,0 3em 0 -.5em #074f99,-2em 2em 0 0 #074f99,-3em 0 0 0 #074f99,-2em -2em 0 .2em #074f99}}#dashboardWrap{width:100%;opacity:0}submodules{display:block;width:100%}submodule{position:absolute;display:block;padding:10px;min-height:220px}.submodule-header{background:#074f99;height:30px;font-weight:700;color:#fff;font-size:14px;padding:5px 15px 0;margin:0}.submod{flow-x:hidden;overflow-y:auto;max-height:170px}[data-col="1"]{left:0}[data-col="2"]{left:33%}[data-col="3"]{left:66%}[data-row="2"]{top:220px}[data-row="3"]{top:440px}[data-row="4"]{top:660px}[data-row="5"]{top:880px}[data-row="6"]{top:1050px}[data-row="7"]{top:1260px}[data-row="8"]{top:1470px}[data-row="9"]{top:1680px}[data-row="10"]{top:1890px}[data-sizex="1"]{width:33%}[data-sizex="2"]{width:66%}[data-sizex="3"]{width:99%}
</style>
<script>
addLoadEvent(function () {
    $("#dashboardWrap").fadeTo('slow', 1);
    $("#dashboardLoader").fadeOut(500);
});
</script>
<div style="text-align:center;font-size:80%"><a href="/Admin/Dashboard/ClearOrder/Yes/">Reset dashboard order</a></div>
<?php
	}
}
