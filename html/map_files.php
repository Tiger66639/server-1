<?php
/*******************************************************************************
 * Copyright (c) 2008 Eclipse Foundation and others.
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * Contributors:
 *    Eclipse Foundation - Initial API and implementation
*******************************************************************************/
include("global.php");
InitPage("login");

global $User;
global $App, $dbh;
if(!$User->is_committer) {
	exitTo("error.php?errNo=3214","error: 3214 - you must be an Eclipse committer to access this page.");
}

require(BABEL_BASE_DIR . "classes/file/file.class.php");


$pageTitle 		= "Babel - Define Map Files";
$pageKeywords 	= "";
$incfile 		= "content/en_map_files.php";

$PROJECT_ID = $App->getHTTPParameter("project_id");
$VERSION	= $App->getHTTPParameter("version");
$LOCATION	= $App->getHTTPParameter("location");
$FILENAME	= $App->getHTTPParameter("filename");
$SUBMIT 	= $App->getHTTPParameter("submit");

if($SUBMIT == "Save") {
	if($PROJECT_ID != "" && $VERSION != "" && $LOCATION != "") {
		$sql = "INSERT INTO map_files VALUES ("
			. $App->returnQuotedString($App->sqlSanitize($PROJECT_ID, $dbh))
			. "," . $App->returnQuotedString($App->sqlSanitize($VERSION, $dbh))
			. "," . $App->returnQuotedString($App->sqlSanitize($FILENAME, $dbh))
			. "," . $App->returnQuotedString($App->sqlSanitize($LOCATION, $dbh))
			. ", 1)";
		mysql_query($sql, $dbh);
		$LOCATION = "";
		$FILENAME = "";
	}
	else {
		$GLOBALS['g_ERRSTRS'][0] = "Project, version and URL cannot be empty.";  
	}
}
if($SUBMIT == "delete") {
	$SUBMIT = "showfiles";
	$sql = "DELETE FROM map_files WHERE  
	project_id = " . $App->returnQuotedString($App->sqlSanitize($PROJECT_ID, $dbh)) . "
	AND version = " . $App->returnQuotedString($App->sqlSanitize($VERSION, $dbh)) . "
	AND filename = ". $App->returnQuotedString($App->sqlSanitize($FILENAME, $dbh)) . " LIMIT 1";
	mysql_query($sql, $dbh);
}

if($SUBMIT == "showfiles") {
	$incfile 	= "content/en_map_files_show.php";
	$sql = "SELECT project_id, version, filename, location FROM map_files WHERE is_active = 1 
	AND project_id = " . $App->returnQuotedString($App->sqlSanitize($PROJECT_ID, $dbh)) . "
	AND version = " . $App->returnQuotedString($App->sqlSanitize($VERSION, $dbh));
	$rs_map_file_list = mysql_query($sql, $dbh);
	include($incfile);
}
else {
	$sql = "SELECT project_id FROM projects WHERE is_active = 1 ORDER BY project_id";
	$rs_project_list = mysql_query($sql, $dbh);
	
	$sql = "SELECT project_id, version FROM project_versions WHERE is_active = 1 ORDER BY project_id ASC, version DESC";
	$rs_version_list = mysql_query($sql, $dbh);
	include("head.php");
	include($incfile);
	include("foot.php");  
}


?>