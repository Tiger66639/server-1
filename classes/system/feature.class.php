<?php
/*******************************************************************************
 * Copyright (c) 2007-2008 Eclipse Foundation and others.
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * Contributors:
 *    Antoine Toulme, Intalio Inc. bug 248845: Refactoring generate1.php into different files with a functional approach
*******************************************************************************/

require(BABEL_BASE_DIR . "classes/system/fragment.class.php"); 

class Feature {
	public $language;
	public $feature_id;
	public $train;
	public $fragments;
	public $output_dir;
	public $tmp_dir;
	
	/**
	 * default constructor
	 */ 
	function Feature($language, $train, $tmp_dir, $output_dir, $fragments = null) {
		$this->language = $language;
		$this->train = $train;
		if (!$fragments) {
			$fragments = Fragment::select($language, $train);
		}
		$this->fragments = $fragments;
		$this->output_dir = $output_dir;
		$this->tmp_dir = $tmp_dir;
		$projects = $this->associated_projects();
		if (count($projects) == 1) {
			$this->feature_id = "org.eclipse.babel.nls_$projects[0]->id_$language->iso";
		} else {
			$this->feature_id = "org.eclipse.babel.nls_$language->iso";
		}
	}
	
	/**
	 * Returns an integer between 0 and 100 representing the percent completion 
	 * of the translation of this feature in this language.
	 */
	function pct_complete() {
		if (strcmp($this->language->iso, "en_AA") == 0) {
			return 100;
		}
		foreach(associated_projects() as $project) {
			$sql = "SELECT pct_complete
				FROM project_progress
				WHERE project_id = \"$project->id\"
					AND version = \"$project->version\"
					AND language_id = $this->language->id";
			$project_pct_complete_result = mysql_query($sql);
			$project_pct_complete = mysql_result($project_pct_complete_result, 0);
			if (!isSet($pct)) {
				$pct = $project_pct_complete;
			} else {
				// there might be some better way to do the average.
				$pct = $pct + $project_pct_complete/2;
			}
		}
		return $pct;
	}
	
	/*
	 * Copies all the necessary legal files in the destination folder specified.
	 */
	function copyLegalFiles($dir) {
		exec("cp ". LEGAL_FILES_DIR. "about.html $dir");
		exec("cp ". LEGAL_FILES_DIR. "eclipse_update_120.jpg $dir");
		exec("cp ". LEGAL_FILES_DIR. "epl-v10.html $dir");
		exec("cp ". LEGAL_FILES_DIR. "feature.properties $dir");
		exec("cp ". LEGAL_FILES_DIR. "license.html $dir");
	}
	
	/**
	 * Generates the fragments and the feature.
	 */
	function generateAll() {
		$this->cleanupOutput();
		foreach($this->fragments as $fragment) {
			$this->generateFragment($fragment);
		}
		$this->generate();
	}
	
	/*
	 * Cleans the $output_dir/eclipse/features/ and $output_dir/eclipse/plugins folders.
	 */
	function cleanupOutput($output_dir = null) {
		if (!$output_dir) {
			$output_dir = $this->output_dir;
		}
		$cmd = "rm -Rf $output_dir/eclipse/features/* ; rm -Rf $output_dir/eclipse/plugins/* ; mkdir -p $output_dir/eclipse/features/ ; mkdir -p $output_dir/eclipse/plugins/";
		system($cmd, $retval);
		if (!$retval) {
			echo "### ERROR during the execution of: $cmd";
		}
	}

	/**
	 * Copies the legal files and generates the feature.xml file in the feature folder.
	 */
	function generate($dir = null) {
		if (!$dir) {
			$dir = "$this->output_dir/eclipse/features/$this->feature_id";
		}
		exec("mkdir -p $dir");
		$this->copyLegalFiles($dir);
		$this->generateFeatureXml($dir);
	}
	
	/**
	 * Generates a fragment.
	 */
	function generateFragment($fragment, $tmp_dir = null, $output_dir = null) {
		if (!$tmp_dir) {
			$tmp_dir = $this->tmp_dir;
		}
		if (!$output_dir) {
			$output_dir = $this->output_dir;
		}
		$fragment->generateFragment($tmp_dir, "$output_dir/eclipse/plugins/");
	}

	/*
	 * Generates the feature.xml file in the designated folder.
	 */
	function generateFeatureXml($dir) {
		$project_toStr = join(",", associated_projects());
		$outp = fopen("$dir/feature.xml", "w");
			fwrite($outp, "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>" .
				"\n<feature id=\"$this->feature_id\"" .
				"\n\tlabel=\"Babel Language Pack for $project_toStr in $this->language->name (pct_complete())\"" .
				"\n\timage=\"eclipse_update_120.jpg\"" .
				"\n\tprovider-name=\"%providerName\"" .
				"\n\tversion=\"$this->train->version_$this->train->timestamp\">" .
				"\n\t<copyright>\n\t\t%copyright\n\t</copyright>" .
				"\n\t<license url=\"%licenseURL\">\n\t\t%license\n\t</license>" .
				"\n\t<description>Babel Language Pack for $project_toStr in $this->language->name</description>" );
				
		foreach ($this->fragments as $fragment) {
			fwrite($outp, "\n\t<plugin fragment=\"true\" id=\"$fragment->fragment_id()\" unpack=\"false\" " .
				"version=\"$train->version_$train->timestamp\" download-size=\"$fragment->filesize\" install-size=\"$fragment->filesize\" />");
		}
		fwrite($outp, "\n</feature>");
		fclose($outp);
	}
	
	/**
	 * Returns the projects associated with this feature.
	 */
	function associated_projects() {
		$projects = array();
		foreach($this->fragments as $fragment) {
			$projects[] = $fragment->associated_projects();
		}
		return array_unique($projects);
	}
	
	/*
	 * jars the content of a directory $dir, places it in a file named $output
	 */
	function internalJar($dir, $output) {
		$cmd = "cd $dir; jar cfM $output .";
		$retval = system($cmd);
		if (!$retval) {
			echo "### ERROR during the execution of: $cmd";
		}
	}
	
	/**
	 * Jars the feature into an output folder.
	 * This function is only used when defining features for a site, not for a normal feature creation
	 */ 
	function jar($dir = null, $output_dir = null) {
		if (!$dir) {
			$dir = "$this->output_dir/eclipse/features/$this->feature_id";
		}
		if (!$output_dir) {
			$output_dir = $this->output_dir;
		}
		$feature_filename = filename() . ".jar";
		$this->internalJar($dir, "$output_dir/$feature_filename");
	}
	
	/*
	 * Returns the file name of the feature as guessed from the feature_id
	 * and the train information.
	 */
	function filename() {
		return "$this->feature_id_$this->train->version_$this->train->timestamp";
	}
	
	/**
	 * Zips the feature as a zip to a destination folder.
	 */
	function zip($destination, $output_dir = null) {
		if (!$output_dir) {
			$output_dir = $this->output_dir;
		}
		 
		$filename = filename() . ".zip";
		$cmd = "cd ${output_dir}; zip -r $filename eclipse ; mv $filename $destination";
		system($cmd, $retval);
		if (!$retval) {
			echo "### ERROR during the execution of: $cmd";
		}
		
		return "$destination/$filename";
	}
}