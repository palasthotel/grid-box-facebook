<?php
/**
 * @author Palasthotel <rezeption@palasthotel.de>
 * @copyright Copyright (c) 2014, Palasthotel
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @package Palasthotel\Grid-WordPress-Box-Social
 */

class grid_instagram_box extends grid_list_box {

	public function type() {
		return 'instagram';
	}

	public function __construct() {
		parent::__construct();
		$this->content->count = 3;
	}

	public function build($editmode) {
		if ( $editmode ) {
			return $this->content;
		} else {
			$output = "<p>Instagram!</p>";
			
			// TODO: cache output with transient for max 500 calls an hour (api limit)
			
			$arr =  $this->getData();
			
			return implode("<br>",$arr);
		}
	}
	
	public function getData(){
		$arr = array();
		$grid_social_boxes = grid_social_boxes_plugin();
		if($grid_social_boxes != null){
			$api = $grid_social_boxes->get_instagram_api();
			if($api != null){
				$result = $api->getUserMedia('self', $this->content->count);
				$images = $result->data;
				foreach ($images as $item){
					$src = $item->images->low_resolution->url;
					$arr[] = "<img src='$src' />";
				}
			}
		}
		return $arr;
	}

	public function contentStructure () {
		$cs = parent::contentStructure();
		$grid_social_boxes = grid_social_boxes_plugin();
		
		$info = array(
			'label' => __("Instagram Account", "grid-social-boxes"),
			'text' => __( 'Not logged in. Goto settings and get an access token.', 'grid-social-boxes' ),
			'type' => 'info',
		);
		
		if($grid_social_boxes->get_instagram_api() != null){
			$user = $grid_social_boxes->get_instagram_api()->getUser();
			$info['text'] = sprintf(esc_html__('Get Instagram posts for: %1$s', 'grid-social-boxes'),$user->data->username);
		}
		
		return array_merge( $cs, array(
			$info,
			array(
				'key' => 'count',
				'label' => t( 'Count' ),
				'type' => 'number',
			),
		));
	}
}
