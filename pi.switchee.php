<?php

$plugin_info = array(
  'pi_name' => 'Switchee',
  'pi_version' =>'2.0.6',
  'pi_author' =>'Mark Croxton',
  'pi_author_url' => 'http://www.hallmark-design.co.uk/',
  'pi_description' => 'Switch/case control structure for templates',
  'pi_usage' => Switchee::usage()
  );

class Switchee {
	
	public 	$return_data = '';
	private $_ph = array();
	
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
		$this->EE =& get_instance();
		
		// reduce the PCRE default recursion limit to a safe level to prevent a server crash 
		// (segmentation fault) when the available stack is exhausted before recursion limit reached
		// Apache *nix executable stack size is 8Mb, so safe size is 16777
		// Apache Win32 executable stack size is 256Kb, so safe size is 524
		ini_set('pcre.recursion_limit', '16777');
		
		// PCRE default backtrack limit is low on PHP <5.3.6 
		// Increase it to the default value in newer versions of PHP
		ini_set('pcre.backtrack_limit', '1000000');
		
		// fetch the tagdata
		$tagdata = $this->EE->TMPL->tagdata;
		
		// the variable we want to find
		$var = $this->EE->TMPL->fetch_param('variable') ? $this->EE->TMPL->fetch_param('variable') : '';
		
		// debug?
		$debug = (bool) preg_match('/1|on|yes|y/i', $this->EE->TMPL->fetch_param('debug'));	
		
		// register POST and GET values
		if (strncmp($var, 'get:', 4) == 0)
		{
			$var = filter_var($this->EE->input->get(substr($var, 4)), FILTER_SANITIZE_STRING);
		}
		
		if (strncmp($var, 'post:', 5) == 0)
		{
			$var = filter_var($this->EE->input->post(substr($var, 5)), FILTER_SANITIZE_STRING);
		}
		
		// register variables created by Stash
		// warning: stash will create a new template object, overwriting the current instance
		if (strncmp($var, 'stash:', 6) == 0)
		{
			$var = substr($var, 6);
			$var = stash::get($var);
		}
		
		// register global vars
		if (strncmp($var, 'global:', 7) == 0)
		{
			$var = substr($var, 7);
			
			if (array_key_exists($var, $this->EE->config->_global_vars))
			{
				$var = $this->EE->config->_global_vars[$var];
			}
			else
			{
				// global has not been parsed yet, so we'll do it the hard way (this adds some overhead)
				$var = $this->EE->TMPL->parse_globals(LD.$var.RD);
			}
		}
		
		// log
		if ($debug)
		{
			$this->EE->TMPL->log_item("Switchee: evaluating variable {$var}");
		}
		
		// replace content inside nested tags with indexed placeholders, storing it in an array for later
		// here's the tricky bit - we only match outer tags
		/*
		$pattern = '/{switchee(?>(?!{\/?switchee).|(?R))*{\/switchee/si';
		*/
		// more memory efficient version of the above...	
		$pattern = '#{switchee(?>(?:[^{]++|{(?!\/?switchee[^}]*}))+|(?R))*{\/switchee#si';
		$tagdata = preg_replace_callback($pattern, array(get_class($this), '_placeholders'), $tagdata);
	
		// returns NULL on PCRE error
		if ($tagdata === NULL && $debug)
		{
			$this->_pcre_error();
		}
		
		// loop through case parameters and find a case pair value that matches our variable
		$index = 0;
		
		// now we need to generate a new array of tag pairs for our tagdata
		$tag_vars = $this->EE->functions->assign_variables($tagdata);
		
		foreach ($tag_vars['var_pair'] as $key => $val)
		{	
			// is this tag pair a case?
			if (preg_match('/^case/', $key))
			{		
				// index of the case tag pair we're looking at
				$index++;	
					
				// replace any regex in the case values with a marker
				$tagdata = str_replace($key, 'case_'.$index, $tagdata);
				
				// get the position of the content inside the case being evaluated
				$starts_at = strpos($tagdata, "{case_".$index."}") + strlen("{case_".$index."}");
				$ends_at = strpos($tagdata, "{/case}", $starts_at);
				
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
						$case_value = $this->EE->security->entity_decode($case_value);
						$var = $this->EE->security->entity_decode($var);

						// is the case value a regular expression?
						// check for a string contained within hashes #regex#
						if (preg_match('/^#(.*)#$/', $case_value))
						{
							if (preg_match($case_value, $var))
							{		
								// we've found a match, grab case content and exit loop	
								$this->return_data = substr($tagdata, $starts_at, $ends_at - $starts_at);
									
								// log
								if ($debug)
								{
									$this->EE->TMPL->log_item("Switchee: regex match: case '{$case_value}' matched variable '{$var}'");
								}
								
								break 2;
							}
						}
					
						if ($case_value == $var)
						{
							// we've found a match, grab case content and exit loop
							$this->return_data = substr($tagdata, $starts_at, $ends_at - $starts_at);
							
							// log
							if ($debug)
							{
								$this->EE->TMPL->log_item("Switchee: string match: case '{$case_value}' matched variable '{$var}'");
							}	

							break 2;
						}
					}
				}
				
				// default value	
				if(isset($val['default']))
				{
					if(strtolower($val['default']) == 'yes' || strtolower($val['default']) == 'true' || $val['default'] == '1')
					{
						// found a default, save matched content but keep search for a mtach (continue loop)
						$this->return_data = substr($tagdata, $starts_at, $ends_at - $starts_at);
							
						// log
						if ($debug)
						{
							$this->EE->TMPL->log_item("Switchee: default case found for variable '{$var}'. This will be returned if no match is found.");
						}	
	
					}
				}	
			}
		}
		
		// replace namespaced no_results with the real deal
		$this->return_data = str_replace(strtolower(__CLASS__).'_no_results', 'no_results', $this->return_data);
		
		// restore original content inside nested tags
		foreach ($this->_ph as $index => $val)
		{
			// convert the outer shell of {switchee} tag pairs to plugin tags {exp:switchee}
			// now we can do this all over again...
			$val = preg_replace( array('/^{switchee/i', '/{\/switchee$/i'), array('{exp:switchee', '{/exp:switchee'), $val);
			$this->return_data = str_replace('{[_'.__CLASS__.'_'.($index+1).']', $val, $this->return_data);
		}
	}
	
	/** 
	 * _placeholders
	 *
	 * Replaces nested tag content with placeholders
	 *
	 * @access private
	 * @param array
	 * @return string
	 */	
	private function _placeholders($matches)
	{
		$this->_ph[] = $matches[0];
		return '{[_'.__CLASS__.'_'.count($this->_ph).']';
	}	
	
	/** 
	 * _pcre error
	 *
	 * Log PCRE error for debugging
	 *
	 * @access private
	 * @return void
	 */		
	private function _pcre_error()
	{
		// either an unsuccessful match, or a PCRE error occurred
        $pcre_err = preg_last_error();  // PHP 5.2 and above

		if ($pcre_err === PREG_NO_ERROR)
		{
			$this->EE->TMPL->log_item("Switchee: Successful non-match");
		}
		else 
		{
            // preg_match error :(
			switch ($pcre_err) 
			{
			    case PREG_INTERNAL_ERROR:
			        $this->EE->TMPL->log_item("Switchee: PREG_INTERNAL_ERROR");
			        break;
			    case PREG_BACKTRACK_LIMIT_ERROR:
			        $this->EE->TMPL->log_item("Switchee: PREG_BACKTRACK_LIMIT_ERROR");
			        break;
			    case PREG_RECURSION_LIMIT_ERROR:
			        $this->EE->TMPL->log_item("Switchee: PREG_RECURSION_LIMIT_ERROR");
			        break;
			    case PREG_BAD_UTF8_ERROR:
			        $this->EE->TMPL->log_item("Switchee: PREG_BAD_UTF8_ERROR");
			        break;
			    case PREG_BAD_UTF8_OFFSET_ERROR:
			        $this->EE->TMPL->log_item("Switchee: PREG_BAD_UTF8_OFFSET_ERROR");
			        break;
			    default:
			        $this->EE->TMPL->log_item("Switchee: Unrecognized PREG error");
			        break;
			}
		}
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
	
	{case value="value4" default="Yes"}	
		
		{!-- you can also nest Switchee by leaving off the 'exp:' in nested tags : --}
		{switchee variable="{another_variable_to_test}" parse="inward"}
			{case value="value1" default="Yes"}
				nested content to show
			{/case}
		{/switchee}	
		
	{/case}
	
{/exp:switchee}

How to support no_result blocks inside wrapped tags:

{if switchee_no_results}
	{redirect="channel/noresult"}
{/if}

GET and POST globals can be evaluated by prefixing with get: or post:, e.g.:
{exp:switchee variable = "post:my_var" parse="inward"}

Any global variable can be evaluated by prefixing with global:, e.g.:
{exp:switchee variable = "global:my_var" parse="inward"}

Any Stash module variable can be evaluated by prefixing with stash:, e.g.:
{exp:switchee variable = "stash:my_var" parse="inward"}

Debugging:
To enable logging add the parameter debug="yes", e.g.:
{exp:switchee variable = "my_var" parse="inward" debug="yes"}

	<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}	
}