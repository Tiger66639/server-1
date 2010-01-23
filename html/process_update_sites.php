<?php
/*******************************************************************************
 * Copyright (c) 2010 Eclipse Foundation and others.
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * Contributors:
 *    Kit Lo (IBM) - Initial API and implementation
 *    Kit Lo (IBM) - Bug 299402, Extract properties files from Eclipse project update sites for translation
*******************************************************************************/
/*
 * Extract properties files from update sites 
 */
$temp_dir = "/tmp/tmp-babel/";
$debug = TRUE;
$files = array();

header("Content-type: text/plain");
include("global.php");
InitPage("");

$headless = 0;
if (!isset($User)) {
  echo "User not defined - running headless\n";
  $User = getGenieUser();
  $headless = 1;
}

require(dirname(__FILE__) . "/../classes/file/file.class.php");
global $dbh;

$temp_unzips_dir = $temp_dir . "unzips/";
if (is_dir($temp_unzips_dir)) {
  exec("rm -rf $temp_unzips_dir");
}
mkdir($temp_unzips_dir, 0777, TRUE) || die("***ERROR: Cannot create working directory: $temp_unzips_dir\n");

global $addon;
$context = $addon->callHook('context');
if ($context == "live") {
  $rsync_host = "download.eclipse.org::eclipseMirror/";
} else {
  $rsync_host = "rsync.osuosl.org::eclipse/";
}

# Get all active update sites
$sql = "SELECT * FROM map_files WHERE is_active = 1 AND is_map_file = 0";
$rs_maps = mysql_query($sql, $dbh);
while($update_site = mysql_fetch_assoc($rs_maps)) {
  $site_url = $update_site['location'];
  $project_id = $update_site['project_id'];
  $version = $update_site['version'];
  # Sample dirs:
  # $site_url         http://download.eclipse.org/eclipse/updates/3.6milestones/S-3.6M4-200912101301
  # $site_dir         eclipse/updates/3.6milestones/S-3.6M4-200912101301/
  # $site_plugins_dir eclipse/updates/3.6milestones/S-3.6M4-200912101301/plugins/
  # $temp_site_dir    /tmp/tmp-babel/update_sites/eclipse/3.6/
  # $temp_unzip_dir   /tmp/tmp-babel/unzips/eclipse/3.6/
  $site_dir = substr($site_url, strpos($site_url, "/", 7) + 1) . "/";
  $site_plugins_dir = $site_dir . "/plugins/";
  $temp_site_dir = $temp_dir . "update_sites/" . $project_id . "/" . $version . "/";
  $temp_unzip_dir = $temp_dir . "unzips/" . $project_id . "/" . $version . "/";

  # Get all current active files for this project version
  $sql = "SELECT * FROM files WHERE is_active = 1 AND project_id = \"$project_id\" AND version = \"$version\"";
  $rs_files = mysql_query($sql, $dbh);
  while ($myrow_files = mysql_fetch_assoc($rs_files)) {
    $file = new File();
    $file->project_id = $myrow_files['project_id'];
    $file->version = $myrow_files['version'];
    $file->name = $myrow_files['name'];
    $file->plugin_id = $myrow_files['plugin_id'];
    $file->file_id = $myrow_files['file_id'];
    $files[$file->file_id] = $file;
  }

  # Get all plugin exclude patterns for this project version
  $sql = "SELECT pattern FROM plugin_exclude_patterns WHERE project_id = \"$project_id\" AND version = \"$version\"";
  $rs_patterns = mysql_query($sql, $dbh);
  $patterns = Array();
  while ($myrow_patterns = mysql_fetch_assoc($rs_patterns)) {
    $patterns[] = $myrow_patterns['pattern'];
  }

  # Rsync update site
  exec("mkdir -p $temp_site_dir; rsync -av --delete $rsync_host$site_plugins_dir $temp_site_dir");

  # Make unzip dir
  mkdir($temp_unzip_dir, 0777, TRUE);

  # Unzip properties files in each jar
  chdir($temp_site_dir);
  foreach (glob("*.jar") as $jar_name) {
    # Sample jar name: org.eclipse.ui.workbench_3.6.0.I20100105-1530.jar
    #
    # Remove plugin version from jar name:
    # org.eclipse.ui.workbench_3.6.0.I20100105-1530.jar => org.eclipse.ui.workbench
    $temp_str = $jar_name;
    $temp_str = substr($temp_str, 0, strrpos($temp_str, "."));
    $temp_str = substr($temp_str, 0, strrpos($temp_str, "."));
    $temp_str = substr($temp_str, 0, strrpos($temp_str, "_"));
    $plugin_id = $temp_str;

    if (!is_dir($temp_unzip_dir . $plugin_id)) {
      mkdir($temp_unzip_dir . $plugin_id, 0777, TRUE);
    }
    chdir($temp_unzip_dir . $plugin_id);
    exec("unzip -o -q $temp_site_dir$jar_name");
  }

  # Collect properties file names
  $properties_file_names = array();
  chdir($temp_unzip_dir);
  exec("find . -name *.properties", $properties_file_names);
  sort($properties_file_names);

  # Parse each properties file
  echo "Start processing properties files in project $project_id version $version...\n";
  foreach ($properties_file_names as $properties_file_name) {
    # Remove "./" from beginning of properties file name
    $properties_file_name = substr($properties_file_name, 2);
    $plugin_id = substr($properties_file_name, 0, strpos($properties_file_name, "/"));
	$file_id = File::getFileID($properties_file_name, $project_id, $version);

    # Match plugin exclude list
    $match = false;
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $properties_file_name)) {
        $match = true;
        break;
      }
    }

    if (!$match) {
      if ($files[$file_id] != null) {
        # Existing file
        $file = $files[$file_id];
        $file->is_active = 1;
        unset($files[$file_id]);
      } else {
        # New file
        $file = new File();
        $file->project_id = $project_id;
        $file->version = $version;
        $file->name = $properties_file_name;
        $file->plugin_id = $plugin_id;
        $file->is_active = 1;
      }
      if (!$file->save()) {
        echo "***ERROR: Cannot save file $file->name\n";
      } else {
        $file_name = $temp_unzip_dir . $properties_file_name;
        $file->parseProperties(file_get_contents($file_name));
        if ($debug) {
          echo "  $properties_file_name\n";
        }
      }
    } else {
      if ($debug) {
        echo "  !!! Excluding $properties_file_name\n";
      }
    }
  }
  echo "Done processing " . sizeof($properties_file_names) . " properties files in project $project_id version $version\n\n";
}

# Deactivate the rest of the files
echo "Start deactivating inactive properties files in all projects above...\n";
foreach ($files as $file) {
  $file->is_active = 0;
  if (!$file->save()) {
    echo "***ERROR: Cannot deactivate file $file->name\n";
  }
  if ($debug) {
    echo "  " . $file->name . "\n";
  }
}
echo "Done deactivating " . sizeof($files) . " inactive properties files in all projects above\n\n";

if ($headless) {
  $User = null;
}

echo "Done\n";
?>