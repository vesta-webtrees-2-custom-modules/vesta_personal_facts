<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Aura\Router\RouterContainer;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Webtrees;
use GeoProjectionMercator;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\PlaceStructure;
use function view;

class FunctionsFactPlace {

    protected $module;

    function __construct(IndividualFactsTabModuleExtended $module) {
        $this->module = $module;
    }

    public function mapLink(Fact $fact): string {
        $ps = PlaceStructure::fromFact($fact);
        if ($ps === null) {
            return '';
        }
        $mapCoordinates = FunctionsPlaceUtils::plac2map($this->module, $ps, true);
        if ($mapCoordinates === null) {
            return '';
        }
        
        //debug
        //TODO: use proper modal here? tooltip isn't helpful on tablets etc
        //also confusing to use non-clickable helptext icon here!  
        $debugMapLinks = boolval($this->module->getPreference('DEBUG_MAP_LINKS', '0'));
        $debug = '';
        if ($debugMapLinks) {
            $title = htmlspecialchars($mapCoordinates->getTrace()->getAll());
            $debug = '<span class="wt-icon-help" title ="' . $title . '"><i class="fas fa-question-circle fa-fw" aria-hidden="true"></i></span>';
        }
            
        return $this->getMapLinks(
            $mapCoordinates->getLati(), 
            $mapCoordinates->getLong(),
            $debug);
    }

    protected function getMapLinks(
        $map_lati,
        $map_long,
        string $debugMapLinks) {

        $html = '';
        if (boolval($this->module->getPreference('GOOGLE_SHOW', '1'))) {
            $zoom = intval($this->module->getPreference('GOOGLE_ZOOM', '17'));
            $title = boolval($this->module->getPreference('GOOGLE_TM', '1')) ? MoreI18N::xlate('Google Maps™') : I18N::translate('Google Maps');
            $title = MoreI18N::xlate('View this location using %s', $title);
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
            $title = MoreI18N::xlate('View this location using %s', $title);
            $html .= $this->linkIcon('icons/bing-maps', $title, 'https://www.bing.com/maps/?lvl=' . $zoom . '&cp=' . $map_lati . '~' . $map_long);
        }

        if (boolval($this->module->getPreference('OSM_SHOW', '1'))) {
            $zoom = intval($this->module->getPreference('OSM_ZOOM', '15'));
            $title = boolval($this->module->getPreference('OSM_TM', '1')) ? MoreI18N::xlate('OpenStreetMap™') : I18N::translate('OpenStreetMap');
            $title = MoreI18N::xlate('View this location using %s', $title);
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
            $title = MoreI18N::xlate('View this location using %s', $title);
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

        $html .= $debugMapLinks;

        return $html;
    }

    protected function getMapLinksMapire($map_lati, $map_long) {
        $html = '';

        if (boolval($this->module->getPreference('MAPIRE_SHOW', '1'))) {
            $zoom = intval($this->module->getPreference('MAPIRE_ZOOM', '15'));
            $embed = boolval($this->module->getPreference('MAPIRE_EMBED', '1'));
            $baseLayer = $this->module->getPreference('MAPIRE_BASE', 'here-aerial');

            list($lon_s, $lat_s, $lon_e, $lat_e) = self::latLonToBBox($map_lati, $map_long, $zoom, 2, 2, 1);

            //https://github.com/Gasillo/geo-tools
            require_once("thirdparty/GeoProjectionMercator.php");
            $proj = new GeoProjectionMercator();

            $s = $proj->LatLonToMeters($lat_s, $lon_s);
            $e = $proj->LatLonToMeters($lat_e, $lon_e);
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
                $layer = '158';
            } else if ($isInUS) {
                $title = I18N::translate('United States of America (1880-1926) | %1$s', $historicMapProvider);
                $url = 'https://maps.arcanum.com/en/map/usa-1880-1926/';
                $layer = '169';
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
    public static function latLonToTiles(string $map_lati, string $map_long, int $zoom): array {
        $lon = (float) $map_long;
        $lat = (float) $map_lati;

        //we don't need the actual tiles numbers - it's more precise not to floor() these!
        $xtile = (($lon + 180) / 360) * pow(2, $zoom);
        $ytile = (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / pi()) / 2 * pow(2, $zoom);

        return array($xtile, $ytile);
    }

    //cf https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames
    public static function tileToLatLon($xtile, $ytile, int $zoom): array {
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
        int $tile_size): array {

        list($xtile, $ytile) = self::latLonToTiles($map_lati, $map_long, $zoom);

        $xtile_s = ($xtile * $tile_size - $width / 2) / $tile_size;
        $ytile_s = ($ytile * $tile_size - $height / 2) / $tile_size;
        $xtile_e = ($xtile * $tile_size + $width / 2) / $tile_size;
        $ytile_e = ($ytile * $tile_size + $height / 2) / $tile_size;

        list($lat_s, $lon_s) = self::tileToLatLon($xtile_s, $ytile_s, $zoom);
        list($lat_e, $lon_e) = self::tileToLatLon($xtile_e, $ytile_e, $zoom);

        return array($lon_s, $lat_s, $lon_e, $lat_e);
    }

    protected function linkIcon($view, $title, $url): string {
        $targetBlank = boolval($this->module->getPreference('TARGETS_BLANK', '0'));

        $t = '';
        if ($targetBlank) {
            $t = ' target="_blank"';
        }

        if (str_starts_with(Webtrees::VERSION, '2.1')) {
            return '<a href="' . $url . '" rel="nofollow" title="' . $title . '"' . $t . '>' .
                view($view) .
                '<span class="visually-hidden">' . $title . '</span>' .
                '</a> ';
        }

        return '<a href="' . $url . '" rel="nofollow" title="' . $title . '"' . $t . '>' .
            view($view) .
            '<span class="sr-only">' . $title . '</span>' .
            '</a> ';
    }

}
