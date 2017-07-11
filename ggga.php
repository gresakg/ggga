<?php

/*
Plugin Name: GA by GG
Plugin URI: http://gresak.net
Description: Simple plugin for google analytics
Author: Gregor Grešak
Version: 3.0.0
Author URI: http://gresak.net
*/

$ggga = Ggga::instance(__FILE__);

class Ggga {

	protected $tracking_id;

	protected $action_hook;

	protected $code_inserted = false;

	private static $instance;

	protected function __construct() {
		$this->set_tracking_id();
		$this->set_action_hook();
		add_action($this->action_hook, array($this,'print_ga'));
		add_action( 'customize_register', array($this,'customizer') );
		add_action ( 'admin_init', array($this, 'register_settings'));
		add_action( 'admin_notices', array($this, 'tracking_id_missing'));
		add_action( $this->action_hook, array($this, 'cf7_event_tracking'));
	}

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

	public function register_settings() {
		register_setting('general','ga_tracking_id');
		register_setting('general','ggga_action_hook');
		add_settings_field( 'ga_tracking_id', "GA Tracking ID", array($this,'tracking_id_input_field'), 'general' );
		add_settings_field( 'ggga_action_hook', "GA Action Hook", array($this,'action_hook_input_field'), 'general' );
	}

	public function tracking_id_input_field() {
		echo "<input type='text' name='ga_tracking_id' value='".get_option( 'ga_tracking_id' )."'>";
	}

	public function action_hook_input_field() {
		echo "<input type='text' name='ggga_action_hook' value='".get_option( 'ggga_action_hook', "wp_head" )."'>";
	}

	public function tracking_id_missing() {
		$class = "notice ";
		if(empty($this->tracking_id)) {
			$message = "Google Analytics tracking_id is missing! Please set it in <a href='".get_admin_url()."options-general.php'>Settings > General</a> ";
			$class .= 'notice-error';
		} elseif(empty(get_option('ga_tracking_id'))) {
			$message = "Google Analytics options are set using theme options! This has been deprecated since the version 1.0 of the plugin. Your code will still work untill you change your theme or the next version of this plugin.<br> Please set the tracking ID and optionally the action hook in <a href='".get_admin_url()."/options_general'>Settings > General</a> ";
			$class .= 'notice-warning is-dismissible';
		}
		if(isset($message)) {
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
		}
	}

	public function customizer($customize) {
		
		$customize->add_section('google_analytics', array(
			"title" => "Google Analytics",
			"priority" => 100
			));
		$customize->add_setting('tracking_id',array("default"=>""));
		$customize->add_control(
			new WP_Customize_Control(
				$customize,
				'tracking_id',
				array(
					'label' => 'Google analytics tracking ID',
					'section' => 'google_analytics',
					'settings' => 'tracking_id'
					)
				)
			);
		$customize->add_setting('ggga_action',array("default"=>"wp_head"));
		$customize->add_control(
			new WP_Customize_Control(
				$customize,
				'ggga_action',
				array(
					'label' => "Action hook",
					'description' => "WP Action hook that will print out the code. You can use it to print the code after the body tag in your theme has an action hook at the approproate place. Do not change if you don't know what you're doing!",
					'section' => 'google_analytics',
					'settings' => 'ggga_action'
					)
				)
			);
	}

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

	protected function get_form_name() {
		global $post;
		preg_match("/\[contact-form-7(.+?)?\]/",$post->post_content,$matches);
		//var_dump($matches);
		$atts = shortcode_parse_atts($matches[1]);
		if(isset($atts['title'])) return $atts['title'];
		else return "Contact form";
	}

	protected function set_tracking_id() {
		$this->tracking_id = get_option('ga_tracking_id');
		if(empty($this->tracking_id)) {
			$this->tracking_id = get_theme_mod('tracking_id');
		} 
	}

	protected function set_action_hook() {
		$this->action_hook = get_option('ggga_action_hook');
		if(empty($this->action_hook)) {
			$this->action_hook = get_theme_mod('ggga_action','wp_head');
		}
	}

	public static function instance($path) {

        if (self::$instance === null) {
            self::$instance = new self($path);
        }
        return self::$instance;
    }

}
