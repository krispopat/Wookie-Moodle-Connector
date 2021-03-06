<?PHP //$Id: block_wookie.php,v 1.2 2010/02/17 09:51:26 scottbw Exp $
/**
 * Wookie
 * By Scott Wilson
 * 
 * This block allows Moodle admins to add Widgets to blocks from an Apache Wookie widget server
 * More info on Wookie at http://incubator.apache.org/wookie/
 *
 * Note that you need to set your Wookie server URL and API KEY in Site Administration
 * using the Modules->Blocks->Widget settings page
 */
require_once($CFG->libdir . '/phpxml/xml.php');
require_once($CFG->libdir .'/filelib.php');
require_once("{$CFG->dirroot}/blocks/wookie/framework/WookieConnectorService.php");
 

class block_wookie extends block_base {
    
    function init() {
        $this->version = 201132800;
        $this->title = 'Widget';
        $this->content_type = BLOCK_TYPE_TEXT;
    }
    
    function has_config(){
        return true;
    }
    
    function instance_allow_config() {
        return true;
    }
    
    function applicable_formats() {
        return array('all' => true);
    }

    /**
    * Widgets actually look nicer without the header, but
    * it means they can't be dragged around when using ajaxy
    * Moodle themes. 
    */
    function hide_header() {
        return false;
    }
    
	function get_content() { 
		if($this->content !== NULL) {
            return $this->content;
        }
        $this->content = new stdClass; 
        if (!$this->config->widget_url && $this->config->widget_id){ 
            $this->instantiateWidget(); 
        } 
        if (!$this->config->widget_url){ 
            $this->content->text = get_string('configurewidget','block_wookie'); 
        } else { 
            // Render output
            $this->content->text = '<iframe style="border:none" src="'.$this->config->widget_url.'" height="'.$this->config->widget_height.'" width="'.$this->config->widget_width.'"></iframe>';
            // Show embed code - comment this line out if you want
            // to disable embedding
            $this->content->footer = $this->get_embed_code();       
        }
        return $this->content;
    }
    
    function get_embed_code(){
        global $CFG;
        $embedtext = '&lt;iframe style="border:none" src="'.$this->config->widget_url.'" height="'.$this->config->widget_height.'" width="'.$this->config->widget_width.'"&gt;&lt;/iframe&gt;';
        $text = '<a href="#" onclick="document.getElementById('."'embed_".$this->instance->id."'".').style.display='."'block'".'"><img src="'.$CFG->wwwroot.'/blocks/wookie/embed.png" alt="get embed code"/></a>';
        $text = $text.'<div id="embed_'.$this->instance->id.'"  style="display:none">';
        $text = $text.'copy the text below into your page:<br/>';
        $text = $text.'<textarea style="width:100%;height:100px">'.$embedtext.'</textarea>';
        $text = $text.'<a href="#" onclick="document.getElementById('."'embed_".$this->instance->id."'".').style.display='."'none'".'">hide embed code</a></div>';
        return $text;
    }
    

 	function specialization() { 
        if ($this->config){ 
            if ($this->config->widget_id) $this->instantiateWidget(); 
        	$this->title = $this->config->widget_title; 
        } 
    }
    
    function instance_allow_multiple() {
        return true;
    }
    
    // Unfortunately, called before we know the widget's actual width
    function preferred_width(){
        return 320;
    }
    
    /**
     * Sets up a widget instance
     */
    function instantiateWidget(){
        global $USER, $CFG, $COURSE;
        $conn = new WookieConnectorService ($CFG->block_wookie_url, $CFG->block_wookie_key, $this->instance->id, $USER->id );

        $widgetInstance = $this->getWidget($conn);  
        $context = get_context_instance(CONTEXT_BLOCK, $this->instance->id);      
        // Set defaults
		if ( $widgetInstance ) {
        	$this->setProperty($widgetInstance, "username", $USER->username, $conn);
        	if (has_capability('moodle/course:manageactivities', $context )) {
        	    $this->setProperty($widgetInstance, "moderator", "true", $conn);
        	}
			//print_object($this);
			if (isset($CFG->block_wookie_moodleparams)) {
			
				$moodleParams = explode(',',$CFG->block_wookie_moodleparams);
				//print_object($moodleParams);
				foreach($moodleParams as $moodleParam) {
					/*
					Each $moodleParam should be in the form:
					widgetvariablename=$moodlevariable
					
					suggesting (\w*)=\$(\w*)(->)*(\w+)* 
					to test that this format works
					*/
					$matches= array();
					$matchCount = preg_match('/([a-zA-Z]*)=\$([a-zA-Z]+)(->)*(\w+)*/',$moodleParam,&$matches);
					//print_object($matches[0]);
					//print_object($moodleParam);
					if (
						$matches[0] != $moodleParam
					) {
					//	line didn't match the expected format so *may* be malicious, so skip
					//we should log this
						//add_to_log();
						//echo("invalid $moodleParam");
						//add_to_log($course->id, 'course', 'view', "view.php?id=$course->id", "$course->id");
						add_to_log($COURSE->id,'block_wookie','invalidparam','admin/settings.php?section=blocksettingwookie',"'$moodleParam' is an invalid Moodle variable for passing to widgets. An Admin should modify the block_wookie_moodleparams setting");
						break;
					//	continue;
					}
					else {
						$paramArray = explode("=",$moodleParam);
						$name = $paramArray[0];
						$moodleParam = $paramArray[1];
					//$param = $paramArray[1]
					//echo "Widget variable name: $name";
					//echo "Moodle Variable name: $moodleParam";
						$evalString = "return ".$moodleParam.";";
					//echo "Eval string: $evalString";
					/*
					probably need to work out a mechanism (e.g. REGEX) to 
					ensure that we've not got a malicious bit of code...
					*/
						$value = eval($evalString);	//this is SODDING dangerous!!!
					//echo "Value : $value";
					//echo '<br />';
						$this->setProperty($widgetInstance,$name,$value,$conn);
					}
				}
				//print_object($moodleParams);
			
			}
			
			
        	// Add participant
        	$this->addParticipant($widgetInstance, $conn);
		}
    }
    
    /**
     * Obtains a Widget instance
     * NOTE that this should in future call the REST API using POST
     * but this is currently tricky with Moodle's download_file_content function
     */
    function getWidget($connection){
        global $USER, $COURSE, $CFG;
        //$request = $CFG->block_wookie_url;
        //$request.= 'WidgetServiceServlet?';
        //$request.= 'requestid=getwidget';
        //$request.= '&api_key='.$CFG->block_wookie_key;
        //$request.= "&widgetid=".urlencode($this->config->widget_id);
        //$request.= '&userid='.$USER->id;
        //$request.= '&shareddatakey='.$this->instance->id;
        
        //$response = download_file_content($request);
        
        //$widget = XML_unserialize($response);
     /*   
        $test = new WookieConnectorService("http://dev.ubuntu-box.htk:8081/wookie/", "TEST", "localhost_dev", "demo_1");
//set locale
$test->setLocale("en");
//set logging path, if not set then logger doesnt do nohting
//$test->setLogPath("/home/raido/dev/www/php_framework/logs/");
//setup different userName
$test->getUser()->setLoginName("demo_1");
//get all available widgets
$availableWidgets = $test->getAvailableWidgets();
        */
		//error_log(print_r ($this->config, true));
        $widget = $connection->getOrCreateInstance ( $this->config->widget_id );
		if ( $widget ) {
	        
	        $this->config->widget_url =$widget->getURL();
	        $this->config->widget_height = $widget->getHeight();
	        $this->config->widget_width = $widget->getWidth();
	        $this->config->widget_title = $widget->getTitle();
	        $this->title = $this->config->widget_title;
	        $this->instance_config_commit();
	        $this->refresh_content();
		}
		return $widget;
    }
    
    /**
     * Sets a Personal Property for a Widget instance
     * NOTE that this should in future call the REST API using POST
     * but this is currently tricky with Moodle's download_file_content function
     */
    function setProperty($widgetInstance, $key,$value, $connection){
        global $USER, $COURSE,$CFG;
        /*
        $request = $CFG->block_wookie_url;;
        $request.= 'WidgetServiceServlet?';
        $request.= 'requestid=setpersonalproperty';
        $request.= '&api_key='.$CFG->block_wookie_key;
        $request.= "&widgetid=".urlencode($this->config->widget_id);
        $request.= '&userid='.$USER->id;
        $request.= '&shareddatakey='.$this->instance->id;
        $request.= '&propertyname='.$key;
        $request.= '&propertyvalue='.$value;
        $response = download_file_content($request);
        
        	$newProperty = new Property('demo_property', 'demo_value');
	$result = $test->setProperty($widget, $newProperty);
	*/
		$newProperty = new Property ( $key, $value );
		$r = $connection->setProperty ( $widgetInstance, $newProperty );
    }
    
    /**
     * Adds a participant
     * NOTE that this should in future call the REST API using POST
     * but this is currently tricky with Moodle's download_file_content function
     */
     function addParticipant($widgetinstance, $connection){
        global $USER, $COURSE, $CFG;
        
        if ($USER->picture != ""){
            $src = $CFG->httpswwwroot.'/user/pix.php/'.$USER->id.'/f1.jpg';
        } else {
            $src = $CFG->httpswwwroot.'/pix/u/f1.png';
        }
        $thisUser = new User ( $USER->id, $USER->username, $src );
        $connection->addParticipant($widgetinstance, $thisUser );
        /*
        $request = $CFG->block_wookie_url;
        $request.= 'WidgetServiceServlet?';
        $request.= 'requestid=addparticipant';
        $request.= '&api_key='.$CFG->block_wookie_key;
        $request.= "&widgetid=".urlencode($this->config->widget_id);
        $request.= '&userid='.$USER->id;
        $request.= '&shareddatakey='.$this->instance->id;
        $request.= '&participant_id='.$USER->id;
        $request.= '&participant_display_name='.$USER->username;
        $request.= '&participant_thumbnail_url='.urlencode($src);
        $response = download_file_content($request);   
        */
     }
}



/**
 * Gallery support class
 */
class gallery {

    function init(){ 
    }

    function showGallery(){
    	global $USER,$CFG;
		$instanceID = required_param('instanceid');
        $gallery = "<div id=\"widget_gallery\" class=\"widget-gallery\">";
        $conn = new WookieConnectorService ($CFG->block_wookie_url, $CFG->block_wookie_key, $instanceID, $USER->id );

        $widgets = $conn->getAvailableWidgets();
        foreach ($widgets as $widget){ 
            $gallery = $gallery."
                <div class=\"wookie-widget\">
                    <div class=\"wookie-icon-area\"><img class=\"wookie-icon\" src=\"".$widget->getIcon()."\" width=\"75\" height=\"75\"/></div>
                      <div class=\"wookie-title\">".$widget->getTitle()."</div>
                      <div class=\"wookie-description\">".$widget->getDescription()."</div>
                      <div class=\"wookie-choose\"><input type=\"button\" class=\"wookie-button\" value=\"select widget\" id=\"".$widget->getIdentifier()."\"></div>
                </div>
            ";
        }
        $gallery = $gallery."</div><br/>";
        return $gallery;
    }
    
}
?>