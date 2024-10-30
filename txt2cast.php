<?php
/*
Plugin Name: CastMyBlog - Automagic Podcasting
Plugin URI: http://castmyblog
Description: When you create a new post, it is automatically converted to audio using text to speech technology, and a podcast-compatible link is inserted into your post. It's all automatic - no messing around with settings and configurations.
Version: 1.1
Author: Dave Holowiski
Author URI: http://holowiski.com
License: GPL2
*/

/*  Copyright 2010  Dave Holowiski (email : david@holowiski.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

include("functions.php");

#delete_option('secret_key');
if (!get_option('secret_key') || (get_option('secret_key')=="")) {
	#contact the server and ask, politely, for a secret key
	#$blog_url=urlencode(bloginfo('url'));
	#erm - don't set $blog_url - this must be an internal variable becasue it causes the blog url to print at the top of every page!
	if (DEVELOPMENT_ENV) {error_log("No Secret Key, getting one");}
	$data=array('please'=>'thank_you');
	$data=http_build_query($data);
	$secret_key=do_post_request(SERVER_URL.'get_secret_key.php', $data);
	if (DEVELOPMENT_ENV) {error_log("trying to get new secret key: ".$secret_key);}
	if ($secret_key) {update_option('secret_key', $secret_key);}
}
 
if (!get_option('show_player')) {
	#first time plugin has ran. Set show_player to bottom, and turn on the player, if exists
	add_option('show_player', "bottom");
	if (function_exists('powerpress_content')) {
		if (!get_option('use_external_player')) {
			add_option('use_external_player', "yes");
		}
	}
}


add_action( 'publish_post', 'process_publish');
add_filter( "the_content", "append_my_podcast" );

function process_publish($post_ID) {
	#1. check if the txt has been submitted
	#2. if not submitted, submit
	#3. if already submitted, check for 'update' flag
		#if 'update' flag, then submit
	#yay!
	if (DEVELOPMENT_ENV){error_log("post_id = ".$post_ID);}
 	$my_content=get_post($post_ID);
	$speech=$my_content->post_content;
	$secret_key=get_option('secret_key');
	$data = array('speech'=>$speech,
			'volume_scale'=>'1',
			'make_audio'=>'Text-To-Speech',
			'save_mp3'=>false,
			'secret_key'=>$secret_key,
			'post_id'=>$post_ID,
			'title'=>strip_tags($my_content->post_title)
			);
	$data = http_build_query($data);
	if (DEVELOPMENT_ENV) {
		error_log("In process_publish: ");
		error_log("Speech=: ".$speech);
		error_log("Secret_key=: ".$secret_key);
		error_log("post_ID=: ".$post_ID);
	}
	#do_post_request('http://sandbox.endofinternet.net/pdcstme/text2wave_api.php', $data);
	$mp3_token= do_post_request(SERVER_URL.'post_rcvr.php', $data);
	if ($mp3_token) {
		#if we're here the post was submitted. Need to set the post_meta
		#this should only happen once, but let's allow for it to happen more than once.
		update_post_meta($post_ID, "mp3_token", $mp3_token);
		error_log("Just attempted to update mp3_token post meta to: ".$mp3_token);
	}
	if (DEVELOPMENT_ENV) {
		error_log("May have just gotten mp3_token");
		error_log("mp3_token=: ".$mp3_token);
	}
}

function append_my_podcast($content) {
	#this fires every time a post is viewed. MUST NOT query the server every time this fires!!!
	#need to query the server, and if there is a podcast, link to it!
	#send the secret key and the $post_ID. recieve the filename
	global $wp_query;
	check_for_removal($wp_query);
	#check to see if teh podcast url is in the post meta. If so do nothing
	if (((get_post_meta($wp_query->post->ID,"podcast_url",true)) && ((get_post_meta($wp_query->post->ID,"txt2cast_check_for_update",true)=="no"))) || 		get_post_meta($wp_query->post->ID,'txt2cast_expired')=='expired') {
		#do nothing. if there is a url, append it & return.
		if (get_post_meta($wp_query->post->ID,"podcast_url",true)) {
			$mp3_address=get_post_meta($wp_query->post->ID,"podcast_url",true);
			#$mp3_address="[powerpress url=\"$mp3_address\"]";
			if (!(function_exists('powerpress_content'))) {
				$options=get_option('show_player');
				if ($options == "top") {$content="<a href='".$mp3_address."'>$mp3_address</a>".$content;}
				if ($options == "bottom") {$content=$content."<a href='".$mp3_address."'>$mp3_address</a>";}
			}
			if (DEVELOPMENT_ENV) {
				error_log($mp3_address);
				error_log("show_player= ". $options);
			}
			return $content;
		} else {
			return $content;
		}
	} else {
		#we're here because either a post has no MP3 URL, or txt2cast_chec_for_update is anything except for no
		#if MP3_URL doesnt exist (different from being empty), do nothing. 
		#this means the user's old posts dont query, unless they re-post them. 
		if (get_post_meta($wp_query->post->ID,"mp3_token", true)) {
			$data = array(
					'secret_key'=>get_option('secret_key'),
					'mp3_token'=>get_post_meta($wp_query->post->ID,"mp3_token", true),
					'url'=>get_permalink($wp_query->post->ID)
					);
			$data = http_build_query($data);
			$mp3_address=do_post_request(SERVER_URL.'query_mp3.php', $data);
			if (DEVELOPMENT_ENV) {
				error_log("Just queried the server");
			}
			#take the URL, do_shortcode and hopefully make a magical podcast!
			#$mp3_address="[powerpress url=\"$mp3_address\"]";
			#now, update the post-meta with the URL
			update_post_meta($wp_query->post->ID,"podcast_url",$mp3_address);
			update_post_meta($wp_query->post->ID,"enclosure",$mp3_address);
			#and update teh check_for_update meta
			update_post_meta($wp_query->post->ID,"txt2cast_check_for_update","no");
			if (!(function_exists('powerpress_content'))) {
				$options=get_option('show_player');
				if ($options == "top") {$content="<a href='".$mp3_address."'>$mp3_address</a>".$content;}
				if ($options == "bottom") {$content=$content."<a href='".$mp3_address."'>$mp3_address</a>";}
			}
		}
		return $content;
	}
}

?>
<?php
// create custom plugin settings menu
add_action('admin_menu', 'txt2cast_create_menu');

function txt2cast_create_menu() {

	//create new top-level menu
	add_menu_page('txt2Cast Plugin Settings', 'CastMyBlog Settings', 'administrator', __FILE__, 'txt2cast_settings_page',plugins_url('/images/icon.png', __FILE__));

	//call register settings function
	add_action( 'admin_init', 'register_mysettings' );
}


function register_mysettings() {
	//register our settings
	register_setting( 'txt2cast-settings-group', 'secret_key' );
	register_setting( 'txt2cast-settings-group', 'show_player' );
	register_setting( 'txt2cast-settings-group', 'use_external_player' );
}

function txt2cast_settings_page() {
?>
<div class="wrap">
<h2>txt2Cast</h2>
<p>
	Hi There. Once you activate the plugin, it's automatically working, there's nothing you need to do!  If you'd like to customize how the plugin works, feel free to adjust the settings below.
</p>

<form method="post" action="options.php">
    <?php settings_fields( 'txt2cast-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Secret Key</th>
        <td><?php echo get_option('secret_key'); ?></td>
        </tr>
		<tr valign="top">
			<?PHP if (function_exists('powerpress_content')) { ?>
				<P>You have the Blubrry Powerpress plugin installed. This is a good thing! To customize your player settings, go to the Powerress Player settings.<P>
			<?PHP } else { ?>
				<P>This plugin will automatically insert a link to the MP3 audio file. If you want a full featured audio player, and if you want to customize your Podcast feed, I highly recommend the <a href="http://wordpress.org/extend/plugins/powerpress/">Blubrry PowerPress Podcasting Plugin</a>. Once you install the plugin, the player will magically show up in your posts.</P>
				
				<th scope="row">Show Episode: </th>
				<td>
					<select name="show_player">
						<option value="top" <?PHP if (get_option('show_player')=="top") {echo("SELECTED");}?>>Show at Top of Post</option>
						<option value="bottom" <?PHP if (get_option('show_player')=="bottom") {echo("SELECTED");}?>>Show at Bottom of Post</option>
						<option value="noshow" <?PHP if (get_option('show_player')=="noshow") {echo("SELECTED");}?>>Do not show</option>
					</select>
				</td>
			<?PHP } ?>

		</tr>
    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
<?php } ?>
