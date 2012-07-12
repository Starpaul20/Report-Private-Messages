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
if(my_strpos($_SERVER['PHP_SELF'], 'modcp.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'modcp_nav_pmreports,modcp_pmreports_report,modcp_pmreports_noreports,modcp_pmreports,modcp_pmreports_allreport,modcp_pmreports_allreports';
}

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
$plugins->add_hook("modcp_start", "reportpm_run");
$plugins->add_hook("postbit_pm", "reportpm_postbit");
$plugins->add_hook("global_start", "reportpm_global");
$plugins->add_hook("private_delete_start", "reportpm_pmdelete");
$plugins->add_hook("private_do_stuff", "reportpm_massdelete");
$plugins->add_hook("fetch_wol_activity_end", "reportpm_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "reportpm_online_location");

$plugins->add_hook("admin_user_users_merge_commit", "reportpm_merge");
$plugins->add_hook("admin_user_users_delete_commit", "reportpm_delete");
$plugins->add_hook("admin_tools_cache_start", "reportpm_datacache_class");
$plugins->add_hook("admin_tools_cache_rebuild", "reportpm_datacache_class");

// The information that shows up on the plugin manager
function reportpm_info()
{
	return array(
		"name"				=> "Report PMs",
		"description"		=> "Allows users to report Private Messages if they are spam/abuse etc.",
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.2",
		"guid"				=> "46a2797acab132157f82b63d11ba2e52",
		"compatibility"		=> "16*"
	);
}

// This function runs when the plugin is installed.
function reportpm_install()
{
	global $db;
	reportpm_uninstall();
	$collation = $db->build_create_table_collation();

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."reportedpms (
				rid int(10) unsigned NOT NULL auto_increment,
				pmid int(10) unsigned NOT NULL default '0',
				uid int(10) unsigned NOT NULL default '0',
				reportstatus int(1) NOT NULL default '0',
				reason varchar(250) NOT NULL default '',
				dateline bigint(30) NOT NULL default '0',
				KEY dateline (dateline),
				PRIMARY KEY(rid)
			) ENGINE=MyISAM{$collation}");

	update_reportedpms();
}

// Checks to make sure plugin is installed
function reportpm_is_installed()
{
	global $db;
	if($db->table_exists("reportedpms"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function reportpm_uninstall()
{
	global $db;
	if($db->table_exists("reportedpms"))
	{
		$db->drop_table("reportedpms");
	}

	$db->delete_query("datacache", "title='reportedpms'");
}

// This function runs when the plugin is activated.
function reportpm_activate()
{
	global $db;

	$insert_array = array(
		'title'		=> 'modcp_pmreports',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->reported_pms}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="modcp.php" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="page" value="{$page}" />
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" align="center" colspan="7"><strong>{$lang->reported_pms}</strong></td>
</tr>
<tr>
<td class="tcat" align="center" width="10%"><span class="smalltext"><strong>{$lang->pm_id}</strong></span></td>
<td class="tcat" align="center" width="15%"><span class="smalltext"><strong>{$lang->sender}</strong></span></td>
<td class="tcat" align="center" width="25%"><span class="smalltext"><strong>{$lang->message}</strong></span></td>
<td class="tcat" align="center" width="15%"><span class="smalltext"><strong>{$lang->reporter}</strong></span></td>
<td class="tcat" align="center" width="20%"><span class="smalltext"><strong>{$lang->report_reason}</strong></span></td>
<td class="tcat" align="center" width="10%"><span class="smalltext"><strong>{$lang->report_time}</strong></span></td>
<td class="tcat" align="center" width="5%"><input type="checkbox" name="allbox" onclick="selectReportedPMs();" /></td>
</tr>
{$pmreports}
{$pmreportspages}
<tr>
<td class="tfoot" colspan="7" align="right"><span class="smalltext"><strong><a href="modcp.php?action=allpmreports">{$lang->view_all_reported_pms}</a></strong></span></td>
</tr>
</table>
<br />
<div align="center"><input type="hidden" name="action" value="do_pmreports" /><input type="submit" class="button" name="reportsubmit" value="{$lang->mark_read}" /></div>
</td>
</tr>
</table>
</form>
{$footer}
<script type="text/javascript">
<!--
	var checked = false;
	function selectReportedPMs()
	{
		if(checked == false)
		{
			checked = true;
			$$(\'input[type="checkbox"]\').invoke(\'writeAttribute\', \'checked\', \'checked\');
		}
		else
		{
			checked = false;
			$$(\'input[type="checkbox"]\').invoke(\'writeAttribute\', \'checked\', \'\');
		}
	}
// -->
</script>
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_pmreports_noreports',
		'template'	=> $db->escape_string('<tr>
<td class="trow1" align="center" colspan="7">{$lang->no_pm_reports}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_pmreports_allreports',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->all_reported_pms}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" align="center" colspan="6"><strong>{$lang->all_reported_pms_note}</strong></td>
</tr>
<tr>
<td class="tcat" align="center" width="10%"><span class="smalltext"><strong>{$lang->pm_id}</strong></span></td>
<td class="tcat" align="center" width="15%"><span class="smalltext"><strong>{$lang->sender}</strong></span></td>
<td class="tcat" align="center" width="25%"><span class="smalltext"><strong>{$lang->message}</strong></span></td>
<td class="tcat" align="center" width="15%"><span class="smalltext"><strong>{$lang->reporter}</strong></span></td>
<td class="tcat" align="center" width="25%"><span class="smalltext"><strong>{$lang->report_reason}</strong></span></td>
<td class="tcat" align="center" width="10%"><span class="smalltext"><strong>{$lang->report_time}</strong></span></td>
</tr>
{$allpmreports}
{$allpmreportspages}
</table>
</td>
</tr>
</table>
{$footer}
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_pmreports_report',
		'template'	=> $db->escape_string('<tr>
<td class="{$trow}" align="center"><label for="pmreports_{$report[\'rid\']}">{$report[\'pmid\']}</label></td>
<td class="{$trow}" align="center"><a href="{$report[\'pmerlink\']}" target="_blank">{$report[\'username\']}</a></td>
<td class="{$trow}">{$report[\'message\']}</td>
<td class="{$trow}" align="center"><a href="{$report[\'reporterlink\']}" target="_blank">{$report[\'reporter\']}</a></td>
<td class="{$trow}">{$report[\'reason\']}</td>
<td class="{$trow}" align="center" style="white-space: nowrap"><span class="smalltext">{$reportdate}<br />{$reporttime}</small></td>
<td class="{$trow}" align="center"><input type="checkbox" class="checkbox" name="reports[]" id="pmreports_{$report[\'rid\']}" value="{$report[\'rid\']}" /></td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_nav_pmreports',
		'template'	=> $db->escape_string('<tr><td class="trow1 smalltext"><a href="modcp.php?action=pmreports" class="modcp_nav_item" style="background:url(\'images/modcp/pmreports.gif\') no-repeat left center;">{$lang->mcp_nav_reported_pms}</a></td></tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'modcp_pmreports_allreport',
		'template'	=> $db->escape_string('<tr>
<td class="{$trow}" align="center">{$report[\'pmid\']}</td>
<td class="{$trow}" align="center"><a href="{$report[\'pmerlink\']}" target="_blank">{$report[\'username\']}</a></td>
<td class="{$trow}">{$report[\'message\']}</td>
<td class="{$trow}" align="center"><a href="{$report[\'reporterlink\']}" target="_blank">{$report[\'reporter\']}</a></td>
<td class="{$trow}">{$report[\'reason\']}</td>
<td class="{$trow}" align="center" style="white-space: nowrap"><span class="smalltext">{$reportdate}<br />{$reporttime}</small></td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'global_unreadpmreports',
		'template'	=> $db->escape_string('<div class="red_alert"><a href="modcp.php?action=pmreports">{$lang->unread_pm_reports}</a></div>
<br />'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'reportpm',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->report_pm}</title>
{$headerinclude}
</head>
<body>
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="trow1" align="center">
<br />
<br />
<strong>{$lang->report_to_mod}</strong>
<form action="reportpm.php" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="do_report" />
<input type="hidden" name="pmid" value="{$pmid}" />
<blockquote>{$lang->only_report}</blockquote>
<br />
<br />
<span class="smalltext">{$lang->report_reason}</span>
<br />
<input type="text" class="textbox" name="reason" size="40" maxlength="250" />
<br />
<br />
<div align="center"><input type="submit" class="button" value="{$lang->report_pm}" /></div>
</form>
</td>
</tr>
</table>
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'postbit_report_pm',
		'template'	=> $db->escape_string('<a href="javascript:MyBB.popupWindow(\'reportpm.php?pmid={$post[\'pmid\']}\', \'report\', \'400\', \'300\')"><img src="{$theme[\'imglangdir\']}/postbit_report.gif" alt="{$lang->postbit_report}" title="{$lang->postbit_report}" /></a>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'reportpm_thanks',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->report_pm}</title>
{$headerinclude}
</head>
<body>
<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="trow1" align="center">
<br />
<br />
<strong>{$lang->thank_you}</strong>
<blockquote>{$lang->pm_reported}</blockquote>
<br /><br />
<div style="text-align: center;">
<script type="text/javascript">
<!--
document.write(\'[<a href="javascript:window.close();">{$lang->close_window}</a>]\');
// -->
</script>
</div>
</td>
</tr>
</table>
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("modcp_nav", "#".preg_quote('{$lang->mcp_nav_reported_posts}</a></td></tr>')."#i", '{$lang->mcp_nav_reported_posts}</a></td></tr><!-- reportpm -->');
	find_replace_templatesets("header", "#".preg_quote('{$unreadreports}')."#i", '{$unreadreports}{$unreadpmreports}');
}

// This function runs when the plugin is deactivated.
function reportpm_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('modcp_pmreports','modcp_pmreports_noreports','postbit_report_pm','reportpm','modcp_pmreports_report','global_unreadpmreports','modcp_pmreports_allreports','modcp_nav_pmreports','modcp_pmreports_allreport','reportpm_thanks')");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("modcp_nav", "#".preg_quote('<!-- reportpm -->')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$unreadpmreports}')."#i", '', 0);
}

// Add report button on PM postbit
function reportpm_postbit($post)
{
	global $db, $mybb, $templates, $theme;

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
	global $db, $mybb, $lang, $modcp_nav, $theme, $header, $headerinclude, $templates, $footer, $cache;
	$lang->load("reportpm");

	require_once MYBB_ROOT."inc/class_parser.php";
	$parser = new postParser;

	if($mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['cancp'] == 1)
	{
		eval("\$reportpmsnav = \"".$templates->get("modcp_nav_pmreports")."\";");
		$modcp_nav = str_replace("<!-- reportpm -->", $reportpmsnav, $modcp_nav);
	}

	if($mybb->input['action'] == "do_pmreports")
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if($mybb->usergroup['issupermod'] == 0 && $mybb->usergroup['cancp'] == 0)
		{
			error_no_permission();
		}

		if(!is_array($mybb->input['reports']))
		{
			error($lang->error_noselected_pmreports);
		}

		$sql = '1=1';
		if(!$mybb->input['allbox'])
		{
			$mybb->input['reports'] = array_map("intval", $mybb->input['reports']);
			$rids = implode($mybb->input['reports'], "','");
			$rids = "'0','{$rids}'";

			$sql = "rid IN ({$rids})";
		}

		$db->update_query("reportedpms", array('reportstatus' => 1), "{$sql}");
		update_reportedpms();

		$page = intval($mybb->input['page']);
		redirect("modcp.php?action=pmreports&page={$page}", $lang->redirect_pmreportsmarked);
	}

	if($mybb->input['action'] == "pmreports")
	{
		add_breadcrumb($lang->nav_modcp, "modcp.php");
		add_breadcrumb($lang->mcp_nav_reported_pms, "modcp.php?action=pmreports");

		if($mybb->usergroup['issupermod'] == 0 && $mybb->usergroup['cancp'] == 0)
		{
			error_no_permission();
		}

		if(!$mybb->settings['threadsperpage'])
		{
			$mybb->settings['threadsperpage'] = 20;
		}

		// Figure out if we need to display multiple pages.
		$perpage = intval($mybb->settings['threadsperpage']);
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$query = $db->simple_select("reportedpms", "COUNT(rid) AS count", "reportstatus ='0'");
		$report_count = $db->fetch_field($query, "count");

		$mybb->input['rid'] = intval($mybb->input['rid']);

		if($mybb->input['rid'])
		{
			$query = $db->simple_select("reportedpms", "COUNT(rid) AS count", "rid <= '".$mybb->input['rid']."'");
			$result = $db->fetch_field($query, "count");
			if(($result % $perpage) == 0)
			{
				$page = $result / $perpage;
			}
			else
			{
				$page = intval($result / $perpage) + 1;
			}
		}
		$pmcount = intval($report_count);
		$pages = $pmcount / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page && $page > 0)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$upper = $start+$perpage;

		$multipage = multipage($pmcount, $perpage, $page, "modcp.php?action=pmreports");
		if($pmcount > $perpage)
		{
			eval("\$pmreportspages = \"".$templates->get("modcp_reports_multipage")."\";");
		}

		$pmreports = '';
		$query = $db->query("
			SELECT r.*, u.username AS reporter, up.username AS username, up.uid as fromuid, p.message AS message
			FROM ".TABLE_PREFIX."reportedpms r
			LEFT JOIN ".TABLE_PREFIX."privatemessages p ON (r.pmid=p.pmid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."users up ON (p.fromid=up.uid)
			WHERE r.reportstatus='0'
			ORDER BY r.dateline DESC
			LIMIT {$start}, {$perpage}
		");
		while($report = $db->fetch_array($query))
		{
			if($report['reportstatus'] == 0)
			{
				$trow = "trow_shaded";
			}
			else
			{
				$trow = alt_trow();
			}

			$report['pmerlink'] = get_profile_link($report['fromuid']);
			$report['reporterlink'] = get_profile_link($report['uid']);
			$reportdate = my_date($mybb->settings['dateformat'], $report['dateline']);
			$reporttime = my_date($mybb->settings['timeformat'], $report['dateline']);

			$parser_options = array(
				"allow_html" => $mybb->settings['pmsallowhtml'],
				"allow_mycode" => $mybb->settings['pmsallowmycode'],
				"allow_smilies" => $mybb->settings['pmsallowsmilies'],
				"allow_imgcode" => $mybb->settings['pmsallowimgcode'],
				"allow_videocode" => $mybb->settings['pmsallowvideocode'],
				"filter_badwords" => 1
			);
			$report['message'] = $parser->parse_message($report['message'], $parser_options);

			eval("\$pmreports .= \"".$templates->get("modcp_pmreports_report")."\";");
		}

		if(!$pmreports)
		{
			eval("\$pmreports = \"".$templates->get("modcp_pmreports_noreports")."\";");
		}

		eval("\$reportedpms = \"".$templates->get("modcp_pmreports")."\";");
		output_page($reportedpms);

	}

	if($mybb->input['action'] == "allpmreports")
	{
		add_breadcrumb($lang->nav_modcp, "modcp.php");
		add_breadcrumb($lang->mcp_nav_all_reported_pms, "modcp.php?action=allpmreports");

		if($mybb->usergroup['issupermod'] == 0 && $mybb->usergroup['cancp'] == 0)
		{
			error_no_permission();
		}

		if(!$mybb->settings['threadsperpage'])
		{
			$mybb->settings['threadsperpage'] = 20;
		}

		// Figure out if we need to display multiple pages.
		$perpage = intval($mybb->settings['threadsperpage']);
		if($mybb->input['page'] != "last")
		{
			$page = intval($mybb->input['page']);
		}

		$query = $db->simple_select("reportedpms", "COUNT(rid) AS count");
		$warnings = $db->fetch_field($query, "count");

		if($mybb->input['rid'])
		{
			$mybb->input['rid'] = intval($mybb->input['rid']);
			$query = $db->simple_select("reportedpms", "COUNT(rid) AS count", "rid <= '".$mybb->input['rid']."'");
			$result = $db->fetch_field($query, "count");
			if(($result % $perpage) == 0)
			{
				$page = $result / $perpage;
			}
			else
			{
				$page = intval($result / $perpage) + 1;
			}
		}
		$pmcount = intval($warnings);
		$pages = $pmcount / $perpage;
		$pages = ceil($pages);

		if($mybb->input['page'] == "last")
		{
			$page = $pages;
		}

		if($page > $pages || $page <= 0)
		{
			$page = 1;
		}

		if($page)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$upper = $start+$perpage;

		$multipage = multipage($pmcount, $perpage, $page, "modcp.php?action=allpmreports");
		if($pmcount > $perpage)
		{
			eval("\$allpmreportspages = \"".$templates->get("modcp_reports_multipage")."\";");
		}

		$pmreports = '';
		$query = $db->query("
			SELECT r.*, u.username AS reporter, up.username AS username, up.uid as fromuid, p.message AS message
			FROM ".TABLE_PREFIX."reportedpms r
			LEFT JOIN ".TABLE_PREFIX."privatemessages p ON (r.pmid=p.pmid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (r.uid=u.uid)
			LEFT JOIN ".TABLE_PREFIX."users up ON (p.fromid=up.uid)
			ORDER BY r.dateline DESC
			LIMIT $start, $perpage
		");
		while($report = $db->fetch_array($query))
		{
			if($report['reportstatus'] == 0)
			{
				$trow = "trow_shaded";
			}
			else
			{
				$trow = alt_trow();
			}

			$report['pmerlink'] = get_profile_link($report['fromuid']);
			$report['reporterlink'] = get_profile_link($report['uid']);
			$reportdate = my_date($mybb->settings['dateformat'], $report['dateline']);
			$reporttime = my_date($mybb->settings['timeformat'], $report['dateline']);

			$parser_options = array(
				"allow_html" => $mybb->settings['pmsallowhtml'],
				"allow_mycode" => $mybb->settings['pmsallowmycode'],
				"allow_smilies" => $mybb->settings['pmsallowsmilies'],
				"allow_imgcode" => $mybb->settings['pmsallowimgcode'],
				"allow_videocode" => $mybb->settings['pmsallowvideocode'],
				"filter_badwords" => 1
			);
			$report['message'] = $parser->parse_message($report['message'], $parser_options);

			eval("\$allpmreports .= \"".$templates->get("modcp_pmreports_allreport")."\";");
		}

		if(!$allpmreports)
		{
			eval("\$allpmreports = \"".$templates->get("modcp_pmreports_noreports")."\";");
		}

		eval("\$allreportedpms = \"".$templates->get("modcp_pmreports_allreports")."\";");
		output_page($allreportedpms);
	}
}

// Alerts mods/admins on new PM reports
function reportpm_global()
{
	global $mybb, $lang, $cache, $templates, $unreadpmreports;
	$lang->load("reportpm");

	$unreadpmreports = '';
	// This user is an administrator or super moderator
	if($mybb->usergroup['issupermod'] == 1 || $mybb->usergroup['cancp'] == 1)
	{
		// Read the reported PMs cache
		$reported = $cache->read("reportedpms");

		// 0 or more reported PMs currently exist
		if($reported['unread'] > 0)
		{
			if($reported['unread'] == 1)
			{
				$lang->unread_pm_reports = $lang->unread_pm_report;
			}
			else
			{
				$lang->unread_pm_reports = $lang->sprintf($lang->unread_pm_reports, $reported['unread']);
			}
			eval("\$unreadpmreports = \"".$templates->get("global_unreadpmreports")."\";");
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

		update_reportedpms();
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

				update_reportedpms();
			}
		}
	}
}

// Online activity
function reportpm_online_activity($user_activity)
{
	global $user;
	if(my_strpos($user['location'], "reportpm.php") !== false)
	{
		$user_activity['activity'] = "reportpm";
		$user_activity['pmid'] = $parameters['pmid'];
	}

	return $user_activity;
}

function reportpm_online_location($plugin_array)
{
    global $db, $mybb, $lang, $parameters;
	$lang->load("reportpm");

	if($plugin_array['user_activity']['activity'] == "reportpm")
	{
		$plugin_array['location_name'] = $lang->reporting_a_pm;
	}

	return $plugin_array;
}

// Merge report history if users are merged
function reportpm_merge()
{
    global $db, $mybb, $source_user, $destination_user;

	$uid = array(
		"uid" => $destination_user['uid']
	);
	$db->update_query("reportedpms", $uid, "uid='{$source_user['uid']}'");
}

// Delete report history if user is deleted
function reportpm_delete()
{
	global $db, $mybb, $user;
	$db->delete_query("reportedpms", "uid='{$user['uid']}'");
}

// Rebuild Report PM cache in Admin CP
function reportpm_datacache_class()
{
	global $cache;

	if(class_exists('MyDatacache'))
	{
		class ReportDatacache extends MyDatacache
		{
			function update_reportedpms()
			{
				update_reportedpms();
			}
		}

		$cache = null;
		$cache = new ReportDatacache;
	}
	else
	{
		class MyDatacache extends datacache
		{
			function update_reportedpms()
			{
				update_reportedpms();
			}
		}

		$cache = null;
		$cache = new MyDatacache;
	}
}

/**
 * Update reported PM cache.
 *
 */
function update_reportedpms()
{
	global $db, $cache;
	$reports = array();
	$query = $db->simple_select("reportedpms", "COUNT(rid) AS unreadcount", "reportstatus='0'");
	$num = $db->fetch_array($query);

	$query = $db->simple_select("reportedpms", "COUNT(rid) AS reportcount");
	$total = $db->fetch_array($query);

	$query = $db->simple_select("reportedpms", "dateline", "reportstatus='0'", array('order_by' => 'dateline', 'order_dir' => 'DESC'));
	$latest = $db->fetch_array($query);

	$reports = array(
		"unread" => $num['unreadcount'],
		"total" => $total['reportcount'],
		"lastdateline" => $latest['dateline']
	);

	$cache->update("reportedpms", $reports);
}

?>