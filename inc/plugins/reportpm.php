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

// Tell MyBB when to run the hooks
$plugins->add_hook("report_type", "reportpm_report");
$plugins->add_hook("postbit_pm", "reportpm_postbit");
$plugins->add_hook("modcp_reports_report", "reportpm_run");
$plugins->add_hook("modcp_allreports_report", "reportpm_run");
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
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is activated.
function reportpm_activate()
{
	global $db;

	$insert_array = array(
		'title'		=> 'postbit_report_pm',
		'template'	=> $db->escape_string('<a href="javascript:;" onclick="MyBB.popupWindow(\'/report.php?type=privatemessage&amp;pid={$pmid}\');" title="{$lang->postbit_report_pm}" class="postbit_report"><span>{$lang->postbit_button_report}</span></a>'),
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
	$db->delete_query("templates", "title IN('postbit_report_pm')");

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
function reportpm_run()
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
			$post = "reputation.php?uid={$usercache[$report['id3']]['uid']}#rid{$report['id']}";
			$user = build_profile_link($pm['username'], $pm['fromid']);
			$report_data['content'] = $lang->sprintf($lang->report_info_pm, $post, $user);
		}
	}
}

// Delete report if PM is deleted
function reportpm_pmdelete()
{
	global $db, $mybb, $lang;
	$lang->load("reportpm");

	// Prevent this PM from being deleted if it has an unread report
	$query = $db->simple_select("reportedpms", "pmid, reportstatus", "pmid='".intval($mybb->input['pmid'])."'");
	$report = $db->fetch_array($query);
	if($report['pmid'])
	{
		if($report['reportstatus'] == 0)
		{
			error($lang->error_pmreport_unread);
		}
		else
		{
			$db->delete_query("reportedpms", "pmid='".intval($mybb->input['pmid'])."'");
		}
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
			$query = $db->simple_select("reportedpms", "pmid, reportstatus", "pmid IN ($pmssql)");
			$report = $db->fetch_array($query);
			if($report['pmid'])
			{
				if($report['reportstatus'] == 0)
				{
					error($lang->error_pmreport_unread_multi);
				}
				else
				{
					$db->delete_query("reportedpms", "pmid IN ($pmssql)");
				}
			}
		}
	}
}

?>