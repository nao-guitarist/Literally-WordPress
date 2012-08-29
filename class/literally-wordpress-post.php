<?php


class LWP_Post extends Literally_WordPress_Common{
	
	/**
	 * Payable post types
	 * @var array
	 */
	public $post_types = array();
	
	/**
	 * Post type array
	 * @var array
	 */
	private $custom_post_type = array();
	
	/**
	 * File directory
	 * @var string
	 */
	public $file_directory = '';
	
	/**
	 * Additional mime types to upload
	 * @var type 
	 */
	private $additional_mimes = array(
		"epub" => "application/epub+zip",
		"azw" => "application/octet-stream"
	);
	
	/**
	 * @see Literally_WordPress_Common
	 */
	public function set_option($option) {
		$option = shortcode_atts(array(
			'payable_post_types' => array(),
			'custom_post_type' => array(),
			'dir' => ''
		), $option);
		if(!empty($option['custom_post_type'])){
			if(empty($option['custom_post_type']['singular'])){
				$option['custom_post_type']['singular'] = $option['custom_post_type']['name'];
			}
			if(false === array_search($option['custom_post_type']['slug'], $option['payable_post_types'])){
				array_push($option['payable_post_types'], $option['custom_post_type']['slug']);
			}
		}
		$this->post_types = apply_filters('lwp_payable_post_types', $option['payable_post_types']);
		$this->custom_post_type = $option['custom_post_type'];
		$this->file_directory = $option['dir'];
		$this->enabled = !empty($this->post_types);
	}
	
	/**
	 * @see Literally_WordPress_Common
	 */
	public function on_construct() {
		if(!empty($this->custom_post_type)){
			add_action("init", array($this, "register_post_type"));
		}
		if($this->is_enabled()){
			add_action('admin_menu', array($this, 'register_metabox'));
			add_action("admin_init", array($this, "update_devices"));
			add_action("save_post", array($this, "save_post"));
			//Tiny MCE
			add_filter("mce_external_plugins", array($this, "mce_plugin"));
			add_filter("mce_external_languages", array($this, "mce_lang"));
			add_filter("mce_buttons_4", array($this, "mce_button"));
			//Filter
			add_filter('the_content', array($this, 'the_content'));
			//Media Uploader
			add_filter("media_upload_tabs", array($this, "upload_tab"));
			add_action("media_upload_ebook", array($this, "generate_tab"));
			add_filter("upload_mimes", array($this, "upload_mimes"));
			//Short code
			add_shortcode("lwp", array($this, "shortcode_capability"));
			add_shortcode('buynow', array($this, 'shortcode_buynow'));
		}
	}
	
	/**
	 * Register custom post type
	 */
	public function register_post_type(){
		if(!empty($this->custom_post_type)){
			$labels = array(
				'name' => $this->custom_post_type['name'],
				'singular_name' => $this->custom_post_type['singular'],
				'add_new' => $this->_('Add New'),
				'add_new_item' => sprintf($this->_('Add New %s'), $this->custom_post_type['singular']),
				'edit_item' => sprintf($this->_("Edit %s"), $this->custom_post_type['name']),
				'new_item' => sprintf($this->_('Add New %s'), $this->custom_post_type['singular']),
				'view_item' => sprintf($this->_('View %s'), $this->custom_post_type['singular']),
				'search_items' => sprintf($this->_("Search %s"), $this->custom_post_type['name']),
				'not_found' =>  sprintf($this->_('No %s was found.'), $this->custom_post_type['singular']),
				'not_found_in_trash' => sprintf($this->_('No %s was found in trash.'), $this->custom_post_type['singular']), 
				'parent_item_colon' => ''
			);
			$args = array(
				'labels' => $labels,
				'public' => true,
				'publicly_queryable' => true,
				'show_ui' => true,
				'query_var' => true,
				'rewrite' => true,
				'capability_type' => 'post',
				'hierarchical' => true,
				'menu_position' => 9,
				'has_archive' => true,
				'supports' => array('title','editor','author','thumbnail','excerpt', 'comments', 'custom-fields'),
				'show_in_nav_menus' => true,
				'menu_icon' => $this->url."/assets/book.png"
			);
			register_post_type($this->custom_post_type['slug'], $args);
		}
	}
	
	/**
	 * TinyMCEにプラグインを登録する
	 * @param array $plugin_array
	 * @return array
	 */
	public function mce_plugin($plugin_array){
		$plugin_array['lwpShortCode'] = $this->url."assets/js/tinymce.js";
		return $plugin_array;
	}
	
	/**
	 * TinyMCEの言語ファイルを追加する
	 * @param array $languages
	 * @return array
	 */
	public function mce_lang($languages){
		$languages["lwpShortCode"] = $this->dir.DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR."js".DIRECTORY_SEPARATOR."tinymce-lang.php";
		return $languages;
	}
	
	/**
	 * TinyMCEのボタンを追加する
	 * @param array $buttons
	 * @return array
	 */
	public function mce_button($buttons){
		if(false !== array_search(get_current_screen()->post_type, $this->post_types) ){
			array_push($buttons, "lwpListBox", "separator");
			array_push($buttons, 'lwpBuyNow', "separator");
		}
		return $buttons;
	}
	
	/**
	 * Register meta box
	 */
	public function register_metabox(){
		//Add metaboxes
		foreach($this->post_types as $post){
			add_meta_box('lwp-detail', $this->_("LWP Post sell Setting"), array($this, 'post_metabox_form'), $post, 'side', 'core');
		}
	}
	
	/**
	 * Add form to post edit screen
	 * @param object $post
	 * @param array $metabox
	 * @return void
	 */
	public function post_metabox_form($post, $metabox){
		require_once $this->dir.DIRECTORY_SEPARATOR."form-template".DIRECTORY_SEPARATOR."edit-detail.php";
		do_action('lwp_payable_post_type_metabox', $post, $metabox);
	}


	/**
	 * Executed when post is saved
	 * @global Literally_WordPress $lwp
	 */
	public function save_post($post_id){
		global $lwp;
		if(isset($_REQUEST["_lwpnonce"]) && wp_verify_nonce($_REQUEST["_lwpnonce"], "lwp_price")){
			//Required. so empty, show error message
			$price = preg_replace("/[^0-9.]/", "", mb_convert_kana($_REQUEST["lwp_price"], "n"));
			if(preg_match("/^[0-9.]+$/", $price)){
				update_post_meta($post_id, $lwp->price_meta_key, $price);
			}else{
				$lwp->message[] = $this->_("Price must be numeric.");
				$lwp->error = true;
			}
		} 
	}

	/**
	 * Output automatic file tables
	 * @global wpdb $wpdb
	 * @global Literally_WordPress $lwp
	 * @param string $content
	 * @return string
	 */
	public function the_content($content){
		global $wpdb, $lwp;
		if(in_the_loop() && false !== array_search(get_post_type(), $this->post_types) && $lwp->needs_auto_layout()){
			$content .= lwp_show_form();
			//if file exists, display file list table.
			if($wpdb->get_var($wpdb->prepare("SELECT COUNT(ID) FROM {$lwp->files} WHERE book_id = %d", get_the_ID()))){
				$content .= lwp_get_device_table().lwp_get_file_list();
			}
		}
		return $content;
	}
	
		
	/**
	 * Short codes for capability
	 * @since 0.8
	 * @global Literally_WordPress $lwp
	 * @param array $atts
	 * @param string $contents
	 * @return string
	 */
	public function shortcode_capability($atts, $contents = null){
		global $lwp;
		//属性値を抽出
		extract(shortcode_atts(array("user" => "owner"), $atts));
		//省略形を優先する
		if(isset($atts[0])){
			$user = $atts[0];
		}
		//属性値によって返す値を検討
		switch($user){
			case "subscriber": //登録済ユーザーの場合
				return is_user_logged_in() ? wpautop($contents) : "";
				break;
			case "non-owner": //オーナーではない場合
				return $lwp->is_owner() ? "" : wpautop($contents);
				break;
			case "non-subscriber": //登録者ではない場合
				return is_user_logged_in() ? "" : wpautop($contents);
				break;
			default:
				return $lwp->is_owner() ? wpautop($contents) : "";
				break;
		}
	}
	
	/**
	 * Show Buynow
	 * @param type $atts
	 * @return string
	 */
	public function shortcode_buynow($atts){
		if(!isset($atts[0]) || !$atts[0]){
			return lwp_buy_now(null, false);
		}elseif($atts[0] == 'link'){
			return lwp_buy_now(null, null);
		}else{
			return lwp_buy_now(null, $atts[0]);
		}
	}
	
	/**
	 * Return device information
	 * 
	 * @since 0.3
	 * @global wpdb $wpdb
	 * @global Literally_WordPress $lwp
	 * @param object $file (optional) 指定した場合はファイルに紐づけられた端末を返す
	 * @return array
	 */
	public function get_devices($file = null){
		global $wpdb, $lwp;
		if(is_numeric($file)){
			$file_id = $file;
		}elseif(is_object($file)){
			$file_id = $file->ID;
		}
		if(!is_null($file)){
			$prepared = <<<EOS
				SELECT * FROM {$lwp->devices} as d
				LEFT JOIN {$lwp->file_relationships} as f
				ON d.ID = f.device_id
				WHERE f.file_id = %d
EOS;
			$sql = $wpdb->prepare($prepared, $file_id);
		}else{
			$sql = "SELECT * FROM {$lwp->devices}";
		}
		return $wpdb->get_results($sql);
	}

	
	/**
	 * CRUD interface for device
	 * @global Literally_WordPress $lwp
	 * @global wpdb $wpdb
	 */
	public function update_devices(){
		global $wpdb, $lwp;
		//Registere form
		if(isset($_REQUEST["_wpnonce"]) && wp_verify_nonce($_REQUEST['_wpnonce'], "lwp_add_device")){
			$req = $wpdb->insert(
				$lwp->devices,
				array(
					"name" => $_REQUEST["device_name"],
					"slug" => $_REQUEST["device_slug"]
				),
				array("%s", "%s")
			);
			if($req)
				$lwp->message[] = $this->_("Device added.");
			else
				$lwp->message[] = $this->_("Failed to add device.");
		}
		//Bulk action
		if(isset($_GET['devices'], $_REQUEST["_wpnonce"]) && wp_verify_nonce($_REQUEST['_wpnonce'], "bulk-devices") && !empty($_GET['devices'])){
			switch($_GET['action']){
				case "delete":
					$ids = implode(',', array_map('intval', $_GET['devices']));
					$wpdb->query("DELETE FROM {$lwp->devices} WHERE ID IN ({$ids})");
					$wpdb->query("DELETE FROM {$lwp->file_relationships} WHERE device_id IN ({$ids})");
					$lwp->message[] = $this->_("Device deleted.");
					break;
			}
		}
		//Update
		if(isset($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], 'edit_device')){
			$wpdb->update(
				$lwp->devices,
				array(
					'name' => (string)$_POST['device_name'],
					'slug' => (string)$_POST['device_slug']
				),
				array('ID' => $_POST['device_id']),
				array('%s', '%s'),
				array('%d')
			);
			$lwp->message[] = $this->_('Device updated.');
		}
	}

	/**
	 * Add media uploader tab
	 * @param array $tabs
	 * @return array
	 */
	public function upload_tab($tabs){
		$post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']): 0;
		if($this->is_enabled() && $this->is_payable(get_post_type($post_id))){
			$tabs["ebook"] = $this->_('Downloadble Contents');
		}
		return $tabs;
	}

	/**
	 * Generage tab with hooked 
	 */
	public function generate_tab(){
		return wp_iframe(array($this, "media_iframe"));
	}
	
	/**
	 * Output uploader inside iframe.
	 */
	public function media_iframe(){
		media_upload_header();
		require_once $this->dir.DIRECTORY_SEPARATOR."admin".DIRECTORY_SEPARATOR."upload.php";
	}
	
	/**
	 * Returns list of files
	 * @since 0.3
	 * @global Literally_WordPress $lwp
	 * @param int $book_id (optional)
	 * @param int $file_id (optional)
	 * @return array|object
	 */
	public function get_files($book_id = null, $file_id = null){
		global $wpdb, $lwp;
		if($book_id && $file_id){
			return array();
		}
		$query = "SELECT * FROM {$lwp->files} WHERE";
		$files = array();
		if($file_id){
			$query .= " ID = %d";
			return $wpdb->get_row($wpdb->prepare($query, $file_id));
		}else{
			$query .= " book_id = %d";
			return $wpdb->get_results($wpdb->prepare($query, $book_id));
		}
	}
	
	/**
	 * UPload file
	 * 
	 * @global wpdb $wpdb
	 * @global Literally_WordPress $lwp
	 * @param int $book_id
	 * @param string $name
	 * @param string $file
	 * @param string $path
	 * @param array $devices
	 * @param string $desc
	 * @param int $public
	 * @param int $free
	 * @return boolean
	 */
	public function upload_file($book_id, $name, $file, $path, $devices, $desc = "", $public = 1, $free = 0){
		global $wpdb, $lwp;
		//Find directory and create if not exists.
		$book_dir = $this->file_directory.DIRECTORY_SEPARATOR.$book_id;
		if(!is_dir($book_dir)){
			if(!@mkdir($book_dir)){
				return false;
			}
		}
		//Create new file
		$file = sanitize_file_name($file);
		//Move file
		if(!@move_uploaded_file($path, $book_dir.DIRECTORY_SEPARATOR.$file)){
			return false;
		}
		//Write to database
		$id = $wpdb->insert(
			$lwp->files,
			array(
				"book_id" => $book_id,
				"name" => $name,
				"detail" => $desc,
				"file" => $file,
				"public" => $public,
				"free" => $free,
				"registered" => gmdate("Y-m-d H:i:s"),
				"updated" => gmdate("Y-m-d H:i:s")
			),
			array("%d", "%s", "%s", "%s", "%d", "%d", "%s", "%s")
		);
		//Registr device
		$inserted_id = $wpdb->insert_id;
		if($inserted_id && !empty($devices)){
			foreach($devices as $d){
				$wpdb->insert(
					$lwp->file_relationships,
					array(
						"file_id" => $inserted_id,
						"device_id" => $d
					),
					array("%d", "%d")
				);
			}
		}
		return $wpdb->insert_id;
	}
	
	/**
	 * Upadte file table
	 * @global wpdb $wpdb
	 * @global Literally_WordPress $lwp
	 * @param int $file_id
	 * @param string $name
	 * @param array $devices
	 * @param string $desc
	 * @param int $public default 1
	 * @param int $free default 0
	 * @return boolean
	 */
	private function update_file($file_id, $name, $devices, $desc, $public = 1, $free = 0){
		global $wpdb, $lwp;
		$req = $wpdb->update(
			$lwp->files,
			array(
				"name" => $name,
				"detail" => $desc,
				"public" => $public,
				"free" => $free,
				"updated" => gmdate("Y-m-d H:i:s")
			),
			array("ID" => $file_id),
			array("%s", "%s", "%d", "%d", "%s"),
			array("%d")
		);
		if($req){
			//Clear all realtionships
			$wpdb->query($wpdb->prepare("DELETE FROM {$lwp->file_relationships} WHERE file_id = %d", $file_id));
			if(!empty($devices)){
				foreach($devices as $d){
					//Create new realtionships
					$wpdb->insert(
						$lwp->file_relationships,
						array(
							"file_id" => $file_id,
							"device_id" => $d
						),
						array("%d","%d")
					);
				}
			}
			return true;
		}else
			return false;
	}
	
	/**
	 * Delete specified file
	 *
	 * @global wpdb $wpdb
	 * @global Literally_WordPress $lwp
	 * @param int $file_id 
	 * @return boolean
	 */
	private function delete_file($file_id){
		global $wpdb, $lwp;
		$file = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$lwp->files} WHERE ID = %d", $file_id));
		if(!$file){
			return false;
		}else{
			//delete file
			if(!unlink($this->file_directory.DIRECTORY_SEPARATOR.$file->book_id.DIRECTORY_SEPARATOR.$file->file))
				return false;
			else{
				if($wpdb->query("DELETE FROM {$lwp->files} WHERE ID = {$file->ID}")){
					$wpdb->query($wpdb->prepare("DELETE FROM {$lwp->file_relationships} WHERE file_id = %d", $file_id));
					return true;
				}else
					return false;
			}
		}
	}
	
	/**
	 * Return error message about uploaded file
	 * @param array $info
	 * @return boolean
	 */
	private function file_has_error($info){
		$message = '';
		switch($info["error"]){
			 case UPLOAD_ERR_INI_SIZE: 
                $message = $this->_("Uploaded file size exceeds the &quot;upload_max_filesize&quot; value defined in php.ini"); 
                break; 
            case UPLOAD_ERR_FORM_SIZE: 
                $message = $this->_("Uploaded file size exceeds"); 
                break; 
            case UPLOAD_ERR_PARTIAL: 
                $message = $this->_("File has been uploaded incompletely. Check your internet connection."); 
                break; 
            case UPLOAD_ERR_NO_FILE: 
                $message = $this->_("No file was uploaded."); 
                break; 
            case UPLOAD_ERR_NO_TMP_DIR: 
                $message = $this->_("No tmp directory exists. Contact to your server administrator."); 
                break; 
            case UPLOAD_ERR_CANT_WRITE: 
                $message = $this->_("Failed to save the uploaded file. Contact to your server administrator.");; 
                break; 
            case UPLOAD_ERR_EXTENSION: 
                $message = $this->_("PHP stops uploading."); 
                break;
			case UPLOAD_ERR_OK:
				$message = false;
				break;
		}
		return $message;
	}
	
	/**
	 * Returns file path if exists
	 * @param object|int $file file object or file id.
	 * @return false|string
	 */
	public function get_file_path($file){
		if(is_numeric($file)){
			$file = $this->get_files(null, $file);
			if(!$file){
				return false;
			}
		}
		$path = $this->file_directory.DIRECTORY_SEPARATOR.$file->book_id.DIRECTORY_SEPARATOR.$file->file;
		return file_exists($path) ? $path : false;
	}
	
	/**
	 * Detect mime types from uploaded file
	 * @param string $path
	 * @return string|false
	 */
	public function detect_mime($path){
		$mime = false;
		$ext = pathinfo($path, PATHINFO_EXTENSION);
		if(array_key_exists($ext, $this->additional_mimes)){
			$mime = $this->additional_mimes[$ext];
		}
		if(!$mime){
			foreach(get_allowed_mime_types() as $e => $m){
				if(false !== strpos($e, $ext)){
					$mime = $m;
					break;
				}
			}
		}
		return $mime;
	}
	
	/**
	 * Add mime types to uploadable contents
	 * @param array $mimes
	 * @return array
	 */
	public function upload_mimes($mimes){
		foreach($this->additional_mimes as $ext => $mime){
			$mimes[$ext] = $mime;
		}
		return apply_filters('lwp_upload_mimes', $mimes);
	}
	
	/**
	 * Output file 
	 * @global boolean $is_IE
	 * @param object $file
	 */
	public function print_file($file){
		global $is_IE;
		if(is_numeric($file)){
			$file = $this->get_files(null, $file);
		}
		//Filter path info. You can override it.
		$path = apply_filters('lwp_file_path', $this->get_file_path($file), $file, get_current_user_id());
		if(!$path || !file_exists($path)){
			$this->kill($this->_('Specified file does not exist.'), 404);
		}
		/*
		 * Here you are, all green.
		 * Let's start print file.
		 */
		//Get file information.
		$mime = $this->detect_mime($file->file);
		$size = filesize($path);
		//Create header
		//If IE and under SSL, echo cache control.
		// @see http://exe.tyo.ro/2010/01/nocachesslie.html
		$cache_header = $is_IE ? array(
			'Cache-Control' => 'public',
			"Pragma" => ''
		) : array();
		$headers = apply_filters('lwp_file_header', array_merge($cache_header, array(
			'Content-Type' => $mime,
			'Content-Disposition' => "attachment; filename=\"{$file->file}\"",
			'Content-Length' => $size
		)), $file, $path);
		//Calculate download rate. Especially for memroy limit.
		//1kb
		$kb = 1024;
		//minimum = 100kb or 1/100 of file size, maximum 2MB
		$per_size = apply_filters('lwp_download_size_per_second', min(max($size / 100, 100 * $kb), $kb * 2048), $file, $path);
		//Do normal operation if flg is true
		if(apply_filters('lwp_ob_download', true, $file, $headers)){
			//Output header
			foreach($headers as $key => $value){
				header("{$key}: {$value}");
			}
			ob_end_flush();
			ob_start('mb_output_handler');
			//Read File
			set_time_limit(0);
			$handle = fopen($path, "r");
			while(!feof($handle)){
				//Output specified size.
				echo fread($handle, $per_size);
				//Flush buffer and sleep.
				ob_flush();
				flush();
				sleep(1);
			}
			//Done!
			fclose($handle);
			//Save log
			$this->save_donwload_log($file->ID);
		}
		//Finish script
		exit;
	}
	
	/**
	 * Save download log
	 * @global wpdb $wpdb
	 * @global Literally_WordPress $lwp
	 * @param int $file_id
	 * @param int $user_id
	 */
	public function save_donwload_log($file_id, $user_id = null){
		global $wpdb, $lwp;
		if(is_null($user_id)){
			$user_id = get_current_user_id();
		}
		$wpdb->insert($lwp->file_logs, array(
			'file_id' => $file_id,
			'user_id' => $user_id,
			'user_agent' => $_SERVER['HTTP_USER_AGENT'],
			'ip_address' => $_SERVER['REMOTE_ADDR'],
			'updated' => gmdate('Y-m-d H:i:s')
		), array('%d', '%d', '%s', '%s', '%s'));
	}
	
	/**
	 * Return if post type is payable
	 * @param string $post_type
	 * @return boolean
	 */
	public function is_payable($post_type){
		return false !== array_search((string)$post_type, $this->post_types);
	}
}