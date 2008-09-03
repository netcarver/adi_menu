<?php
$plugin['name'] = 'adi_menu';
$plugin['version'] = '0.4';
$plugin['author'] = 'Adi Gilbert';
$plugin['author_uri'] = 'http://www.greatoceanmedia.com.au/';
$plugin['description'] = 'Section hierarchy, section menu and breadcrumb trail';
$plugin['type'] = '1';

@include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

/*
	adi_menu - section hierarchy, section menu and breadcrumb trail

	Written by Adi Gilbert

	Released under the GNU Lesser General Public License

	Version history:
		0.4		- enhancement: adi_breadcrumb tag attribute 'link_last' - last section crumb in list displayed in plain text (now the default behaviour)
				- fix: adi_menu_breadcrumb error when visiting sections/pages that don't exist when in clean URL mode
				- fix: adi_menu_breadcrumb displayed default section as link regardless of 'link' attribute setting
		0.3		- enhancement: new adi_menu tag attribute 'link_span' - wrap <span>...</span> around contents of links
				- enhancement: new adi_menu tag attributes 'list_id' & 'list_id_prefix' - output unique IDs to <li> elements
				- enhancement: new adi_menu tag attribute 'active_li_class' - output class on active <li>
				- enhancement: adi_menu admin now displays summary of configured section hierarchy
				- modification: adi_breadcrumb tag attribute 'sep' deprecated for 'separator'
		0.2		- fix: adi_menu_breadcrumb error when visiting pages that are excluded in adi_menu admin
				- fix: adi_menu_breadcrumb now copes with section loops, error message output
				- fix: adi_menu tag can now be used more than once on a page
				- enhancement: adi_menu admin section loop warning message
				- enhancement: adi_menu admin now displays sections in alphabetical order
		0.1		- initial release

*/

if (@txpinterface == 'admin') 
	{
	$myevent = 'adi_menu_admin';
	$mytab = 'adi_menu';

	// set the privilege levels
	add_privs($myevent, '1,2,3,6');

	// add new tab under 'Presentation'
	register_tab("presentation", $myevent, $mytab);

	register_callback("adi_menu_admin", $myevent);
	//register_callback('adi_menu', 'adi_menu');
	}

function adi_menu_admin($event, $step) 
	{
	$debug = 0; // display debug info

	// tooltip help information
	$h['se'] = "The TXP section name, set in Sections tab";
	$h['ti'] = "The TXP section title, set in Sections tab";
	$h['ex'] = "Tick if the section should be excluded by default from the menu";
	$h['pa'] = "The section's parent";
	$h['so'] = "The section's sort order number";
	$h['ch'] = "Tick if the section should be cloned in its own submenu";
	$h['ct'] = "Used as the section's title if it's cloned";
	$h['in'] = "Install adi_menu";
	$h['un'] = "Uninstall adi_menu";
	$h['im'] = "Import parent settings from cnk_section_tree (install adi_menu first)";

	$installed = adi_menu_column_found('adi_menu_parent');

	$something = ps("something");
	$res = FALSE;

	if ($installed) 
		{
		if ($something == "uninstall") 
			{
			require_privs('plugin');
			$res = adi_menu_uninstall();
			if ($res)
	   			pagetop("adi_menu admin", "adi_menu: uninstall successful");
			else
	   			pagetop("adi_menu admin", "adi_menu: uninstall not successful");
			$installed = adi_menu_column_found('adi_menu_parent');
			}
		else if ($something == "install") 
			{
			require_privs('plugin');
			pagetop("adi_menu admin", "adi_menu: already installed");
			}
		else if ($something == "import") 
			{
			$res = adi_menu_import();
	   		$res ? pagetop("adi_menu admin", "adi_menu: import successful") :
				pagetop("adi_menu admin", "adi_menu: import failed");
			}
		else if ($step == "update") 
			{
	   		pagetop("adi_menu admin", "adi_menu: updated");
				if ($debug) 
					{
					echo "<br/>Parent: ";
					print_r(ps('parent'));
					echo "<br/>Alt title: ";
					print_r(ps('alt_title'));
					echo "<br/>Exclude: ";
					print_r(ps('exclude'));
					echo "<br/>Clone: ";
					print_r(ps('clone'));
					echo "<br/>Sort: ";
					print_r(ps('sort'));
					}
				$parent = ps('parent');
				$alt_title = ps('alt_title');
				$exclude = ps('exclude');
				$clone = ps('clone');
				$sort = ps('sort');
				$sections = adi_get_sections();
				adi_menu_update($sections,$parent,$alt_title,$exclude,$clone,$sort);
			}
		else if ($step == "admin")
		   	pagetop("adi_menu admin", "adi_menu: no admin option selected");
		else // do nothing
		   	pagetop("adi_menu admin", "");
		}
	else 
		{ // not installed
		if ($something == "install") 
			{
			require_privs('plugin');
			$res = adi_menu_install();
			if ($res)
	    		pagetop("adi_menu admin", "adi_menu: install successful");
			else
	    		pagetop("adi_menu admin", "adi_menu: install not successful");
			$installed = adi_menu_column_found('adi_menu_parent');
			}
		else if ($something == "uninstall") 
			{
			require_privs('plugin');
			pagetop("adi_menu admin", "adi_menu: not installed");
			}
		else if ($something == "import")
	   		pagetop("adi_menu admin", "adi_menu: must be installed first");
		}

	if ($installed) 
		{
		$sections = adi_get_sections();
		// perform check for section loops
		$out = "";
		foreach ($sections as $index => $section)
			if (adi_menu_loop_check($sections,$index,$index,FALSE))
				$out .= " ".$index;
		if ($out) 
			{
			echo tag(
				"<strong>** WARNING: Section loops found (check: $out) **</strong>",
				'div',
				' align="center" style="margin:1em"'
				);
			}
		echo form(
			startTable('list').
			tr(
				hcell(adi_menu_tip("Section",$h['se'])).
				hcell(adi_menu_tip("Title",$h['ti'])).
				hcell(adi_menu_tip("Exclude?",$h['ex'])).
				hcell(adi_menu_tip("Parent",$h['pa'])).
				hcell(adi_menu_tip("Sort order",$h['so'])).
				hcell(adi_menu_tip("Clone?",$h['ch'])).
				hcell(adi_menu_tip("Clone title",$h['ct']))
				).
			//'<tfoot style="font-weight:bold"><tr><td>Section</td><td>Title</td><td>Exclude?</td><td>Parent</td><td>Sort order</td><td>Clone?</td><td>Clone title</td></tr></tfoot>'.
			adi_menu_display_settings($sections).
			endTable().
			tag(
				fInput("submit", "update", "Update", "smallerbox").
				eInput("adi_menu_admin").sInput("update"),
				'div',
				' align="center" style="margin-top:2em"'
				)
			);
		}
	else if (($something != "uninstall") && ($something != "import")) 
		{
		pagetop("adi_menu admin", "adi_menu: not installed");
		}

	if ($debug) 
		{
		echo "Event: ".$event."<br/>Step: ".$step."<br/>Something: ".$something;
		}

    echo form(
		tag("Administration","h3").
		graf(
			" ".adi_menu_tip("Install",$h['in']).
			fInput("radio", "something", "install", "edit", "", "", "20", "1").
			" ".adi_menu_tip("Uninstall",$h['un']).
        	fInput("radio", "something", "uninstall", "edit", "", "", "20", "1").
			" ".adi_menu_tip("Import",$h['im']).
        	fInput("radio", "something", "import", "edit", "", "", "20", "1").
			" ".
        	fInput("submit", "do_something", "Do admin", "smallerbox","",'return verify(\''.gTxt('are_you_sure').'\')')
			).
        eInput("adi_menu_admin").sInput("admin")
		,"text-align:center;margin-top:3em"
		);

	// output hierarchy summary
	global $sections,$exclude,$sort,$default_first,$default_title,$include_default,$menu_id;
	$sections=$exclude="";
	$sort="NULL";
	$default_first=$include_default="1";
	$menu_id = "mainmenu";
	$section_list = adi_menu_section_list(FALSE);
	$hierarchy = adi_menu_hierarchy($section_list,'',0);
	$out = adi_menu_markup($hierarchy,0);
	echo '<div style="margin-left:10em">';
	echo tag("Summary","h3",' style="margin-top:3em"');
	echo tag("The above configuration will generate the following section hierarchy (subject to adi_menu tag attributes):","p");
	foreach ($out as $index => $value)
		print $out[$index];
	echo '</div>';
	}

function adi_menu_tip($term,$help) 
	{
	return '<dfn'.
		' title="'.$help.'"'.
		' style="border-bottom:1px dashed black"'.
		'>'.
		$term.
		'</dfn>';
	}

function adi_menu_column_found($column) 
	{
	// if 'adi_menu_parent' column present then assume adi_menu is installed
	$rs = safe_query('SELECT * FROM '.safe_pfx('txp_section'));
	$a = nextRow($rs);
	return array_key_exists($column, $a);
	}

function adi_menu_import() 
	{
	// import parent settings from 'parent' column - as used in cnk_section_tree
	if (adi_menu_column_found('parent')) 
		{ // 'parent' column present
		$sql_fields = "name,parent";
		$sql_tables = safe_pfx('txp_section');
		$rs = safe_query("SELECT ".$sql_fields." FROM ".$sql_tables);
		while ($a = nextRow($rs)) 
			{
			extract($a);
			$import[$name] = $a;
			}
		foreach ($import as $index => $section) 
			{
			$where = 'name="'.$index.'"';
			$import[$index]['parent'] == 'default' ? // don't want 'default' as a parent
				$set = 'adi_menu_parent=""' :
				$set = 'adi_menu_parent="'.$import[$index]['parent'].'"';
			safe_update('txp_section', $set, $where, $debug='');
			}
		return TRUE;
		}
	else
		return FALSE;
	}

function adi_menu_display_settings($sections) 
	{
	$out = '';
	foreach ($sections as $index => $section) 
		{
		$name = $section['name'];
		$title = $section['title'];
		$parent = $section['adi_menu_parent'];
		$alt_title = $section['adi_menu_title'];
		$exclude = $section['adi_menu_exclude'];
		$clone = $section['adi_menu_clone'];
		$sort = $section['adi_menu_sort'];
		$out .= tr(
			tda($name).
			tda($title).
			tda(checkbox("exclude[$name]", "1", $exclude),' style="text-align:center"').
			tda(adi_menu_section_popup("parent[$name]",$parent)).
			tda(finput("text","sort[$name]",$sort,'','','',4),' style="text-align:center"').
			tda(checkbox("clone[$name]", "1", $clone),' style="text-align:center"').
			tda(finput("text","alt_title[$name]",$alt_title))
			);
		}
	return $out;
	}

function adi_menu_section_popup($select_name,$value) 
	{
	$rs = safe_column('name', 'txp_section', 'TRUE');
	if ($rs) 
		{
		return selectInput($select_name, $rs, $value, TRUE);
		}
	return false;
	}

function adi_menu_update($sections,$parent,$alt_title,$exclude,$clone,$sort) 
	{
	foreach ($sections as $index => $section) 
		{
		$where = 'name="'.$index.'"';
		$set = 'adi_menu_parent="'.$parent[$index].'"';
		safe_update('txp_section', $set, $where, $debug='');
		$set = 'adi_menu_title="'.$alt_title[$index].'"';
		safe_update('txp_section', $set, $where, $debug='');
		empty($exclude[$index]) ? $set = 'adi_menu_exclude="0"' : $set = 'adi_menu_exclude="1"';
		safe_update('txp_section', $set, $where, $debug='');
		empty($clone[$index]) ? $set = 'adi_menu_clone="0"' : $set = 'adi_menu_clone="1"';
		safe_update('txp_section', $set, $where, $debug='');
		$set = 'adi_menu_sort="'.$sort[$index].'"';
		safe_update('txp_section', $set, $where, $debug='');
		}
	}

function adi_menu_install() 
	{
	$section = safe_pfx('txp_section');
	return safe_query('ALTER TABLE '.$section." ADD adi_menu_parent VARCHAR(128) DEFAULT '';")
		&& safe_query('ALTER TABLE '.$section." ADD adi_menu_title VARCHAR(128) DEFAULT '';")
		&& safe_query('ALTER TABLE '.$section." ADD adi_menu_exclude BOOLEAN DEFAULT FALSE NOT NULL;")
		&& safe_query('ALTER TABLE '.$section." ADD adi_menu_clone BOOLEAN DEFAULT FALSE NOT NULL;")
		&& safe_query('ALTER TABLE '.$section." ADD adi_menu_sort TINYINT(3) UNSIGNED DEFAULT 0 NOT NULL;");
	}

function adi_menu_uninstall() 
	{
	$section = safe_pfx('txp_section');
	return safe_query('ALTER TABLE '.$section." DROP COLUMN adi_menu_parent;")
		&& safe_query('ALTER TABLE '.$section." DROP COLUMN adi_menu_title;")
		&& safe_query('ALTER TABLE '.$section." DROP COLUMN adi_menu_exclude;")
		&& safe_query('ALTER TABLE '.$section." DROP COLUMN adi_menu_clone;")
		&& safe_query('ALTER TABLE '.$section." DROP COLUMN adi_menu_sort;");
	}

function adi_get_sections() 
	{
	$sql_fields = "name, title, adi_menu_parent, adi_menu_title, adi_menu_exclude, adi_menu_clone, adi_menu_sort";
	$sql_tables = safe_pfx('txp_section');
	$rs = safe_query("SELECT ".$sql_fields." FROM ".$sql_tables." ORDER BY name");
	while ($a = nextRow($rs)) 
		{
		extract($a); // set 'name','title','parent' etc in $a
		$out[$name] = $a;
		}
	return $out;
	}

function adi_menu_loop_check($section_list,$start,$child,$found) 
	{
	if (($child == $start) && $found) // loop found
		return TRUE;
	if ($child == $start)
		$found = TRUE;
	if ($section_list[$child]['adi_menu_parent']) // has parent
		return adi_menu_loop_check($section_list,$start,$section_list[$child]['adi_menu_parent'],$found);
	else
		return FALSE; // no more ancestors
	}

function adi_menu_section_list($ignore_exclude) 
	{
	global $sections,$exclude,$sort,$default_first,$default_title,$include_default;
	$fields = 'name,title,adi_menu_parent,adi_menu_clone,adi_menu_title';
	if ($sections) 
		{
		$sections = do_list($sections);
		$sections = join("','", doSlash($sections));
		$rs = safe_rows_start($fields, 'txp_section', "name in ('$sections') order by ".($sort ? $sort : "field(name, '$sections')"));
		}
	else 
		{
		if ($exclude) 
			{
			$exclude = do_list($exclude);
			$exclude = join("','", doSlash($exclude));
			$exclude = "and name not in('$exclude')";
			}
		if (!$include_default) $exclude = "and name != 'default'";
		$ignore_exclude ?
			$exclude_option = "adi_menu_exclude = 0 or adi_menu_exclude = 1" : // i.e. TRUE or FALSE
			$exclude_option = "adi_menu_exclude = 0";
		$rs = safe_rows_start($fields, 'txp_section', "$exclude_option $exclude order by ".$sort);
		}
	if ($rs) 
		{
		$out = array();
		while ($a = nextRow($rs)) 
			{
			extract($a); // sets 'name','title','adi_menu_parent' etc in $a
			$a['url'] = pagelinkurl(array('s' => $name)); // add url to $a
			$out[$name] = $a;
			}
		if ($out && $default_title && $include_default) // set default section title
			$out['default']['title'] = $default_title;
		if ($out && $default_first && $include_default) { // shift default section to beginning
			$remember['default'] = $out['default']; // remember 'default' element
			unset($out['default']); // remove 'default' element
			$out = array_merge($remember, $out); // join together, 'default' now at beginning
			}
		return $out;
		}
	}

function adi_menu_breadcrumb($atts) 
	{
	global $s; // the current section
	global $label,$separator,$sep,$title,$link,$linkclass,$include_default;
	global $sections,$exclude,$sort,$default_first,$default_title,$include_default,$link_last;
	$sections=$exclude="";
	$sort="NULL";
	$default_first=$include_default="1";

	extract(lAtts(array(
		'label'				=> 'You are here: ',	// String to prepend to the output
		'separator'			=> ' &#187; ',			// string to be used as the breadcrumb separator (default: >>)
		'sep'				=> '',					// deprecated - see 'separator'
		'title'				=> '1',					// display section titles or not
		'link'				=> '1',					// output sections as links or not
		'linkclass'			=> 'noline',			// class for breadcrumb links
		'link_last'			=> '0',					// display last section crumb as link or not
		'include_default'	=> '1',					// include 'default' section or not
		'default_title'		=> 'Home',				// title for 'default' section
		), $atts));

	if ($sep) $separator = $sep; // deprecated attribute 'sep', use 'separator' instead

	function adi_menu_lineage($section_list,$child) 
		{
		global $s,$label,$separator,$sep,$title,$link,$linkclass,$include_default,$default_title,$link_last;
		global $is_article_list; // TXP global variable
		static $count = 0;
		$out = array();
		if (!array_key_exists($child, $section_list)) 
			{ // bomb out if section not found
			$out[] = '?';
			return $out;
			}
		if ($s == $child) $count++;
		if ($count > 1) 
			{
			$out[] = "Warning: Section loop found";
			return $out;
			}
		if ($include_default || ($child != 'default')) 
			{
			if ($section_list[$child]['adi_menu_parent']) // has parent
				$out = array_merge($out,adi_menu_lineage($section_list,$section_list[$child]['adi_menu_parent']));
			if (!$section_list[$child]['adi_menu_parent']) { // has no parent - i.e. top level section
				$out[] = $label;
				if ($include_default) 
					{
					if (($s == 'default') && (!$link_last) && ($is_article_list)) // if (section=default) AND (link_last=0) AND (not single article), switch off link mode
						$link = 0;
					$out[] = $link ? tag($default_title,'a',' class="'.$linkclass.'" href="'.$section_list['default']['url'].'"') : $default_title;
					}
				else
					$out[] = "";
				if ($include_default && ($s != 'default'))
					$out[] = $separator;
				}
			else
				$out[] = $separator;
			$title ?
				$crumb = $section_list[$child]['title'] :
				$crumb = $section_list[$child]['name'];
			if (($s == $child) && (!$link_last) && ($is_article_list)) // if (last breadcrumb) AND (link_last=0) AND (not single article), switch off link mode
				$link = 0;
			if ($s != 'default')
				$link ?
					$out[] = tag($crumb,'a',' class="'.$linkclass.'" href="'.$section_list[$child]['url'].'"') :
					$out[] = $crumb;
			}
		return $out;
		}

	function adi_menu_lineage2($section_list,$child) 
		{
		global $linkclass,$label,$title,$include_default,$separator,$s,$link,$link_last;
		global $is_article_list; // TXP global variable
		static $count = 0; // loop counter
		$out = array();
		if (!array_key_exists($child, $section_list)) 
			{ // bomb out if section not found
			$out[] = '?';
			return $out;
			}
		if ($s == $child) $count++;
		if ($count > 1) { // bomb out if loop found
			$out[] = "Warning, section loop found: ";
			return $out;
			}
		if ($section_list[$child]['adi_menu_parent']) // has parent
			$out = array_merge($out,adi_menu_lineage2($section_list,$section_list[$child]['adi_menu_parent']));
		else { // top of the food chain
			if (($include_default) && ($child != 'default')) // if (include default) AND (not at 'default' yet)
				$out = array_merge($out,adi_menu_lineage2($section_list,'default')); // do extra, 'default' iteration
			else
				$out[] = $label; // add the "You are here" bit
			}
		$title ? // output section's title or not
			$crumb = $section_list[$child]['title'] :
			$crumb = $section_list[$child]['name'];
		if (($s == $child) && (!$link_last) && ($is_article_list)) // if (last breadcrumb) AND (link_last=0) AND (not single article), switch off link mode
			$link = FALSE;
		$link ? // output section as a link or not
			$out[] = tag($crumb,'a',' class="'.$linkclass.'" href="'.$section_list[$child]['url'].'"') :
			$out[] = $crumb;
		if ($s != $child) $out[] = $separator; // add separator if not last crumb
		return $out;
		}

	$section_list = adi_menu_section_list(TRUE);
	//$out = adi_menu_lineage($section_list,$s);
	$out = adi_menu_lineage2($section_list,$s);
	return doWrap($out, '', '');
	}

function adi_menu_hierarchy($section_list,$this_section,$clone) 
	{
	global $clone_title;
	$hierarchy = array();
	if ($clone) { // clone parent as its child
		$hierarchy[$this_section]['name'] = $this_section;
		$hierarchy[$this_section]['title'] =
			$section_list[$this_section]['adi_menu_title'] ?
				$section_list[$this_section]['adi_menu_title'] :
				$clone_title; // use alt title
		$hierarchy[$this_section]['url'] = $section_list[$this_section]['url'];
		$hierarchy[$this_section]['clone'] = TRUE;
		$hierarchy[$this_section]['parent'] = $this_section;
		$hierarchy[$this_section]['child'] = array(); // that's enough inbreeding
		}
	foreach ($section_list as $index => $section) 
		{
		if ($section['adi_menu_parent'] == $this_section) 
			{
			$hierarchy[$index]['name'] = $section['name'];
			$hierarchy[$index]['title'] = $section['title'];
			$hierarchy[$index]['url'] = $section['url'];
			$hierarchy[$index]['clone'] = FALSE;
			//$hierarchy[$index]['parent'] = $section['adi_menu_parent']; // not currently required
			$hierarchy[$index]['child'] = adi_menu_hierarchy($section_list,$index,$section['adi_menu_clone']);
			}
		}
	return $hierarchy;
	}

function adi_menu_markup($hierarchy,$level) 
	{
	global $menu_id,$parent_class,$active_class,$s,$class,$link_span,$list_id,$list_id_prefix,$active_li_class;
	$level ? $css_id = '' : $css_id = ' id="'.$menu_id.'"'; // set CSS ID on top level <ul> only
	$level ? $css_class = '' : $css_class = ' class="'.$class.'"';
	$out[] = '<ul'.$css_id.$css_class.'>';
	foreach ($hierarchy as $index => $section) 
		{
		$parent = !empty($hierarchy[$index]['child']);
		$parent ? $class_list = $parent_class : $class_list = '';
		$name = $hierarchy[$index]['name'];
		$title = $hierarchy[$index]['title'];
		$url = $hierarchy[$index]['url'];
		$link_span ?
			$link_content = '<span>'.$title.'</span>' :
			$link_content = $title;
		$hierarchy[$index]['clone'] ? // section is a clone, so make ID unique
			$clone_suffix = '_clone' :
			$clone_suffix = '';
		$list_id ?
			$li_id = ' id="'.$list_id_prefix.$name.$clone_suffix.'"' :
			$li_id = '';
		if ($active_li_class and (0 == strcasecmp($s, $name)))
			$class_list ?
				$class_list .= ' '.$active_li_class :
				$class_list = $active_li_class;
		$class_list ? $css_class = ' class="'.$class_list.'"' : $css_class = '';
		$out[] = '<li'.$li_id.$css_class.'>';
		$out[] = tag($link_content,'a',(($active_class and (0 == strcasecmp($s, $name))) ?
			' class="'.$active_class.'"' :
			'' ).' href="'.$url.'"');
		if ($parent)
			$out = array_merge($out,adi_menu_markup($hierarchy[$index]['child'],$level+1));
		$out[] = "</li>";
		}
	$out[] = "</ul>";
	return $out;
	}

function adi_menu($atts) 
	{
	global $s,$out,$sort,$menu_id,$parent_class,$active_class,$exclude,$sections,$default_title,$default_first,$clone_title,$include_default,$class,$link_span,$list_id,$list_id_prefix,$active_li_class;

	extract(lAtts(array(
		'active_class'		=> 'active_class',	// CSS Class for current section (<a>)
		'active_li_class'	=> '',				// CSS Class for current section (<li>)
		'class'				=> 'section_list',	// CSS Class for top level <ul>
		'include_default'	=> '1',				// include 'default' section or not
		'default_title'		=> 'Home',			// title for 'default' section
		'exclude'			=> '',				// list of sections to be excluded
		'sections'			=> '',				// list of sections to be included
		'sort'				=> 'NULL',			// i.e. database order
		'menu_id'			=> 'mainmenu',		// CSS ID for top level <ul>
		'parent_class'		=> 'menuparent',	// CSS Class for parent <li>
		'default_first'		=> '1',				// section 'default' to be listed first
		'clone_title'		=> 'Summary',		// Default title of child clone
		'link_span'			=> '0',				// <span> contents of link or not
		'list_id'			=> '0',				// output <li> IDs or not
		'list_id_prefix'	=> 'menu_',			// <li> ID prefix
		'debug'				=> '0'
		), $atts));

	$default_title = trim($default_title);
	$clone_title = trim($clone_title);
	if (empty($clone_title)) // don't want it to be empty
		$clone_title = 'Summary';
	$sections = trim($sections); // menu not output if sections = " "
	$sort = trim($sort); // MySQL error if sort = " "
	// set sort to database order by default
	empty($sort) ? $sort = 'NULL' : $sort = doSlash($sort);

	/* adi_menu - main procedure */
	$section_list = adi_menu_section_list(FALSE);
	$hierarchy = adi_menu_hierarchy($section_list,'',0);
	if ($debug) 
		{
		echo "SECTION LIST<br/>";
		dmp($section_list);
		echo "HIERARCHY<br/>";
		dmp($hierarchy);
		}
	$out = adi_menu_markup($hierarchy,0);
	return doWrap($out, '', '');
	}

# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
<h1><strong>adi_menu</strong> &#8211; Section hierarchy, section menu and breadcrumb trail</h1>

	<p>This plugin provides:</p>

	<ul>
		<li>a new tab under Presentation, to set up the section hierarachy</li>
		<li>two new tags:
	<ul>
		<li><code>&lt;txp:adi_menu /&gt;</code> to output the section menu markup</li>
		<li><code>&lt;txp:adi_menu_breadcrumb /&gt;</code> to output a breadcrumb trail</li>
	</ul></li>
	</ul>

	<h2><strong>Installation</strong></h2>

	<p><i>Installation of <code>adi_menu</code> will add some columns to your Textpattern database.  These columns are named with an <code>adi_menu</code> prefix so should not interfere with anything alse.  That said, if you are of a cautious frame of mind then I can thoroughly recommend <code>rss_admin_db_manager</code> to do database backups before installation.</i></p>

	<p>Once the plugin is installed and activated, go to the <code>adi_menu</code> tab under Presentation, select Install and click the &#8220;Do admin&#8221; button. Here you will find an Uninstall option as well.</p>

	<p>Next, select the Import option if you want to copy section parent settings from <code>cnk_section_tree</code>.</p>

	<p>Assign parents, sort order etc and add the <code>&lt;txp:adi_menu /&gt;</code> &amp; <code>&lt;txp:adi_menu_breadcrumb /&gt;</code> tags to your pages or forms.</p>

	<p>Style the menu using <span class="caps">CSS</span>.</p>

	<h2><strong>Admin tab</strong></h2>

	<p>Users with sufficient privileges will see the <code>adi_menu</code> admin tab, under Presentation.  This provides:</p>

	<ul>
		<li>Section hierarchy &#8211; can be created by assigning parents</li>
		<li>Section exclusion &#8211; define which sections should be permanently excluded from the rendered section menu (sections can also be excluded using a <code>&lt;txp:adi_menu /&gt;</code> tag attribute)</li>
		<li>Sorting &#8211; specify a custom sort order, if required</li>
		<li>Cloning &#8211; specify that a section must appear as a child in its own subsection list (more on that below)</li>
		<li>Admin functions &#8211; install, uninstall and import</li>
		<li>A summary of the configured section hierarchy</li>
	</ul>

	<p>Tooltips are available here &#8211; just hover over anything with a dashed underline.</p>

	<h2><strong>adi_menu &#8211; usage</strong></h2>

	<p>Place the <code>&lt;txp:adi_menu /&gt;</code> tag wherever you want the menu to appear.</p>

	<h2><strong>adi_menu &#8211; attributes</strong></h2>

	<p><code>class="class name"</code></p>

	<p>- class applied to the top level <code>&lt;ul&gt;</code>. Default = &#8220;section_list&#8221;.</p>

	<p><code>menu_id="id name"</code></p>

	<p>- the ID to be used on the top level <code>&lt;ul&gt;</code>.  Default = &#8220;mainmenu&#8221;.</p>

	<p><code>active_class="class name"</code></p>

	<p>- class applied to the current section link. Default = &#8220;active_class&#8221;.</p>

	<p><code>active_li_class="class name"</code></p>

	<p>- class applied to the current section <code>&lt;li&gt;</code> element. Default = &#8220;&#8221; (no class).</p>

	<p><code>parent_class="class name"</code></p>

	<p>- the class to be used on section <code>&lt;li&gt;</code> that are parents. Default = &#8220;menuparent&#8221;.</p>

	<p><code>link_span="boolean"</code></p>

	<p>- specifies whether the contents of the links should be wrapped in  <code>&lt;span&gt;...&lt;/span&gt;</code>. Default = &#8220;0&#8221; (No).</p>

	<p><code>list_id="boolean"</code></p>

	<p>- specifies whether the <code>&lt;li&gt;</code> elements should have unique IDs applied. IDs are based on the section names. Default = &#8220;0&#8221; (No). Note that cloned section IDs will have a suffix of &#8220;_clone&#8221; added.</p>

	<p><code>list_id_prefix="text"</code></p>

	<p>- the prefix to be used for the <code>&lt;li&gt;</code> IDs. Default = &#8220;menu_&#8221;.</p>

	<p><code>include_default="boolean"</code></p>

	<p>- include &#8216;default&#8217; section in menu or not. Default = &#8220;1&#8221; (Yes).</p>

	<p><code>default_title="text"</code></p>

	<p>- title to be used for default section. Default = &#8220;Home&#8221;.</p>

	<p><code>default_first="boolean"</code></p>

	<p>- specifies whether the default section should be listed first.  Default = &#8220;1&#8221; (Yes).</p>

	<p><code>exclude="list"</code></p>

	<p>- comma separated list of sections to be excluded from the menu. Default = none.</p>

	<p><code>sections="list"</code></p>

	<p>- comma separated list of sections to be included. Default = all.</p>

	<p><code>sort="sort values"</code></p>

	<p>- the sort method to be used. Default = database order (i.e. the order in which sections were originally added). Other options: &#8220;name&#8221; &#8211; alphabetical order; &#8220;adi_menu_sort&#8221; &#8211; use order specified in admin tab; &#8220;adi_menu_sort,name&#8221; &#8211; use sort order, then if same use alphabetical order.</p>

	<p><code>clone_title="text"</code></p>

	<p>- the default title to be used for cloned sections, if no title has been specified in the admin tab. Default = &#8220;Summary&#8221;.</p>

	<h2><strong>adi_menu_breadcrumb &#8211; usage</strong></h2>

	<p>Place the <code>&lt;txp:adi_menu_breadcrumb /&gt;</code> tag wherever you want the breadcrumbs to appear.</p>

	<h2><strong>adi_menu_breadcrumb &#8211; attributes</strong></h2>

	<p><code>label="text"</code></p>

	<p>- the text to precede the breadcrumb trail output. Default = &#8220;You are here: &#8220;.</p>

	<p><code>separator="text"</code></p>

	<p>- the text to use as a separator between the crumbs. Default = &#8220; &amp;#187; &#8220;.</p>

	<p><code>title="boolean"</code></p>

	<p>- specifies whether the section titles should be used. Default = &#8220;1&#8221; (Yes).</p>

	<p><code>link="boolean"</code></p>

	<p>- specifies whether the sections should links or not. Default = &#8220;1&#8221; (Yes).</p>

	<p><code>linkclass="class name"</code></p>

	<p>- the <span class="caps">CSS</span> class assigned to the breadcrumb links. Default =&#8220;noline&#8221;.</p>

	<p><code>link_last="boolean"</code></p>

	<p>- specifies whether the last section crumb in list (i.e. the current section) should be a link or not. Only applies in article list mode. Default = &#8220;0&#8221; (No).</p>

	<p><code>include_default="boolean"</code></p>

	<p>- specifies whether the &#8216;default&#8217; section should be output. Default = &#8220;1&#8221; (Yes).</p>

	<p><code>default_title="text"</code></p>

	<p>- the title to be used for the &#8216;default&#8217; section. Default = &#8220;Home&#8221;</p>

	<h3><strong>Breadcrumb trail usage example</strong></h3>

	<p>To output a breadcrumb trail, including the article&#8217;s title, try the following:</p>

<pre><code>&lt;div id="breadcrumb"&gt;
&lt;txp:adi_menu_breadcrumb /&gt;
&lt;txp:if_individual_article&gt;&amp;#187;&amp;#160;&lt;txp:title/&gt;&lt;/txp:if_individual_article&gt;
&lt;/div&gt;
</code></pre>
# --- END PLUGIN HELP ---
-->
<?php
}
?>