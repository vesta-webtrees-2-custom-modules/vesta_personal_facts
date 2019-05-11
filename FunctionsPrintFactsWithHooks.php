<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderUtils;
use Cissee\WebtreesExt\Functions\FunctionsPrintFacts_2x;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Individual;

class FunctionsPrintFactsWithHooks extends FunctionsPrintFacts_2x {

  protected $module;

  function __construct($functionsPrint, $module) {
    parent::__construct($functionsPrint);
    $this->module = $module;
  }

  protected function getOutputForRelationship(
          Fact $event,
          Individual $person,
          Individual $associate,
          $relationship_name_prefix,
          $relationship_name,
          $relationship_name_suffix,
          $inverse) {

    $outs = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $person->tree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($event, $person, $associate, $relationship_name_prefix, $relationship_name, $relationship_name_suffix, $inverse) {
              return $module->hFactsTabGetOutputForAssoRel($event, $person, $associate, $relationship_name_prefix, $relationship_name, $relationship_name_suffix, $inverse);
            })
            ->toArray();

    foreach ($outs as $out) {
      if ($out == null) {
        //first return wins
        return null; //do not proceed
      }
      if (($out->getMain() != '') || ($out->getScript() != '')) {
        //first return wins
        return $out;
      }
    }

    //nothing hooked or only empty string(s) returned: fallback!
    //TODO: should check whether default relationship chart is available!
    return parent::getOutputForRelationship(
                    $event,
                    $person,
                    $associate,
                    $relationship_name_prefix,
                    $relationship_name,
                    $relationship_name_suffix,
                    $inverse);
  }

  protected function additionalStyleadds(Fact $fact, $styleadd) {
    // Event of close relative
    if (preg_match('/^_[A-Z_]{3,5}_[A-Z0-9]{4}$/', $fact->getTag())) {
      $styleadd = trim($styleadd . ' wt-relation-fact-pfh collapse');
    }

    // Event of close associates
    if ($fact->id() == 'asso') {
      if ($this->module->getPreference('ASSO_SEPARATE', '0')) {
        $styleadd = trim($styleadd . ' wt-associate-fact-pfh collapse');
      } else {
        $styleadd = trim($styleadd . ' wt-relation-fact-pfh collapse');
      }
    }

    // historical facts
    if ($fact->id() == 'histo') {
      $styleadd = trim($styleadd . ' wt-historic-fact-pfh collapse');
    }

    $additions = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $fact->record()->tree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) {
              return $module->hFactsTabGetStyleadds();
            })
            ->toArray();

    foreach ($additions as $a) {
      foreach ($a as $id => $cssClass) {
        if ($fact->id() === $id) {
          $styleadd = trim($styleadd . ' ' . $cssClass);
        }
      }
    }
    return $styleadd;
  }

}
