<?php

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

class qa_custom_rss_process
{

	function init_feed()
	{
		$requestlower=strtolower(qa_request());
		$foursuffix=substr($requestlower, -4);

		if ( ($foursuffix=='.rss') || ($foursuffix=='.xml') ) {
			$requestlower=substr($requestlower, 0, -4);
		}

		$requestlowerparts=explode('/', $requestlower);

		$feedtype=@$requestlowerparts[1];
		$feedparams=array_slice($requestlowerparts, 2);

		if ($feedtype !== 'question_custom') {
			return;
		}

		// echo $feedtype;
		$feedoption=null;
		$categoryslugs=$feedparams;

		$countslugs=@count($categoryslugs);

		require_once QA_INCLUDE_DIR.'db/selects.php';

		$sitetitle=qa_opt('site_title');
		$siteurl=qa_opt('site_url');
		$full=qa_opt('feed_full_text');
		$count=qa_opt('feed_number_items');
		$showurllinks=qa_opt('show_url_links');

		$linkrequest=$feedtype.($countslugs ? ('/'.implode('/', $categoryslugs)) : '');
		$linkparams=null;

		$questions=qa_feed_load_ifcategory($categoryslugs, 'main/recent_qs_title', 'main/recent_qs_in_x', $title,
			qa_db_qs_selectspec(null, 'created', 0, $categoryslugs, null, false, $full, $count)
			// qa_db_qs_selectspec(null, 'created', 0, $categoryslugs, null, false, $full, $count),
			// qa_db_recent_a_qs_selectspec(null, 0, $categoryslugs, null, false, $full, $count)
		);
		$title .= ' 画像あり';
		//	Remove duplicate questions (perhaps referenced in an answer and a comment) and cut down to size

		require_once QA_INCLUDE_DIR.'app/format.php';
		require_once QA_INCLUDE_DIR.'app/updates.php';
		require_once QA_INCLUDE_DIR.'util/string.php';

		if ( ($feedtype!='search') && ($feedtype!='hot') ) // leave search results and hot questions sorted by relevance
			$questions=qa_any_sort_and_dedupe($questions);

		$questions=array_slice($questions, 0, $count);
		$blockwordspreg=qa_get_block_words_preg();


		//	Prepare the XML output

		$lines=array();

		$lines[]='<?xml version="1.0" encoding="utf-8"?>';
		$lines[]='<rss version="2.0">';
		$lines[]='<channel>';

		$lines[]='<title>'.qa_xml($sitetitle.' - '.$title).'</title>';
		$lines[]='<link>'.qa_xml(qa_path($linkrequest, $linkparams, $siteurl)).'</link>';
		$lines[]='<description>Powered by Question2Answer</description>';

		foreach ($questions as $question) {

		//	Determine whether this is a question, answer or comment, and act accordingly

			$options=array('blockwordspreg' => @$blockwordspreg, 'showurllinks' => $showurllinks);

			$time=null;
			$htmlcontent=null;

			if (isset($question['opostid'])) {
				$time=$question['otime'];

				if ($full)
					$htmlcontent=qa_viewer_html($question['ocontent'], $question['oformat'], $options);

			} elseif (isset($question['postid'])) {
				$time=$question['created'];

				if ($full)
					$htmlcontent=qa_viewer_html($question['content'], $question['format'], $options);
			}
			if (!preg_match( '/<img(.+?)>/i', $htmlcontent)) {
				continue;
			}
			switch (@$question['obasetype'].'-'.@$question['oupdatetype']) {
				case 'Q-':
				case '-':
					$langstring=null;
					break;

				case 'Q-'.QA_UPDATE_VISIBLE:
					$langstring=$question['hidden'] ? 'misc/feed_hidden_prefix' : 'misc/feed_reshown_prefix';
					break;

				case 'Q-'.QA_UPDATE_CLOSED:
					$langstring=isset($question['closedbyid']) ? 'misc/feed_closed_prefix' : 'misc/feed_reopened_prefix';
					break;

				case 'Q-'.QA_UPDATE_TAGS:
					$langstring='misc/feed_retagged_prefix';
					break;

				case 'Q-'.QA_UPDATE_CATEGORY:
					$langstring='misc/feed_recategorized_prefix';
					break;

				case 'A-':
					$langstring='misc/feed_a_prefix';
					break;

				case 'A-'.QA_UPDATE_SELECTED:
					$langstring='misc/feed_a_selected_prefix';
					break;

				case 'A-'.QA_UPDATE_VISIBLE:
					$langstring=$question['ohidden'] ? 'misc/feed_hidden_prefix' : 'misc/feed_a_reshown_prefix';
					break;

				case 'A-'.QA_UPDATE_CONTENT:
					$langstring='misc/feed_a_edited_prefix';
					break;

				case 'C-':
					$langstring='misc/feed_c_prefix';
					break;

				case 'C-'.QA_UPDATE_TYPE:
					$langstring='misc/feed_c_moved_prefix';
					break;

				case 'C-'.QA_UPDATE_VISIBLE:
					$langstring=$question['ohidden'] ? 'misc/feed_hidden_prefix' : 'misc/feed_c_reshown_prefix';
					break;

				case 'C-'.QA_UPDATE_CONTENT:
					$langstring='misc/feed_c_edited_prefix';
					break;

				case 'Q-'.QA_UPDATE_CONTENT:
				default:
					$langstring='misc/feed_edited_prefix';
					break;

			}

			$titleprefix=isset($langstring) ? qa_lang($langstring) : '';

			$urlxml=qa_xml(qa_q_path($question['postid'], $question['title'], true, @$question['obasetype'], @$question['opostid']));

			if (isset($blockwordspreg))
				$question['title']=qa_block_words_replace($question['title'], $blockwordspreg);

		//	Build the inner XML structure for each item

			$lines[]='<item>';
			$lines[]='<title>'.qa_xml($titleprefix.$question['title']).'</title>';
			$lines[]='<link>'.$urlxml.'</link>';

			if (isset($htmlcontent))
				$lines[]='<description>'.qa_xml($htmlcontent).'</description>';

			if (isset($question['categoryname']))
				$lines[]='<category>'.qa_xml($question['categoryname']).'</category>';

			$lines[]='<guid isPermaLink="true">'.$urlxml.'</guid>';

			if (isset($time))
				$lines[]='<pubDate>'.qa_xml(gmdate('r', $time)).'</pubDate>';

			$lines[]='</item>';
		}

		$lines[]='</channel>';
		$lines[]='</rss>';


		//	Disconnect here, once all output is ready to go

		qa_db_disconnect();


		//	Output the XML - and we're done!

		header('Content-type: text/xml; charset=utf-8');
		echo implode("\n", $lines);

		qa_exit();

	}
}

/*
	Omit PHP closing tag to help avoid accidental output
*/
