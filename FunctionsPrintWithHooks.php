<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderUtils;
use Cissee\WebtreesExt\Functions\FunctionsPrint_2x;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Vesta\Model\GenericViewElement;
use Vesta\Model\PlaceStructure;

class FunctionsPrintWithHooks extends FunctionsPrint_2x {

  protected $module;

  function __construct($module) {
    $this->module = $module;
  }

  protected function formatPlaceSubRecords(PlaceStructure $ps): GenericViewElement {
    $additions = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $ps->getTree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($ps) {
              return $module->hFactsTabGetFormatPlaceAdditions($ps);
            })
            ->toArray();

    //$additions = HookProvider2::getInstance()->get('hFactsTabGetFormatPlaceAdditions')->execute($ps);

    $html = '';
    $script = '';

    $html .= $this->formatPlaceHebRomn($ps);
    $html .= $this->formatPlaceCustomFieldsAfterNames($ps);

    $hideCoordinates = $this->module->getPreference('LINKS_AFTER_PLAC', '0');

    if ($ps->getLati() || $ps->getLong()) {
      //use direct lati/long, if set
      if ($hideCoordinates) {
        $html .= $this->getMapLinks($ps->getLati(), $ps->getLong());
      } else {
        $html .= $this->formatPlaceLatiLong($ps->getLati(), $ps->getLong());
      }
    } else {
      //use first provided lati/long
      foreach ($additions as $addition) {
        $ll = $addition->getLatiLong();
        if (($ll !== null) && (count($ll) >= 2)) {
          $long = array_pop($ll);
          $lati = array_pop($ll);
          if ($hideCoordinates) {
            $html .= $this->getMapLinks($lati, $long);

            foreach ($additions as $addition) {
              $html .= $addition->getHtmlAfterNames();
            }
          } else {
            foreach ($additions as $addition) {
              $html .= $addition->getHtmlAfterNames();
            }

            $tooltip = $addition->getLatiLongTooltip();
            $html .= $this->formatPlaceLatiLong($lati, $long, $tooltip);
          }
          break;
        }
      }
    }

    $html .= $this->formatPlaceCustomFieldsAfterLatiLong($ps);
    foreach ($additions as $addition) {
      $html .= $addition->getHtmlAfterLatiLong();
    }
    $html .= $this->formatPlaceNotes($ps);
    $html .= $this->formatPlaceCustomFieldsAfterNotes($ps);
    foreach ($additions as $addition) {
      $html .= $addition->getHtmlAfterNotes();
    }

    foreach ($additions as $addition) {
      $script .= $addition->getScript();
    }

    return new GenericViewElement($html, $script);
  }

  public function getMapLinks($map_lati, $map_long) {
    $html = '';

    if (boolval($this->module->getPreference('GOOGLE_SHOW', '1'))) {
      $zoom = intval($this->module->getPreference('GOOGLE_ZOOM', '17'));
      if ($zoom === 17) {
        //use default link (better map centering)				
        $html .= $this->linkIcon('icons/google-maps', I18N::translate(boolval($this->module->getPreference('GOOGLE_TM', '1')) ? 'Google Maps™' : 'Google Maps'), 'https://maps.google.com/maps?q=' . $map_lati . ',' . $map_long);
      } else {
        $html .= $this->linkIcon('icons/google-maps', I18N::translate(boolval($this->module->getPreference('GOOGLE_TM', '1')) ? 'Google Maps™' : 'Google Maps'), 'https://maps.google.com/maps?q=' . $map_lati . ',' . $map_long . '&ll=' . $map_lati . ',' . $map_long . '&z=' . $zoom);
      }
    }

    if (boolval($this->module->getPreference('BING_SHOW', '1'))) {
      $zoom = intval($this->module->getPreference('BING_ZOOM', '15'));

      $html .= $this->linkIcon('icons/bing-maps', I18N::translate(boolval($this->module->getPreference('BING_TM', '1')) ? 'Bing Maps™' : 'Bing Maps'), 'https://www.bing.com/maps/?lvl=' . $zoom . '&cp=' . $map_lati . '~' . $map_long);
    }

    if (boolval($this->module->getPreference('OSM_SHOW', '1'))) {
      $zoom = intval($this->module->getPreference('OSM_ZOOM', '15'));
      if (boolval($this->module->getPreference('OSM_MARKER', '0'))) {
        $html .= $this->linkIcon('icons/openstreetmap', I18N::translate(boolval($this->module->getPreference('OSM_TM', '1')) ? 'OpenStreetMap™' : 'OpenStreetMap'), 'https://www.openstreetmap.org/?mlat=' . $map_lati . '&mlon=' . $map_long . '#map=' . $zoom . '/' . $map_lati . '/' . $map_long);
      } else {
        $html .= $this->linkIcon('icons/openstreetmap', I18N::translate(boolval($this->module->getPreference('OSM_TM', '1')) ? 'OpenStreetMap™' : 'OpenStreetMap'), 'https://www.openstreetmap.org/#map=' . $zoom . '/' . $map_lati . '/' . $map_long);
      }
    }

    return $html;
  }

  public function linkIcon($view, $title, $url) {
    return '<a href="' . $url . '" rel="nofollow" title="' . $title . '">' .
            view($view) .
            '<span class="sr-only">' . $title . '</span>' .
            '</a>';
  }

}
