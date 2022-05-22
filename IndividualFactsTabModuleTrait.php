<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\I18N;
use Vesta\CommonI18N;
use Vesta\ControlPanelUtils\Model\ControlPanelCheckbox;
use Vesta\ControlPanelUtils\Model\ControlPanelCheckboxInverted;
use Vesta\ControlPanelUtils\Model\ControlPanelFactRestriction;
use Vesta\ControlPanelUtils\Model\ControlPanelPreferences;
use Vesta\ControlPanelUtils\Model\ControlPanelRadioButton;
use Vesta\ControlPanelUtils\Model\ControlPanelRadioButtons;
use Vesta\ControlPanelUtils\Model\ControlPanelRange;
use Vesta\ControlPanelUtils\Model\ControlPanelSection;
use Vesta\ControlPanelUtils\Model\ControlPanelSubsection;
use Vesta\ControlPanelUtils\Model\ControlPanelTextbox;
use Vesta\ModuleI18N;

trait IndividualFactsTabModuleTrait {

    protected function getMainTitle() {
        return CommonI18N::titleVestaPersonalFacts();
    }

    public function getShortDescription() {
        $part2 = I18N::translate('Replacement for the original \'Facts and events\' module.');
        $part2 .= ' ';

        $part2 .= ' ' . I18N::translate('Also extends facts and events on the family page.');
        $part2 .= ' ' . I18N::translate('Also provides additional map links.');

        //_FSFTID now handled by webtrees!

        if (!$this->isEnabled()) {
            $part2 = ModuleI18N::translate($this, $part2);
        }
        return MoreI18N::xlate('A tab showing the facts and events of an individual.') . ' ' . $part2;
    }

    protected function getFullDescription() {
        $description = array();
        $description[] = /* I18N: Module Configuration */I18N::translate('An extended \'Facts and Events\' tab, with hooks for other custom modules.');
        $description[] = /* I18N: Module Configuration */I18N::translate('Intended as a replacement for the original \'Facts and events\' module.');

        $description[] = I18N::translate('Also extends facts and events on the family page.');
        $description[] = I18N::translate('Also provides additional map links.');

        //_FSFTID now handled by webtrees!

        $description[] = CommonI18N::requires1(CommonI18N::titleVestaCommon());
        return $description;
    }

    protected function createPrefs() {
        $generalSub = array();
        $generalSub[] = new ControlPanelSubsection(
            CommonI18N::displayedTitle(),
            array(/* new ControlPanelCheckbox(
              I18N::translate('Include the %1$s symbol in the module title', $this->getVestaSymbol()),
              null,
              'VESTA',
              '1'), */
            new ControlPanelCheckbox(
                CommonI18N::vestaSymbolInTabTitle(),
                CommonI18N::vestaSymbolInTitle2(),
                'VESTA_TAB',
                '1'),
            new ControlPanelCheckbox(
                CommonI18N::vestaSymbolInSidebarTitle(),
                CommonI18N::vestaSymbolInTitle2(),
                'VESTA_SIDEBAR',
                '1')));

        //'ASSO_SEPARATE' is obsolete (now properly handled by webtrees)

        $factsAndEventsSub = array();
        $factsAndEventsSub[] = new ControlPanelSubsection(
            MoreI18N::xlate('Associated events'),
            array(
            new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Only show specific facts and events'),
                /* I18N: Module Configuration */ I18N::translate('If this option is checked, additional facts and events where the individual is listed as an associate are restricted to the following facts and events.') . ' ' .
                CommonI18N::bothEmpty(),
                'ASSO_RESTRICTED',
                '0'),
            ControlPanelFactRestriction::createWithIndividualFacts(
                CommonI18N::restrictIndi(),
                'ASSO_RESTRICTED_INDI',
                'CHR,BAPM'),
            ControlPanelFactRestriction::createWithFamilyFacts(
                CommonI18N::restrictFam(),
                'ASSO_RESTRICTED_FAM',
                'MARR')));

        $placeSub1 = array();
        $placeSub1[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Latitude/Longitude'),
            array(
            new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Hide coordinates, show map links after place name'),
                /* I18N: Module Configuration */ I18N::translate('In the original tab, coordinates are shown (with map links). This tends to clutter the place display, in particular if there are additional modules providing coordinates.'),
                'LINKS_AFTER_PLAC',
                '0')));

        $placeSub = array();
        $placeSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */MoreI18N::xlate('General'),
            array(
            new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Open links in new browser tab'),
                null,
                'TARGETS_BLANK',
                '1'),
            new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Show additional info for map links'),
                /* I18N: Module Configuration */ I18N::translate('Display an icon with a tooltip indicating the source of the map links. This is intended mainly for debugging.'),
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
                /* I18N: Module Configuration */ I18N::translate('The original tab had the trademark sign - keep it if you like, or if you feel legally bound to.'),
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
                /* I18N: Module Configuration */ I18N::translate('The original tab had the trademark sign - keep it if you like, or if you feel legally bound to.'),
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
            /* I18N: Module Configuration */I18N::translate('OpenStreetMap'),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Show links to OpenStreetMap'),
                null,
                'OSM_SHOW',
                '1'),
            new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include \'TM\' in tooltip'),
                /* I18N: Module Configuration */ I18N::translate('The original tab had the trademark sign - keep it if you like, or if you feel legally bound to.'),
                'OSM_TM',
                '1'),
            new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Include marker'),
                /* I18N: Module Configuration */ I18N::translate('Include a marker in the linked map.'),
                'OSM_MARKER',
                '0'),
            new ControlPanelRange(
                /* I18N: Module Configuration */I18N::translate('Zoom level of linked map'),
                null,
                1,
                20,
                'OSM_ZOOM',
                15)));

        $historicMapProvider = I18N::translate("Arcanum Maps");
        $placeSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('%1$s (historic maps)', $historicMapProvider),
            array(new ControlPanelCheckbox(
                /* I18N: Module Configuration */I18N::translate('Show links to a historic map of Europe in the XIX. century, or the United States of America (1880-1926), if applicable'),
                /* I18N: Module Configuration */ I18N::translate('The link item will not be shown for locations outside the respective boundaries of Europe and the US.'),
                'MAPIRE_SHOW',
                '1'),
            new ControlPanelRange(
                /* I18N: Module Configuration */I18N::translate('Zoom level of linked map'),
                null,
                1,
                20,
                'MAPIRE_ZOOM',
                15),
            new ControlPanelCheckboxInverted(
                /* I18N: Module Configuration */I18N::translate('Show linked map with additional options'),
                /* I18N: Module Configuration */ I18N::translate('When checked, links to the main %1$s page, where you can switch between different historical layers available for the respective location, and fade in and out of a modern map, which may additionally be configured here:', $historicMapProvider),
                'MAPIRE_EMBED',
                '1'),
            new ControlPanelRadioButtons(
                true,
                array(
                new ControlPanelRadioButton(/* I18N: Module Configuration */I18N::translate('OSM Base Map'), null, 'osm'),
                new ControlPanelRadioButton(/* I18N: Module Configuration */I18N::translate('Aerial Base Map'), null, 'here-aerial')),
                null,
                'MAPIRE_BASE',
                'here-aerial')));

        $link = '<a href="https://dopiaza.org/tools/datauri/index.php">https://dopiaza.org/tools/datauri/index.php</a>';
        $link2 = '<a href="https://en.mapy.cz/img/favicon/favicon.ico">https://en.mapy.cz/img/favicon/favicon.ico</a>';

        $placeSub[] = new ControlPanelSubsection(
            /* I18N: Module Configuration */I18N::translate('Custom Map Provider'),
            array(new ControlPanelTextbox(
                /* I18N: Module Configuration: Name of a custom map provider */I18N::translate('Name'),
                /* I18N: Module Configuration */ I18N::translate('You can also configure a custom map provider. Enter its name here.'),
                'CMP_1_TITLE',
                '',
                false,
                31),
            new ControlPanelTextbox(
                /* I18N: Module Configuration */I18N::translate('URI template'),
                /* I18N: Module Configuration */ I18N::translate('The uri for map links, with placeholders (%1$s and %2$s) for map coordinates. The zoom level should be part of the uri. Example: %3$s', '\'lati\'', '\'long\'', 'https://en.mapy.cz/zakladni?x={long}&y={lati}&z=11'),
                'CMP_1_LINK_URI',
                '',
                false,
                -1,
                null),
            new ControlPanelTextbox(
                /* I18N: Module Configuration */I18N::translate('Link icon as data URI'),
                /* I18N: Module Configuration */ I18N::translate('Base64-encoded data URI for the link icon. Convert the icon to a data URI e.g. via %1$s. The result should start with something like %2$s. A good source of the icon image is usually the map provider\'s favicon image, such as %3$s. It will be displayed properly resized.', $link, '\'data:image/x-icon;base64\'', $link2),
                'CMP_1_ICON_DATA_URI',
                '',
                false,
                -1,
                "[a-zA-z0-9\-:/;,+=]*")));

        $sections = array();
        $sections[] = new ControlPanelSection(
            CommonI18N::general(),
            null,
            $generalSub);
        $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('Facts and Events List'),
            null,
            $factsAndEventsSub);
        $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */I18N::translate('Place Settings'),
            null,
            $placeSub1);

        $mapLinksDesc = 
            /* I18N: Module Configuration */I18N::translate('Map links are also configurable via separate modules now.') . ' ' .
            /* I18N: Module Configuration */I18N::translate('The following options may be used alternatively or additionally.') . ' ' .
            /* I18N: Module Configuration */I18N::translate('They are used wherever facts are displayed, i.e. also outside this module.');

        $sections[] = new ControlPanelSection(
            /* I18N: Module Configuration */MoreI18N::xlate('Map links'),
            $mapLinksDesc,
            $placeSub);

        return new ControlPanelPreferences($sections);
    }

}
