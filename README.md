#REA XML Parser

This code allows you to easily retrieve an associative arary of properties indexed by property type. Property types as specified in the REAXML documentation include:
 
 * residential
 * rental
 * land
 * rural
 * commercial
 * commercialLand
 * business

##Installation 

Using composer: `composer require thinkerytim/REA-XML-Parser`

##Usage

##Usage:
		$rea = new Parser($debug=true); //uses default fields
	
		$properties = $rea->parse_dir($xml_file_dir, $processed_dir, $failed_dir, $excluded_files=array());
 	
 		//or
 		$property = $rea->parse_file($xml_file);

For a full list of fields please see. http://reaxml.realestate.com.au/ and click 'Mandatory Fields'


##Further Reading:
 - You can see a write-up of this code been used to create posts in WordPress here: http://www.devblog.com.au/rea-xml-parser-and-wordpress

##License: 
 - This code is licensed under GNU GPL v3 and may be used and distributed freely. You may fork the code make changes add extra features etc. Any changes to this code should be released to the open source community.

	