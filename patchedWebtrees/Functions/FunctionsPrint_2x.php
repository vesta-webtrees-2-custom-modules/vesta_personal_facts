<?php

namespace Cissee\WebtreesExt\Functions;

use Fisharebest\Webtrees\Age;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomCode\GedcomCodeStat;
use Fisharebest\Webtrees\GedcomCode\GedcomCodeTemp;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
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
      $title = ' title="' . htmlspecialchars($tooltip) . '"';
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
      
      $mapLinks = $this->getMapLinks($map_lati, $map_long);
      $html .= $mapLinks;
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

  //[RC] quick fix for webtrees #3532
  public static function formatFactDate(
          Fact $event, 
          GedcomRecord $record, 
          $anchor, 
          $time,
          bool $asso): string
    {
        $factrec = $event->gedcom();
        $html    = '';
        // Recorded age
        if (preg_match('/\n2 AGE (.+)/', $factrec, $match)) {
            $fact_age = FunctionsPrint::formatGedcomAge($match[1]);
        } else {
            $fact_age = '';
        }
        if (preg_match('/\n2 HUSB\n3 AGE (.+)/', $factrec, $match)) {
            $husb_age = FunctionsPrint::formatGedcomAge($match[1]);
        } else {
            $husb_age = '';
        }
        if (preg_match('/\n2 WIFE\n3 AGE (.+)/', $factrec, $match)) {
            $wife_age = FunctionsPrint::formatGedcomAge($match[1]);
        } else {
            $wife_age = '';
        }

        // Calculated age
        $fact = $event->getTag();
        if (preg_match('/\n2 DATE (.+)/', $factrec, $match)) {
            $date = new Date($match[1]);
            $html .= ' ' . $date->display($anchor);
            // time
            if ($time && preg_match('/\n3 TIME (.+)/', $factrec, $match)) {
                $html .= ' – <span class="date">' . $match[1] . '</span>';
            }
            if ($record instanceof Individual) {
                //[RC] quick fix for webtrees #3532
                if (!$asso && in_array($fact, Gedcom::BIRTH_EVENTS, true) && $record->tree()->getPreference('SHOW_PARENTS_AGE')) {
                    // age of parents at child birth
                    $html .= FunctionsPrint::formatParentsAges($record, $date);
                }
                if ($fact !== 'BIRT' && $fact !== 'CHAN' && $fact !== '_TODO') {
                    // age at event
                    $birth_date = $record->getBirthDate();
                    // Can't use getDeathDate(), as this also gives BURI/CREM events, which
                    // wouldn't give the correct "days after death" result for people with
                    // no DEAT.
                    $death_event = $record->facts(['DEAT'])->first();
                    if ($death_event instanceof Fact) {
                        $death_date = $death_event->date();
                    } else {
                        $death_date = new Date('');
                    }
                    $ageText = '';
                    if ($fact === 'DEAT' || Date::compare($date, $death_date) <= 0 || !$record->isDead()) {
                        // Before death, print age
                        $age = (new Age($birth_date, $date))->ageAtEvent(false);
                        // Only show calculated age if it differs from recorded age
                        if ($age !== '') {
                            if ($fact_age !== '' && $fact_age !== $age) {
                                $ageText = $age;
                            } elseif ($fact_age === '' && $husb_age === '' && $wife_age === '') {
                                $ageText = $age;
                            } elseif ($husb_age !== '' && $husb_age !== $age && $record->sex() === 'M') {
                                $ageText = $age;
                            } elseif ($wife_age !== '' && $wife_age !== $age && $record->sex() === 'F') {
                                $ageText = $age;
                            }
                        }
                    }
                    if ($fact !== 'DEAT' && $death_date->isOK() && Date::compare($death_date, $date) < 0) {
                        // After death, print time since death
                        $ageText = (new Age($death_date, $date))->timeAfterDeath();
                        // Family events which occur after death are probably errors
                        if ($event->record() instanceof Family) {
                            $ageText .= view('icons/warning');
                        }
                    }
                    if ($ageText !== '') {
                        $html .= ' <span class="age">' . $ageText . '</span>';
                    }
                }
            }
        }
        // print gedcom ages
        $age_labels = [
            I18N::translate('Age')     => $fact_age,
            I18N::translate('Husband') => $husb_age,
            I18N::translate('Wife')    => $wife_age,
        ];

        foreach (array_filter($age_labels) as $label => $age) {
            $html .= ' <span class="label">' . $label . ':</span> <span class="age">' . $age . '</span>';
        }

        return $html;
    }
}
