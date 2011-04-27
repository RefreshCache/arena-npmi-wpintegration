<?php
/*
Plugin Name: ChMS Dynamic Grid 
Description: This plugin is a dynamic grid generator from a ChMS web service call. This is dependent on the ChMS Web Services plugin.
Version: 0.1
Author: North Point Community Church
Author URI: http://northpoint.org
License: GPL2
*/


/*
- parameters
	- results, presumably of a DB or API call with data. It should be an array of arrays, each one with key/field => value
	- columns in the form of an array of field names => field labels in the order to be displayed
	- optional ID of the table
	
 */
 
class ChMSDynamicGridWidget extends WP_Widget {
	
	function ChMSDynamicGridWidget() {
		parent::WP_Widget(false, $name = 'ChMS Dynamic Grid Widget');
	}
	
	function widget($args, $instance) {
        extract( $args );

		session_start();
		$user = getChMS()->getChmsProfile();
	
		$ws_uri = null;
		// which format is the URI:
		// - a hard coded one (e.g. person/list) 
		// - a user attribute (e.g. $user->FamilyLink)
		// - one that uses a user attribute (e.g. /person/{id}/attribute/list) - currently only {id} needs to be accounted for
        if (strpos($instance['ws_method_uri'],'$user') !== false) {
        	eval("\$ws_uri = ".$instance['ws_method_uri'].";");
        } else if (strpos($instance['ws_method_uri'],'{id}') !== false) {
        	$ws_uri = str_replace('{id}',$user->PersonID,esc_attr($instance['ws_method_uri']));
        } else {
         	$ws_uri = esc_attr($instance['ws_method_uri']);      
        }
       	
        
		$ws_params = $instance['ws_method_params'];
		eval("\$params = array(".$ws_params.");");
		if (WP_DEBUG === true) error_log( "PARAMS: ".print_r($params,true)."\n");
        $field_names = $instance['field_names'];
 		eval("\$cols = array(".$field_names.");");

		$rsXml = getChMS()->call_ws($ws_uri,$params);
		$rsArr = ChmsUtil::xml2array($rsXml);

		$rsArray = $instance['ws_return_array'];
		eval("\$a = \$rsArr".$rsArray.";");
		

 		echo $before_widget;

 		//echo "<p>Gonna Invoke the URI: ".$instance['ws_method_uri']."</p>\n";
 		//echo "<p>Args Array [".print_r($args,true)."]</p>\n";
 		$gridId = "grid-".$args['widget_id'];
 		echo $this->_generateGrid($a,$cols,$gridId);
 		 
		echo $after_widget;
	
		// insert the script block if it was set; also see if the CSS block was specified	
		$jq = $instance['jq_script'];
		$css = $instance['grid_css'];
		if ( (isset($jq) && $jq != '') || (isset($css) && $css != '') )  { 
			if (isset($css) && $css != '') {
				// replace instances of table with table#id and strip out any newlines
				$css = str_replace('table','table#'.$gridId, (str_replace(array("\r", "\r\n", "\n"),"",$css)) );
			}
?>
		<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function($) {
<?php echo $jq; ?>
<?php if (isset($css) && $css != '') { ?>
	$('<style>')
	  .attr('type','text/css')
      .html('<?php echo $css; ?>')
      .appendTo('head');
<?php } ?>
});
/* ]]> */			
		</script>			
<?php		
		}
		
		
	}
	
	function form($instance) {
        $ws_uri = esc_attr($instance['ws_method_uri']);
        $ws_params = esc_attr($instance['ws_method_params']);
        $ws_arr = esc_attr($instance['ws_return_array']);
        $field_names = esc_attr($instance['field_names']);
        $jq_script = esc_attr($instance['jq_script']);
        $grid_css = esc_attr($instance['grid_css']);
        ?>
         <p>
          <label for="<?php echo $this->get_field_id('ws_method_uri'); ?>"><?php _e('Web Service Method URI:'); ?></label> 
          <input class="widefat" id="<?php echo $this->get_field_id('ws_method_uri'); ?>" name="<?php echo $this->get_field_name('ws_method_uri'); ?>" type="text" value="<?php echo $ws_uri; ?>" />
        </p>
         <p>
          <label for="<?php echo $this->get_field_id('ws_method_params'); ?>"><?php _e('Web Service Method Parameters (e.g. "lastName" => $person->LastName, "firstName" => $person->FirstName) :'); ?></label> 
          <input class="widefat" id="<?php echo $this->get_field_id('ws_method_params'); ?>" name="<?php echo $this->get_field_name('ws_method_params'); ?>" type="text" value="<?php echo $ws_params; ?>" />
        </p>
         <p>
          <label for="<?php echo $this->get_field_id('ws_return_array'); ?>"><?php _e('Web Service Return Array (e.g. ["Persons"]["Person"]) :'); ?></label> 
          <input class="widefat" id="<?php echo $this->get_field_id('ws_return_array'); ?>" name="<?php echo $this->get_field_name('ws_return_array'); ?>" type="text" value="<?php echo $ws_arr; ?>" />
        </p>
         <p>
          <label for="<?php echo $this->get_field_id('field_names'); ?>"><?php _e('Field Name -> Label Pairs:'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('field_names'); ?>" name="<?php echo $this->get_field_name('field_names'); ?>" type="text" value="<?php echo $field_names; ?>" />
         </p> 
         <p>
          <label for="<?php echo $this->get_field_id('jq_script'); ?>"><?php _e('Script to execute (between the &lt;script&gt; tags):'); ?></label>
          <textarea class="widefat" id="<?php echo $this->get_field_id('jq_script'); ?>" name="<?php echo $this->get_field_name('jq_script'); ?>" rows="10"><?php echo $jq_script; ?></textarea>
         </p> 
         <p>
          <label for="<?php echo $this->get_field_id('grid_css'); ?>"><?php _e('CSS rules to apply to the table (between the &lt;style&gt; tags):'); ?></label>
          <textarea class="widefat" id="<?php echo $this->get_field_id('grid_css'); ?>" name="<?php echo $this->get_field_name('grid_css'); ?>" rows="10"><?php echo $grid_css; ?></textarea>
         </p> 
         <?php
	}
	
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['ws_method_uri'] = strip_tags($new_instance['ws_method_uri']);
		$instance['ws_method_params'] = $new_instance['ws_method_params'];
		$instance['ws_return_array'] = $new_instance['ws_return_array'];
		$instance['field_names'] = $new_instance['field_names'];
		$instance['jq_script'] = $new_instance['jq_script'];
		$instance['grid_css'] = $new_instance['grid_css'];
        return $instance;
	
	}

	public static function _generateGrid($results, $columns = null, $id = null) {
		if (!isset($results)) throw new Exception("You must specify a valid results set");
		
		if ( (!isset($columns) || count($columns) == 0) && count($results) > 0) {
			$columns = array_keys($results);
		}
		$count = 0;
		$gridHtml = '<table class="datagrid"' . ( isset($id) ? ' id="'.$id.'">' : '>' );
		$headHtml = '<thead>';
		$bodyHtml = '<tbody>';
		foreach ($results as $row) {
			if ($count == 0) $headHtml .= '<tr>';
			$bodyHtml .= '<tr class="gridrow' . ( ($count % 2 == 1) ? '-even' : '-odd' ) . '">';
			foreach ($columns as $field => $label) {
				if ($count == 0) $headHtml .= '<th id="'. self::_safeAttribute($field) .'">' . $label . '</th>';
				$bodyHtml .= '<td class="'. self::_safeAttribute($field) .'">' . $row[$field] . '</td>';	
			}
			$bodyHtml .= '</tr>';
			$count++;
		}
		$headHtml .= '</tr></thead>';
		$bodyHtml .= '</tbody>';
		$gridHtml .= $headHtml . $bodyHtml . '</table>' . "\n";
		return $gridHtml;		
	}
	
	public static function _safeAttribute($s) {
		$s = preg_replace('/[^-A-Za-z0-9_]*/',"",$s);
		return $s;
	}

}

add_action('widgets_init', create_function('', 'return register_widget("ChMSDynamicGridWidget");'));

?>