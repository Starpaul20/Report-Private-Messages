<?php
/**
 * Report PMs
 * Copyright 2011 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(my_strpos($_SERVER['PHP_SELF'], 'private.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'postbit_report_pm';
}

if(my_strpos($_SERVER['PHP_SELF'], 'modcp.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'modcp_viewpm';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("report_type", "reportpm_report");
$plugins->add_hook("postbit_pm", "reportpm_postbit");
$plugins->add_hook("modcp_reports_report", "reportpm_modcp");
$plugins->add_hook("modcp_allreports_report", "reportpm_modcp");
$plugins->add_hook("modcp_start", "reportpm_view");
$plugins->add_hook("private_delete_start", "reportpm_pmdelete");
$plugins->add_hook("private_do_stuff", "reportpm_massdelete");

// The information that shows up on the plugin manager
function reportpm_info()
{
	global $lang;
	$lang->load("reportpm", true);

	return array(
		"name"				=> $lang->reportpm_info_name,
		"description"		=> $lang->reportpm_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"codename"			=> "reportpm",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is activated.
function reportpm_activate()
{
	global $db;

	// Insert templates
	$insert_array = array(
		'title'		=> 'postbit_report_pm',
		'template'	=> $db->escape_string('<a href="javascript:;" onclick="MyBB.popupWindow(\'/report.php?type=privatemessage&amp;pid={$pmid}\');" title="{$lang->postbit_report_pm}" class="postbit_report"><span>{$lang->postbit_button_report}</span></a>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_viewpm',
		'template'	=> $db->escape_string('<div class="modal">
<div style="overflow-y: auto; max-height: 400px;">
<table width="100%" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" border="0" align="center" class="tborder">
<tr>
<td colspan="2" class="thead"><strong>{$lang->view_reported_pm}</strong></td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->subject}</strong></td>
<td class="trow1">{$report[\'subject\']}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->from_user}</strong></td>
<td class="trow2">{$report[\'fromuser\']}</td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->to_user}</strong></td>
<td class="trow1">{$report[\'touser\']}</td>
</tr>
<tr>
<td class="trow2"><strong>{$lang->date_sent}</strong></td>
<td class="trow2">{$report[\'dateline\']}</td>
</tr>
<tr>
<td class="trow1"><strong>{$lang->ip_address}</strong></td>
<td class="trow1">{$ipaddress}</td>
</tr>
<tr>
<td colspan="2" class="trow2">{$report[\'message\']}</td>
</tr>
</table>
</div>
</div>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("private_read", "#".preg_quote('{$headerinclude}')."#i", '{$headerinclude}<script type="text/javascript" src="{$mybb->asset_url}/jscripts/report.js?ver=1800"></script>');
}

// This function runs when the plugin is deactivated.
function reportpm_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('postbit_report_pm','modcp_viewpm')");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("private_read", "#".preg_quote('<script type="text/javascript" src="{$mybb->asset_url}/jscripts/report.js?ver=1800"></script>')."#i", '', 0);
}

// Report the PM
function reportpm_report()
{
	global $db, $mybb, $report_type, $error, $verified, $report_type_db, $id, $id2, $id3;

	if($report_type == 'privatemessage')
	{
		$query = $db->simple_select("privatemessages", "*", "pmid = '".$mybb->get_input('pid', 1)."'");

		if(!$db->num_rows($query))
		{
			$error = $lang->error_invalid_report;
		}
		else
		{
			$verified = true;
			$pm = $db->fetch_array($query);

			$id = $pm['pmid']; // id is the pm id
			$id2 = $pm['fromid']; // id2 is the user who sent the message
			$id3 = $pm['uid']; // id3 is the user who received the message

			$report_type_db = "type = 'privatemessage'";
		}
	}
}

// Add report button on PM postbit
function reportpm_postbit($post)
{
	global $db, $mybb, $templates, $lang;
	$lang->load("reportpm");

	$pmid = intval($mybb->input['pmid']);

	$query = $db->simple_select("privatemessages", "folder", "pmid='{$pmid}'");
	$folder = $db->fetch_array($query);

	if($folder['folder'] != 2)
	{
		eval("\$post['button_report'] = \"".$templates->get("postbit_report_pm")."\";");
	}

	return $post;
}

// The Mod CP report PM pages
function reportpm_modcp()
{
	global $db, $lang, $report, $report_data;
	$lang->load("reportpm");

	if($report['type'] == 'privatemessage')
	{
		$query = $db->query("
			SELECT pm.pmid, pm.fromid, u.username, pm.subject
			FROM ".TABLE_PREFIX."privatemessages pm
			LEFT JOIN ".TABLE_PREFIX."users u ON (pm.fromid = u.uid)
			WHERE pm.pmid='{$report['id']}'
		");
		while($pm = $db->fetch_array($query))
		{
			$pmview = "MyBB.popupWindow('/modcp.php?action=viewpm&amp;rid={$report['rid']}');";
			$user = build_profile_link($pm['username'], $pm['fromid']);
			$report_data['content'] = $lang->sprintf($lang->report_info_pm, $pmview, $user);
		}
	}
}

// View the PM from the Mod CP
function reportpm_view()
{
	global $db, $mybb, $lang, $templates, $theme;
	$lang->load("reportpm");

	if($mybb->input['action'] == "viewpm")
	{
		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;

		if($mybb->usergroup['canmanagereportedcontent'] == 0)
		{
			error_no_permission();
		}

		$rid = $mybb->get_input('rid', 1);

		$query = $db->query("
			SELECT pm.*, r.type, t.username AS to_username, u.username AS from_username
			FROM ".TABLE_PREFIX."reportedcontent r
			LEFT JOIN ".TABLE_PREFIX."privatemessages pm ON (pm.pmid = r.id)
			LEFT JOIN ".TABLE_PREFIX."users u ON (pm.fromid = u.uid)
			LEFT JOIN ".TABLE_PREFIX."users t ON (pm.toid = t.uid)
			WHERE r.rid='{$rid}'
		");
		$report = $db->fetch_array($query);

		// Make sure we are looking at a real pm report here.
		if(!$report || $report['type'] != 'privatemessage')
		{
			error($lang->error_badreport);
		}

		$report['touser'] = htmlspecialchars_uni($report['to_username']);
		$report['fromuser'] = htmlspecialchars_uni($report['from_username']);
		$report['subject'] = htmlspecialchars_uni($report['subject']);
		$report['dateline'] = my_date($mybb->settings['dateformat'], $report['dateline']).", ".my_date($mybb->settings['timeformat'], $report['dateline']);

		if(empty($report['ipaddress']))
		{
			$ipaddress = $lang->na;
		}
		else
		{
			$ipaddress = my_inet_ntop($db->unescape_binary($report['ipaddress']));
		}

		// Parse PM text
		$parser_options = array(
			"allow_html" => $mybb->settings['pmsallowhtml'],
			"allow_mycode" => $mybb->settings['pmsallowmycode'],
			"allow_smilies" => $mybb->settings['pmsallowsmilies'],
			"allow_imgcode" => $mybb->settings['pmsallowimgcode'],
			"allow_videocode" => $mybb->settings['pmsallowvideocode'],
			"nl2br" => 1
		);
		$report['message'] = $parser->parse_message($report['message'], $parser_options);

		eval("\$viewpm = \"".$templates->get("modcp_viewpm", 1, 0)."\";");
		echo $viewpm;
		exit;
	}
}

// Delete report if PM is deleted
function reportpm_pmdelete()
{
	global $db, $mybb, $lang;
	$lang->load("reportpm");

	// Prevent this PM from being deleted if it has an unread report
	$query = $db->simple_select('reportedcontent', 'id', "reportstatus='0' AND type = 'privatemessage'");
	$report = $db->fetch_array($query);

	if($report['id'] == (int)$mybb->input['pmid'])
	{
		error($lang->error_pmreport_unread);
	}
}

// Mass delete reports if PMs are deleted
function reportpm_massdelete()
{
	global $db, $mybb, $lang;
	$lang->load("reportpm");

	if($mybb->input['delete'])
	{
		if(is_array($mybb->input['check']))
		{
			$pmssql = '';
			foreach($mybb->input['check'] as $key => $val)
			{
				if($pmssql)
				{
					$pmssql .= ",";
				}
				$pmssql .= "'".intval($key)."'";
			}

			// Prevent any PMs from being deleted if it has an unread report
			$query = $db->simple_select("reportedcontent", "id, reportstatus", "id IN ($pmssql) AND type = 'privatemessage'");
			$report = $db->fetch_array($query);
			if($report['id'])
			{
				if($report['reportstatus'] == 0)
				{
					error($lang->error_pmreport_unread_multi);
				}
			}
		}
	}
}

?>