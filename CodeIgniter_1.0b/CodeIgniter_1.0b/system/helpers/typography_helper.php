<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
|==========================================================
| Code Igniter - by pMachine
|----------------------------------------------------------
| www.codeignitor.com
|----------------------------------------------------------
| Copyright (c) 2006, pMachine, Inc.
|----------------------------------------------------------
| This library is licensed under an open source agreement:
| www.codeignitor.com/docs/license.html
|----------------------------------------------------------
| File: helpers/typography_helper.php
|----------------------------------------------------------
| Purpose: Typography related Helpers
|==========================================================
*/

	
/*
|==========================================================
| Newlines to <br /> Except within <pre> tags
|==========================================================
*/
function nl2br_except_pre($str)
{
	$ex = explode("pre>",$str);
	$ct = count($ex);
	
	$newstr = "";
	for ($i = 0; $i < $ct; $i++)
	{
		if (($i % 2) == 0)
		{
			$newstr .= nl2br($ex[$i]);
		}
		else 
		{
			$newstr .= $ex[$i];
		}
		
		if ($ct - 1 != $i) 
			$newstr .= "pre>";
	}
	
	return $newstr;
}



/*
|==========================================================
| Auto Typography Class
|==========================================================
|
*/
function auto_typography($str)
{
	$TYPE = new Auto_typography();
	return $TYPE->convert($str);
}

class Auto_typography {

	// Block level elements that should not be wrapped inside <p> tags
	var $block_elements = 'div|blockquote|pre|code|h\d|script|ol|un';
	
	// Elements that should not have <p> and <br /> tags within them.
	var $skip_elements	= 'pre|ol|ul';
	
	// Tags we want the parser to completely ignore when splitting the string.
	var $ignore_elements = 'a|b|i|em|strong|span|img|li';	


	/*
	|==========================================================
	| Main Processing Function
	|==========================================================
	|
	*/
	function convert($str)
	{
		if ($str == '')
		{
			return '';
		}
		
		/*
		|------------------------------------------------
		| Standardize Newlines to make matching easier
		|------------------------------------------------
		*/
		$str = preg_replace("/(\r\n|\r)/", "\n", $str);
		
		/*
		|------------------------------------------------
		| Reduce line breaks
		|------------------------------------------------
		|
		| If there are more than two consecutive line 
		| breaks we'll compress them down to a maximum
		| of two since there's no benefit to more.
		|
		*/
		$str = preg_replace("/\n\n+/", "\n\n", $str);

		/*
		|------------------------------------------------
		| Convert quotes within tags to tempoarary marker
		|------------------------------------------------
		|
		| We don't want curly quotes converted within 
		| tags so we'll temporarily convert them to 
		| {{{DQ}}} and {{{SQ}}}
		|
		*/			
		if (preg_match_all("#\<.+?>#si", $str, $matches))
		{
			for ($i = 0; $i < count($matches['0']); $i++)
			{
				$str = str_replace($matches['0'][$i], 
									str_replace(array("'",'"'), array('{{{SQ}}}', '{{{DQ}}}'), $matches['0'][$i]),
									$str);
			}
		}
		
		/*
		|------------------------------------------------
		| Convert "ignore" tags to tempoarary marker
		|------------------------------------------------
		|
		| The parser splits out the string at every tag
		| it encounters.  Certain inline tags, like image 
		| tags, links, span tags, etc. will be adversely
		| affected if they are split out so we'll convert
		| the opening < temporarily to: {{{tag}}}
		|
		*/			
		$str = preg_replace("#<(/*)(".$this->ignore_elements.")#i", "{{{tag}}}\\1\\2", $str);	
		
		/*
		|------------------------------------------------
		| Split the string at every tag
		|------------------------------------------------
		|
		| This creates an array with this prototype:
		|
		|	[array]
		|	{
		|		[0] = <opening tag>
		|		[1] = Content contained between the tags
		|		[2] = <closing tag>
		|		Etc...
		|	}
		|
		*/			
		$chunks = preg_split('/(<(?:[^<>]+(?:"[^"]*"|\'[^\']*\')?)+>)/', $str, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
		
		/*
		|------------------------------------------------
		| Build our finalized string
		|------------------------------------------------
		|
		| We'll cycle through the array, skipping tags,
		| and processing the contained text
		|
		*/			
		$str = '';
		$process = TRUE;
		foreach ($chunks as $chunk)
		{
			/*
			|------------------------------------------------
			| Are we dealing with a tag?
			|------------------------------------------------
			|
			| If so, we'll skip the processing for this cycle.
			| Well also set the "process" flag which allows us
			| to skip <pre> tags and a few other things.
			|
			*/			
			if (preg_match("#<(/*)(".$this->block_elements.").*?\>#", $chunk, $match)) 
			{
				if (preg_match("#".$this->skip_elements."#", $match['2']))
				{
					$process =  ($match['1'] == '/') ? TRUE : FALSE;		
				}
		
				$str .= $chunk;
				continue;
			}
		
			if ($process == FALSE)
			{
				$str .= $chunk;
				continue;
			}
			
			/*
			|------------------------------------------------
			| Convert Newlines into <p> and <br /> tags
			|------------------------------------------------
			*/			
			$str .= $this->format_newlines($chunk);
		}

		/*
		|------------------------------------------------
		| Convert Quotes and other characters
		|------------------------------------------------
		*/	
		$str = $this->format_characters($str);

		/*
		|------------------------------------------------
		| Final clean up
		|------------------------------------------------
		|
		| We'll swap our temporary markers back and do
		| some clean up.
		|
		*/			
		$str = preg_replace('#(<p>\n*</p>)#', '', $str);
		$str = preg_replace('#(<p.*?>)<p>#', "\\1", $str);
		
		$str = str_replace(
							array('</p></p>', '</p><p>', '{{{tag}}}', '{{{DQ}}}', '{{{SQ}}}'), 
							array('</p>', '<p>', '<', '"', "'"), 
							$str
							);
		
		return $str;
	}
	
	/*
	|==========================================================
	| Format Characters
	|==========================================================
	|
	| This function mainly converts double and single quotes
	| to entities, but since these are directional, it does
	| it based on some rules.  It also converts em-dashes
	| and a couple other things.
	|
	*/
	function format_characters($str)
	{	
		$table = array(
						' "'		=> " &#8220;",
						'" '		=> "&#8221; ",
						" '"		=> " &#8216;",
						"' "		=> "&#8217; ",
						
						'>"'		=> ">&#8220;",
						'"<'		=> "&#8221;<",
						">'"		=> ">&#8216;",
						"'<"		=> "&#8217;<",

						"\"."		=> "&#8221;.",
						"\","		=> "&#8221;,",
						"\";"		=> "&#8221;;",
						"\":"		=> "&#8221;:",
						"\"!"		=> "&#8221;!",
						"\"?"		=> "&#8221;?",
						
						".  "		=> ".&nbsp; ",
						"?  "		=> "?&nbsp; ",
						"!  "		=> "!&nbsp; ",
						":  "		=> ":&nbsp; ",
					);

		// These deal with quotes within quotes, like:  "'hi here'"
		$start = 0;
		$space = array("\n", "\t", " ");
		
		while(TRUE)
		{
			$current = strpos(substr($str, $start), "\"'");
			
			if ($current === FALSE) break;
			
			$one_before = substr($str, $start+$current-1, 1);
			$one_after = substr($str, $start+$current+2, 1);
			
			if ( ! in_array($one_after, $space) && $one_after != "<")
			{
				$str = str_replace(	$one_before."\"'".$one_after,
									$one_before."&#8220;&#8216;".$one_after,
									$str);
			}
			elseif ( ! in_array($one_before, $space) && (in_array($one_after, $space) OR $one_after == '<'))
			{
				$str = str_replace(	$one_before."\"'".$one_after,
									$one_before."&#8221;&#8217;".$one_after,
									$str);
			}
			
			$start = $start+$current+2;
		}
		
		$start = 0;
		
		while(TRUE)
		{
			$current = strpos(substr($str, $start), "'\"");
			
			if ($current === FALSE) break;
			
			$one_before = substr($str, $start+$current-1, 1);
			$one_after = substr($str, $start+$current+2, 1);
			
			if ( in_array($one_before, $space) && ! in_array($one_after, $space) && $one_after != "<")
			{
				$str = str_replace(	$one_before."'\"".$one_after,
									$one_before."&#8216;&#8220;".$one_after,
									$str);
			}
			elseif ( ! in_array($one_before, $space) && $one_before != ">")
			{
				$str = str_replace(	$one_before."'\"".$one_after,
									$one_before."&#8217;&#8221;".$one_after,
									$str);
			}
			
			$start = $start+$current+2;
		}
		
		// Are there quotes within a word, as in:  ("something")
		if (preg_match_all("/(.)\"(\S+?)\"(.)/", $str, $matches))
		{
			for ($i=0, $s=sizeof($matches['0']); $i < $s; ++$i)
			{
				if ( ! in_array($matches['1'][$i], $space) && ! in_array($matches['3'][$i], $space))
				{
					$str = str_replace(	$matches['0'][$i],
										$matches['1'][$i]."&#8220;".$matches['2'][$i]."&#8221;".$matches['3'][$i],
										$str);
				}
			}
		}
		
		if (preg_match_all("/(.)\'(\S+?)\'(.)/", $str, $matches))
		{
			for ($i=0, $s=sizeof($matches['0']); $i < $s; ++$i)
			{
				if ( ! in_array($matches['1'][$i], $space) && ! in_array($matches['3'][$i], $space))
				{
					$str = str_replace(	$matches['0'][$i],
										$matches['1'][$i]."&#8216;".$matches['2'][$i]."&#8217;".$matches['3'][$i],
										$str);
				}
			}
		}
		
		// How about one apostrophe, as in Rick's
		
		$start = 0;
		
		while(TRUE)
		{
			$current = strpos(substr($str, $start), "'");
			
			if ($current === FALSE) break;
			
			$one_before = substr($str, $start+$current-1, 1);
			$one_after = substr($str, $start+$current+1, 1);
			
			if ( ! in_array($one_before, $space) && ! in_array($one_after, $space))
			{
				$str = str_replace(	$one_before."'".$one_after,
									$one_before."&#8217;".$one_after,
									$str);
			}
			
			$start = $start+$current+2;
		}
		
		// Em dashes
		
		$start = 0;
		
		while(TRUE)
		{
			$current = strpos(substr($str, $start), "--");
			
			if ($current === FALSE) break;
			
			$one_before = substr($str, $start+$current-1, 1);
			$one_after = substr($str, $start+$current+2, 1);
			$two_before = substr($str, $start+$current-2, 1);
			$two_after = substr($str, $start+$current+3, 1);
			
			if (( ! in_array($one_before, $space) && ! in_array($one_after, $space))
				OR
				( ! in_array($two_before, $space) && ! in_array($two_after, $space) && $one_before == ' ' && $one_after == ' ')
				)
			{
				$str = str_replace(	$two_before.$one_before."--".$one_after.$two_after,
									$two_before.trim($one_before)."&#8212;".trim($one_after).$two_after,
									$str);
			}
			
			$start = $start+$current+2;
		}
		
		// Ellipsis
		$str = preg_replace("#(\w)\.\.\.(\s|<br />|</p>)#", "\\1&#8230;\\2", $str); 
		$str = preg_replace("#(\s|<br />|</p>)\.\.\.(\w)#", "\\1&#8230;\\2", $str); 
		
		// Run the translation array we defined above		
		$str = str_replace(array_keys($table), array_values($table), $str);
		
		// If there are any stray double quotes we'll catch them here
		
		$start = 0;
		
		while(TRUE)
		{
			$current = strpos(substr($str, $start), '"');
			
			if ($current === FALSE) break;
			
			$one_before = substr($str, $start+$current-1, 1);
			$one_after = substr($str, $start+$current+1, 1);
			
			if ( ! in_array($one_after, $space))
			{
				$str = str_replace(	$one_before.'"'.$one_after,
									$one_before."&#8220;".$one_after,
									$str);
			}
			elseif( ! in_array($one_before, $space))
			{
				$str = str_replace(	$one_before."'".$one_after,
									$one_before."&#8221;".$one_after,
									$str);
			}
			
			$start = $start+$current+2;
		}
		
		$start = 0;
		
		while(TRUE)
		{
			$current = strpos(substr($str, $start), "'");
			
			if ($current === FALSE) break;
			
			$one_before = substr($str, $start+$current-1, 1);
			$one_after = substr($str, $start+$current+1, 1);
			
			if ( ! in_array($one_after, $space))
			{
				$str = str_replace(	$one_before."'".$one_after,
									$one_before."&#8216;".$one_after,
									$str);
			}
			elseif( ! in_array($one_before, $space))
			{
				$str = str_replace(	$one_before."'".$one_after,
									$one_before."&#8217;".$one_after,
									$str);
			}
			
			$start = $start+$current+2;
		}
		
		return $str;
	}
	
	/*
	|==========================================================
	| Format Newlines
	|==========================================================
	|
	| Converts newline characters into either <p> tags or <br />
	|
	*/	
	function format_newlines($str)
	{
		if ($str == '' OR strpos($str, "\n") === FALSE)
		{
			return $str;
		}
			
		$str = str_replace("\n\n", "</p>\n\n<p>", $str);
		$str = preg_replace("/([^\n])(\n)([^\n])/", "\\1<br />\\2\\3", $str);
		
		return '<p>'.$str.'</p>';
	}	
}

 
?>