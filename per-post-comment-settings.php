<?php
/*
Plugin Name: Per Post Comment Settings
Plugin URI: http://082net.com/tag/per-post-comment-settings/?orderby=modified
Version: 0.121
Description: You can change comment settings post by post on WP 2.7 or greater.
Author: Cheon, Youngmin
Author URI: http://082net.com
*/

/*
*********************************************************
*	 Lisense : GNU GPL(http://www.gnu.org/copyleft/gpl.html)					*
*	 This program is free software; you can redistribute it and/or				*
*	 modify it under the terms of the GNU General Public License					*
*	 as published by the Free Software Foundation; either version 2			*
*	 of the License, or (at your option) any later version.								*
*																												*
*	 This program is distributed in the hope that it will be useful,				*
*	 but WITHOUT ANY WARRANTY; without even the implied warranty of	*
*	 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the	*
*	 GNU General Public License for more details.											*
*********************************************************
*/

class WP_PPCS {
	var $support_previous_plugins = false;
	var $version = '0.12';
	var $base, $plugin, $url, $path;
	var $defaults;
	var $tmp_funcs;
	var $is_ping = false;
	var $current_post;
	var $options = array('comment_registration', 'close_comments_for_old_posts', 'close_comments_days_old', 'thread_comments', 'thread_comments_depth', 'page_comments', 'default_comments_page', 'comment_order', 'comments_per_page');
	var $no_int_options = array('default_comments_page', 'comment_order');

	var $version_check_url = 'http://082net.com/update-check.php?check_plugin=per-post-comment-settings';
	var $plugin_home = 'http://082net.com/tag/per-post-comment-settings/?orderby=modified';

	function WP_PPCS() {
		$this->__construct();
	}

	function __construct($type='comments') {
		if ( $type != 'comments' || $type != 'comment' )
			$this->is_ping = true;
		$this->plugin = plugin_basename(__FILE__);
		$this->base = dirname($this->plugin);
		$this->url = WP_PLUGIN_URL . '/' . dirname($this->plugin);
		$this->path = WP_PLUGIN_DIR . '/' . dirname($this->plugin);

		// localize
//		load_plugin_textdomain('wp-ppcs', false, dirname($this->plugin).'/languages');

		// Hooks
		$this->register_hooks();
	}

	function register_hooks() {
		add_action('admin_menu', array(&$this, 'admin_init'));
		add_action('save_post', array(&$this, 'update_meta'));
		add_action('init', array(&$this, 'register_pre_option'));
	}

	function admin_init() {
		global $pagenow;
		if ( in_array( $pagenow, array('post.php', 'post-new.php', 'page.php', 'page-new.php') ) ) {
			// add meta box to post(page) editor
			add_meta_box('wp_ppcs', __('Other Comment Settings'), array(&$this, 'meta_box'), 'post', 'side', 'default');
			add_meta_box('wp_ppcs', __('Other Comment Settings'), array(&$this, 'meta_box'), 'page', 'side', 'default');
		}
	}

	function get_post_settings($post_id=0, $merge=false) {
		global $post;
		$post_id = (int)$post_id;
		if ( 0 == $post_id )
			$post_id = $post->ID;
		$default = $this->get_wp_settings();
		if ( 0 == $post_id )
			return ( $merge ? $default : array() );

		$conf = array();
		foreach ($this->options as $name) {
			$tmp = get_post_meta($post_id, $name, true);
			if ( '' !== $tmp ) {
				$conf[$name] = !in_array($name, $this->no_int_options) ? (int)$tmp : trim($tmp);
			}
		}
		// old plugin support
		if ( $this->support_previous_plugins ) {
			if ( $threaded_and_paged = get_post_meta($post_id, 'tpg_comments', true) ) {// tp-guestbook
				$threaded_and_paged = strtolower($threaded_and_paged);
				$threaded_and_paged = $threaded_and_paged == 'on' ? 1 : 0;
				if ( $threaded_and_paged && !($conf['thread_comments'] && $conf['page_comments']) )
					$conf['thread_comments'] = $conf['page_comments'] = 1;
				elseif ( !$threaded_and_paged && ($conf['thread_comments'] || $conf['page_comments']) )
					$conf['thread_comments'] = $conf['page_comments'] = 0;
			}
			if ( $paged = get_post_meta($post_id, 'paged_comments', true) ) {// paged comments, tp-guestbook.
				$paged = strtolower($paged);
				if ( $paged == 'on' && !$conf['page_comments'] )
					$conf['page_comments'] = 1;
				elseif ( $paged == 'off' && $conf['page_comments'] )
					$conf['page_comments'] = 0;
			}
			if ( $order = get_post_meta($post_id, 'comment_ordering', true) )// paged comments, tp-guestbook.
				$conf['comment_order'] = strtolower($order);// if neither 'desc' nor 'asc', follow default setting.
		}

		if ( $merge )
			$conf = array_merge($default, $conf);
		return $conf;
	}

	function get_wp_settings() {
		if ( isset($this->defaults) )
			return $this->defaults;

		$this->defaults = array();
		foreach ($this->options as $name)
			$this->defaults[$name] = !in_array($name, $this->no_int_options) ? (int)get_option($name) : get_option($name);

		return $this->defaults;
	}

	function update_meta($post_id) {
		if ( !isset($_POST['wp_ppcs_settings']) )
			return;
		$defaults = $this->get_wp_settings();

		foreach ($this->options as $name)
			$_POST[$name] = !in_array($name, $this->no_int_options) ? (int)$_POST[$name] : trim($_POST[$name]);

		foreach ($defaults as $key => $def) {
			$current = get_post_meta($post_id, $key, true);
			if ( '' !== $current && $_POST[$key] == $current )
				continue;// already applied
			if ( '' !== $current && $def == $_POST[$key] )
				delete_post_meta($post_id, $key);// we don't need custom value no more.
			elseif ( $def != $_POST[$key] ) {
				if ( '' === $current )
					add_post_meta($post_id, $key, $_POST[$key], true);
				else
					update_post_meta($post_id, $key, $_POST[$key]);
			}
		}
	}

	function meta_box($type) {
		global $post;
		$conf = $this->get_post_settings($post->ID, true);// get merged settings
?>
<div class="form-table">
<input type="hidden" name="wp_ppcs_settings" value="1" />
<p><label for="comment_registration">
<input name="comment_registration" type="checkbox" id="comment_registration" value="1" <?php checked('1', $conf['comment_registration']); ?> />
<?php _e('Users must be registered and logged in to comment') ?>
</label></p>

<p><label for="close_comments_for_old_posts">
<input name="close_comments_for_old_posts" type="checkbox" id="close_comments_for_old_posts" value="1" <?php checked('1', $conf['close_comments_for_old_posts']); ?> />
<?php printf( __('Automatically close comments on articles older than %s days'), '</label><input name="close_comments_days_old" type="text" id="close_comments_days_old" value="' . esc_attr($conf['close_comments_days_old']) . '" class="small-text" />') ?>
</p>

<p><label for="thread_comments">
<input name="thread_comments" type="checkbox" id="thread_comments" value="1" <?php checked('1', $conf['thread_comments']); ?> />
<?php

$maxdeep = (int) apply_filters( 'thread_comments_depth_max', 10 );

$thread_comments_depth = '</label><select name="thread_comments_depth" id="thread_comments_depth">';
for ( $i = 1; $i <= $maxdeep; $i++ ) {
	$thread_comments_depth .= "<option value='$i'";
	if ( $conf['thread_comments_depth'] == $i ) $thread_comments_depth .= " selected='selected'";
	$thread_comments_depth .= ">$i</option>";
}
$thread_comments_depth .= '</select>';

printf( __('Enable threaded (nested) comments %s levels deep'), $thread_comments_depth );

?></p>

<p><label for="page_comments">
<input name="page_comments" type="checkbox" id="page_comments" value="1" <?php checked('1', $conf['page_comments']); ?> />
<?php

$default_comments_page = '</label><label for="default_comments_page"><select name="default_comments_page" id="default_comments_page"><option value="newest"';
if ( 'newest' == $conf['default_comments_page'] ) $default_comments_page .= ' selected="selected"';
$default_comments_page .= '>' . __('last') . '</option><option value="oldest"';
if ( 'oldest' == $conf['default_comments_page'] ) $default_comments_page .= ' selected="selected"';
$default_comments_page .= '>' . __('first') . '</option></select>';

printf( __('Break comments into pages with %1$s comments per page and the %2$s page displayed by default'), '</label><label for="comments_per_page"><input name="comments_per_page" type="text" id="comments_per_page" value="' . esc_attr($conf['comments_per_page']) . '" class="small-text" />', $default_comments_page );

?></label>
</p>

<p><label for="comment_order"><?php

$comment_order = '<select name="comment_order" id="comment_order"><option value="asc"';
if ( 'asc' == $conf['comment_order'] ) $comment_order .= ' selected="selected"';
$comment_order .= '>' . __('older') . '</option><option value="desc"';
if ( 'desc' == $conf['comment_order'] ) $comment_order .= ' selected="selected"';
$comment_order .= '>' . __('newer') . '</option></select>';

printf( __('Comments should be displayed with the %s comments at the top of each page'), $comment_order );

?></label></p>
</div>
<?php
	}

	function pre_option($key) {
		global $post, $id, $withcomments;
		if ( is_admin() || is_feed() )
			return false;
		
		$post_id = isset($post) ? (int) $post->ID : 0;
		if (!$post_id && isset($id))
			$post_id = (int) $id;
		if ( !$post_id && isset($_POST['comment_post_ID']) )
			$post_id = (int) $_POST['comment_post_ID'];
		if (!$post_id)
			return false;

		if ( '' !== $value = get_post_meta($post_id, $key, true) ) {// automatically cached by wordpress
			return $value;
		}

		return false;
	}

	function register_pre_option() {
		foreach ($this->options as $name) {
			$func = create_function('$a', 'return WP_PPCS::pre_option(\''.$name.'\');');
			add_filter("pre_option_{$name}", $func);
//			$this->tmp_funcs[$name] = $func;
		}
	}

	function &get_instance() {
		static $instance = array();
		if ( empty( $instance ) ) {
			$instance[] =& new WP_PPCS();
		}
		return $instance[0];
	}

}
// end of class

$wp_ppcs =& WP_PPCS::get_instance();
?>