<?php
/**
 * @author Palasthotel <rezeption@palasthotel.de>
 * @copyright Copyright (c) 2014, Palasthotel
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @package Palasthotel\Grid-Social-Boxes
 */
class grid_social_timeline_box extends grid_list_box  {
	
	const PREFIX_TWITTER = "twitter";
	const PREFIX_INSTAGRAM = "instagram";
	const PREFIX_YOUTUBE = "youtube";
	const PREFIX_FACEBOOK = "facebook";
	
	public function __construct() {
		parent::__construct();
		$this->content->limit = 5;
		$this->content->sort = 1;
	}
	
	public function type() {
		return 'social_timeline';
	}
	
	/**
	 * build the content
	 *
	 * @param bool $editmode
	 *
	 * @return $content
	 */
	public function build( $editmode ) {
		
		if(empty($this->content->sort)) $this->content->sort = 1;
		
		if ( $editmode ) {
			$this->content->title = t("Social timeline by User");
			return $this->content;
		} else {
			$content = array();
			$grid_social_boxes = grid_social_boxes_plugin();
			$timezone = new DateTimeZone(get_option('timezone_string'));
			
			/**
			 * get twitter contents
			 */
			if($this->hasTwitter() ){
				/**
				 * @var \Abraham\TwitterOAuth\TwitterOAuth $connection
				 */
				$user = (!empty($this->content->twitter_user))? $this->content->twitter_user: "";
				$limit = (!empty($this->content->twitter_limit))?$this->content->twitter_limit:"";
				$connection = $grid_social_boxes->get_twitter_api();
				if ( 'retweets' == $this->content->twitter_retweet ) {
					$result = $connection->get(
						'https://api.twitter.com:443/1.1/search/tweets.json?src=typd&q='.$this->content->twitter_user,
						array(
							"count" => $this->content->twitter_limit,
						)
					);
					$result = $result->statuses;
				} else {
					$result = $connection->get(
						'https://api.twitter.com:443/1.1/statuses/user_timeline.json',
						array(
							'screen_name' => $user,
							"count" => $limit,
							"tweet_mode" => "extended",
						)
					);
				}
				
				foreach( $result as $key => $tweet ){
					$datetime = new DateTime($tweet->created_at);
					$datetime->setTimezone($timezone);
					$content[] = (object) array(
						"datetime" => $datetime,
						"content" => $tweet,
						"type" => self::PREFIX_TWITTER,
					);
				}
				
			}
			
			/**
			 * get instagram contents
			 */
			if($this->hasInstagram()){
				$api = $grid_social_boxes->get_instagram_api();
				if($api != null){
					$count = (!empty($this->content->instagram_count))? $this->content->instagram_count: 3;
					$result = $api->getUserMedia('self', $count);
					$images = $result->data;
					foreach ($images as $item){
						$src = $item->images->low_resolution->url;
						$datetime = DateTime::createFromFormat( 'U', (int)$item->created_time );
						$datetime->setTimezone($timezone);
						$content[] = (object)array(
							"datetime" => $datetime,
							"content" => $item,
							"type" => self::PREFIX_INSTAGRAM
						);
					}
				}
			}
			
			if($this->hasYoutube()){
				$helper_box = new grid_youtube_box();
				$videos_options = null;
				$q = (!empty($this->content->youtube_q))? $this->content->youtube_q: "";
				$count = (!empty($this->content->youtube_count))?$this->content->youtube_count:"";
				switch ($this->content->youtube_type){
					case "channel":
						$channels = $helper_box->getChannels(array(
								"forUsername"=> $q,
								"maxResults" => 1,
							)
						);
						if(count($channels)>0){
							$videos_options = array(
								"channelId" => $channels[0]->id,
								"maxResults" => $count,
								"order" => "date",
							);
						}
						break;
					case "search":
					default:
						$videos_options = array(
							"q"=> $q,
							"maxResults" => $count,
						);
				}
				if($videos_options != null){
					$videos = $helper_box->getVideos($videos_options);
					foreach($videos as $video){
						$datetime = new DateTime($video->published);
						$datetime->setTimezone($timezone);
						$content[] = (object)array(
							"datetime" => $datetime,
							"content" => $video,
							"type" => self::PREFIX_YOUTUBE,
						);
					}
				}
			}
			
			if($this->hasFacebook()){
				$facebook = new grid_facebook_feed_box();
				$feed_object = $facebook->get_feed($this->content->facebook_fb_page, $this->content->facebook_type);
				$feed = array();
				$feed_body = null;
				if($feed_object != null){
					$feed_body = $feed_object->getDecodedBody();
				}
				if(null != $feed_body && !empty($feed_body["data"])){
					$feed = $feed_body["data"];
				}
				$noi = (!empty($this->content->facebook_number_of_items))? $this->content->facebook_number_of_items: 3;
				foreach ($feed as $index => $post){
					if($index >= $noi) break;
					$post = (object)$post;
					$datetime = new DateTime($post->created_time);
					$datetime->setTimezone($timezone);
					$content[] = (object)array(
						"datetime" => $datetime,
						"content" => $facebook->get_post($post, $this->content->facebook_fb_page),
						"post" => $post,
						"type" => self::PREFIX_FACEBOOK,
					);
				}
			}
			
			/**
			 * sort by timestamp
			 */
			$sort = $this->content->sort;
			usort($content, function($a, $b) use ($sort){
				if($a->datetime == $b->datetime){
					return 0;
				}
				return ($a->datetime > $b->datetime) ? -1*intval($sort): intval($sort);
			});
			
			/**
			 * throw away more than limit items
			 */
			array_splice($content, $this->content->limit);
			
			/**
			 * render items
			 */
			for ($position = 0; $position < count($content); $position++){
				$content[$position]->rendered = $this->renderItem($content[$position], $position);
			}
			
			return $content;
		}
	}
	
	/**
	 * @param $item
	 *
	 * @param $position
	 *
	 * @return string
	 */
	private function renderItem($item, $position){
		$grid_social_boxes = grid_social_boxes_plugin();
		ob_start();
		if($overridden_template = locate_template("grid/grid-box-social_timeline--".$item->type.".tpl.php")){
			include $overridden_template;
		} else {
			require $grid_social_boxes->dir."/templates/grid-box-social_timeline--".$item->type.".tpl.php";
		}
		$rendered = ob_get_contents();
		ob_end_clean();
		return $rendered;
	}
	
	/**
	 * content structure
	 * @return array
	 */
	public function contentStructure () {
		$cs = parent::contentStructure();

		$grid_social_boxes = grid_social_boxes_plugin();
		
		$apis = array();
		
		if($grid_social_boxes->get_twitter_api() != null){
			$twitter = new grid_twitter_box();
			$apis = array_merge(
				$apis,
				array(
					array(
						"label"=> "",
						"text" => __("Configuration for Twitter.", "grid-social-boxes"),
						"type" => "info",
					),
					array(
						'key' => self::PREFIX_TWITTER,
						'label' => __("Activate Twitter posts", 'grid-social-boxes'),
						'type' => "checkbox",
					),
				),
				$this->prefixStructure($twitter->contentStructure(), self::PREFIX_TWITTER)
			);
		}
		
		if($grid_social_boxes->get_instagram_api() != null){
			$instagram = new grid_instagram_box();
			$apis = array_merge(
				$apis,
				array(
					array(
						"label"=> "",
						"text" => __("Configuration for Instagram.", "grid-social-boxes"),
						"type" => "info",
					),
					array(
						'key' => self::PREFIX_INSTAGRAM,
						'label' => __("Activate Instagram posts", 'grid-social-boxes'),
						'type' => "checkbox",
					),
				),
				$this->prefixStructure($instagram->contentStructure(), self::PREFIX_INSTAGRAM)
			);
		}
		
		if($grid_social_boxes->get_youtube_api() != null){
			$youtube = new grid_youtube_box();
			$apis = array_merge(
				$apis,
				array(
					array(
						"label"=> "",
						"text" => __("Configuration for Youtube.", "grid-social-boxes"),
						"type" => "info",
					),
					array(
						'key' => self::PREFIX_YOUTUBE,
						'label' => __("Activate Youtube posts", 'grid-social-boxes'),
						'type' => "checkbox",
					),
				),
				$this->prefixStructure($youtube->contentStructure(), self::PREFIX_YOUTUBE)
			);
		}
		
		if($grid_social_boxes->get_facebook_api() != null){
			$facebook = new grid_facebook_feed_box();
			$apis = array_merge(
				$apis,
				array(
					array(
						"label"=> "",
						"text" => __("Configuration for Facebook.", "grid-social-boxes"),
						"type" => "info",
					),
					array(
						'key' => self::PREFIX_FACEBOOK,
						'label' => __("Activate Facebook posts", 'grid-social-boxes'),
						'type' => "checkbox",
					),
				),
				$this->prefixStructure($facebook->contentStructure(), self::PREFIX_FACEBOOK)
			);
		}
		
		if(count($apis) > 0){
			return array_merge(
				$cs,
				array(
					array(
						'key' => 'limit',
						'type' => 'number',
						'label' => 'Anzahl der Einträge insgesamt',
					),
					array(
						'key' => 'sort',
						'label' => __('Sort order', 'grid-social-boxes'),
						'type' => 'select',
						'selections' => array(
							array( 'key' => 1, 'text' => __("Latest first", 'grid-social-boxes')),
							array( 'key' => -1, 'text' => __("Oldest first", 'grid-social-boxes')),
						),
					)
				),
				$apis
			);
		}
		
		return array_merge(
			$cs,
			array(
				array(
					"label"=> "Info",
					"text" => __("You have to confiure the social apis first.", "grid-social-boxes"),
					"type" => "info",
				)
			)
		);
		
	}
	
	/**
	 * check if twitter config is set
	 * @return bool
	 */
	public function hasTwitter(){
		return $this->isWorking(self::PREFIX_TWITTER);
	}
	
	/**
	 * check if instagram config is set
	 * @return bool
	 */
	public function hasInstagram(){
		return $this->isWorking(self::PREFIX_INSTAGRAM);
	}
	
	/**
	 * check if youtube config is set
	 * @return bool
	 */
	public function hasYoutube(){
		return $this->isWorking(self::PREFIX_YOUTUBE);
	}
	
	/**
	 * check if facebook config is set
	 * @return bool
	 */
	public function hasFacebook(){
		return $this->isWorking(self::PREFIX_FACEBOOK);
	}
	
	/**
	 * check api prefix configurations
	 * @param $api_prefix
	 *
	 * @return bool
	 */
	private function isWorking($api_prefix){
		return (isset($this->content->{$api_prefix}) && $this->content->{$api_prefix});
	}
	
	/**
	 * prefix structures
	 *
	 * @param $structure
	 * @param $prefix
	 *
	 * @return mixed
	 */
	private function prefixStructure($structure, $prefix){
		for($i = 0; $i < count($structure); $i++){
			if(isset($structure[$i]["key"])) $structure[$i]["key"] = $prefix."_".$structure[$i]["key"];
		}
		return $structure;
	}
	
	
	
}