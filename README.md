##Switchee v3.0.0


### Switch/case control structure for templates
-------------------------------------------

Switchee adds a powerful switch/case control structure to ExpressionEngine templates, and can be used as an alternative to `if/else` conditionals.

### Switchee vs If/Else

Prior to EE.2.9.x, if/else "advanced conditionals" were parsed at the very end of the parsing process, with the result that tags within non-matching conditons were still parsed unecessarily before being removed.

With the release of EE 2.9.0, if/else conditionals can now evaluate regular expressions and are parsed "when ready" - when the variable being evaluated has a known value. In _most_ cases this solves the main problem that Switchee was designed to solve, however there are [some occassions](https://gist.github.com/croxton/9d012297096892ca5c10) where this can still result in non-matching conditions being parsed. Unlike if/else, Switchee is parsed linearly with other tags - immediately after preceding tags but before any following tags on a given layer of the template - so you can rely on it to always remove non-matching conditions before they can be parsed.

Switchee has the following advantages over if/else:

* Parsed linearly
* Evaluate global, GET, POST and Stash variables
* Optionally, all matching cases can be returned with `match="all"`
* Fast and stable with very large strings without hitting PCRE memory / recursion issues (tested with millions of lines)

If/else has the following advantages over Switchee:

* Native, first-party functionality
* Comparison, logical and mathematical operators
* String concatenation
* Error handling


So which should you use today? 

##### Presentation logic
For general _presentation logic_ if/else is less verbose and the operators make it the more flexible choice. 

##### Dynamic variables
When working with dynamic variables such as those created by [Stash](https://github.com/croxton/Stash) or passed in the POST array, Switchee is the obvious choice.

##### Routing logic
Need to output an entirely different page for a particular segment value? I strongly recommend you use [Resource Router](https://github.com/rsanchez/resource_router) or the native template routes functionality instead of complicating your templates with routing logic.


### Installation

Unzip the download and rename the extracted directory to 'switchee'.

#### ExpressionEngine 2.x

Move the 'switchee' folder to the `./system/expressionengine/third_party` directory.

#### ExpressionEngine 3.x

Move the 'switchee' folder to the `./system/user/addons` directory.


### How to use

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

