<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderUtils;
use Cissee\WebtreesExt\Functions\FunctionsPrint_2x;
use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Model\GenericViewElement;
use Vesta\Model\PlaceStructure;
use function view;

class FunctionsPrintWithHooks extends FunctionsPrint_2x {

  protected $module;

  function __construct($module) {
    $this->module = $module;
  }

  protected function formatPlaceNameAndSubRecords(PlaceStructure $ps) {
    $html1 = '';
    $script1 = '';
    
    $html = '';
    $script = '';

    $html .= $this->formatPlaceHebRomn($ps);
    $html .= $this->formatPlaceCustomFieldsAfterNames($ps);

    //modernized (for now, expected to be fast enough without ajax)
    $mapCoordinates = FunctionsPlaceUtils::plac2Map($this->module, $ps, true);
    if ($mapCoordinates !== null) {
      $hideCoordinates = $this->module->getPreference('LINKS_AFTER_PLAC', '0');
      if ($hideCoordinates) {
        $html .= $this->getMapLinks($mapCoordinates->getLati(), $mapCoordinates->getLong());
      } else {
        $html .= $this->formatPlaceLatiLong($mapCoordinates->getLati(), $mapCoordinates->getLong(), $mapCoordinates->getTrace()->getAll());
      }
      
      //debug
      //TODO: use proper modal here? tooltip isn't helpful on tablets etc
      $debugMapLinks = $this->module->getPreference('DEBUG_MAP_LINKS', '1');
      if ($debugMapLinks) {
        $html .= '<span class="wt-icon-help" title ="' . $mapCoordinates->getTrace()->getAll() . '"><i class="fas fa-question-circle fa-fw" aria-hidden="true"></i></span>';
      }   
    }
    
    $factPlaceAdditions = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $ps->getTree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($ps) {
              return $module->factPlaceAdditions($ps);
            })
            ->filter() //filter null values
            ->toArray();
    
    foreach ($factPlaceAdditions as $factPlaceAddition) {
      $html1 .= $factPlaceAddition->getBeforePlace()->getMain();
      $script1 .= $factPlaceAddition->getBeforePlace()->getScript();
    }
    $html1 .= $this->formatPlaceName($ps);
    
    $html .= $this->formatPlaceCustomFieldsAfterLatiLong($ps);
    foreach ($factPlaceAdditions as $factPlaceAddition) {
      $html .= $factPlaceAddition->getAfterMap()->getMain();
      $script .= $factPlaceAddition->getAfterMap()->getScript();
    }
    $html .= $this->formatPlaceNotes($ps);
    $html .= $this->formatPlaceCustomFieldsAfterNotes($ps);
    foreach ($factPlaceAdditions as $factPlaceAddition) {
      $html .= $factPlaceAddition->getAfterNotes()->getMain();
      $script .= $factPlaceAddition->getAfterNotes()->getScript();
    }
    
    /*
    //legacy stuff
    $html .= '<br/>--LEGACY--<br/>';

    $additions = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $ps->getTree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($ps) {
              return $module->hFactsTabGetFormatPlaceAdditions($ps);
            })            
            ->toArray();
    
    $hideCoordinates = $this->module->getPreference('LINKS_AFTER_PLAC', '0');
    if ($ps->getLati() && $ps->getLong()) {
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
    */
    
    return array(
        new GenericViewElement($html1, $script1),
        new GenericViewElement($html, $script));
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

    return $html;
  }

  public function linkIcon($view, $title, $url) {
    return '<a href="' . $url . '" rel="nofollow" title="' . $title . '">' .
            view($view) .
            '<span class="sr-only">' . $title . '</span>' .
            '</a>';
  }

}
