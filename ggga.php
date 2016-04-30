<?php

/*
Plugin Name: GA by GG
Plugin URI: http://gresak.net
Description: Simple plugin for google analytics
Author: Gregor Grešak
Version: 1.0
Author URI: http://gresak.net
*/

new Ggga();

class Ggga {

	protected $tracking_id;

	public function __construct() {
		$this->tracking_id = get_theme_mod('tracking_id');
		$this->set_actions();
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
	}

	public function print_ga() {
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
	}

	protected function set_actions() {
		if(has_action('after_body_tag')) {
			add_action('after_body_tag',array($this,'print_ga'));
		} else {
			add_action('get_footer',array($this,'print_ga'));
		}
		add_action( 'customize_register', array($this,'customizer') );
	}

}
