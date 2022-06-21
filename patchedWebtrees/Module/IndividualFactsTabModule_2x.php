<?php

namespace Cissee\WebtreesExt\Module;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Elements\AdoptedByWhichParent;
use Fisharebest\Webtrees\Elements\CustomElement;
use Fisharebest\Webtrees\Elements\XrefFamily;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\IndividualFactsTabModule;
use Fisharebest\Webtrees\Module\ModuleSidebarInterface;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\IndividualFactsService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;
use Illuminate\Support\Collection;
use function view;

/**
 * Class IndividualFactsTabModule
 * [RC] patched
 */
class IndividualFactsTabModule_2x extends IndividualFactsTabModule implements ModuleTabInterface {

    protected ClipboardService $clipboard_service;
    protected IndividualFactsService $individual_facts_service;
    protected ModuleService $module_service;
    protected LinkedRecordService $linked_record_service;

    protected function getViewNameTab(): string {
        return 'modules/personal_facts/tab';
    }

    public function __construct(
        ClipboardService $clipboard_service,
        IndividualFactsService $individual_facts_service,
        ModuleService $module_service,
        LinkedRecordService $linked_record_service) {

        parent::__construct($clipboard_service, $individual_facts_service, $module_service);

        $this->clipboard_service = $clipboard_service;
        $this->individual_facts_service = $individual_facts_service;
        $this->module_service = $module_service;
        $this->linked_record_service = $linked_record_service;
    }

    /**
     * Generate the HTML content of this tab.
     *
     * @param Individual $individual
     *
     * @return string
     */
    public function getTabContent(Individual $individual): string {

        // Which facts and events are handled by other modules?
        $sidebar_facts = $this->module_service
            ->findByComponent(ModuleSidebarInterface::class, $individual->tree(), Auth::user())
            ->map(fn (ModuleSidebarInterface $sidebar): Collection => $sidebar->supportedFacts())
            ->flatten();

        $tab_facts = $this->module_service
            ->findByComponent(ModuleTabInterface::class, $individual->tree(), Auth::user())
            ->map(fn (ModuleTabInterface $tab): Collection => $tab->supportedFacts())
            ->flatten();

        // Don't show family meta-data tags
        $exclude_facts  = new Collection(['FAM:CHAN', 'FAM:_UID']);
        // Don't show tags that are shown in tabs or sidebars
        $exclude_facts = $exclude_facts->merge($sidebar_facts)->merge($tab_facts);
        
        $individual_facts = $this->individual_facts_service->individualFacts($individual, $exclude_facts);
        $family_facts     = $this->individual_facts_service->familyFacts($individual, $exclude_facts);
        $relative_facts   = $this->individual_facts_service->relativeFacts($individual);
        
        //[RC] adjusted
        $associate_facts  = $this->associateFacts($individual);
        $historic_facts   = $this->individual_facts_service->historicFacts($individual);

        $individual_facts = $individual_facts
            ->merge($family_facts)
            ->merge($relative_facts)
            ->merge($associate_facts)
            ->merge($historic_facts);

        //[RC] ADDED
        $individual_facts = $individual_facts
            ->merge($this->additionalFacts($individual));

        $individual_facts = Fact::sortFacts($individual_facts);

        // Facts of relatives take the form 1 EVEN / 2 TYPE Event of Individual
        // Ensure custom tags from there are recognised
        Registry::elementFactory()->registerTags([
            'INDI:EVEN:CEME'      => new CustomElement('Cemetery'),
            'INDI:EVEN:_GODP'     => new CustomElement('Godparent'),
            'INDI:EVEN:FAMC'      => new XrefFamily(I18N::translate('Adoptive parents')),
            'INDI:EVEN:FAMC:ADOP' => new AdoptedByWhichParent(I18N::translate('Adoption')),
        ]);
        
        $view = view($this->getViewNameTab(), [
            'can_edit' => $individual->canEdit(),
            'clipboard_facts'     => $this->clipboard_service->pastableFacts($individual),
            'has_associate_facts' => $associate_facts->isNotEmpty(),
            'has_historic_facts' => $historic_facts->isNotEmpty(),
            'has_relative_facts' => $relative_facts->isNotEmpty(),
            'individual' => $individual,
            'facts' => $individual_facts,
            //for further extensions in custom views
            'module' => $this
        ]);
        
        return $view;
    }

    /**
     * [RC] OverrideHook
     * 
     * @param Fact $fact
     * @return boolean
     */
    protected function filterAssociateFact(Fact $fact) {
        return true;
    }

    //[RC] ADDED - should be in Fact class
    public static function getAttributes($fact, $tag) {
        preg_match_all('/\n2 (?:' . $tag . ') ?(.*(?:(?:\n3 CONT ?.*)*)*)/', $fact->gedcom(), $matches);
        $attributes = array();
        foreach ($matches[1] as $match) {
            $attributes[] = preg_replace("/\n3 CONT ?/", "\n", $match);
        }

        return $attributes;
    }

    //[RC] adapted, webtrees now has this in individual_facts_service
    protected function associateFacts(Individual $person): Collection {
        $facts = [];

        /** @var Individual[] $associates */
        $asso1 = $this->linked_record_service->linkedIndividuals($person, 'ASSO');
        $asso2 = $this->linked_record_service->linkedIndividuals($person, '_ASSO');
        $asso3 = $this->linked_record_service->linkedFamilies($person, 'ASSO');
        $asso4 = $this->linked_record_service->linkedFamilies($person, '_ASSO');

        $associates = $asso1->merge($asso2)->merge($asso3)->merge($asso4);

        //#17: remove duplicates
        $associates = $associates->unique();

        foreach ($associates as $associate) {
            foreach ($associate->facts() as $fact) {
                //[RC] addded
                if (!$this->filterAssociateFact($fact)) {
                    continue;
                }

                //webtrees 2.x fix for #1192
                //we cannot use it because we require the per-asso relas!
                /*
                  if (preg_match('/\n\d _?ASSO @' . $person->xref() . '@/', $fact->gedcom())) {

                  }
                 */

                //[RC] PATCHED: fix for #1192
                //plus extension for RELA
                //#17: also handle 1 ASSO
                preg_match_all('/^1 ASSO @(' . Gedcom::REGEX_XREF . ')@((\n[2-9].*)*)/', $fact->gedcom(), $arecs1, PREG_SET_ORDER);

                preg_match_all('/\n2 _?ASSO @(.*)@((\n[3].*)*)/', $fact->gedcom(), $arecs2, PREG_SET_ORDER);
                $arecs = array_merge($arecs1, $arecs2);

                foreach ($arecs as $arec) {
                    $xref = $arec[1];
                    $rela = $arec[2];

                    if ($xref === $person->xref()) {
                        // Extract the important details from the fact
                        $factrec = explode("\n", $fact->gedcom(), 2)[0];

                        if (preg_match('/\n2 DATE .*/', $fact->gedcom(), $match)) {
                            $factrec .= $match[0];
                        }
                        if (preg_match('/\n2 PLAC .*/', $fact->gedcom(), $match)) {
                            $factrec .= $match[0];
                        }
                        //[RC] adjusted for Issue #7
                        if (preg_match('/\n2 TYPE .*/', $fact->gedcom(), $match)) {
                            $factrec .= $match[0];
                        }
                        if ($associate instanceof Family) {
                            foreach ($associate->spouses() as $spouse) {
                                $factrec .= "\n2 _ASSO @" . $spouse->xref() . '@';

                                //[RC] extension for RELA
                                // Is there a "RELA" tag (code adjusted from elsewhere - note though that strictly according to the Gedcom spec, RELA is mandatory!)
                                if (preg_match('/\n3 RELA (.+)/', $rela)) {
                                    //preserve RELA
                                    $factrec .= $rela;
                                } else {
                                    //skip
                                }
                            }
                        } else {
                            $factrec .= "\n2 _ASSO @" . $associate->xref() . '@';

                            //[RC] extension for RELA
                            // Is there a "RELA" tag (code adjusted from elsewhere - note though that strictly according to the Gedcom spec, RELA is mandatory!)
                            if (preg_match('/\n\d RELA (.+)/', $rela)) {
                                //preserve RELA
                                $factrec .= $rela;
                            } else {
                                //skip
                            }
                        }
                        $facts[] = new Fact($factrec, $associate, 'asso');
                    }
                }
            }
        }

        return new Collection($facts);
    }

    //[RC] added
    //OverrideHook
    protected function additionalFacts(Individual $person) {
        return array();
    }
}
