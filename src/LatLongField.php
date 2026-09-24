<?php

namespace Restruct\SilverStripe\Forms;

use SilverStripe\Core\Environment;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\TextField;
use SilverStripe\View\Requirements;
use SilverStripe\View\TemplateGlobalProvider;

class LatLongField
    extends TextField
    implements TemplateGlobalProvider
{
    protected $template = 'LatLongField';

    /**
     * @var string[]
     */
    protected $address_input_fields = [];

    /**
     * @var string[]
     */
    protected $location_picker_options = [];

    public function __construct(string $name, ?string $title = null, string $value = '', ?int $maxLength = null, ?Form $form = null)
    {
        parent::__construct($name, $title, $value, $maxLength, $form);

        Requirements::css('restruct/silverstripe-latlongfield:client/css/latlongfield.css');
        Requirements::javascript('//maps.google.com/maps/api/js?key=' . self::gmaps_api_key());
        Requirements::javascript('restruct/silverstripe-latlongfield:client/js/jquery.locationpicker.js');
        Requirements::javascript('restruct/silverstripe-latlongfield:client/js/latlongfield.js');
    }

    public static function get_template_global_variables()
    {
        return [
            'GMapsApiKey' => 'gmaps_api_key',
        ];
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

    public function Field($properties = [])
    {
        $this->addExtraClass('text'); // for styling...

        if($this->address_input_fields) {
            $this->setAttribute('readonly', 'readonly');
        }
        if(!$this->getAttribute('placeholder')) {
            $this->setAttribute('placeholder', '(empty / no location yet)');
        }
        if(!$this->RightTitle() && !$this->getDescription()) {
            $this->setDescription('Type an address (eg. “49 Oxford Street, London”) and click “🔍” (search)');
        }
        # Pass the caller's properties on (this used to be `parent::Field($properties = [])`,
        # which silently discarded them)
        return parent::Field($properties);
    }

    /**
     * Whether to render the Bootstrap 4 input-group markup (buttons wrapped in
     * .input-group-prepend / .input-group-append, "font-weight-bold") instead of the flat
     * Bootstrap 5 markup ("fw-bold").
     *
     * The Silverstripe 5 CMS (silverstripe/admin 2) ships Bootstrap 4; the Silverstripe 6 CMS
     * (admin 3) ships Bootstrap 5, whose stylesheet has no rules at all for the BS4 wrappers, so the
     * old markup left the buttons unstyled and applied BS5's seam-join to the wrappers instead.
     * admin 3 requires framework 6, so the framework major is an exact proxy: ViewLayerData only
     * exists in framework 6. Front-end forms without Bootstrap are unaffected either way.
     *
     * @return bool
     */
    public function getUsesBootstrap4InputGroup()
    {
        return !class_exists('SilverStripe\\View\\ViewLayerData');
    }

    /**
     * @return string[]|null address field names, or null when none are set
     */
    public function getAddressInputFields()
    {
        # Initialised: without it, a field with no address fields counted an undefined variable,
        # which is a TypeError on PHP 8 as soon as the template runs
        $fields = [];
        foreach ($this->address_input_fields as $field) {
            $fields[] = is_object($field) && is_a($field, FormField::class) ? $field->getName() : $field;
        }
        return count($fields) ? $fields : null;
    }

    /**
     * JSON for the template's data-addressfields attribute ("null" when none are set).
     *
     * The template used to pipe the array getter through `.JSON`. Silverstripe 6 wraps a list
     * array in an ArrayList, which has no JSON(), so the attribute rendered empty there. Encoding
     * here gives the same output on 5 and 6; the template's default casting attribute-escapes it.
     *
     * @return string
     */
    public function getAddressInputFieldsJSON()
    {
        return (string) json_encode($this->getAddressInputFields());
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
     * @return array|null options passed to the jQuery location picker, or null when none are set
     */
    public function getLocationPickerOptions()
    {
        return count($this->location_picker_options) ? $this->location_picker_options : null;
    }

    /**
     * JSON for the template's data-locationpickeroptions attribute ("null" when none are set).
     * See getAddressInputFieldsJSON() for why this is encoded here and not in the template.
     *
     * @return string
     */
    public function getLocationPickerOptionsJSON()
    {
        return (string) json_encode($this->getLocationPickerOptions());
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
        return self::parseLatLong($val) !== null;
    }

    /**
     * Split a "lat,long" string into two floats, or return null when it is not a valid coordinate:
     * both parts numeric (surrounding whitespace allowed), latitude within -90..90 and longitude
     * within -180..180.
     *
     * validateLatLong() used to test the parts with floatval(), which rejected any coordinate on
     * the equator or the prime meridian ("0,5.1") and accepted "52abc,4" or "999,999".
     *
     * @param mixed $val
     * @return float[]|null [lat, long]
     */
    protected static function parseLatLong($val)
    {
        if (!is_string($val)) {
            return null;
        }
        $LatLngArr = explode(',', $val);
        if (count($LatLngArr) !== 2) {
            return null;
        }
        [$lat, $lng] = array_map('trim', $LatLngArr);
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }
        return [$lat, $lng];
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
     * Returns null when either coordinate is not a valid "lat,long" string (see validateLatLong()):
     * this used to throw a TypeError on PHP 8, and projects pass in values from visitors' cookies.
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

//        [$lat1, $lng1] = explode(",", $fromcoordinate, 2);
//        [$lat2, $lng2] = explode(",", $tocoordinate, 2);
        $from = self::parseLatLong($fromcoordinate);
        $to = self::parseLatLong($tocoordinate);
        if ($from === null || $to === null) {
            return null;
        }
        [$lat1, $lng1] = $from;
        [$lat2, $lng2] = $to;

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
