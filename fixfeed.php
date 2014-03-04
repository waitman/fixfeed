<?php
/*
	fixfeed.php
	
Copyright (c) 2014, Waitman Gobble <ns@waitman.net>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met: 

1. Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer. 
2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution. 

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are those
of the authors and should not be interpreted as representing official policies, 
either expressed or implied, of the FreeBSD Project.

*/

/* keep track of times, avoid duplicates.. offset by a second. 
   (only to keep validator from whining) */

$rec=array();
function difftime($v) {
	global $rec;
	while ($rec[$v]) $v++;
	$rec[$v]=true;
	return ($v);
}

/* trivially use the highest time as the feed update time */

function maxtime() {
	global $rec;
	$d=array_keys($rec);
	asort($d);
	$max = array_pop($d);
	return($max);
}

/* pull the Topic and CVE from the content and use as the summary */

function summary($v) {
	$d=join('',file($v));
	$x=explode("\n",$d);
	$topic='';
	$cve = '';
	foreach ($x as $l=>$m) {
		if (substr($m,0,6)=='Topic:') $topic = trim(str_replace('Topic:','',$m));
		if (substr($m,0,9)=='CVE Name:') $cve = trim(str_replace('CVE Name:','',$m));
	}
	return (trim($topic.' '.$cve));
}

/* convert the entire content into HTML for display in newsreader */

function content($v) {
	$d=join('',file($v));
	$x=explode("\n",$d);
	$cd=array();
	foreach ($x as $v) {
		$v=trim(str_replace("\n",'',$v));
		$cd[]=''.htmlspecialchars($v,ENT_COMPAT,'ISO-8859-1',true).'<br />';
	}
	return ('<![CDATA[<html>'.join("\n",$cd).'</html>]]>');
}
	
	
	
/* generate uuid from url */

function id($v) {
$v = sha1($v);
$guid=substr($v,0,8).'-'.substr($v,8,4).'-'.substr($v,12,4).'-'.substr($v,16,4).'-'.substr($v,20,12);
return ($guid);
}


$links=array();
$dates=array();
$titles=array();

/* original feed for cloning */

$f=join('',file('http://www.freebsd.org/security/rss.xml'));

/* extract links, pubdate, and title */

$d=explode('<link>',$f);
array_shift($d);
foreach ($d as $k=>$v) {
	$x=explode('</link>',$v);
	$links[] = array_shift($x);
}
$d=explode('<pubDate>',$f);
array_shift($d);
foreach ($d as $k=>$v) {
        $x=explode('</pubDate>',$v);
        $dates[] = array_shift($x);
} 
$d=explode('<title>',$f);
array_shift($d);
foreach ($d as $k=>$v) {
	$x=explode('</title>',$v);
	$titles[] = array_shift($x);
}

/* rebuild feed, in reverse - this way pubdates, links, titles match up */

$feed=array();
while (count($dates)) {
	$date=array_pop($dates);
	$link=array_pop($links);
	$id=id($link);
	$title=array_pop($titles);
	$summary = summary($link);
	
	/* RFC 3339 Time */

	$feed[]='<link href="'.$link.'" />
<updated>'.date('Y-m-d\TH:i:sP',difftime(strtotime($date))).'</updated>
<title>'.$title.'</title>
<summary>'.$summary.'</summary>
<content type="text/html">'.content($link).'</content>
<id>urn:uuid:'.$id.'</id>
';
}

/* re-reverse */

$j=array_reverse($feed);

/* output heading */

echo '<?xml version="1.0"?>
<feed xmlns="http://www.w3.org/2005/Atom">
<title>FreeBSD Security Advisories</title>
<subtitle>Security Advisories published from the FreeBSD Project</subtitle>
<link href="http://www.FreeBSD.org/security" />
<link href="http://www.da3m0n8t3r.com/atom.xml" rel="self" />
<id>http://www.da3m0n8t3r.com/atom.xml</id>
<updated>'.date('Y-m-d\TH:i:sP',maxtime()).'</updated>
<author>
<name>FreeBSD Security Team</name>
<email>secteam@FreeBSD.org</email>
</author>
';

/* ouput entries */

foreach ($j as $v) {
	echo '<entry>
'.$v.'</entry>
';
}
echo '</feed>
';

//EOF
