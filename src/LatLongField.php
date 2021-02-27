<?php

namespace Restruct\SilverStripe\Forms;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;

class LatLongField
    extends TextField
{
    protected $template = 'LatLongField';

//    /**
//     * @config
//     */
//    private static $google_maps_api_key;

    /**
     * Allow setting a custom js/jquery input selection for the address fields
     * Javascript code which should return a string when evaluated, 'that' being the original field
     */
//	protected $js_input_selector = '$(that).val()';
//
//	public function getInputSelector(){
//		return $this->js_input_selector;
//	}
//
//	public function setInputSelector($val){
//		$this->js_input_selector = $val;
//	}

    /**
     * @var string[]
     */
    protected $address_input_fields = array();

    /**
     * @var string[]
     */
    protected $location_picker_options = array();

    public function __construct(string $name, ?string $title = null, string $value = '', ?int $maxLength = null, ?Form $form = null)
    {
        parent::__construct($name, $title, $value, $maxLength, $form);

        Requirements::css('restruct/silverstripe-latlongfield:client/css/latlongfield.css');
        Requirements::javascript('//maps.google.com/maps/api/js?key=' . self::gmaps_api_key());
        Requirements::javascript('restruct/silverstripe-latlongfield:client/js/jquery.locationpicker.js');
        Requirements::javascript('restruct/silverstripe-latlongfield:client/js/latlongfield.js');
    }

    /**
     * Get the value of GMAPS_API_KEY from environment.
     * Allowing an optional secondary GMAPS_BROWSER_KEY facilitates working with two keys,
     * one 'public' and one private (eg only for server-server use).
     *
     * @param bool $requirePrimaryKey require primary key (default is to return 'browser' key if available)
     * @return mixed
     */
    public static function gmaps_api_key($requirePrimaryKey = false)
    {
        if(!$requirePrimaryKey){
            $browserKey = Environment::getEnv('GMAPS_BROWSER_KEY');
            if($browserKey) {
                return $browserKey;
            }
        }
        return Environment::getEnv('GMAPS_API_KEY');
    }

    public function Field($properties = array())
    {
        $this->addExtraClass('text'); // for styling...

        if($this->address_input_fields) {
            $this->setAttribute('readonly', 'readonly');
        }


        if(!$this->getAttribute('placeholder'))
            $this->setAttribute('placeholder', '(empty / no location yet)');

        if(!$this->RightTitle() && !$this->getDescription())
            $this->setDescription('Type an address (eg. “49 Oxford Street, London”) and click “🔍” (search)');

        return parent::Field($properties = array());
    }

    /**
     * @return boolean|ArrayList
     */
    public function getAddressInputFields()
    {
        if(!count($this->address_input_fields)) return false;
        $ret = array();
        // $this->address_input_fields can be FormFields or strings of fieldNames
        foreach($this->address_input_fields as $field){
            // String
            if(is_string($field)) $ret[] = ArrayData::create(array('value' => $field));
            // pointer to FormField (use getName())
            if(is_object($field) && is_a($field, FormField::class)){
                $ret[] = ArrayData::create(array('value' => $field->getName()));
            }
        }
        return new ArrayList($ret);
    }

    /**
     * @param string $fieldName
     */
    public function addAddressInputField($fieldName)
    {
        $this->address_input_fields[] = $fieldName;
    }

    /**
     * @param array $fieldNames
     */
    public function setAddressInputFields(array $fieldNames)
    {
        $this->address_input_fields = $fieldNames;
    }

    /**
     * @return boolean|ArrayList
     */
    public function getLocationPickerOptions()
    {
        if(!count($this->location_picker_options)) return false;
        $ret = array();
        foreach($this->location_picker_options as $key => $val){
            $ret[] = ArrayData::create(array('key' => $key,'val'=>$val));
        }
        return new ArrayList($ret);
    }

    /**
     * @param array $options
     */
    public function setLocationPickerOptions(array $options)
    {
        foreach($options as $key => $val){
            $this->location_picker_options[$key] = $val;
        }
    }

    /*
     * Helpers
     */

    // validate a string to be a valid lat long value 52.12759,5.429787
    public static function validateLatLong($val)
    {
        $LatLngArr = explode(',', $val);
        if(count($LatLngArr) == 2){
            if(floatval($LatLngArr[0]) && floatval($LatLngArr[1])){
                return true;
            }else{
                return false;
            }
        }
        return false;
    }

    public static function GeoCode($address)
    {
        $gmaps_api_key = self::gmaps_api_key(true);
        if(!$gmaps_api_key) {
            return user_error('No GMAPS_API_KEY set in ENV, LatLongField::GeoCode()');
        }

        //https://maps.googleapis.com/maps/api/geocode/json?address=1600+Amphitheatre+Parkway,+Mountain+View,+CA&key=YOUR_API_KEY
        $url = "https://maps.googleapis.com/maps/api/geocode/json?key={$gmaps_api_key}";
        $url .= "&address=" . urlencode($address);

        $result = file_get_contents($url);
        $data = json_decode($result, TRUE);

        // return first result
        if($data['status']=="OK"){
            return array_shift($data['results']);
        }
        return null;
    }


    /*
     * Calculate the distance between two geo coordinates in KM
     */
    public static function calCulateDistance($fromcoordinate, $tocoordinate, $decimals=0)
    {
//        // Create procedure if not exists;
//        $exists = DB::query("SELECT IF(
//			EXISTS (
//				SELECT 1 FROM Information_schema.Routines
//				WHERE SPECIFIC_NAME = 'calc_distance'
//				AND ROUTINE_TYPE='FUNCTION'
//				),
//			'function exists', 'not found')");
////		Debug::dump($exists->numRecords( ));
//        if (array_shift($exists->first()) == 'not found') {
//            Debug::dump('LatLongField::calCulateDistance - INFO: DEFINING calc_distance FUNCTION in DB');
//            DB::query("CREATE FUNCTION calc_distance
//					(lat1 DECIMAL(10,6), long1 DECIMAL(10,6), lat2 DECIMAL(10,6), long2 DECIMAL(10,6))
//					RETURNS DECIMAL(10,6)
//					RETURN (6353 * 2 * ASIN(SQRT(
//							POWER(SIN((lat1 - abs(lat2)) * pi()/180 / 2),2) + COS(lat1 * pi()/180 )
//							* COS( abs(lat2) *  pi()/180) * POWER(SIN((long1 - long2) *  pi()/180 / 2), $decimals)
//						)))");
//        }
//        $query_result = DB::query("SELECT ROUND(calc_distance($fromcoordinate,$tocoordinate), 0)");
//        $result = array_shift($query_result->first());
////		Debug::dump("Distance between Eiffel Tower (48.858278,2.294254) and Big Ben (51.500705,-0.124575)
////			".DB::query("SELECT ROUND(calc_distance(51.500705,-0.124575,48.858278,2.294254), 2)")." KM");
//        //Debug::dump("Distance Eiffel Tower (48.858278,2.294254) - Big Ben (51.500705,-0.124575): $result KM");
//        return $result;

        list($lat1, $lng1) = explode(",", $fromcoordinate, 2);
        list($lat2, $lng2) = explode(",", $tocoordinate, 2);

        $pi80 = M_PI / 180;
        $lat1 *= $pi80;
        $lng1 *= $pi80;
        $lat2 *= $pi80;
        $lng2 *= $pi80;

        $r = 6372.797; // mean radius of Earth in km
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;
        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $km = $r * $c;

        //return $km;
        return round($km, $decimals);
    }



}
