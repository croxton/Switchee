##Switchee v2.1.1


### Switch/case control structure for templates
-------------------------------------------

With EEs if/else conditionals, each condition is parsed before  being removed at the end of the parsing process. This means if you wrap if/else tags around lots of other tags then your template will be running many unnecessary queries and functions.

As Switchee is a tag we can use parse=“inward” to ensure that unmatched conditions are not parsed before being removed from the template.

* Allows multiple case values separated by pipe ‘|’.
* Supports regular expression matching like so: #regex#
* Multiple regular expressions separated by | can be used for one case value
* Supports empty string matches represesnted by ‘’ or “”


### HOW TO USE

	{exp:switchee variable="{variable_to_test}" parse="inward"}
		
		{case value="value1|value2"}
			Content to show
		{/case}
		
		{case value="value3" default="yes"}
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
		
		{case value="value4" default="yes"}	
			
			{!-- you can also nest Switchee by leaving off the 'exp:' in nested tags : --}
			{switchee variable="{another_variable_to_test}" parse="inward"}
				{case value="value1"}
					nested content to show
				{/case}
			{/switchee}	
			
		{/case}
		
	{/exp:switchee}

#### Support for no_result blocks inside wrapped tags:

	{if switchee_no_results}
		{redirect="channel/noresult"}
	{/if}

#### Return all matching cases with `match="all"`

	{!-- Output: 'Orange O' --}	
	{exp:switchee variable="orange" match="all" parse="inward"}
		
		{case value="orange"}
			Orange
		{/case}
		
		{case value="#^o#"}
			O
		{/case}
		
		{case value="apple"}
			Apple
		{/case}
		
	{/exp:switchee}


GET and POST globals can be evaluated by prefixing with get: or post:, e.g.:

	{exp:switchee variable = "post:my_var" parse="inward"}

Global variable can be evaluated by prefixing with global:, e.g.:

	{exp:switchee variable = "global:my_var" parse="inward"}

Stash variables can be evaluated by prefixing with stash:, e.g.:

	{exp:switchee variable = "stash:my_var" parse="inward"}

