<?php
/**
 * Plugin Name: Post Expiring
 * Description: Allows you to add an expiration date to posts.
 * Version: 1.4
 * Author: Piotr Potrebka
 * Author URI: http://potrebka.pl
 * License: GPL2
 */
new ExpiringPosts();
class ExpiringPosts {

	public function __construct() {
		load_plugin_textdomain('postexpiring', false, basename( dirname( __FILE__ ) ) . '/languages' );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'add_expiring_field') );
		add_action( 'save_post', array( $this, 'save_post_meta' ), 10, 2 );	
		
		add_filter( 'manage_post_posts_columns', array( $this, 'manage_posts_columns' ), 5 );
		add_action( 'manage_post_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 5, 2 );	
		
		add_filter( 'manage_page_posts_columns', array( $this, 'manage_posts_columns' ), 5 );
		add_action( 'manage_page_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 5, 2 );
		
		add_filter( 'posts_clauses', array( $this, 'posts_clauses' ), 10, 2 );
		
		add_filter( 'get_next_post_join', array( $this, 'posts_join_clauses' ), 10, 2 );
		add_filter( 'get_previous_post_join', array( $this, 'posts_join_clauses' ), 10, 2 );		
		add_filter( 'get_next_post_where', array( $this, 'posts_where_clauses' ), 10, 2 );
		add_filter( 'get_previous_post_where', array( $this, 'posts_where_clauses' ), 10, 2 );
		
	}
	
	public function posts_join_clauses( $join ) {
		global $wpdb;
		$join .= " LEFT JOIN $wpdb->postmeta AS exp ON (p.ID = exp.post_id AND exp.meta_key = 'postexpired') ";
		return $join;
	}
	
	public function posts_where_clauses( $where ) {
		global $wpdb;
		$current_date = current_time( 'mysql' );
		$where .= " AND ( (exp.meta_key = 'postexpired' AND CAST(exp.meta_value AS CHAR) > '".$current_date."') OR exp.post_id IS NULL ) ";
		return $where;
	}
	
	public function posts_clauses( $clauses, $query ) {
		global $wpdb;
		if ( is_admin() AND ( !$query->is_main_query() || !is_feed() ) ) return $clauses;
		$current_date = current_time( 'mysql' );
		$clauses['join'] .= " LEFT JOIN $wpdb->postmeta AS exp ON ($wpdb->posts.ID = exp.post_id AND exp.meta_key = 'postexpired') ";
		$clauses['where'] .= " AND ( (exp.meta_key = 'postexpired' AND CAST(exp.meta_value AS CHAR) > '".$current_date."') OR exp.post_id IS NULL ) ";
		return $clauses;
	}
	
	public function enqueue_scripts( $hook ) {
		if( 'post-new.php' != $hook AND 'post.php' != $hook ) return;
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'datetimepicker', plugins_url('assets/js/jquery.datetimepicker.js', __FILE__), array('jquery'), null, true );
		wp_enqueue_script( 'post-expiring', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), null, true );
		wp_enqueue_style( 'post-expiring', plugins_url('assets/css/post-expiring.css', __FILE__) );
	}
		
	public function manage_posts_columns( $columns ){
		$columns['expiring'] = __( 'Expiring', 'postexpiring' );
		return $columns;
	}
	
	public function manage_posts_custom_column( $column_name, $id ){
		global $post;
		if( $column_name === 'expiring' ){
			$postexpired = get_post_meta( $post->ID, 'postexpired', true );
			if( preg_match("/^\d{4}-\d{2}-\d{2}$/", $postexpired) ) {
				$postexpired .= ' 00:00';
			}
			echo !empty($postexpired) ? $postexpired : __('Never');
		}
	}
	
	public function save_post_meta( $post_id, $post ) {
		if ( $post_id === null || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) return;
		if( isset($_POST['post_expiring']) ) {
			preg_match("/\d{4}\-\d{2}-\d{2} \d{2}:\d{2}/", $_POST['post_expiring'], $expired);
			if( empty( $expired ) ) {
				delete_post_meta( $post_id, 'postexpired' );
			}
			if ( !empty($_POST['post_expiring']) AND isset( $expired[0] ) ) {
				add_post_meta( $post_id, 'postexpired', esc_sql( $_POST['post_expiring'] ), true ) || update_post_meta( $post_id, 'postexpired', esc_sql( $_POST['post_expiring'] ) );
			}
		}
	}
	
	public function add_expiring_field() {
		
		global $post;
		if( !$post->post_type OR ( $post->post_type != 'page' AND $post->post_type != 'post' ) ) return;
		$screen = get_current_screen();
		if( $screen->base != 'post' ) return;
		$postexpired = get_post_meta( $post->ID, 'postexpired', true ); // pobieram meta dane 
		if( preg_match("/^\d{4}-\d{2}-\d{2}$/", $postexpired) ) {
			$postexpired .= ' 00:00';
		}
		$lang = explode( '-', get_bloginfo( 'language' ) );
		$lang = isset($lang[0]) ? $lang[0] : 'en';
		?>
		<script>
		jQuery(document).ready( function($) {
			$('.expiring-datepicker').datetimepicker({
				format:'Y-m-d H:i',
				lang: '<?php echo $lang; ?>',
				timepickerScrollbar:false
			});
		})
		</script>
		<div class="misc-pub-section curtime misc-pub-curtime">
			<span id="timestamp"><?php _e('Expiring:', 'postexpiring'); ?></span> <span class="setexpiringdate"><?php echo !empty($postexpired) ? $postexpired : __('Never'); ?></span>
			<a href="#edit_expiringdate" class="edit-expiringdate hide-if-no-js"><span aria-hidden="true"><?php _e( 'Edit' ); ?></span> <span class="screen-reader-text"><?php _e('Edit expiring date', 'postexpiring'); ?></span></a>
			<div id="expiringdatediv" class="hide-if-js">
				<div class="wrap"><input type="text" class="expiring-datepicker" data-exdate="<?php echo esc_attr($postexpired); ?>" value="<?php echo esc_attr($postexpired); ?>" style="font-size: 12px;" name="post_expiring" /><a class="set-expiringdate hide-if-no-js button" href="#edit_expiringdate"><?php _e('OK'); ?></a></div>
				<div><a class="cancel-expiringdate hide-if-no-js button-cancel" href="#edit_expiringdate"><?php _e('Cancel'); ?></a></div>				
			</div>
		</div>
		<?php
	}
}

