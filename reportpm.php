<?php
/**
 * Report PMs
 * Copyright 2011 Starpaul20
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'reportpm.php');

$templatelist = "reportpm,reportpm_thanks,report_noreason";

require_once "./global.php";

// Load global language phrases
$lang->load("reportpm");

if($mybb->usergroup['canview'] == 0 || !$mybb->user['uid'])
{
	error_no_permission();
}

if($mybb->input['action'] != "do_report")
{
	$mybb->input['action'] = "report";
}

$pmid = intval($mybb->input['pmid']);
$query = $db->simple_select("privatemessages", "pmid, uid, fromid", "pmid='{$pmid}'");
$pm = $db->fetch_array($query);

if(!$pm['pmid'])
{
	$error = $lang->error_invalidpm;
	eval("\$report_error = \"".$templates->get("report_error")."\";");
	output_page($report_error);
	exit;
}

if($mybb->user['uid'] != $pm['uid'])
{
	$error = $lang->error_cannotreport;
	eval("\$report_error = \"".$templates->get("report_error")."\";");
	output_page($report_error);
	exit;
}

if($mybb->user['uid'] == $pm['fromid'])
{
	$error = $lang->error_cannotreportsent;
	eval("\$report_error = \"".$templates->get("report_error")."\";");
	output_page($report_error);
	exit;
}

if($mybb->input['action'] == "report")
{
	eval("\$reportpm = \"".$templates->get("reportpm")."\";");
	output_page($reportpm);
}

if($mybb->input['action'] == "do_report" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->input['my_post_key']);

	if(!trim($mybb->input['reason']))
	{
		eval("\$reportpm = \"".$templates->get("report_noreason")."\";");
		output_page($reportpm);
		exit;
	}

	$reportedpm = array(
		"pmid" => intval($mybb->input['pmid']),
		"uid" => intval($mybb->user['uid']),
		"dateline" => TIME_NOW,
		"reportstatus" => 0,
		"reason" => $db->escape_string(htmlspecialchars_uni($mybb->input['reason']))
	);
	$db->insert_query("reportedpms", $reportedpm);
	update_reportedpms();

	eval("\$reportpm = \"".$templates->get("reportpm_thanks")."\";");
	output_page($reportpm);
}

?>