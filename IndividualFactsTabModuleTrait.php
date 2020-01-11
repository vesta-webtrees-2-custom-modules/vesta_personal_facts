<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Fisharebest\Webtrees\I18N;
use Vesta\ControlPanel\Model\ControlPanelCheckbox;
use Vesta\ControlPanel\Model\ControlPanelFactRestriction;
use Vesta\ControlPanel\Model\ControlPanelPreferences;
use Vesta\ControlPanel\Model\ControlPanelRange;
use Vesta\ControlPanel\Model\ControlPanelSection;
use Vesta\ControlPanel\Model\ControlPanelSubsection;

trait IndividualFactsTabModuleTrait {

  protected function getMainTitle() {
    return I18N::translate('Vesta Facts and events');
  }

  public function getShortDescription() {
    return
            I18N::translate('A tab showing the facts and events of an individual.') . ' ' .
            I18N::translate('Replacement for the original \'Facts and events\' module.');
  }

  protected function getFullDescription() {
    $description = array();
    $description[] = /* I18N: Module Configuration */I18N::translate('An extended \'Facts and Events\' tab, with hooks for other custom modules.');
    $description[] = /* I18N: Module Configuration */I18N::translate('Intended as a replacement for the original \'Facts and events\' module.');
    $description[] = /* I18N: Module Configuration */I18N::translate('Requires the \'%1$s Vesta Common\' module.', $this->getVestaSymbol());
    return $description;
  }

  protected function createPrefs() {
    $generalSub = array();
    $generalSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Displayed title'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
                null,
                'VESTA',
                '1'),
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include the %1$s symbol in the tab title', $this->getVestaSymbol()),
                /* I18N: Module Configuration */I18N::translate('Deselect in order to have the tab appear exactly as the original tab.'),
                'VESTA_TAB',
                '1')));

    $factsAndEventsSub = array();
    $factsAndEventsSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Facts and events of inverse associates'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Use separate toggle checkbox'),
                /* I18N: Module Configuration */I18N::translate('In the original tab, two kinds of additional facts and events are displayed when \'Events of close relatives\' is selected on the tab:') . ' ' .
                /* I18N: Module Configuration */I18N::translate('Actual events of close relatives, and facts and events where the current individual is listed as an associate.') . ' ' .
                /* I18N: Module Configuration */I18N::translate('The latter are not actually restricted to close relatives, and therefore it may be less confusing to offer a separate toggle checkbox for them.'),
                'ASSO_SEPARATE',
                '0'),
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Only show specific facts and events'),
                /* I18N: Module Configuration */I18N::translate('If this option is checked, additional facts and events of inverse associates are limited to the following facts and events.') . ' ' .
                /* I18N: Module Configuration */I18N::translate('In particular if both lists are empty, no additional facts and events of this kind will be shown.'),
                'ASSO_RESTRICTED',
                '0'),
        new ControlPanelFactRestriction(
                false,
                /* I18N: Module Configuration */I18N::translate('Restrict to this list of GEDCOM individual facts and events. You can modify this list by removing or adding fact and event names, even custom ones, as necessary.'),
                'ASSO_RESTRICTED_INDI',
                'CHR,BAPM'),
        new ControlPanelFactRestriction(
                true,
                /* I18N: Module Configuration */I18N::translate('Restrict to this list of GEDCOM family facts and events. You can modify this list by removing or adding fact and event names, even custom ones, as necessary.'),
                'ASSO_RESTRICTED_FAM',
                'MARR')));

    $placeSub = array();
    $placeSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Latitude/Longitude'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Hide coordinates, show map links after place name'),
                /* I18N: Module Configuration */I18N::translate('In the original tab, coordinates are shown (with map links). This tends to clutter the place display, in particular if there are additional modules providing coordinates.'),
                'LINKS_AFTER_PLAC',
                '0'),
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Open links in new browser tab'),
                null,
                'TARGETS_BLANK',
                '1'),
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Show additional info for map links'),
                /* I18N: Module Configuration */I18N::translate('Display an icon with a tooltip indicating the source of the map links. This is intended mainly for debugging.'),
                'DEBUG_MAP_LINKS',
                '0')));
    
    $placeSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Google Maps'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Show links to Google Maps'),
                null,
                'GOOGLE_SHOW',
                '1'),
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include \'TM\' in tooltip'),
                /* I18N: Module Configuration */I18N::translate('The original tab had the trademark sign - keep it if you like, or if you feel legally bound to.'),
                'GOOGLE_TM',
                '1'),
        new ControlPanelRange(
                /* I18N: Module Configuration */I18N::translate('Zoom level of linked map'),
                null,
                1,
                20,
                'GOOGLE_ZOOM',
                17)));
    
    $placeSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Bing Maps'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Show links to Bing Maps'),
                null,
                'BING_SHOW',
                '1'),
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include \'TM\' in tooltip'),
                /* I18N: Module Configuration */I18N::translate('The original tab had the trademark sign - keep it if you like, or if you feel legally bound to.'),
                'BING_TM',
                '1'),
        new ControlPanelRange(
                /* I18N: Module Configuration */I18N::translate('Zoom level of linked map'),
                null,
                1,
                20,
                'BING_ZOOM',
                15)));

    $placeSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('OpenStreetMaps'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Show links to OpenStreetMaps'),
                null,
                'OSM_SHOW',
                '1'),
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include \'TM\' in tooltip'),
                /* I18N: Module Configuration */I18N::translate('The original tab had the trademark sign - keep it if you like, or if you feel legally bound to.'),
                'OSM_TM',
                '1'),
        new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include marker'),
                /* I18N: Module Configuration */I18N::translate('Include a marker in the linked map.'),
                'OSM_MARKER',
                '0'),
        new ControlPanelRange(
                /* I18N: Module Configuration */I18N::translate('Zoom level of linked map'),
                null,
                1,
                20,
                'OSM_ZOOM',
                15)));

    $placeSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Europe in the XIX. century | Mapire'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Show links to a historic map of Europe'),
                null,
                'MAPIRE_SHOW',
                '1'),
        new ControlPanelRange(
                /* I18N: Module Configuration */I18N::translate('Zoom level of linked map'),
                null,
                1,
                20,
                'MAPIRE_ZOOM',
                15)));

    $sections = array();
    $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('General'),
            null,
            $generalSub);
    $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('Facts and Events List'),
            null,
            $factsAndEventsSub);
    $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('Place Settings'),
            null,
            $placeSub);

    return new ControlPanelPreferences($sections);
  }

}
