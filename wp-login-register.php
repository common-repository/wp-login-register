<?php
/*
Plugin Name:  WP Login Register
Description:  The plugin allows to save the data of each login (IP, username, date, time). Only administrators can see this information on admin panel.
Version:      1.0.2
Author:       Cristian Carrera
Author URI:   https://profiles.wordpress.org/kriss73/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  
Domain Path:  

WP Login Register is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
WP Login Register is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with WP Login Register. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/


//////////// Register activation hook
function wlr_register_activation() { 
  /* Create transient data */
  set_transient( 'fx-admin-notice-example', true, 5 );
  //create table
  global $wpdb;
  $nombreTabla = $wpdb->prefix . "loginregister";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$created = dbDelta(  
		"CREATE TABLE IF NOT EXISTS $nombreTabla ( 
		id INT NOT NULL AUTO_INCREMENT ,
		id_user INT(6) NOT NULL ,
		r_ip VARCHAR(20) NULL ,
		r_time VARCHAR(20) NOT NULL ,
		PRIMARY KEY  (id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci;"
	);  
} 
register_activation_hook( __FILE__, 'wlr_register_activation' );

//Display message when install finish
function wlr_admin_notice(){ 
    /* Check transient, if available display notice */
    if( get_transient( 'fx-admin-notice-example' ) ){
        ?>
        <div class="updated notice is-dismissible">
            <p>Thank you for installing WP Login Register! You can see the access record in the <a href="<?php echo get_dashboard_url();?>">Dashboard</a> and on <a href="<?php get_admin_url();?>admin.php?page=wp-login-register">this page</a></p>
        </div>
        <?php
        /* Delete transient, only display this notice once. */
        delete_transient( 'fx-admin-notice-example' );
    }
}
add_action( 'admin_notices', 'wlr_admin_notice' ); 

//////////// Register uninstall hook
function wlr_uninstall() {
  // drop a custom database table
  global $wpdb;
  $nombreTabla = $wpdb->prefix . "loginregister";
  $wpdb->query("DROP TABLE IF EXISTS $nombreTabla");
}
register_uninstall_hook(__FILE__, 'wlr_uninstall'); 

// set the last login date
add_action('wp_login','wlr_set_last_login', 0, 2);
function wlr_set_last_login($login, $user) {
    $user = get_user_by('login',$login);
    $time = current_time( 'timestamp' );

    //insert sql
    //get ip
    function wlr_getRealIP() {
		if (!empty($_SERVER['HTTP_CLIENT_IP']))
		return $_SERVER['HTTP_CLIENT_IP'];

		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
		return $_SERVER['HTTP_X_FORWARDED_FOR'];
		return $_SERVER['REMOTE_ADDR'];
	}	
	global $current_user;
    get_currentuserinfo();
    $r_ip = wlr_getRealIP();
    $id_user = $user->ID;
    global $wpdb;
    $nombreTabla = $wpdb->prefix . "loginregister";
	$wpdb->insert($nombreTabla, array(
	   "id_user" => $id_user,
	   "r_ip" => $r_ip,
	   "r_time" => $time,		   
	));
}

///////////// Add Widget to dashboard
add_action( 'plugins_loaded', 'wlr_display_dashboard_widget' );
function wlr_display_dashboard_widget() {
  if (current_user_can('manage_options')) {
    function wlr_your_dashboard_widget() {
    	//query sql
    	global $wpdb;
      $limitmax = 10; //limit amount of results on dashboard
    	$nombreTabla = $wpdb->prefix . "loginregister";
    	$results= $wpdb->get_results( "SELECT * FROM $nombreTabla ORDER BY r_time DESC LIMIT ".$limitmax );
    	if ($results) {
        echo "Last 10 results:<br><br>";
    		foreach ( $results as $page ) {
    			$out_time = date("d/m/Y - G:i",$page->r_time);		
    			$user = get_user_by( 'ID', $page->id_user);
    			echo $out_time.' hs';
          echo ' | <strong>User:</strong> '.$user->user_login;
          echo ' | <strong>IP:</strong> '.$page->r_ip.'<br>';
    		}
        echo '<br><a href="'.get_admin_url().'admin.php?page=wp-login-register">View all</a>';
    	} else {
    		echo 'No data saved yet.';
    	}
    };
    function wlr_add_your_dashboard_widget() {
    	wp_add_dashboard_widget( 'wlr_your_dashboard_widget', __( 'WP Login Register' ), 'wlr_your_dashboard_widget' );
    }
    add_action('wp_dashboard_setup', 'wlr_add_your_dashboard_widget' );
  }
}

////////////////////// Add page to admin
 $nombreTabla = $wpdb->prefix . "loginregister";
$results = $wpdb->get_results( "SELECT * FROM $nombreTabla ORDER BY r_time DESC" );

add_action('admin_menu', 'wlr_create_custom_panel');

function wlr_create_custom_panel() {
  //create admin page
  add_menu_page('WP Login Register', 'WP Login Register', 'manage_options', 'wp-login-register', 'wlr_custom_page');
}
//Layout admin page function
function wlr_custom_page(){
  echo '<div class="wrap">
  <h2>WP Login Register</h2>';

  // Query to creae table
  if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
  }    
  class Example_List_Table extends WP_List_Table {
    /* Prepare the items for the table to process   */
    public function wlr_prepare_items()  {
        $columns = $this->get_columns();
        $hidden = $this->wlr_get_hidden_columns();
        $sortable = $this->wlr_get_sortable_columns();

        $data = $this->wlr_table_data();
        if ($results) {
         usort( $data, array( &$this, 'sort_data' ) );
        }

        $perPage = 10;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );
if ($results) {
        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);
      }

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /** Override the parent columns method. Defines the columns to use in your listing table     */
    public function get_columns()  {
        $columns = array(
          'r_time'        => 'Time',
            'id_user'       => 'User',
            'r_ip' => 'IP'            
        );

        return $columns;
    }

    /** Define which columns are hidden     */
    public function wlr_get_hidden_columns()  {
        return array();
    }

    /** Define the sortable columns     */
    public function wlr_get_sortable_columns()  {
        return array(
          'r_time' => array('r_time', false),
          'r_ip' => array('r_ip', false),
          );
    }

    /**  Get the table data      */
    private function wlr_table_data()  {
      global $wpdb;
      $nombreTabla = $wpdb->prefix . "loginregister";
     $results = $wpdb->get_results( "SELECT * FROM $nombreTabla ORDER BY r_time DESC" );
     if ($results) {
      $data = array();
      foreach ( $results as $page ) {
        $out_time = date("d/m/Y - G:i",$page->r_time);    
        $user = get_user_by( 'id', $page->id_user);
        $data[] = array(
                    'r_time'        => $out_time." hs",
                    'id_user'       => $user->user_login,
                    'r_ip' => $page->r_ip                    
                    );       
      }
    }
        return $data;        
    }

    /**
     * Define what data to show on each column of the table
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     * @return Mixed
     */
    public function column_default( $item, $column_name )    {
        switch( $column_name ) {
          case 'r_time':
          case 'id_user':
          case 'r_ip':
          return $item[ $column_name ];

          default:
          return print_r( $item, true ) ;
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b )  {
        // Set defaults
        $orderby = 'r_time';
        $order = 'desc';

        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))   {
            $orderby = $_GET['orderby'];
        }

        // If order is set use this as the order
        if(!empty($_GET['order']))    {
            $order = $_GET['order'];
        }
        $result = strcmp( $a[$orderby], $b[$orderby] );

        if($order === 'asc')   {
            return $result;
        }
        return -$result;
    }
}
  
  $myListTable = new Example_List_Table();
  $myListTable->wlr_prepare_items(); 
  $myListTable->display(); 




  echo '</div>';
///////////////////////////////////////////////////////
}

?>