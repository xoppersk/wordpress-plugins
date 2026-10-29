<?php
/**
 * Liberty's latest YouTube videos and Shorts, read from YouTube's public feeds.
 * No API key, no quota and no cost; results are cached and the last good copy
 * is kept so the Watch page never goes blank if YouTube is slow.
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param string $kind 'videos' (everything on the channel) or 'shorts'.
 * @return array<int, array{id: string, title: string, date: int, thumb: string, url: string}>
 */
function liberty_youtube_videos( $kind = 'videos', $limit = 12 ) {
	$channel = (string) liberty_opt( 'live', 'channel', LIBERTY_DEFAULT_CHANNEL );
	$key     = 'liberty_yt_' . $kind . '_' . md5( $channel );
	$videos  = get_transient( $key );

	if ( ! is_array( $videos ) ) {
		$feed = 'shorts' === $kind
			? 'https://www.youtube.com/feeds/videos.xml?playlist_id=UUSH' . substr( $channel, 2 )
			: 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode( $channel );
		$videos = liberty_parse_youtube_feed( $feed );
		if ( $videos ) {
			set_transient( $key, $videos, 20 * MINUTE_IN_SECONDS );
			update_option( $key . '_last', $videos, false );
		} else {
			$videos = get_option( $key . '_last', array() );
			set_transient( $key, $videos, 5 * MINUTE_IN_SECONDS ); // retry soon
		}
	}
	return array_slice( (array) $videos, 0, $limit );
}

function liberty_parse_youtube_feed( $url ) {
	$res = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return array();
	}
	$prev = libxml_use_internal_errors( true );
	$xml  = simplexml_load_string( wp_remote_retrieve_body( $res ), 'SimpleXMLElement', LIBXML_NONET );
	libxml_use_internal_errors( $prev );
	if ( ! $xml ) {
		return array();
	}
	$out = array();
	foreach ( $xml->entry as $entry ) {
		$yt = $entry->children( 'http://www.youtube.com/xml/schemas/2015' );
		$id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $yt->videoId );
		if ( ! $id ) {
			continue;
		}
		$out[] = array(
			'id'    => $id,
			'title' => liberty_clean_video_title( (string) $entry->title, (string) $entry->published ),
			'date'  => (int) strtotime( (string) $entry->published ),
			'thumb' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
			'url'   => 'https://www.youtube.com/watch?v=' . $id,
		);
	}
	return $out;
}

/**
 * "🛑 LIVE | LIBERTY NEWS UPDATE" → "Liberty News Update, 23 October".
 */
function liberty_clean_video_title( $title, $published = '' ) {
	$title = trim( preg_replace( '/^\s*(?:🛑|🔴)?\s*(?:LIVE\s*[|:\-]\s*)?/u', '', $title ) );
	$fixed = liberty_title_case( $title );
	if ( $fixed !== $title && str_contains( $fixed, 'Update' ) && $published ) {
		$fixed .= ', ' . wp_date( 'j F', (int) strtotime( $published ) );
	}
	return $fixed;
}

/**
 * Shows the newsroom maintains on the Newsroom settings screen.
 *
 * @return array<int, array{name: string, kind: string, text: string, link: string}>
 */
function liberty_shows() {
	$raw  = (string) liberty_opt( 'newsroom', 'shows', '' );
	$rows = array();
	foreach ( preg_split( '/\R/', $raw ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( ! empty( $p[0] ) ) {
			$rows[] = array( 'name' => $p[0], 'kind' => $p[1] ?? '', 'text' => $p[2] ?? '', 'link' => $p[3] ?? '' );
		}
	}
	if ( ! $rows ) {
		$rows = array(
			array( 'name' => 'Liberty News Update', 'kind' => 'Bulletin', 'text' => 'Today’s stories, read from the Liberty newsroom.', 'link' => '' ),
			array( 'name' => 'The Topic', 'kind' => 'Debate', 'text' => 'One national question, argued out by the people closest to it.', 'link' => '' ),
			array( 'name' => 'Liberty Sport', 'kind' => 'Sport', 'text' => 'Football, basketball and the athletes flying the flag.', 'link' => '' ),
			array( 'name' => 'Media Trends', 'kind' => 'Media', 'text' => 'The media, the message and the people who make it.', 'link' => '' ),
			array( 'name' => 'Teens Circle Podcast', 'kind' => 'Youth', 'text' => 'Young Sierra Leoneans, their questions, their voices.', 'link' => '' ),
		);
	}
	return $rows;
}
