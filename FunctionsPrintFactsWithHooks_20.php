<?php

namespace Cissee\Webtrees\Module\PersonalFacts;

use Cissee\WebtreesExt\Functions\FunctionsPrintFacts_20;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Individual;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderUtils;
use Vesta\Model\GenericViewElement;

class FunctionsPrintFactsWithHooks_20 extends FunctionsPrintFacts_20 {

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
    if ($fact->getTag() === 'EVEN' && $fact->value() === 'CLOSE_RELATIVE') {
      $styleadd = trim($styleadd . ' wt-relation-fact-pfh collapse');
    }

    // Event of close associates
    if ($fact->id() == 'asso') {
      //if ($this->module->getPreference('ASSO_SEPARATE', '0')) {
        $styleadd = trim($styleadd . ' wt-associate-fact-pfh collapse');
      //} else {
      //  $styleadd = trim($styleadd . ' wt-relation-fact-pfh collapse');
      //}
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

  protected function printAdditionalEditControls(Fact $fact): GenericViewElement {
    $additions = IndividualFactsTabExtenderUtils::accessibleModules($this->module, $fact->record()->tree(), Auth::user())
            ->map(function (IndividualFactsTabExtenderInterface $module) use ($fact) {
              return $module->hFactsTabGetAdditionalEditControls($fact);
            })
            ->toArray();
            
    return GenericViewElement::implode($additions);
  }
}
