/*******************************************************************************
 * Copyright (c) 2007 Eclipse Foundation and others.
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * Contributors:
 *    Paul Colton (Aptana)- initial API and implementation
 *    Eclipse Foundation
*******************************************************************************/

YAHOO.tranlsation = new Object();

YAHOO.tranlsation.posted = false;


function showTranslateStringForm(stringIdIn){
	var callback = 
	{ 
		start:function(eventType, args){ 
		},
		success: function(o) {
			var langDomNode = document.getElementById('translation-form-container');
			langDomNode.innerHTML = o.responseText;
			YAHOO.util.Event.onAvailable("translation-form",setupTranslatFormCB);
			
		},
		failure: function(o) {
			YAHOO.log('failed!');
		} 
	} 
	YAHOO.util.Connect.asyncRequest('POST', "callback/getCurrentStringTranslation.php", callback, "string_id="+stringIdIn);
}

function setupTranslatFormCB(){
	YAHOO.util.Event.addListener("translation-form","submit",translationSumbitStop);
	YAHOO.util.Event.addListener("allversions","click",translateAll);
	YAHOO.util.Event.addListener("onlysametrans","click",translateOnlySameTranslations);
}


function translationClear(){
	if(YAHOO.tranlsation.posted == true){
		YAHOO.tranlsation.posted = false;
	}else{
		var langDomNode = document.getElementById('translation-form-container');
		langDomNode.innerHTML = "";
	}
}


function translateAll(e){
	translationSumbit("all");
}
function translateOnlySameTranslations(e){
	translationSumbit("onlysame")
}

function translationSumbitStop(e){
	YAHOO.util.Event.stopEvent(e);
}

function translationSumbit(allornot){
	var target = document.getElementById('translation-form');

	var callback = 
	{ 
		start:function(eventType, args){ 
		},
		success: function(o) {
			YAHOO.projectStringsManager.getAjaxProjectStrings();
		},
		failure: function(o) {
			YAHOO.log('failed!');
		} 
	} 
	
	YAHOO.tranlsation.posted = true;
	
	var post = "string_id="+target.string_id.value+
			   "&translation="+sub(target.translation.value)+
			   "&translate_action="+allornot;
			   
	YAHOO.util.Connect.asyncRequest('POST', "callback/setStringTranslation.php", callback, post);
}

function sub(it){
	it = it.replace(/\+/g,"%2b");
	return it.replace(/&/g,"%26"); 
}