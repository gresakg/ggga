<?php

/*
Plugin Name: GA by GG
Plugin URI: http://demo.gresak.net/ggga
Description: Simple plugin for google analytics
Author: Gregor Grešak
Version: 3.1.0
Author URI: http://gresak.net
*/

$ggga = Ggga::instance(__FILE__);

class Ggga {

	protected $tracking_id;

	protected $action_hook;

	protected $code_inserted = false;

	protected $missing_hook;

	private static $instance;

	protected function __construct() {
		$this->set_tracking_id();
		$this->set_action_hook();
		add_action($this->action_hook, array($this,'print_ga'));
		add_action('wp_footer',array($this, 'is_code_inserted'));
		add_action( $this->action_hook, array($this, 'cf7_event_tracking'));
		add_action('wp_footer', array($this,'outbound_link_tracking'));
		add_action ( 'admin_init', array($this, 'register_settings'));
		add_action( 'admin_notices', array($this, 'tracking_id_missing'));
		add_filter('init', array($this,'updater'));
	}

	/**
	 * Sets the google analytics tracking id. If the option is not set, checks
	 * theme costumizer mod for compatibility reasons
	 */
	protected function set_tracking_id() {
		$this->tracking_id = get_option('ga_tracking_id');
		if(empty($this->tracking_id)) {
			if ($this->tracking_id = get_theme_mod('tracking_id')) add_option('ga_tracking_id',$this->tracking_id);
		} 
	}

	/**
	 * Allows the use of themes custom action hook for tracking output
	 */
	protected function set_action_hook() {
		$this->action_hook = get_option('ggga_action_hook');
		if(empty($this->action_hook)) {
			$this->action_hook = get_theme_mod('ggga_action','wp_head');
		}
		$this->missing_hook = get_option('ggga_missing_hook');
		if($this->missing_hook === false) {
			add_option('ggga_missing_hook',0);
		}
	}

	/**
	 * Output of the actual code
	 * @return [type] [description]
	 */
	public function print_ga() {

		if($this->code_inserted) return;

		if(!empty($this->tracking_id)) {
			echo "
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

  ga('create', '" . $this->tracking_id . "', 'auto');
  ga('send', 'pageview');

</script>";
		}
		$this->code_inserted = true;	
	}

	/**
	 * Catches the last train to output the code in case the custom theme hook
	 * no longer exists (because theme was updated)
	 * @return boolean [description]
	 */
	public function is_code_inserted() {
		if($this->code_inserted === false) {
			update_option("missing_hook", 1);
			$this->print_ga();
		}
	}

	/**
	 * Tracks CF7 posts as events.
	 * @return [type] [description]
	 */
	public function cf7_event_tracking() {
		if(!defined("WPCF7_VERSION")) return;

		//general, for contact forms in footers and sidebars
		$string="Contact form";

		//for singular posts take the post title
		if(is_singular()) {
			//global $post;
			$string = $this->get_form_name();//$post->post_title;
		} 
		echo "
<script>
	document.addEventListener( 'wpcf7mailsent', function( event ) {
	    ga('send', 'event', '".$string."', 'submit');
	}, false );
</script>
";
	}

	protected function get_form_name() {
		global $post;
		preg_match("/\[contact-form-7(.+?)?\]/",$post->post_content,$matches);
		//var_dump($matches);
		$atts = shortcode_parse_atts($matches[1]);
		if(isset($atts['title'])) return $atts['title'];
		else return "Contact form";
	}

	/**
	 * Tracks outbound links click.
	 * @return [type] [description]
	 */
	public function outbound_link_tracking() {
		// default is do track
		if(!get_option('ggga_track_outbound',true)) return;
		echo "
<script>
(function($){
	$('a').on('click',function(e){
		var url = $(this).attr('href');
		if(typeof url == 'undefined') { return; }
		if (e.currentTarget.host != window.location.host) {
			ga('send', 'event', 'outbound', 'click', url, {
			    'transport': 'beacon'
			});
		}
	});
})(jQuery);
</script>
";
	}

	public function register_settings() {
		add_settings_section('gg_analytics',__('Analytics Settings'), array($this, 'settings_section_callback'),'general');
		register_setting('general','ga_tracking_id');
		register_setting('general','ggga_action_hook');
		register_setting('general','ggga_track_outbound');
		add_settings_field( 'ga_tracking_id', "GA Tracking ID", array($this,'tracking_id_input_field'), 'general', 'gg_analytics');
		add_settings_field( 'ggga_action_hook', "GA Action Hook", array($this,'action_hook_input_field'), 'general', 'gg_analytics' );
		add_settings_field( 'ggga_track_outbound', "GA Track Outbound links", array($this,'track_outbound_checkbox'), 'general', 'gg_analytics' );
	}

	public function settings_section_callback() {
		echo "<p>".__("Section for your Analytics settings.")."</p>";
	}


	public function tracking_id_input_field() {
		echo "<input type='text' name='ga_tracking_id' value='".get_option( 'ga_tracking_id' )."'>";
	}

	public function action_hook_input_field() {
		echo "<input type='text' name='ggga_action_hook' value='".get_option( 'ggga_action_hook', "wp_head" )."'>";
	}

	public function track_outbound_checkbox() {
		$checked = get_option('ggga_track_outbound',true)?"checked='checked' ":"";
		echo  "<input type='checkbox' name='ggga_track_outbound' value='1' ".$checked.">";
	}

	public function tracking_id_missing() {
		$class = "notice ";
		if(empty($this->tracking_id)) {
			$message = "Google Analytics tracking_id is missing! Please set it in <a href='".get_admin_url()."options-general.php'>Settings > General</a> ";
			$class .= 'notice-error';
		}
		if(isset($message)) {
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
		}
	}

	public function updater() {
		include_once "GG_auto_update.php";
		$version = get_file_data(__FILE__,array("Version"));
		$version = $version[0];
		$remote_path = "http://demo.gresak.net/ggga/update.php";
		$plugin = "ggga/ggga.php";
		new GG_auto_update($version,$remote_path,$plugin); 
	}

	public static function instance($path) {

        if (self::$instance === null) {
            self::$instance = new self($path);
        }
        return self::$instance;
    }

}
