<?php

$plugin_info = array(
  'pi_name' => 'Switchee',
  'pi_version' =>'1.5',
  'pi_author' =>'Mark Croxton',
  'pi_author_url' => 'http://www.hallmark-design.co.uk/',
  'pi_description' => 'Switch/case control structure for templates',
  'pi_usage' => Switchee::usage()
  );

class Switchee {
	
	var $return_data = '';
	
	/** 
	 * Constructor
	 *
	 * Evaluates case values and extracts the content of the 
	 * first case that matches the variable parameter
	 *
	 * @access public
	 * @return void
	 */
	public function Switchee() 
	{
		global $TMPL, $REGX, $IN;
		
		// the variable we want to find
		$var = $TMPL->fetch_param('variable') ? $TMPL->fetch_param('variable') : '';
		
		// register POST and GET values
		if (strncmp($var, 'get:', 4) == 0)
		{
			$var = filter_var($IN->GBL(substr($var, 4), 'GET'), FILTER_SANITIZE_STRING);
		}
		
		if (strncmp($var, 'post:', 5) == 0)
		{
			$var = filter_var($IN->GBL(substr($var, 5), 'POST'), FILTER_SANITIZE_STRING);
		}
		
		// fetch the tagdata
		$tagdata = $TMPL->tagdata;
			
		// loop through case parameters and find a case pair value that matches our variable
		$index = 0;
		foreach ($TMPL->var_pair as $key => $val)
		{
			// is this tag pair a case?
			if (preg_match('/^case/', $key))
			{
				// index of the case tag pair we're looking at
				$index++;	
					
				// define the pattern we're searching for in tagdata that encloses the current case content
				// make search string safe by replacing any regex in the case values with a marker
				$pattern = '/{case_'.$index.'}(.*){&#47;case}/Usi';
				$tagdata = str_replace($key, 'case_'.$index, $tagdata);
				
				if(isset($val['value']))
				{
					$val_array = array();
					
					if (stristr($val['value'], '|'))
					{
						$val_array = explode('|', $val['value']);
					}
					else
					{
						$val_array[] = $val['value'];
					}

					// loop through each value and look for a match
					foreach ($val_array as $case_index => $case_value)
					{
						// convert '' and "" to an actual empty string
						if ($case_value == "''" || $case_value == '""')
						{
							$case_value = '';
						}
						
						// decode any encoded characters
						$case_value = $REGX->unhtmlentities($case_value);
						$var 		= $REGX->unhtmlentities($var);

						// is the case value a regular expression?
						// check for a string contained within hashes #regex#
						if (preg_match('/^#(.*)#$/', $case_value))
						{
							if (preg_match($case_value, $var))
							{
								// we've found a match, grab case content and exit loop
								preg_match($pattern, $tagdata, $matches);
								$this->return_data = @$matches[1]; // fail gracefully
								break 2;
							}
						}
					
						if ($case_value == $var)
						{
							// we've found a match, grab case content and exit loop
							preg_match($pattern, $tagdata, $matches);
							$this->return_data = @$matches[1];
							break 2;
						}	
						
					}
				}
				
				// default value	
				if(isset($val['default']))
				{
					if(strtolower($val['default']) == 'yes' || strtolower($val['default']) == 'true' || $val['default'] == '1')
					{
						// found a default, save matched content and continue loop
						preg_match($pattern, $tagdata, $matches);
						$this->return_data = @$matches[1];
					}
				}	
			}
		}
		
		// replace namespaced no_results with the real deal
		$this->return_data = str_replace(strtolower(__CLASS__).'_no_results', 'no_results', $this->return_data);
	}

	// usage instructions
	public function usage() 
	{
  		ob_start();
?>
-------------------
HOW TO USE
-------------------
{exp:switchee variable = "{variable_to_test}" parse="inward"}
	
	{case value="value1|value2"}
		Content to show
	{/case}
	
	{case value="value3" default="Yes"}
		Content to show
	{/case}
	
	{case value="#^P(\d+)$#|''"}
		Use regular expressions enclosed by hashes #regex#
		Be careful to encode the following reserved characters as follows:
		{ = &#123;
		| = &#124;
		} = &#125;
		Use '' to represent an empty string
	{/case}
	
{/exp:switchee}


How to support no_result blocks inside wrapped tags:

{if switchee_no_results}
	{redirect="channel/noresult"}
{/if}

GET and POST globals can also be evaluated by prefixing with get: or post:, e.g.:
{exp:switchee variable = "post:my_var" parse="inward"}


Requires PHP 5.

	<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}	
}