Simple Silverstripe Latlong Field
=================================

<img width="465" alt="Screenshot 2021-02-27 at 16 22 59" src="https://user-images.githubusercontent.com/1005986/109391592-26ad4e00-7918-11eb-89ee-c68f441f4549.png">
CMS editor can type street address, then click search. A (draggable) pointer will be shown on a map and the field's content will be replaced with the LatLong code. Originally abstracted from the mappable module (I think).

## Requirements
SilverStripe 4.0 or higher  
**SS3-4 upgrade:** move LatLongField::google_maps_api_key to environment var (see Config below)

## Config  

```
.env:
GMAPS_API_KEY="..."
GMAPS_BROWSER_KEY="..." (optional secondary 'public' key to use in the browser)
