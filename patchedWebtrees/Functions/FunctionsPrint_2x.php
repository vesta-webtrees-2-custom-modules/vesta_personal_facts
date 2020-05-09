<?php

namespace Cissee\WebtreesExt\Functions;

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\GedcomCode\GedcomCodeStat;
use Fisharebest\Webtrees\GedcomCode\GedcomCodeTemp;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Place;
use Vesta\Model\GenericViewElement;
use Vesta\Model\PlaceStructure;
use function view;

//[RC] adapted: some methods as non-static for easier extensibility
/**
 * Class FunctionsPrint - common functions
 */
class FunctionsPrint_2x {

  //[RC] added/ extracted
  public function getMapLinks($map_lati, $map_long) {
    $html = '';

    $html .= '<a href="https://maps.google.com/maps?q=' . e($map_lati) . ',' . e($map_long) . '" rel="nofollow" title="' . I18N::translate('Google Maps™') . '">' .
            view('icons/google-maps') .
            '<span class="sr-only">' . I18N::translate('Google Maps™') . '</span>' .
            '</a>';

    $html .= '<a href="https://www.bing.com/maps/?lvl=15&cp=' . e($map_lati) . '~' . e($map_long) . '" rel="nofollow" title="' . I18N::translate('Bing Maps™') . '">' .
            view('icons/bing-maps') .
            '<span class="sr-only">' . I18N::translate('Bing Maps™') . '</span>' .
            '</a>';

    $html .= '<a href="https://www.openstreetmap.org/#map=15/' . e($map_lati) . '/' . e($map_long) . '" rel="nofollow" title="' . I18N::translate('OpenStreetMap™') . '">' .
            view('icons/openstreetmap') .
            '<span class="sr-only">' . I18N::translate('OpenStreetMap™') . '</span>' .
            '</a>';

    return $html;
  }

  /**
   * print fact PLACe TEMPle STATus
   *
   * @param Fact $event gedcom fact record
   * @param bool $anchor to print a link to placelist
   * @param bool $sub_records to print place subrecords
   * @param bool $lds to print LDS TEMPle and STATus
   *
   * @return GenericViewElement
   */
  //[RC] refactored completely
  public function formatFactPlace(Fact $event, $anchor = false, $sub_records = false, $lds = false): GenericViewElement {
    if ($anchor || $sub_records) {
      $ps = PlaceStructure::fromFact($event);
      if ($ps === null) {
        return GenericViewElement::createEmpty();
      }
      $gves = $this->formatPlaceNameAndSubRecords($ps);
    }
    
    $html = '';
    $script = '';

    if ($anchor) {
      // Show the full place name, for facts/events tab
      $gve = $gves[0];
      $html .= $gve->getMain();
      $script .= $gve->getScript();
    } else {
      // Abbreviate the place name, for chart boxes
      return GenericViewElement::create($event->place()->getShortName());
    }

    if ($sub_records) {
      $gve = $gves[1];
      $html .= $gve->getMain();
      $script .= $gve->getScript();
    }

    $html .= $this->formatFactLds($event, $lds);
    return new GenericViewElement($html, $script);
  }

  //[RC] added
  //Override point
  public function formatPlaceNameAndSubRecords(PlaceStructure $ps) {
    $html1 = $this->formatPlaceName($ps);
    
    $html2 = '';

    $html2 .= $this->formatPlaceHebRomn($ps);
    $html2 .= $this->formatPlaceCustomFieldsAfterNames($ps);
    $html2 .= $this->formatPlaceLatiLong($ps->getLati(), $ps->getLong());
    $html2 .= $this->formatPlaceCustomFieldsAfterLatiLong($ps);
    $html2 .= $this->formatPlaceNotes($ps);
    $html2 .= $this->formatPlaceCustomFieldsAfterNotes($ps);

    return array(
        GenericViewElement::create($html1), 
        GenericViewElement::create($html2));
  }

  //[RC] added
  //Override point
  protected function formatPlaceName(PlaceStructure $place) {
    return '<a href="' . $place->getPlace()->url() . '">' . $place->getPlace()->fullName() . '</a>';
  }
  
  //[RC] added
  //Override point
  protected function formatPlaceHebRomn(PlaceStructure $place) {
    $html = '';
    if (preg_match_all('/\n3 (?:_HEB|ROMN) (.+)/', $place->getGedcom(), $matches)) {
      foreach ($matches[1] as $match) {
        $wt_place = new Place($match, $place->getTree());
        $html .= ' - ' . $wt_place->fullName();
      }
    }
    return $html;
  }

  //[RC] added
  //Override point
  protected function formatPlaceCustomFieldsAfterNames(PlaceStructure $place) {
    return '';
  }

  //[RC] added
  //Override point
  protected function formatPlaceLatiLong($map_lati, $map_long, $tooltip = null) {
    $html = '';
    $title = '';
    if ($tooltip) {
      $title = ' title="' . $tooltip . '"';
    }
    if ($map_lati) {
      $html .= '<br><span class="label"' . $title . '>' . GedcomTag::getLabel('LATI') . ': </span>' . $map_lati;
    }
    if ($map_long) {
      $html .= ' <span class="label"' . $title . '>' . GedcomTag::getLabel('LONG') . ': </span>' . $map_long;
    }
    if ($map_lati && $map_long) {
      $map_lati = trim(strtr($map_lati, "NSEW,�", " - -. ")); // S5,6789 ==> -5.6789
      $map_long = trim(strtr($map_long, "NSEW,�", " - -. ")); // E3.456� ==> 3.456
      //[RC] adjusted - made extensible
      $html .= $this->getMapLinks($map_lati, $map_long);
    }
    return $html;
  }

  //[RC] added
  //Override point
  protected function formatPlaceCustomFieldsAfterLatiLong(PlaceStructure $place) {
    return '';
  }

  //[RC] added
  //Override point
  protected function formatPlaceNotes(PlaceStructure $place) {
    $html = '';
    if (preg_match('/\d NOTE (.*)/', $place->getGedcom(), $match)) {
      $html .= '<br>' . FunctionsPrint::printFactNotes($place->getTree(), $place->getGedcom(), 3);
    }
    return $html;
  }

  //[RC] added
  //Override point
  protected function formatPlaceCustomFieldsAfterNotes(PlaceStructure $place) {
    return '';
  }

  //[RC] added
  //Override point
  protected function formatFactLds(Fact $event, $lds = false) {
    $html = '';
    if ($lds) {
      if (preg_match('/2 TEMP (.*)/', $event->gedcom(), $match)) {
        $html .= '<br>' . I18N::translate('LDS temple') . ': ' . GedcomCodeTemp::templeName($match[1]);
      }
      if (preg_match('/2 STAT (.*)/', $event->gedcom(), $match)) {
        $html .= '<br>' . I18N::translate('Status') . ': ' . GedcomCodeStat::statusName($match[1]);
        if (preg_match('/3 DATE (.*)/', $event->gedcom(), $match)) {
          $date = new Date($match[1]);
          $html .= ', ' . GedcomTag::getLabel('STAT:DATE') . ': ' . $date->display();
        }
      }
    }
    return $html;
  }

}
