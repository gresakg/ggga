<?php

/*
Plugin Name: GA by GG
Plugin URI: http://demo.gresak.net/ggga
Description: Simple plugin for google analytics
Author: Gregor Grešak
Version: 4.0.0
Author URI: http://gresak.net
Text Domain: ggga
Domain Path: /languages/
*/

$ggga = Ggga::instance(__FILE__);

class Ggga {

	public $plugin_name = "Ggga";

	protected $tracking_id;

	protected $action_hook;

	protected $code_inserted = false;

	protected $missing_hook;

	protected $codes = array();

	protected $cf7string = "Contact form";

	protected $path;

	private static $instance;

	protected function __construct($path) {
		$this->path = dirname($path);
		$this->set_tracking_id();
		$this->set_action_hook();
		$this->set_tracking_codes();
		add_action($this->action_hook, array($this,'print_ga'));
		add_action('wp_footer',array($this, 'is_code_inserted'));
		add_action( $this->action_hook, array($this, 'cf7_event_tracking'));
		add_action($this->action_hook, array($this,'outbound_link_tracking'));
		add_action ( 'admin_init', array($this, 'register_settings'));
		add_action( 'admin_notices', array($this, 'tracking_id_missing'));
		add_action( 'plugins_loaded', array($this,'load_textdomain'));
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
		// gtag must be in the head section
		if(!get_option('ggga_use_old_analytics_js',true)) {
			$this->action_hook = "wp_head";
			return;
		}
		$this->action_hook = get_option('ggga_action_hook');
		if(empty($this->action_hook)) {
			$this->action_hook = get_theme_mod('ggga_action','wp_head');
		}
		$this->missing_hook = get_option('ggga_missing_hook');
		if($this->missing_hook === false) {
			add_option('ggga_missing_hook',0);
		}
	}

	protected function set_tracking_codes() {
		if(get_option('ggga_use_old_analytics_js',true)) {
			$this->code["main"] = "
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

  ga('create', '" . $this->tracking_id . "', 'auto');
  ga('send', 'pageview');

</script>";
			$this->code['cf7'] = "
<script>
	document.addEventListener( 'wpcf7mailsent', function( event ) {
	    ga('send', 'event', '".$this->cf7string."', 'submit');
	}, false );
</script>";
			$this->code['links'] = "ga('send', 'event', 'outbound', 'click', url, {
			    'transport': 'beacon'
			});";
		} else {
			$this->code['main'] = "
<!-- Global site tag (gtag.js) - Google Analytics -->
<script async src=\"https://www.googletagmanager.com/gtag/js?id=".$this->tracking_id."\"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '".$this->tracking_id."');
</script>";
			$this->code['cf7'] = "
<script>
	document.addEventListener( 'wpcf7mailsent', function( event ) {
	    gtag('event', '".$this->cf7string."' , {'event_category': 'cf7submit'});
	}, false );
</script>";
			$this->code['links'] = "gtag('event','click',{'event_category':'outbound','event_label':url})";

		}
	}

	/**
	 * Output of the actual code
	 * @return [type] [description]
	 */
	public function print_ga() {

		if($this->code_inserted) return;

		if(!empty($this->tracking_id)) {
			echo $this->code["main"];
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
		if(!defined("WPCF7_VERSION") || !get_option('ggga_track_cf7',true)) return;
		//for singular posts take the post title
		if(is_singular()) {
			//global $post;
			$this->cf7string = $this->get_form_name();//$post->post_title;
		} 
		echo $this->code['cf7'];
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
		if (e.currentTarget.host != window.location.host) {"
			.$this->code['links'].
		"
		}
	});
})(jQuery);
</script>
";
	}

	public function register_settings() {
		add_settings_section('gg_analytics',__('GG Analytics Settings','ggga'), array($this, 'settings_section_callback'),'general');
		register_setting('general','ga_tracking_id');
		register_setting('general','ggga_action_hook');
		register_setting('general','ggga_track_outbound');
		register_setting('general','ggga_use_old_analytics_js');
		add_settings_field( 'ga_tracking_id', __("GA Tracking ID",'ggga'), array($this,'tracking_id_input_field'), 'general', 'gg_analytics');
		add_settings_field( 'ggga_track_outbound', __("GA Track Outbound links",'ggga'), array($this,'track_outbound_checkbox'), 'general', 'gg_analytics' );
		if(defined("WPCF7_VERSION")) {
			register_setting('general','ggga_track_cf7');
			add_settings_field( 'ggga_track_cf7', __('Track CF7 form submissions','ggga'), array($this,'track_cf7_checkbox'), 'general', 'gg_analytics');
		}
		add_settings_field('ggga_use_old_analytics_js', 
			__("Use old analytics.js tracking code", 'ggga'),
			array($this, "use_analytics_js"), 
			"general",'gg_analytics');
		if(get_option('ggga_use_old_analytics_js',true)) {
			add_settings_field( 'ggga_action_hook', __("GA Action Hook",'ggga'), array($this,'action_hook_input_field'), 'general', 'gg_analytics' );
		}
	}

	public function settings_section_callback() {
		echo "<p>".__("Section for your Analytics settings.",'ggga')."</p>";
	}

	public function use_analytics_js() {
		$default = false;
		if(get_option('ga_tracking_id',false)) {
			$default=true;
		}
		$checked = get_option('ggga_use_old_analytics_js',$default)?"checked='checked' ":"";
		echo  "<input type='checkbox' name='ggga_use_old_analytics_js' value='1' ".$checked.">";
		printf ( __(" <span class='small'> By default, %s uses the new gtag.js since plugin version 4.0.0. 
					However, if you upgraded the plugin from an older version, this will be checked untill you decide it's time to use 
					the new code.</span>",'ggga'), $this->plugin_name);
	}


	public function tracking_id_input_field() {
		echo "<input type='text' name='ga_tracking_id' value='".get_option( 'ga_tracking_id' )."'>";
	}

	public function action_hook_input_field() {
		echo "<input type='text' name='ggga_action_hook' value='".get_option( 'ggga_action_hook', "wp_head" )."'>";
		echo "<br>".__("If you are using the old analytics.js code, it is recommended to put it right after the opening body tag. 
			If your theme has a hook there, you can use that hook to output tracking code. Else it's best to leave it as is.
			Don't change this if you don't know what you are doing.<br>The new tracking code ignores this setting.",'ggga');
	}

	public function track_outbound_checkbox() {
		$checked = get_option('ggga_track_outbound',true)?"checked='checked' ":"";
		echo  "<input type='checkbox' name='ggga_track_outbound' value='1' ".$checked.">";
	}

	public function track_cf7_checkbox() {
		$checked = get_option('ggga_track_cf7',true)?"checked='checked' ":"";
		echo  "<input type='checkbox' name='ggga_track_cf7' value='1' ".$checked.">";
	}

	public function tracking_id_missing() {
		$class = "notice ";
		if(empty($this->tracking_id)) {
			$message = sprintf( __("Google Analytics tracking_id is missing! Please set it in <a href='%soptions-general.php'>Settings > General</a> ",'ggga'),get_admin_url());
			$class .= 'notice-error';
		}
		if(get_option('ggga_use_old_analytics_js','undefined') == "undefined") {
			$message = sprintf( __($this->plugin_name . " has been updated to version 4.0.0. and needs your attention.<br> 
				The new version uses the gtag.js tracking code by default for new users (as recommended by Google since December 2017). However, if you 
				upgraded the plugin from an older version, you still have your old analytics.js code in place as we don't want to break your
				settings. To decide which code to use, please go to  <a href='%soptions-general.php'>Settings > General</a> 
				and either submit the form by leaving the setting checked, or uncheck it to use the new code. 
				<a href='https://gresak.net/analytics-integration-plugin/'>More informations</a><br>
				Until you do that, this notice will remain active.",'ggga'),get_admin_url());
			$class .= "notice-success is-dismissible";
		}
		if(isset($message)) {
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'ggga', false, $this->path."/languages/" );
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
