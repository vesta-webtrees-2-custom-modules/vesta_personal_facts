<?php

namespace Cissee\WebtreesExt\Module;

use Cissee\WebtreesExt\ToggleableFactsCategory;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleHistoricEventsInterface;
use Fisharebest\Webtrees\Module\ModuleSidebarInterface;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Services\ClipboardService;
use Fisharebest\Webtrees\Services\ModuleService;
use Illuminate\Support\Collection;
use Vesta\Model\GenericViewElement;

/**
 * Class IndividualFactsTabModule
 * [RC] patched
 */
class IndividualFactsTabModule_2x extends AbstractModule implements ModuleTabInterface {

  use ModuleTabTrait;

  /** @var ModuleService */
  protected $module_service;

  /** @var ClipboardService */
  protected $clipboard_service;
  protected $functionsPrintFacts;
  protected $viewName = 'modules/personal_facts/tab';

  public function setFunctionsPrintFacts($functionsPrintFacts) {
    $this->functionsPrintFacts = $functionsPrintFacts;
  }

  public function setViewName($viewName) {
    $this->viewName = $viewName;
  }

  public function __construct(ModuleService $module_service, ClipboardService $clipboard_service) {
    $this->module_service = $module_service;
    $this->clipboard_service = $clipboard_service;
  }

  /** {@inheritdoc} */
  public function getTitle(): string {
    return /* I18N: Name of a module/tab on the individual page. */ I18N::translate('Facts and events');
  }

  /** {@inheritdoc} */
  public function getDescription(): string {
    return /* I18N: Description of the “Facts and events” module */ I18N::translate('A tab showing the facts and events of an individual.');
  }

  /** {@inheritdoc} */
  public function defaultTabOrder(): int {
    return 10;
  }

  /** {@inheritdoc} */
  public function isGrayedOut(Individual $individual): bool {
    return false;
  }

  public function getTabContent(Individual $individual): string {
    // Only include events of close relatives that are between birth and death
    $min_date = $individual->getEstimatedBirthDate();
    $max_date = $individual->getEstimatedDeathDate();

    // Which facts and events are handled by other modules?
    $sidebar_facts = $this->module_service
            ->findByComponent(ModuleSidebarInterface::class, $individual->tree(), Auth::user())
            ->map(function (ModuleSidebarInterface $sidebar): Collection {
      return $sidebar->supportedFacts();
    });

    $tab_facts = $this->module_service
            ->findByComponent(ModuleTabInterface::class, $individual->tree(), Auth::user())
            ->map(function (ModuleTabInterface $sidebar): Collection {
      return $sidebar->supportedFacts();
    });

    $exclude_facts = $sidebar_facts->merge($tab_facts)->flatten();


    // The individual’s own facts
    $indifacts = $individual->facts()
            ->filter(function (Fact $fact) use ($exclude_facts): bool {
      return !$exclude_facts->contains($fact->getTag());
    });

    // Add spouse-family facts
    foreach ($individual->spouseFamilies() as $family) {
      foreach ($family->facts() as $fact) {
        if (!$exclude_facts->contains($fact->getTag()) && $fact->getTag() !== 'CHAN') {
          $indifacts->push($fact);
        }
      }

      $spouse = $family->spouse($individual);

      if ($spouse instanceof Individual) {
        $spouse_facts = $this->spouseFacts($individual, $spouse, $min_date, $max_date);
        $indifacts = $indifacts->merge($spouse_facts);
      }

      $child_facts = $this->childFacts($individual, $family, '_CHIL', '', $min_date, $max_date);
      $indifacts = $indifacts->merge($child_facts);
    }

    $parent_facts = $this->parentFacts($individual, 1, $min_date, $max_date);
    $associate_facts = $this->associateFacts($individual);
    $historical_facts = $this->historicalFacts($individual);

    $indifacts = $indifacts
            ->merge($parent_facts)
            ->merge($associate_facts)
            ->merge($historical_facts);

    //[RC] ADDED
    $indifacts = $indifacts
            ->merge($this->additionalFacts($individual));

    $indifacts = Fact::sortFacts($indifacts);

    //[RC] additions		
    $show_relatives_facts = $individual->tree()->getPreference('SHOW_RELATIVES_EVENTS');
    $toggleableFactsCategories = $this->getToggleableFactsCategories($show_relatives_facts, !empty($historical_facts));

    //[RC] additions		
    $outputBeforeTab = $this->getOutputBeforeTab($individual);
    $outputAfterTab = $this->getOutputAfterTab($individual);
    $outputInDescriptionbox = $this->getOutputInDescriptionbox($individual);
    $outputAfterDescriptionbox = $this->getOutputAfterDescriptionbox($individual);

    $view = view($this->viewName, [
                'can_edit' => $individual->canEdit(),
                'clipboard_facts' => $this->clipboard_service->pastableFacts($individual, $exclude_facts),
                'has_historical_facts' => !empty($historical_facts),
                'individual' => $individual,
                'facts' => $indifacts,
                //[RC] additions
                'toggleableFactsCategories' => $toggleableFactsCategories,
                'printFactFunction' => function (Fact $fact) use ($individual) {
                  return $this->functionsPrintFacts->printFactAndReturnScript($fact, $individual);
                },
                'outputBeforeTab' => $outputBeforeTab,
                'outputAfterTab' => $outputAfterTab,
                'outputInDescriptionbox' => $outputInDescriptionbox,
                'outputAfterDescriptionbox' => $outputAfterDescriptionbox
    ]);

    return $view;
  }

  /**
   * Does a relative event occur within a date range (i.e. the individual's lifetime)?
   *
   * @param Fact $fact
   * @param Date $min_date
   * @param Date $max_date
   *
   * @return bool
   */
  private static function includeFact(Fact $fact, Date $min_date, Date $max_date): bool {
    $fact_date = $fact->date();

    return $fact_date->isOK() && Date::compare($min_date, $fact_date) <= 0 && Date::compare($fact_date, $max_date) <= 0;
  }

  /** {@inheritdoc} */
  public function hasTabContent(Individual $individual): bool {
    return true;
  }

  /** {@inheritdoc} */
  public function canLoadAjax(): bool {
    return false;
  }

  /**
   * Spouse facts that are shown on an individual’s page.
   *
   * @param Individual $individual Show events that occured during the lifetime of this individual
   * @param Individual $spouse     Show events of this individual
   * @param Date       $min_date
   * @param Date       $max_date
   *
   * @return Fact[]
   */
  private static function spouseFacts(Individual $individual, Individual $spouse, Date $min_date, Date $max_date): array {
    $SHOW_RELATIVES_EVENTS = $individual->tree()->getPreference('SHOW_RELATIVES_EVENTS');

    $facts = [];
    if (strstr($SHOW_RELATIVES_EVENTS, '_DEAT_SPOU')) {
      foreach ($spouse->facts(Gedcom::DEATH_EVENTS) as $fact) {
        if (self::includeFact($fact, $min_date, $max_date)) {
          // Convert the event to a close relatives event.
          $rela_fact = clone($fact);
          $rela_fact->setTag('_' . $fact->getTag() . '_SPOU');
          $facts[] = $rela_fact;
        }
      }
    }

    return $facts;
  }

  /**
   * Get the events of children and grandchildren.
   *
   * @param Individual $person
   * @param Family     $family
   * @param string     $option
   * @param string     $relation
   * @param Date       $min_date
   * @param Date       $max_date
   *
   * @return Fact[]
   */
  private static function childFacts(Individual $person, Family $family, $option, $relation, Date $min_date, Date $max_date): array {
    $SHOW_RELATIVES_EVENTS = $person->tree()->getPreference('SHOW_RELATIVES_EVENTS');

    $facts = [];

    // Deal with recursion.
    switch ($option) {
      case '_CHIL':
        // Add grandchildren
        foreach ($family->children() as $child) {
          foreach ($child->spouseFamilies() as $cfamily) {
            switch ($child->sex()) {
              case 'M':
                foreach (self::childFacts($person, $cfamily, '_GCHI', 'son', $min_date, $max_date) as $fact) {
                  $facts[] = $fact;
                }
                break;
              case 'F':
                foreach (self::childFacts($person, $cfamily, '_GCHI', 'dau', $min_date, $max_date) as $fact) {
                  $facts[] = $fact;
                }
                break;
              default:
                foreach (self::childFacts($person, $cfamily, '_GCHI', 'chi', $min_date, $max_date) as $fact) {
                  $facts[] = $fact;
                }
                break;
            }
          }
        }
        break;
    }

    // For each child in the family
    foreach ($family->children() as $child) {
      if ($child->xref() == $person->xref()) {
        // We are not our own sibling!
        continue;
      }
      // add child’s birth
      if (strpos($SHOW_RELATIVES_EVENTS, '_BIRT' . str_replace('_HSIB', '_SIBL', $option)) !== false) {
        foreach ($child->facts(Gedcom::BIRTH_EVENTS) as $fact) {
          // Always show _BIRT_CHIL, even if the dates are not known
          if ($option == '_CHIL' || self::includeFact($fact, $min_date, $max_date)) {
            if ($option == '_GCHI' && $relation == 'dau') {
              // Convert the event to a close relatives event.
              $rela_fact = clone($fact);
              $rela_fact->setTag('_' . $fact->getTag() . '_GCH1');
              $facts[] = $rela_fact;
            } elseif ($option == '_GCHI' && $relation == 'son') {
              // Convert the event to a close relatives event.
              $rela_fact = clone($fact);
              $rela_fact->setTag('_' . $fact->getTag() . '_GCH2');
              $facts[] = $rela_fact;
            } else {
              // Convert the event to a close relatives event.
              $rela_fact = clone($fact);
              $rela_fact->setTag('_' . $fact->getTag() . $option);
              $facts[] = $rela_fact;
            }
          }
        }
      }
      // add child’s death
      if (strpos($SHOW_RELATIVES_EVENTS, '_DEAT' . str_replace('_HSIB', '_SIBL', $option)) !== false) {
        foreach ($child->facts(Gedcom::DEATH_EVENTS) as $fact) {
          if (self::includeFact($fact, $min_date, $max_date)) {
            if ($option == '_GCHI' && $relation == 'dau') {
              // Convert the event to a close relatives event.
              $rela_fact = clone($fact);
              $rela_fact->setTag('_' . $fact->getTag() . '_GCH1');
              $facts[] = $rela_fact;
            } elseif ($option == '_GCHI' && $relation == 'son') {
              // Convert the event to a close relatives event.
              $rela_fact = clone($fact);
              $rela_fact->setTag('_' . $fact->getTag() . '_GCH2');
              $facts[] = $rela_fact;
            } else {
              // Convert the event to a close relatives event.
              $rela_fact = clone($fact);
              $rela_fact->setTag('_' . $fact->getTag() . $option);
              $facts[] = $rela_fact;
            }
          }
        }
      }
      // add child’s marriage
      if (strstr($SHOW_RELATIVES_EVENTS, '_MARR' . str_replace('_HSIB', '_SIBL', $option))) {
        foreach ($child->spouseFamilies() as $sfamily) {
          foreach ($sfamily->facts(['MARR']) as $fact) {
            if (self::includeFact($fact, $min_date, $max_date)) {
              if ($option == '_GCHI' && $relation == 'dau') {
                // Convert the event to a close relatives event.
                $rela_fact = clone($fact);
                $rela_fact->setTag('_' . $fact->getTag() . '_GCH1');
                $facts[] = $rela_fact;
              } elseif ($option == '_GCHI' && $relation == 'son') {
                // Convert the event to a close relatives event.
                $rela_fact = clone($fact);
                $rela_fact->setTag('_' . $fact->getTag() . '_GCH2');
                $facts[] = $rela_fact;
              } else {
                // Convert the event to a close relatives event.
                $rela_fact = clone($fact);
                $rela_fact->setTag('_' . $fact->getTag() . $option);
                $facts[] = $rela_fact;
              }
            }
          }
        }
      }
    }

    return $facts;
  }

  /**
   * Get the events of parents and grandparents.
   *
   * @param Individual $person
   * @param int        $sosa
   * @param Date       $min_date
   * @param Date       $max_date
   *
   * @return Fact[]
   */
  private static function parentFacts(Individual $person, $sosa, Date $min_date, Date $max_date): array {
    $SHOW_RELATIVES_EVENTS = $person->tree()->getPreference('SHOW_RELATIVES_EVENTS');

    $facts = [];

    if ($sosa == 1) {
      foreach ($person->childFamilies() as $family) {
        // Add siblings
        foreach (self::childFacts($person, $family, '_SIBL', '', $min_date, $max_date) as $fact) {
          $facts[] = $fact;
        }
        foreach ($family->spouses() as $spouse) {
          foreach ($spouse->spouseFamilies() as $sfamily) {
            if ($family !== $sfamily) {
              // Add half-siblings
              foreach (self::childFacts($person, $sfamily, '_HSIB', '', $min_date, $max_date) as $fact) {
                $facts[] = $fact;
              }
            }
          }
          // Add grandparents
          foreach (self::parentFacts($spouse, $spouse->sex() == 'F' ? 3 : 2, $min_date, $max_date) as $fact) {
            $facts[] = $fact;
          }
        }
      }

      if (strstr($SHOW_RELATIVES_EVENTS, '_MARR_PARE')) {
        // add father/mother marriages
        foreach ($person->childFamilies() as $sfamily) {
          foreach ($sfamily->facts(['MARR']) as $fact) {
            if (self::includeFact($fact, $min_date, $max_date)) {
              // marriage of parents (to each other)
              $rela_fact = clone($fact);
              $rela_fact->setTag('_' . $fact->getTag() . '_FAMC');
              $facts[] = $rela_fact;
            }
          }
        }
        foreach ($person->childStepFamilies() as $sfamily) {
          foreach ($sfamily->facts(['MARR']) as $fact) {
            if (self::includeFact($fact, $min_date, $max_date)) {
              // marriage of a parent (to another spouse)
              // Convert the event to a close relatives event
              $rela_fact = clone($fact);
              $rela_fact->setTag('_' . $fact->getTag() . '_PARE');
              $facts[] = $rela_fact;
            }
          }
        }
      }
    }

    foreach ($person->childFamilies() as $family) {
      foreach ($family->spouses() as $parent) {
        if (strstr($SHOW_RELATIVES_EVENTS, '_DEAT' . ($sosa == 1 ? '_PARE' : '_GPAR'))) {
          foreach ($parent->facts(Gedcom::DEATH_EVENTS) as $fact) {
            if (self::includeFact($fact, $min_date, $max_date)) {
              switch ($sosa) {
                case 1:
                  // Convert the event to a close relatives event.
                  $rela_fact = clone($fact);
                  $rela_fact->setTag('_' . $fact->getTag() . '_PARE');
                  $facts[] = $rela_fact;
                  break;
                case 2:
                  // Convert the event to a close relatives event
                  $rela_fact = clone($fact);
                  $rela_fact->setTag('_' . $fact->getTag() . '_GPA1');
                  $facts[] = $rela_fact;
                  break;
                case 3:
                  // Convert the event to a close relatives event
                  $rela_fact = clone($fact);
                  $rela_fact->setTag('_' . $fact->getTag() . '_GPA2');
                  $facts[] = $rela_fact;
                  break;
              }
            }
          }
        }
      }
    }

    return $facts;
  }

  private function historicalFacts(Individual $individual): array {
    return $this->module_service->findByInterface(ModuleHistoricEventsInterface::class)
                    ->map(function (ModuleHistoricEventsInterface $module) use ($individual): Collection {
                      return $module->historicEventsForIndividual($individual);
                    })
                    ->flatten()
                    ->all();
  }

  //[RC] OverrideHook

  /**
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

  /**
   * Get the events of associates.
   *
   * @param Individual $person
   *
   * @return Fact[]
   */
  //[RC] changed visibility, made non-static
  protected function associateFacts(Individual $person) {
    $facts = [];

    $associates = array_merge(
            $person->linkedIndividuals('ASSO'),
            $person->linkedIndividuals('_ASSO'),
            $person->linkedFamilies('ASSO'),
            $person->linkedFamilies('_ASSO')
    );
    foreach ($associates as $associate) {
      foreach ($associate->facts() as $fact) {
        //[RC] addded
        if (!$this->filterAssociateFact($fact)) {
          continue;
        }

        //[RC] PATCHED: FIX FOR ISSUE #1192
        //plus extension for RELA
        preg_match_all('/\n2 _ASSO @(.*)@((\n[3].*)*)/', $fact->gedcom(), $arecs1, PREG_SET_ORDER);
        preg_match_all('/\n2 ASSO @(.*)@((\n[3].*)*)/', $fact->gedcom(), $arecs2, PREG_SET_ORDER);

        $arecs = array_merge($arecs1, $arecs2);

        foreach ($arecs as $arec) {
          $xref = $arec[1];
          $rela = $arec[2];

          if ($xref === $person->xref()) {
            // Extract the important details from the fact
            $factrec = '1 ' . $fact->getTag();
            if (preg_match('/\n2 DATE .*/', $fact->gedcom(), $match)) {
              $factrec .= $match[0];
            }
            if (preg_match('/\n2 PLAC .*/', $fact->gedcom(), $match)) {
              $factrec .= $match[0];
            }
            if ($associate instanceof Family) {
              foreach ($associate->spouses() as $spouse) {
                $factrec .= "\n2 _ASSO @" . $spouse->xref() . '@';

                // Is there a "RELA" tag (code adjusted from elsewhere - note though that strictly according to the Gedcom spec, RELA is mandatory!)
                if (preg_match('/\n3 RELA (.+)/', $rela, $rmatch)) {
                  //preserve RELA
                  $factrec .= $rela;
                } else {
                  //skip
                }
              }
            } else {
              $factrec .= "\n2 _ASSO @" . $associate->xref() . '@';

              // Is there a "RELA" tag (code adjusted from elsewhere - note though that strictly according to the Gedcom spec, RELA is mandatory!)
              if (preg_match('/\n3 RELA (.+)/', $rela, $rmatch)) {
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

    return $facts;
  }

  //[RC] added
  //OverrideHook
  protected function additionalFacts(Individual $person) {
    return array();
  }

  //[RC] added/ refactored
  //OverrideHook
  protected function getToggleableFactsCategories($show_relatives_facts, $has_historical_facts) {
    $categories = [];

    /* [RC] note: this is problematic wrt asso events, which we still may want to show */
    if ($show_relatives_facts) {
      $categories[] = new ToggleableFactsCategory(
              'show-relatives-facts',
              '.wt-relation-fact',
              I18N::translate('Events of close relatives'));
    }

    if ($has_historical_facts) {
      $categories[] = new ToggleableFactsCategory(
              'show-historical-facts',
              '.wt-historic-fact',
              I18N::translate('Historical facts'));
    }

    return $categories;
  }

  //[RC] override hooks

  protected function getOutputBeforeTab(Individual $person) {
    return new GenericViewElement('', '');
  }

  protected function getOutputAfterTab(Individual $person) {
    return new GenericViewElement('', '');
  }

  protected function getOutputInDescriptionBox(Individual $person) {
    return new GenericViewElement('', '');
  }

  protected function getOutputAfterDescriptionBox(Individual $person) {
    return new GenericViewElement('', '');
  }

}
