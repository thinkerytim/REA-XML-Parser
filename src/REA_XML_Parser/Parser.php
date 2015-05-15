<?php
/**
 *
 * Author: Ben Dougherty
 * URL: http://www.devblog.com.au, 
 *	 	http://www.mandurahweb.com.au
 *
 * This code was originally written for a project by mandurahweb.com. Please give credit if you
 * use this code in any of your projects. You can see a write-up of this code been
 * used to create posts in WordPress here: http://www.devblog.com.au/rea-xml-parser-and-wordpress
 *
 * Forked and maintained / updated by:
 * Author: The Thinkery LLC
 * URL: http://www.thethinkery.net
 *
 * License: GNU GPL v3
 */

namespace REA_XML_Parser;

class Parser {

	/* Default Fields we return. You can specify any
	 * fields int the REA XML standard
	 */
	private $fields = array (
		'uniqueID',
		'agentID',
		'priceView',
		'description',
		'headline',
		'energyRating',
		'features' => array(
			'bedrooms',
			'bathrooms',
			'garages',
			'carports',
			'airConditioning',
			'pool',
			'alarmSystem',
			'otherFeatures',
			'livingArea',
			'alarmSystem',
			'dishwasher',
			'gym',
			'broadband'
		),
		'address' => array(
			'streetNumber',
			'street',
			'suburb',
			'state',
			'postcode',
			'country'
		),
		'buildingDetails' => array(
			'area'
		),
		'listingAgent' => array(
			'name',
			'telephone',
			'email'
		),
		'images',
		'status',
	);

	/* default files exluded when parsing a directory */
	private $default_excluded_files = array(".", "..");

	/* Keeps track of excluded files */
	private $excluded_files;
	
	/* debug status */
	protected $debug;

	function __construct($debug=false, $fields=array()) {

		/* Use requested fields if set */
		if(!empty($fields)) {
			$this->fields = $fields;
		}
		$this->debug = $debug; /* Set debug flag */
		
	}

	/*
	 * xml_string $xml_string
	 *
	 * Returns an associative array of properties keyed by property type
	 * for the XML string. XML string must be valid.
	 */
	function parse_xml($xml_string) {

    	$properties = array();
        $properties_array = array();
        $xml = false;

        try {
            /* Create XML document. */

            /* Some of the xml files I received were invalid. This could be due to a number
             * of reasons. SimpleXMLElement still spits out some ugly errors even with the try
             * catch so we suppress them when not in debug mode
             */
            if($this->debug) {
                $xml = new \SimpleXMLElement($xml_string);
            }
            else {
                @$xml = new \SimpleXMLElement($xml_string);
            }

        }
        catch(\Exception $e) {
            $this->feedback($e->getMessage());
        }

        // Loaded the file
        if($xml === false) {
            return array();
        }

        // Get property type.
        $all_properties = $xml->xpath("/propertyList/*");

        if(is_array($all_properties) && count($all_properties) > 0) {
            foreach ($all_properties as $property) {
                $property_type = $property->getName();

                $prop = array();//reset property
                $prop['images'] = array();
                $prop['floorplans'] = array();

                /* For every property we select all
                 * the requested fields
                 */
                foreach($this->fields as $key => $field) {
                    if(is_array($field)) {
                        foreach($field as $sub_field) {
                            $prop[$key][$sub_field] = trim((string)$property->{$key}->{$sub_field});
                        }
                    }
                    else {

                        // Different handling for multi fields.
                        if (isset($this->multi_fields[$field])) {

                            // Pull out the field key and attribute we want.
                            $field_key = $this->multi_fields[$field]['key'];
                            $field_attribute = $this->multi_fields[$field]['attribute'];

                            // Make sure the field exists.
                            if(!is_null($property->$field->$field_key)) {

                                // Get a value for every field.
                                foreach ($property->$field->$field_key as $f) {

                                    $attr = $f->attributes();
                                    if ($attr) {
                                        $prop[$field][(string)$attr->$field_attribute] = (string)$f;

                                    }
                                }
                            }
                        }
                        elseif($field == "objects" || $field == "images") {

                            // Parse all the floorplans.
                            if(!is_null($property->$field->floorplan)) {
                                foreach($property->$field->floorplan as $floorplan) {
                                    $attr = $floorplan->attributes();
                                    if($attr) {
                                    	$location = $attr->url ?: $attr->file;
                                        $prop['floorplans']['floorplan_'.(string)$attr->id] = (string) $location;
                                    }
                                }
                            }
                            if(!is_null($property->$field->img)) {
                                foreach($property->$field->img as $img) {
                                    $attr = $img->attributes();
                                    if($attr) {
                                    	$location = $attr->url ?: $attr->file;
                                        $prop['images']['img_' . (string)$attr->id] = (string) $location;
                                    }
                                }
                            }
                        }
                        else {
                            $prop[$field] = trim((string)$property->{$field});
                        }
                    }
                }

                if(in_array("status", $this->fields)) {
                    $attr = $property->attributes();

                    //save status
                    $prop['status'] = (string)$attr->status;
                }

                // Save the property
                if(!empty($property_type)) {
                    $properties_array[$property_type][] = $prop;
                }
                else {
                    $properties_array['INVALID_PROPERTY_TYPE'][] = $prop;
                }
            }
        }

        return $properties_array;
	}

	/**
	 * string $xml_file_dir
	 * string $processed_dir
	 * string $failed_dir
	 * string[] $excluded_files
	 *
	 * Returns an associative array of properties keyed by property type
	 */
	function parse_directory($xml_file_dir, $processed_dir=false, $failed_dir=false, $excluded_files=array()) {
		$properties = array();
		if(file_exists($xml_file_dir)) {
			if($handle = opendir($xml_file_dir)) {

				/* Merged default excluded files with user specified files */
				$this->excluded_files = array_merge($excluded_files, $this->default_excluded_files);

				/* Loop through all the files. */
				while(false !== ($xml_file = readdir($handle))) {

					/* Ensure it's not excluded. */
					if(!in_array($xml_file, $this->excluded_files)) {
						
						/* Get the full path */
						$xml_full_path = $xml_file_dir  . "/" . $xml_file;

						/* retrieve the properties from this file. */
						$prop = $this->parse_file($xml_full_path, $xml_file, $processed_dir, $failed_dir);

						if(is_array($prop) && count($prop) > 0) {

							/* We have to get the array key which is the property
							 * type so we can do a merge with $property[$property_type]
							 * otherwise our properties get overwritten when we try to merge
							 * properties of the same type which already exist.
							 */
							$array_key = array_keys($prop);
							$property_type = $array_key[0];

							if(!isset($properties[$property_type])) {
								//initialise
								$properties[$property_type] = array();
							}

							/* We need the array prop because it includes the property type */
							$properties[$property_type] = array_merge($prop[$property_type], $properties[$property_type]);

							//file loaded
							$file_loaded = true;
						}
						else {
							$this->feedback("no properties returned from file");
						}						
					}
				}
				closedir($handle);
			}
			else {
				$this->feedback("Could not open directory");
			}	
		}
		else {
			throw new \Exception("Directory could not be found");
		}

		return $properties;
	}

	/* Parse a REA XML File. */
	function parse_file($xml_full_path, $xml_file, $processed_dir=false, $failed_dir=false) {
		
		$properties = array();
		if(file_exists($xml_full_path)) {

			$this->feedback("parsing XML file $xml_file");

			/* Parse the XML file */
			$properties = $this->parse_xml(file_get_contents($xml_full_path));

			if(is_array($properties) && (count($properties) > 0)) {
				/* If a processed/removed directory was supplied then we move
				* the xml files accordingly after they've been processed
				*/
				if($processed_dir !== false) {
					if(file_exists($processed_dir)) {
						$this->xml_processed($xml_file, $xml_full_path, $processed_dir);		
					}
					else {
						$this->feedback("Processed dir: $processed_dir does not exist");
					}
					
				}		
			}
			else {
				if($failed_dir !== false) {
					if(file_exists($failed_dir)) {
						$this->xml_load_failed($xml_file, $xml_full_path, $failed_dir);	
					}
					else {
						$this->feedback("Failed dir: $failed_dir does not exist");
					}
					
				}					
			}
		}
		else {
			throw new \Exception("File could not be found");
		}

		return $properties;
	}

	/* Called if the xml file was processed */
	function xml_processed($xml_file, $xml_full_path, $processed_dir) {
		//do anything specific to xml_processed

		//move file
		$this->move_file($xml_file, $xml_full_path, $processed_dir);
	}

	/* Called if the xml file was not correctly processed */
	private function xml_load_failed($xml_file, $xml_full_path, $failed_dir) {
		//do anything specific to xml_failed

		//move file
		$this->move_file($xml_file, $xml_full_path, $failed_dir);
	}

	/* Moves a file to a new location */
	private function move_file($file, $file_full_path, $new_dir) {
		if(copy($file_full_path, $new_dir . "/$file")) {
			unlink($file_full_path);
		}
	}

	/* Reset excluded files */
	public function reset_excluded_files() {
		$this->excluded_files = $this->default_excluded_files;
	}

	/* Display Feedback if in debug mode */
	private function feedback($string) {
		if($this->debug) {
			print $string . "<br/>";
		}
	}
	
}
