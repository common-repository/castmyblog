<?PHP

define('DEVELOPMENT_ENV', false); #causes error_log messages to be put in the apache log. Extremley Verbose!
define('DEVELOPMENT_ENV_URL', false); #causes the development server to be contacted instead of the production server

if (DEVELOPMENT_ENV_URL) {
	define('SERVER_URL', 'http://10.0.1.11/pdcstme/');
} else {
	define('SERVER_URL', 'http://castmyblog.com/pdcstme/');
}

function do_post_request($url, $data, $optional_headers = null)
{
  $params = array('http' => array(
              'method' => 'POST',
              'content' => $data
            ));
  if ($optional_headers !== null) {
    $params['http']['header'] = $optional_headers;
  }

	if (DEVELOPMENT_ENV) {
		error_log("In do_post_request");
		error_log("URL=:".$url);
	}

  $ctx = stream_context_create($params);
  $fp = @fopen($url, 'rb', false, $ctx);
  if (!$fp) {
	if (DEVELOPMENT_ENV) {
		error_log("Bad URL - ".$php_errormsg);
	}
    #throw new Exception("Problem with $url, $php_errormsg");
  }
  $response = @stream_get_contents($fp);
  if (DEVELOPMENT_ENV) {
	error_log("Contacted URL, response is: ".$response);
  }
  #if ($response === false) {
  #  throw new Exception("Problem reading data from $url, $php_errormsg");
  #}
  return $response;
}

function check_for_removal($wp_query)
 {
	#if the post is older than 30 days, remove the mp3 link, enclosure and other info. 
	#the free version of the service only allows files to exist for 30 days. 
	#******if you think it' sneaky to remove these lines of code, don't bother******
	#******the files are also deleted off the server after 30 days, so the links wont work anyway, all it will do is give you broken links.******
	#if you upgrade to the full version of the service, these files can be re-created.
	$post_date=strtotime($wp_query->post->post_date);
	$today=strtotime('-30 days');
	if ($today > $post_date) {
		if (DEVELOPMENT_ENV) {error_log("About to delete mp3 enclosure");}
		#remove the info.
		delete_post_meta($wp_query->post->ID,'enclosure');
		delete_post_meta($wp_query->post->ID,'mp3_token');
		delete_post_meta($wp_query->post->ID,'podcast_url');
		delete_post_meta($wp_query->post->ID,'txt2cast_check_for_update');
		update_post_meta($wp_query->post->ID,'txt2cast_expired', 'expired');
	}
	if (DEVELOPMENT_ENV) {
		error_log("post date is: ". $post_date);
		error_log("today, minus 30 days, is: ".$today);
		error_log("post_meta, enclosure: ".get_post_meta($wp_query->post->ID,'enclosure'));
		
	}
}
?>
