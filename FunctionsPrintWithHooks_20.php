<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Aura\Router\RouterContainer;
use Cissee\WebtreesExt\Functions\FunctionsPrint_20;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\I18N;
use GeoProjectionMercator;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderUtils;
use Vesta\Model\GenericViewElement;
use Vesta\Model\PlaceStructure;
use Vesta\Model\VestalRequest;
use Vesta\Model\VestalResponse;
use function GuzzleHttp\json_encode;
use function view;

class FunctionsPrintWithHooks_20 extends FunctionsPrint_20 {

  protected $module;

  function __construct($module) {
    parent::__construct($module->useVestals());
    $this->module = $module;
  }
  
  public function vestalsForFactPlace(
          Fact $event): array {
    
    $ps = PlaceStructure::fromFact($event);
    if ($ps === null) {
      return [];
    }
    return $this->vestalsForPlaceNameAndSubRecords($ps);
  }
  
  protected function mapCoordinates(
          PlaceStructure $ps): string {
   
    $html = '';
    $mapCoordinates = FunctionsPlaceUtils::plac2map($this->module, $ps, true);
    if ($mapCoordinates !== null) {
      $hideCoordinates = $this->module->getPreference('LINKS_AFTER_PLAC', '0');

      if ($hideCoordinates) {
        $html .= $this->getMapLinks($mapCoordinates->getLati(), $mapCoordinates->getLong());
      } else {
        $html .= $this->formatPlaceLatiLong($mapCoordinates->getLati(), $mapCoordinates->getLong(), $mapCoordinates->getTrace()->getAll());
      }

      //debug
      //TODO: use proper modal here? tooltip isn't helpful on tablets etc
      $debugMapLinks = $this->module->getPreference('DEBUG_MAP_LINKS', '0');
      if ($debugMapLinks) {
        $title = htmlspecialchars($mapCoordinates->getTrace()->getAll());
        $html .= '<span class="wt-icon-help" title ="' . $title . '"><i class="fas fa-question-circle fa-fw" aria-hidden="true"></i></span>';
      }
    }
    
    return $html;
  }
          
  public function vestalMapCoordinates(
          PlaceStructure $ps): VestalResponse {
        
    $key = md5(json_encode($ps));
    return new VestalResponse($key.'_vestalMapCoordinates', $this->mapCoordinates($ps));
  }
  
  public function vestalBeforePlace(
          PlaceStructure $ps): VestalResponse {
    
    $factPlaceAdditions = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $ps->getTree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($ps) {
              return $module->factPlaceAdditionsBeforePlace($ps);
            })
            ->filter() //filter null values
            ->toArray();
    
    $html1 = '';        
    foreach ($factPlaceAdditions as $factPlaceAddition) {
      $html1 .= $factPlaceAddition;
    }
    
    $key = md5(json_encode($ps));
    return new VestalResponse($key.'_vestalBeforePlace', $html1);
  }
  
  public function vestalAfterMap(
          PlaceStructure $ps): VestalResponse {
    
    $factPlaceAdditions = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $ps->getTree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($ps) {
              return $module->factPlaceAdditionsAfterMap($ps);
            })
            ->filter() //filter null values
            ->toArray();
    
    $html = '';        
    foreach ($factPlaceAdditions as $factPlaceAddition) {
      $html .= $factPlaceAddition;
    }
   
    $key = md5(json_encode($ps));
    return new VestalResponse($key.'_vestalAfterMap', $html);
  }
  
  public function vestalAfterNotes(
          PlaceStructure $ps): VestalResponse {
    
    $factPlaceAdditions = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $ps->getTree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($ps) {
              return $module->factPlaceAdditionsAfterNotes($ps);
            })
            ->filter() //filter null values
            ->toArray();
    
    $html = '';        
    foreach ($factPlaceAdditions as $factPlaceAddition) {
      $html .= $factPlaceAddition;
    }
   
    $key = md5(json_encode($ps));
    return new VestalResponse($key.'_vestalAfterNotes', $html);
  }
  
  public function vestalsForPlaceNameAndSubRecords(
          PlaceStructure $ps): array {
    
    $useVestals = $this->module->useVestals();
    
    if (!$useVestals) {
      return [];
    }
    
    $requests = [];
    
    $key = md5(json_encode($ps));
    $requests[$key.'_vestalMapCoordinates'] = new VestalRequest('vestalMapCoordinates', $ps);
    $requests[$key.'_vestalBeforePlace'] = new VestalRequest('vestalBeforePlace', $ps);
    $requests[$key.'_vestalAfterMap'] = new VestalRequest('vestalAfterMap', $ps);
    $requests[$key.'_vestalAfterNotes'] = new VestalRequest('vestalAfterNotes', $ps);
            
    return $requests;
  }
  
  public function formatPlaceNameAndSubRecords(
          PlaceStructure $ps,
          bool $useVestals): array {
    
    $html1 = '';
    $script1 = '';
    
    $html = '';
    $script = '';

    $html .= $this->formatPlaceHebRomn($ps);
    $html .= $this->formatPlaceCustomFieldsAfterNames($ps);

    if ($useVestals) {
      //add placeholders for Vestals      
      
      $key = md5(json_encode($ps));
      
      $html .= "<span class = '" . $key . "_vestalMapCoordinates'></span>";
      
    } else {
      $html .= $this->mapCoordinates($ps);
    }
    
    if ($useVestals) {
      //add placeholders for Vestals      
      //TODO: cleanup: strictly we have to handle script here!
      
      $key = md5(json_encode($ps));
      
      $html1 .= "<span class = '" . $key . "_vestalBeforePlace'></span>";
      $html1 .= $this->formatPlaceName($ps);
      
      $html .= $this->formatPlaceCustomFieldsAfterLatiLong($ps);
      
      $html .= "<span class = '" . $key . "_vestalAfterMap'></span>";
      $html .= $this->formatPlaceNotes($ps);
      $html .= $this->formatPlaceCustomFieldsAfterNotes($ps);
      
      $html .= "<span class = '" . $key . "_vestalAfterNotes'></span>";

      return array(
          new GenericViewElement($html1, $script1),
          new GenericViewElement($html, $script));
    }
    
    $factPlaceAdditionsBeforePlace = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $ps->getTree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($ps) {
              return $module->factPlaceAdditionsBeforePlace($ps);
            })
            ->filter() //filter null values
            ->toArray();
            
    $factPlaceAdditionsAfterMap = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $ps->getTree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($ps) {
              return $module->factPlaceAdditionsAfterMap($ps);
            })
            ->filter() //filter null values
            ->toArray();
            
    $factPlaceAdditionsAfterNotes = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $ps->getTree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($ps) {
              return $module->factPlaceAdditionsAfterNotes($ps);
            })
            ->filter() //filter null values
            ->toArray();
            
    foreach ($factPlaceAdditionsBeforePlace as $factPlaceAddition) {
      $html1 .= $factPlaceAddition;
    }
    $html1 .= $this->formatPlaceName($ps);
    
    $html .= $this->formatPlaceCustomFieldsAfterLatiLong($ps);
    foreach ($factPlaceAdditionsAfterMap as $factPlaceAddition) {
      $html .= $factPlaceAddition;
    }
    $html .= $this->formatPlaceNotes($ps);
    $html .= $this->formatPlaceCustomFieldsAfterNotes($ps);
    foreach ($factPlaceAdditionsAfterNotes as $factPlaceAddition) {
      $html .= $factPlaceAddition;
    }
    
    return array(
        GenericViewElement::create($html1),
        GenericViewElement::create($html));
  }

  public function getMapLinks($map_lati, $map_long) {
    $html = '';
    if (boolval($this->module->getPreference('GOOGLE_SHOW', '1'))) {
      $zoom = intval($this->module->getPreference('GOOGLE_ZOOM', '17'));
      $title = boolval($this->module->getPreference('GOOGLE_TM', '1')) ? MoreI18N::xlate('Google Maps™') : I18N::translate('Google Maps');
      if ($zoom === 17) {
        //use default link (better map centering)				
        $html .= $this->linkIcon('icons/google-maps', $title, 'https://maps.google.com/maps?q=' . $map_lati . ',' . $map_long);
      } else {
        $html .= $this->linkIcon('icons/google-maps', $title, 'https://maps.google.com/maps?q=' . $map_lati . ',' . $map_long . '&ll=' . $map_lati . ',' . $map_long . '&z=' . $zoom);
      }
    }

    if (boolval($this->module->getPreference('BING_SHOW', '1'))) {
      $zoom = intval($this->module->getPreference('BING_ZOOM', '15'));
      $title = boolval($this->module->getPreference('BING_TM', '1')) ? MoreI18N::xlate('Bing Maps™') : I18N::translate('Bing Maps');
      $html .= $this->linkIcon('icons/bing-maps', $title, 'https://www.bing.com/maps/?lvl=' . $zoom . '&cp=' . $map_lati . '~' . $map_long);
    }

    if (boolval($this->module->getPreference('OSM_SHOW', '1'))) {
      $zoom = intval($this->module->getPreference('OSM_ZOOM', '15'));
      $title = boolval($this->module->getPreference('OSM_TM', '1')) ? MoreI18N::xlate('OpenStreetMap™') : I18N::translate('OpenStreetMap');
      if (boolval($this->module->getPreference('OSM_MARKER', '0'))) {
        $html .= $this->linkIcon('icons/openstreetmap', $title, 'https://www.openstreetmap.org/?mlat=' . $map_lati . '&mlon=' . $map_long . '#map=' . $zoom . '/' . $map_lati . '/' . $map_long);
      } else {
        $html .= $this->linkIcon('icons/openstreetmap', $title, 'https://www.openstreetmap.org/#map=' . $zoom . '/' . $map_lati . '/' . $map_long);
      }
    }

    $html .= $this->getMapLinksMapire($map_lati, $map_long);
    
    if (boolval($this->module->getPreference('CMP_1_LINK_URI', ''))) {
      $title = I18N::translate('Custom Map Provider');
      if (boolval($this->module->getPreference('CMP_1_TITLE', ''))) {
        //non-translated!
        $title = $this->module->getPreference('CMP_1_TITLE', '');
      }
      $uriTemplate = $this->module->getPreference('CMP_1_LINK_URI', '');
      
      //reuse Aura router for uri template functionality
      $router_container = new RouterContainer();
      $router = $router_container->getMap();
      $router->get('CMP_1', $uriTemplate);
      $url = $router_container->getGenerator()->generate('CMP_1', [
          'lati' => $map_lati,
          'long' => $map_long]);      
      
      $html .= $this->linkIcon($this->module->name() . '::icons/custom-map-provider-1', $title, $url);
    }
    
    return $html;
  }

  public function getMapLinksMapire($map_lati, $map_long) {
    $html = '';
    
    if (boolval($this->module->getPreference('MAPIRE_SHOW', '1'))) {
      $zoom = intval($this->module->getPreference('MAPIRE_ZOOM', '15'));
      $embed = boolval($this->module->getPreference('MAPIRE_EMBED', '1'));
      $baseLayer = $this->module->getPreference('MAPIRE_BASE', 'here-aerial');
      
      list($lon_s,$lat_s,$lon_e,$lat_e) = FunctionsPrintWithHooks_20::latLonToBBox($map_lati, $map_long, $zoom, 2, 2, 1);
      
      //https://github.com/Gasillo/geo-tools
      require_once("thirdparty/GeoProjectionMercator.php");
      $proj = new GeoProjectionMercator();
      
      $s = $proj->LatLonToMeters($lat_s,$lon_s);
      $e = $proj->LatLonToMeters($lat_e,$lon_e);
      $xs = $s["xm"];
      $ys = $s["ym"];
      $xe = $e["xm"];
      $ye = $e["ym"];
      
      if ($xe < $xs) {
        list($xe, $xs) = array($xs, $xe);
      }
      if ($ye < $ys) {
        list($ye, $ys) = array($ys, $ye);
      }      

      //issue #10: no point in providing the link icon outside Europe!
      //except there is also a US map available now
      $isInEurope = (35 < $map_lati) && ($map_lati < 66) && (-10 < $map_long) && ($map_long < 45);  
      $isInUS = (25 < $map_lati) && ($map_lati < 50) && (-125 < $map_long) && ($map_long < -65);  

      $historicMapProvider = I18N::translate("Arcanum Maps");
      if ($isInEurope) {
        $title = I18N::translate('Europe in the XIX. century | %1$s', $historicMapProvider);
        $url = 'https://maps.arcanum.com/en/map/europe-19century-secondsurvey/';
        $layer='158';
      } else if ($isInUS) {
        $title = I18N::translate('United States of America (1880-1926) | %1$s', $historicMapProvider);
        $url = 'https://maps.arcanum.com/en/map/usa-1880-1926/';
        $layer='169';
      } else {
        return '';
      }
      
      if ($embed) {
        $url .= "embed/";
      }
      $url .= '?bbox=' . $xs . ',' . $ys . ',' . $xe . ',' . $ye . '&map-list=0&layers=' . $layer;
      if (!$embed) {
        $url .= "%2C" . $baseLayer;
      }
      
      $html .= $this->linkIcon($this->module->name() . '::icons/mapire-eu-maps', $title, $url);
    }
    
    return $html;
  }  
    
  //cf https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Lon..2Flat._to_bbox
  public static function latLonToTiles(string $map_lati, string $map_long, int $zoom) {
    $lon = (float)$map_long;
    $lat = (float)$map_lati;
    
    //we don't need the actual tiles numbers - it's more precise not to floor() these!
    $xtile = (($lon + 180) / 360) * pow(2, $zoom);
    $ytile = (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / pi()) /2 * pow(2, $zoom);
    
    return array($xtile, $ytile);
  }
  
  //cf https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames
  public static function tileToLatLon($xtile, $ytile, int $zoom) {
    $n = pow(2, $zoom);
    $lon_deg = $xtile / $n * 360.0 - 180.0;
    $lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $ytile / $n))));
    return array($lat_deg, $lon_deg);
  }
  
  //cf https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames
  //http://www.openstreetmap.org/export/embed.html?bbox=15.7444,43.708,15.8431,43.7541&layer=mapnik
  public static function latLonToBBox(
          string $map_lati, 
          string $map_long, 
          int $zoom,
          int $width,
          int $height,
          int $tile_size) {

    list($xtile, $ytile) = FunctionsPrintWithHooks_20::latLonToTiles($map_lati, $map_long, $zoom);

    $xtile_s = ($xtile * $tile_size - $width/2) / $tile_size;
    $ytile_s = ($ytile * $tile_size - $height/2) / $tile_size;
    $xtile_e = ($xtile * $tile_size + $width/2) / $tile_size;
    $ytile_e = ($ytile * $tile_size + $height/2) / $tile_size;

    list($lat_s, $lon_s) = FunctionsPrintWithHooks_20::tileToLatLon($xtile_s, $ytile_s, $zoom);
    list($lat_e, $lon_e) = FunctionsPrintWithHooks_20::tileToLatLon($xtile_e, $ytile_e, $zoom);

    return array($lon_s,$lat_s,$lon_e,$lat_e);
  }
  
  public function linkIcon($view, $title, $url) {
    $targetBlank = boolval($this->module->getPreference('TARGETS_BLANK', '0'));
    
    $t = '';
    if ($targetBlank) {
      $t = ' target="_blank"';
    }
    
    return '<a href="' . $url . '" rel="nofollow" title="' . $title . '"'.$t.'>' .
            view($view) .
            '<span class="sr-only">' . $title . '</span>' .
            '</a>';
  }

}
